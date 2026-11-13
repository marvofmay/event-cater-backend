<?php

declare(strict_types=1);

namespace App\Module\Saas\Presentation\Console;

use App\Module\Saas\Application\TrialMailer;
use App\Module\Saas\Infrastructure\ControlPlane;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:saas:process-trials', description: 'Trial reminders, paid reminders, suspend overdue accounts, purge trial data.')]
final class ProcessTrialsCommand extends Command
{
    private const SYSTEM_ACTOR = '00000000-0000-4000-8000-000000000099';
    private const PURGE_AFTER_DAYS = 7;
    private const PAID_REMIND_AFTER_DAYS = 3;
    private const PAID_SUSPEND_AFTER_DAYS = 14;

    public function __construct(private ControlPlane $control, private TrialMailer $mailer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->control->db()->createSchemaManager()->tablesExist(['saas_tenant'])) {
            $output->writeln('saas_tenant missing');

            return Command::SUCCESS;
        }

        $columns = $this->control->db()->createSchemaManager()->listTableColumns('saas_tenant');
        if (!isset($columns['plan'])) {
            $output->writeln('Trial columns missing - run migrations first.');

            return Command::SUCCESS;
        }

        $now = new \DateTimeImmutable('now');
        $this->sendReminders($now, $output);
        $this->remindPaid($now, $output);
        $this->suspendExpired($now, $output);
        $this->suspendPaid($now, $output);
        $this->purgeExpired($now, $output);

