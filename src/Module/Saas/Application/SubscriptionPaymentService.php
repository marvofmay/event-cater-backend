<?php

declare(strict_types=1);

namespace App\Module\Saas\Application;

use App\Module\Saas\Infrastructure\ControlPlane;
use App\Module\Saas\Infrastructure\TpayClient;
use App\Module\Saas\Infrastructure\TpayRejectedException;
use App\Module\Saas\Infrastructure\TpayTemporaryException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class SubscriptionPaymentService
{
    private const SYSTEM_ACTOR = '00000000-0000-4000-8000-000000000099';

    public function __construct(
        private ControlPlane $control,
        private TpayClient $tpay,
        private TrialMailer $mailer,
        private LoggerInterface $logger,
        #[Autowire(service: 'limiter.saas_payment')]
        private RateLimiterFactory $limiter,
        #[Autowire('%env(default:app_public_url:APP_URL)%')]
        private string $appUrl,
    ) {
    }

    public function config(): array
    {
        return [
            'enabled' => $this->tpay->configured(),
            'sandbox' => $this->tpay->sandbox(),
            'amount' => $this->tpay->amount(),
            'currency' => 'PLN',
        ];
    }

    public function start(string $slug, string $email, string $ip): array
    {
        if (!$this->limiter->create(hash('sha256', $ip))->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(3600, 'Zbyt wiele prób. Spróbuj ponownie później.');
        }
        if (!$this->tpay->configured()) {
            throw new ServiceUnavailableHttpException(null, 'Płatność BLIK jest chwilowo niedostępna.');
        }
        $slug = strtolower(trim($slug));
        $email = strtolower(trim($email));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/D', $slug) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Podaj kod firmy i adres e-mail właściciela konta.');
        }
        $tenant = $this->control->db()->fetchAssociative(
            'SELECT * FROM saas_tenant WHERE slug=? AND contact_email=?',
            [$slug, $email]
        );
        if (!$tenant || !$this->payable($tenant)) {
            throw new BadRequestHttpException('Nie znaleziono firmy o tym kodzie i adresie e-mail albo konta nie można teraz opłacić.');
        }

        $uuid = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');
        $this->control->db()->insert('saas_payment', [
            'uuid' => $uuid,
            'tenant_uuid' => $tenant['uuid'],
            'amount' => $this->tpay->amount(),
            'currency' => 'PLN',
            'status' => 'pending',
            'tpay_transaction_id' => null,
            'tpay_title' => null,
            'paid_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $origin = rtrim($this->appUrl, '/');
        try {
            $created = $this->tpay->createBlikTransaction(
                $uuid,
                $email,
                mb_substr((string) $tenant['name'], 0, 64),
                'Abonament EventCater',
                $origin.'/api/saas/payments/tpay/notify',
                $origin.'/oplata?wynik=ok&platnosc='.$uuid,
                $origin.'/oplata?wynik=blad&platnosc='.$uuid,
            );
        } catch (TpayTemporaryException $error) {
            $this->control->db()->update('saas_payment', [
                'status' => 'failed',
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['uuid' => $uuid]);
            throw new ServiceUnavailableHttpException(null, $error->getMessage());
        }

        $this->control->db()->update('saas_payment', [
            'tpay_transaction_id' => $created['transactionId'],
            'tpay_title' => $created['title'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['uuid' => $uuid]);

        return [
            'uuid' => $uuid,
            'paymentUrl' => $created['paymentUrl'],
            'amount' => $this->tpay->amount(),
            'sandbox' => $this->tpay->sandbox(),
        ];
    }

    public function status(string $uuid): array
    {
        if (!preg_match('/^[0-9a-f-]{36}$/Di', $uuid)) {
            throw new NotFoundHttpException('Nie znaleziono płatności.');
        }
        $payment = $this->control->db()->fetchAssociative('SELECT * FROM saas_payment WHERE uuid=?', [$uuid]);
        if (!$payment) {
            throw new NotFoundHttpException('Nie znaleziono płatności.');
        }
        if ($payment['status'] === 'pending' && is_string($payment['tpay_transaction_id']) && $payment['tpay_transaction_id'] !== '') {
            $created = strtotime((string) $payment['created_at']) ?: 0;
            if ($created >= time() - 7200 && $this->tpay->transactionPaid($payment['tpay_transaction_id'])) {
                $this->markPaid($payment, (string) ($payment['tpay_title'] ?? ''), 'status');
                $payment = $this->control->db()->fetchAssociative('SELECT * FROM saas_payment WHERE uuid=?', [$uuid]) ?: $payment;
            }
        }
        $paidUntil = null;
        if ($payment['status'] === 'paid') {
            $paidUntil = $this->control->db()->fetchOne('SELECT paid_until FROM saas_tenant WHERE uuid=?', [$payment['tenant_uuid']]);
        }

        return [
            'status' => $payment['status'],
            'paidUntil' => $paidUntil ? (new \DateTimeImmutable((string) $paidUntil))->format(\DateTimeInterface::ATOM) : null,
            'sandbox' => $this->tpay->sandbox(),
        ];
    }

    /** @param array<string, mixed> $fields */
    public function handleNotification(string $rawBody, ?string $jws, array $fields): void
    {
        $this->tpay->assertNotification($rawBody, $jws, $fields);
        $crc = (string) $fields['tr_crc'];
        $payment = $this->control->db()->fetchAssociative('SELECT * FROM saas_payment WHERE uuid=?', [$crc]);
        if (!$payment) {
            throw new TpayRejectedException('Nieznana płatność.');
        }
        $status = (string) ($fields['tr_status'] ?? '');
        if ($status === 'chargeback') {
            $this->markRefunded($payment, (string) $fields['tr_id']);

            return;
        }
        $this->markPaid($payment, (string) $fields['tr_id'], 'notify');
    }

    /** @param array<string, mixed> $payment */
    private function markPaid(array $payment, string $title, string $source): void
    {
        $mail = null;
        $this->control->db()->transactional(function () use ($payment, $title, $source, &$mail): void {
            $current = $this->control->db()->fetchAssociative('SELECT * FROM saas_payment WHERE uuid=? FOR UPDATE', [$payment['uuid']]);
            if (!$current || $current['status'] === 'paid') {
                return;
            }
            if ($current['status'] !== 'pending') {
                return;
            }
            $tenant = $this->control->db()->fetchAssociative('SELECT * FROM saas_tenant WHERE uuid=? FOR UPDATE', [$current['tenant_uuid']]);
            $now = new \DateTimeImmutable('now');
            $stamp = $now->format('Y-m-d H:i:s');
            $this->control->db()->update('saas_payment', [
                'status' => 'paid',
                'tpay_title' => $title !== '' ? $title : $current['tpay_title'],
                'paid_at' => $stamp,
                'updated_at' => $stamp,
            ], ['uuid' => $current['uuid']]);
            if (!$tenant || !$this->payable($tenant)) {
                $this->logger->error('Tpay payment received for a tenant that cannot be activated.', ['payment' => $current['uuid']]);

                return;
            }
            $anchor = $now;
            if (($tenant['plan'] ?? '') === 'trial' && !empty($tenant['trial_ends_at'])) {
                $trialEnd = new \DateTimeImmutable((string) $tenant['trial_ends_at']);
                if ($trialEnd > $anchor) {
                    $anchor = $trialEnd;
                }
            }
            if (!empty($tenant['paid_until'])) {
                $existing = new \DateTimeImmutable((string) $tenant['paid_until']);
                if ($existing > $anchor) {
                    $anchor = $existing;
                }
            }
            $paidUntil = $anchor->modify('+1 month');
            $fields = [
                'status' => 'active',
                'plan' => 'paid',
                'trial_ends_at' => null,
                'paid_until' => $paidUntil->format('Y-m-d H:i:s'),
                'version' => (int) $tenant['version'] + 1,
                'updated_at' => $stamp,
            ];
            $columns = $this->control->db()->createSchemaManager()->listTableColumns('saas_tenant');
            if (isset($columns['paid_reminded_at'])) {
                $fields['paid_reminded_at'] = null;
            }
            $this->control->db()->update('saas_tenant', $fields, ['uuid' => $tenant['uuid']]);
            $this->control->db()->insert('saas_audit', [
                'uuid' => Uuid::uuid4()->toString(),
                'tenant_uuid' => $tenant['uuid'],
                'actor_uuid' => self::SYSTEM_ACTOR,
                'action' => 'payment_paid',
                'reason' => 'BLIK Tpay '.$this->tpay->amount().' PLN do '.$paidUntil->format('Y-m-d H:i').' ('.$source.', '.$title.')',
                'created_at' => $stamp,
            ]);
            $mail = [
                'email' => $tenant['contact_email'],
                'name' => $tenant['name'],
                'slug' => $tenant['slug'],
                'until' => $paidUntil,
            ];
        });
        if (is_array($mail)) {
            try {
                $this->mailer->sendPaymentReceived($mail['email'], $mail['name'], $mail['slug'], $mail['until'], $this->tpay->sandbox());
            } catch (\Throwable $error) {
                $this->logger->error('Payment mail failed.', ['errorMessage' => $error->getMessage()]);
            }
        }
    }

    /** @param array<string, mixed> $payment */
    private function markRefunded(array $payment, string $title): void
    {
        $this->control->db()->transactional(function () use ($payment, $title): void {
            $current = $this->control->db()->fetchAssociative('SELECT * FROM saas_payment WHERE uuid=? FOR UPDATE', [$payment['uuid']]);
            if (!$current || $current['status'] === 'refunded') {
                return;
            }
            $tenant = $this->control->db()->fetchAssociative('SELECT * FROM saas_tenant WHERE uuid=? FOR UPDATE', [$current['tenant_uuid']]);
            $stamp = date('Y-m-d H:i:s');
            $this->control->db()->update('saas_payment', [
                'status' => 'refunded',
                'tpay_title' => $title !== '' ? $title : $current['tpay_title'],
                'updated_at' => $stamp,
            ], ['uuid' => $current['uuid']]);
            if ($tenant && $tenant['status'] === 'active' && ($tenant['plan'] ?? '') === 'paid' && $tenant['slug'] !== 'default') {
                $this->control->db()->update('saas_tenant', [
                    'status' => 'suspended',
                    'version' => (int) $tenant['version'] + 1,
                    'updated_at' => $stamp,
                ], ['uuid' => $tenant['uuid']]);
                $this->control->db()->insert('saas_audit', [
                    'uuid' => Uuid::uuid4()->toString(),
                    'tenant_uuid' => $tenant['uuid'],
                    'actor_uuid' => self::SYSTEM_ACTOR,
                    'action' => 'payment_refunded',
                    'reason' => 'Zwrot Tpay '.$title.'. Konto zawieszone.',
                    'created_at' => $stamp,
                ]);
            }
        });
    }

    /** @param array<string, mixed> $tenant */
    private function payable(array $tenant): bool
    {
        if ($tenant['slug'] === 'default' || ($tenant['provisioning'] ?? '') !== 'ready') {
            return false;
        }

        return in_array($tenant['status'], ['active', 'suspended'], true);
    }
}
