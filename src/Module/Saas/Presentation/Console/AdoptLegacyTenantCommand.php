<?php

declare(strict_types=1);

namespace App\Module\Saas\Presentation\Console;

use App\Module\Saas\Infrastructure\ControlPlane;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:saas:adopt-legacy-tenant', description: 'Copy the legacy business database and private media into the first named tenant, preserving the source database.')]
final class AdoptLegacyTenantCommand extends Command
{
    public function __construct(private readonly ControlPlane $control, #[Autowire('%kernel.project_dir%')] private readonly string $projectDirectory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('slug', InputArgument::REQUIRED)
            ->addArgument('name', InputArgument::REQUIRED)
            ->addArgument('technical-email', InputArgument::REQUIRED)
            ->addOption('execute', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $slug = $input->getArgument('slug');
        $name = trim($input->getArgument('name'));
        $email = strtolower(trim($input->getArgument('technical-email')));
        $db = $this->control->db();
        $legacy = $this->control->tenant('default');
        $admin = $this->control->technicalAdministrator($email);
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/D', $slug) || $slug === 'default' || $name === '' || mb_strlen($name) > 200 || !$admin || !$admin['active']) {
            throw new \InvalidArgumentException('A valid tenant name, slug and active central technical administrator are required.');
        }
        if (!$legacy || $legacy['status'] !== 'active' || $this->control->tenant($slug) || (int) $db->fetchOne("SELECT COUNT(*) FROM saas_tenant WHERE slug<>'default'") !== 0) {
            throw new \RuntimeException('This operation requires the active legacy tenant and no existing named tenants.');
        }
        $source = $this->control->defaultDatabase();
        $tables = array_values(array_filter($db->createSchemaManager()->listTableNames(), static fn (string $table): bool => !str_starts_with($table, 'saas_')));
        $output->writeln(sprintf('Copy %d business tables from %s to tenant %s; preserve the source and disable default access.', count($tables), $source, $slug));
        if (!$input->getOption('execute')) {
            $output->writeln('Stop workers, take a backup, then run with --execute.');

            return Command::SUCCESS;
        }
        $uuid = Uuid::uuid4()->toString();
        $database = 'ec_tenant_'.str_replace('-', '', $uuid);
        $quote = $db->getDatabasePlatform()->quoteSingleIdentifier(...);
        $now = date('Y-m-d H:i:s');
        $db->insert('saas_tenant', ['uuid' => $uuid, 'slug' => $slug, 'name' => $name, 'contact_email' => $email, 'database_name' => $database, 'status' => 'inactive', 'provisioning' => 'pending', 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $db->update('saas_tenant', ['status' => 'inactive', 'version' => (int) $legacy['version'] + 1, 'updated_at' => $now], ['uuid' => $legacy['uuid']]);
        try {
            $db->executeStatement('CREATE DATABASE '.$quote($database).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $db->executeStatement('SET FOREIGN_KEY_CHECKS=0');
            $db->executeStatement('USE '.$quote($database));
            foreach ($tables as $table) {
                $definition = $db->fetchAssociative('SHOW CREATE TABLE '.$quote($source).'.'.$quote($table));
                $db->executeStatement($definition['Create Table']);
                $db->executeStatement('INSERT INTO '.$quote($database).'.'.$quote($table).' SELECT * FROM '.$quote($source).'.'.$quote($table));
                $count = (int) $db->fetchOne('SELECT COUNT(*) FROM '.$quote($database).'.'.$quote($table));
                if ($count !== (int) $db->fetchOne('SELECT COUNT(*) FROM '.$quote($source).'.'.$quote($table))) {
                    throw new \RuntimeException('Row count mismatch: '.$table);
                }
                $output->writeln(sprintf('%s: %d rows copied', $table, $count));
            }
            $db->executeStatement('SET FOREIGN_KEY_CHECKS=1');
            $db->executeStatement('USE '.$quote($source));
            $filesystem = new Filesystem();
            foreach (['event-media', 'equipment-photos'] as $folder) {
                $directory = $this->projectDirectory.'/storage/'.$folder;
                if (!is_dir($directory)) {
                    continue;
                }
                $temporary = $this->projectDirectory.'/storage/.migration-'.$folder.'-'.$uuid;
                $filesystem->mirror($directory, $temporary);
                $filesystem->rename($temporary, $directory.'/'.$uuid);
            }
            $db->transactional(function () use ($db, $uuid, $admin, $now): void {
                $db->update('saas_tenant', ['status' => 'active', 'provisioning' => 'ready', 'updated_at' => $now], ['uuid' => $uuid]);
                $db->insert('saas_audit', ['uuid' => Uuid::uuid4()->toString(), 'tenant_uuid' => $uuid, 'actor_uuid' => $admin['uuid'], 'action' => 'legacy_adopted', 'reason' => 'Copied all legacy business data and private media. Source database retained; default tenant disabled.', 'created_at' => $now]);
            });
        } catch (\Throwable $error) {
            $db->executeStatement('SET FOREIGN_KEY_CHECKS=1');
            $db->executeStatement('USE '.$quote($source));
            $db->update('saas_tenant', ['provisioning' => 'failed'], ['uuid' => $uuid]);
            $db->update('saas_tenant', ['status' => 'active', 'version' => (int) $legacy['version'] + 2], ['uuid' => $legacy['uuid']]);
            throw $error;
        }
        $output->writeln('<info>Tenant ready: '.$slug.'. Source data preserved.</info>');

        return Command::SUCCESS;
    }
}