        return Command::SUCCESS;
    }

    private function sendReminders(\DateTimeImmutable $now, OutputInterface $output): void
    {
        $windows = [
            ['column' => 'trial_reminded_5d_at', 'hours' => 5 * 24, 'label' => '5 dni'],
            ['column' => 'trial_reminded_3d_at', 'hours' => 3 * 24, 'label' => '3 dni'],
            ['column' => 'trial_reminded_24h_at', 'hours' => 24, 'label' => '24 godziny'],
        ];

        foreach ($windows as $window) {
            $center = $now->modify('+'.$window['hours'].' hours');
            $low = $center->modify('-6 hours')->format('Y-m-d H:i:s');
            $high = $center->modify('+6 hours')->format('Y-m-d H:i:s');

            $rows = $this->control->db()->fetchAllAssociative(
                "SELECT * FROM saas_tenant
                 WHERE plan='trial' AND status='active' AND provisioning='ready'
                   AND trial_ends_at IS NOT NULL
                   AND trial_ends_at BETWEEN ? AND ?
                   AND {$window['column']} IS NULL
                   AND slug<>'default'",
                [$low, $high]
            );

            foreach ($rows as $row) {
                $ends = new \DateTimeImmutable($row['trial_ends_at']);
                try {
                    $this->mailer->sendReminder($row['contact_email'], $row['name'], $row['slug'], $ends, $window['label']);
                    $this->control->db()->update(
                        'saas_tenant',
                        [
                            $window['column'] => $now->format('Y-m-d H:i:s'),
                            'updated_at' => $now->format('Y-m-d H:i:s'),
                        ],
                        ['uuid' => $row['uuid']]
                    );
                    $output->writeln(sprintf('Reminder %s -> %s (%s)', $window['label'], $row['slug'], $row['contact_email']));
                } catch (\Throwable $e) {
                    $output->writeln(sprintf('Reminder failed for %s: %s', $row['slug'], $e->getMessage()));
                }
            }
        }
    }

    private function suspendExpired(\DateTimeImmutable $now, OutputInterface $output): void
    {
        $rows = $this->control->db()->fetchAllAssociative(
            "SELECT * FROM saas_tenant
             WHERE plan='trial' AND status='active'
               AND trial_ends_at IS NOT NULL AND trial_ends_at <= ?
               AND slug<>'default'",
            [$now->format('Y-m-d H:i:s')]
        );

        foreach ($rows as $row) {
            $this->control->db()->transactional(function () use ($row, $now): void {
                $tenant = $this->control->db()->fetchAssociative('SELECT * FROM saas_tenant WHERE uuid=? FOR UPDATE', [$row['uuid']]);
                if (!$tenant || $tenant['status'] !== 'active' || ($tenant['plan'] ?? '') !== 'trial') {
                    return;
                }
                $this->control->db()->update('saas_tenant', [
                    'status' => 'suspended',
                    'version' => (int) $tenant['version'] + 1,
                    'updated_at' => $now->format('Y-m-d H:i:s'),
                ], ['uuid' => $tenant['uuid']]);
                $this->control->db()->insert('saas_audit', [
                    'uuid' => Uuid::uuid4()->toString(),
                    'tenant_uuid' => $tenant['uuid'],
                    'actor_uuid' => self::SYSTEM_ACTOR,
                    'action' => 'active -> suspended',
                    'reason' => 'Automatyczne zawieszenie po zakończeniu 30-dniowego triala. Dane zachowane przez 7 dni.',
                    'created_at' => $now->format('Y-m-d H:i:s'),
                ]);
            });
            try {
                $this->mailer->sendSuspendedNotice($row['contact_email'], $row['name'], $row['slug']);
            } catch (\Throwable) {
            }
            $output->writeln('Suspended trial: '.$row['slug']);
        }
    }

    /** One polite reminder once the paid month is 3 days overdue. Account stays active. */
    private function remindPaid(\DateTimeImmutable $now, OutputInterface $output): void
    {
        $columns = $this->control->db()->createSchemaManager()->listTableColumns('saas_tenant');
        if (!isset($columns['paid_until'], $columns['paid_reminded_at'])) {
            $output->writeln('Paid reminder column missing - run migrations first.');

            return;
        }
        $remindBefore = $now->modify('-'.self::PAID_REMIND_AFTER_DAYS.' days')->format('Y-m-d H:i:s');
        $stillBeforeSuspend = $now->modify('-'.self::PAID_SUSPEND_AFTER_DAYS.' days')->format('Y-m-d H:i:s');
        $rows = $this->control->db()->fetchAllAssociative(
            "SELECT * FROM saas_tenant
             WHERE plan='paid' AND status='active'
               AND paid_until IS NOT NULL AND paid_until <= ? AND paid_until > ?
               AND paid_reminded_at IS NULL
               AND slug<>'default'",
            [$remindBefore, $stillBeforeSuspend]
        );
        foreach ($rows as $row) {
            $due = new \DateTimeImmutable((string) $row['paid_until']);
            try {
                $this->mailer->sendPaidReminder($row['contact_email'], $row['name'], $row['slug'], $due);
            } catch (\Throwable $e) {
                $output->writeln(sprintf('Paid reminder failed for %s: %s', $row['slug'], $e->getMessage()));
                continue;
            }
            $this->control->db()->transactional(function () use ($row, $now): void {
                $tenant = $this->control->db()->fetchAssociative('SELECT * FROM saas_tenant WHERE uuid=? FOR UPDATE', [$row['uuid']]);
                if (!$tenant || $tenant['status'] !== 'active' || ($tenant['plan'] ?? '') !== 'paid' || !empty($tenant['paid_reminded_at'])) {
                    return;
                }
                $stamp = $now->format('Y-m-d H:i:s');
                $this->control->db()->update('saas_tenant', [
                    'paid_reminded_at' => $stamp,
                    'updated_at' => $stamp,
                ], ['uuid' => $tenant['uuid']]);
                $this->control->db()->insert('saas_audit', [
                    'uuid' => Uuid::uuid4()->toString(),
                    'tenant_uuid' => $tenant['uuid'],
                    'actor_uuid' => self::SYSTEM_ACTOR,
                    'action' => 'paid_reminder',
                    'reason' => 'Przypomnienie 3 dni po terminie opłacenia abonamentu. Konto nadal aktywne.',
                    'created_at' => $stamp,
                ]);
            });
            $output->writeln('Paid reminder -> '.$row['slug'].' ('.$row['contact_email'].')');
        }
    }

    /** Suspend a paid account 14 days after paid_until when no new BLIK arrived. Data stays. */
    private function suspendPaid(\DateTimeImmutable $now, OutputInterface $output): void
    {
        $columns = $this->control->db()->createSchemaManager()->listTableColumns('saas_tenant');
        if (!isset($columns['paid_until'])) {
            return;
        }
        $cutoff = $now->modify('-'.self::PAID_SUSPEND_AFTER_DAYS.' days');
        $rows = $this->control->db()->fetchAllAssociative(
            "SELECT * FROM saas_tenant
             WHERE plan='paid' AND status='active'
               AND paid_until IS NOT NULL AND paid_until <= ?
               AND slug<>'default'",
            [$cutoff->format('Y-m-d H:i:s')]
        );
        foreach ($rows as $row) {
            $this->control->db()->transactional(function () use ($row, $now, $cutoff): void {
                $tenant = $this->control->db()->fetchAssociative('SELECT * FROM saas_tenant WHERE uuid=? FOR UPDATE', [$row['uuid']]);
                if (!$tenant || $tenant['status'] !== 'active' || ($tenant['plan'] ?? '') !== 'paid' || empty($tenant['paid_until'])) {
                    return;
                }
                if (new \DateTimeImmutable((string) $tenant['paid_until']) > $cutoff) {
                    return;
                }
                $this->control->db()->update('saas_tenant', [
                    'status' => 'suspended',
                    'version' => (int) $tenant['version'] + 1,
                    'updated_at' => $now->format('Y-m-d H:i:s'),
                ], ['uuid' => $tenant['uuid']]);
                $this->control->db()->insert('saas_audit', [
                    'uuid' => Uuid::uuid4()->toString(),
                    'tenant_uuid' => $tenant['uuid'],
                    'actor_uuid' => self::SYSTEM_ACTOR,
                    'action' => 'active -> suspended',
                    'reason' => 'Minęło 14 dni od terminu opłacenia abonamentu. Brak wpłaty. Konto zawieszone. Dane zostają.',
                    'created_at' => $now->format('Y-m-d H:i:s'),
                ]);
            });
            try {
                $this->mailer->sendPaidSuspendedNotice($row['contact_email'], $row['name'], $row['slug']);
            } catch (\Throwable) {
            }
            $output->writeln('Suspended paid: '.$row['slug']);
        }
    }

    /** Drop tenant DB and mark deleted 7 days after trial_ends_at (only unpaid trials). */
    private function purgeExpired(\DateTimeImmutable $now, OutputInterface $output): void
    {
        $cutoff = $now->modify('-'.self::PURGE_AFTER_DAYS.' days')->format('Y-m-d H:i:s');
        $rows = $this->control->db()->fetchAllAssociative(
            "SELECT * FROM saas_tenant
             WHERE plan='trial' AND status='suspended'
               AND trial_ends_at IS NOT NULL AND trial_ends_at <= ?
               AND slug<>'default'",
            [$cutoff]
        );

        foreach ($rows as $row) {
            $database = (string) ($row['database_name'] ?? '');
            if (!preg_match('/^ec_tenant_[a-f0-9]{32}$/D', $database)) {
                $output->writeln('Skip purge (invalid db): '.$row['slug']);
                continue;
            }

            try {
                $platform = $this->control->db()->getDatabasePlatform();
                $this->control->db()->executeStatement(
                    'DROP DATABASE IF EXISTS '.$platform->quoteSingleIdentifier($database)
                );
            } catch (\Throwable $e) {
                $output->writeln(sprintf('DROP failed for %s: %s', $row['slug'], $e->getMessage()));
                continue;
            }

            $freedSlug = 'deleted-'.substr(str_replace('-', '', $row['uuid']), 0, 16);
            $this->control->db()->transactional(function () use ($row, $now, $freedSlug): void {
                $tenant = $this->control->db()->fetchAssociative('SELECT * FROM saas_tenant WHERE uuid=? FOR UPDATE', [$row['uuid']]);
                if (!$tenant || ($tenant['plan'] ?? '') !== 'trial' || $tenant['status'] !== 'suspended') {
                    return;
                }
                $this->control->db()->update('saas_tenant', [
                    'status' => 'deleted',
                    'slug' => $freedSlug,
                    'provisioning' => 'failed',
                    'version' => (int) $tenant['version'] + 1,
                    'updated_at' => $now->format('Y-m-d H:i:s'),
                ], ['uuid' => $tenant['uuid']]);
                $this->control->db()->insert('saas_audit', [
                    'uuid' => Uuid::uuid4()->toString(),
                    'tenant_uuid' => $tenant['uuid'],
                    'actor_uuid' => self::SYSTEM_ACTOR,
                    'action' => 'suspended -> deleted',
                    'reason' => 'Automatyczne usunięcie danych triala 7 dni po zakończeniu. Baza usunięta.',
                    'created_at' => $now->format('Y-m-d H:i:s'),
                ]);
            });
            $output->writeln('Purged trial data: '.$row['slug'].' (db '.$database.')');
        }
    }
}
