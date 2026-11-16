<?php

declare(strict_types=1);

namespace App\Module\Saas\Application;

use App\Module\Saas\Domain\TenantStatus;
use App\Module\Saas\Infrastructure\ControlPlane;
use App\Module\Saas\Infrastructure\TenantProvisioner;
use App\Module\System\Domain\Service\User\PasswordPolicy;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class SaasService
{
    private const SYSTEM_ACTOR = '00000000-0000-4000-8000-000000000099';

    public function __construct(
        private ControlPlane $control,
        private TenantProvisioner $provisioner,
        private LoggerInterface $logger,
        private TrialMailer $trialMailer,
        #[Autowire(service: 'limiter.saas_login')] private RateLimiterFactory $loginLimiter,
        #[Autowire(service: 'limiter.saas_trial')] private RateLimiterFactory $trialLimiter,
    ) {
    }

    public function login(string $email, #[\SensitiveParameter] string $password, string $ip): array
    {
        if (!$this->loginLimiter->create(hash('sha256', strtolower($email).'|'.$ip))->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'Spróbuj ponownie później.');
        }
        $admin = $this->control->db()->fetchAssociative('SELECT * FROM saas_administrator WHERE email=? AND active=1', [strtolower($email)]);
        if (!$admin || !password_verify($password, $admin['password'])) {
            throw new AccessDeniedHttpException('Nieprawidłowe dane logowania.');
        }
        $token = bin2hex(random_bytes(32));
        $this->control->db()->insert('saas_session', ['token_hash' => hash('sha256', $token), 'administrator_uuid' => $admin['uuid'], 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);

        return ['token' => $token, 'expiresIn' => 3600, 'email' => $admin['email']];
    }

    public function authenticate(Request $request): string
    {
        $token = $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/D', $token, $matches)) {
            throw new AccessDeniedHttpException('Wymagane konto superadministratora SaaS.');
        }
        $uuid = $this->control->db()->fetchOne('SELECT a.uuid FROM saas_administrator a JOIN saas_session s ON s.administrator_uuid=a.uuid WHERE s.token_hash=? AND s.expires_at>NOW() AND a.active=1', [hash('sha256', $matches[1])]);
        if (!$uuid) {
            throw new AccessDeniedHttpException('Sesja wygasła. Zaloguj się ponownie.');
        }

        return $uuid;
    }

    public function logout(Request $request): void
    {
        $this->authenticate($request);
        $this->control->db()->delete('saas_session', ['token_hash' => hash('sha256', substr($request->headers->get('Authorization'), 7))]);
    }

    public function list(): array
    {
        return $this->control->db()->fetchAllAssociative('SELECT uuid,slug,name,contact_email AS contactEmail,status,provisioning,version,plan,trial_ends_at AS trialEndsAt,paid_until AS paidUntil,created_at AS createdAt,updated_at AS updatedAt FROM saas_tenant ORDER BY created_at DESC, name');
    }

    public function tenantOptions(): array
    {
        return $this->control->db()->fetchAllAssociative("SELECT slug,name FROM saas_tenant WHERE status='active' AND provisioning='ready' AND slug<>'default' ORDER BY name");
    }

    public function create(#[\SensitiveParameter] array $input, string $actor): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slug = (string) ($input['slug'] ?? '');
        $email = strtolower(trim((string) ($input['ownerEmail'] ?? '')));
        $password = (string) ($input['ownerPassword'] ?? '');
        if ('' === $name || mb_strlen($name) > 200 || !preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/D', $slug) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100 || strlen($password) < 12 || strlen($password) > 72) {
            throw new BadRequestHttpException('Podaj nazwę, kod firmy (2-63 znaki: a-z, cyfry, myślnik), e-mail i hasło administratora (12-72 znaki).');
        }
        $uuid = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');
        $tenant = ['uuid' => $uuid, 'slug' => $slug, 'name' => $name, 'contact_email' => $email, 'database_name' => 'ec_tenant_'.str_replace('-', '', $uuid), 'status' => 'inactive', 'provisioning' => 'pending', 'plan' => 'paid', 'trial_ends_at' => null, 'version' => 1, 'created_at' => $now, 'updated_at' => $now];
        try {
            $this->control->db()->transactional(function () use ($tenant, $uuid, $actor, $email): void {
                $this->control->db()->insert('saas_tenant', $tenant);
                $this->audit($uuid, $actor, 'created', 'Utworzono klienta; konto właściciela: '.$email);
            });
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            throw new ConflictHttpException('Kod firmy jest już zajęty.');
        }
        try {
            $this->provisioner->provision($tenant, $email, $password);
            $this->audit($uuid, $actor, 'provisioned', 'Przygotowano bazę i konto administratora.');
        } catch (\Throwable $error) {
            $this->logger->error('Tenant provisioning failed.', ['tenantUuid' => $uuid, 'errorType' => get_class($error), 'errorCode' => (string) $error->getCode()]);
            $this->audit($uuid, $actor, 'provisioning_failed', 'Wymagana diagnostyka operatora; klient pozostaje nieaktywny.');
        }

        return $this->detail($uuid);
    }

    /** Public self-service 30-day trial. Returns credentials once for UI + mail. */
    public function createTrial(#[\SensitiveParameter] array $input, string $ip): array
    {
        if (!$this->trialLimiter->create(hash('sha256', $ip))->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(3600, 'Zbyt wiele prób. Spróbuj ponownie później.');
        }

        $name = trim((string) ($input['name'] ?? ''));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $email = strtolower(trim((string) ($input['ownerEmail'] ?? '')));
        $password = (string) ($input['ownerPassword'] ?? '');
        $confirmation = (string) ($input['ownerPasswordConfirmation'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 200 || !preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/D', $slug) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) < 5 || strlen($email) > 100) {
            throw new BadRequestHttpException('Podaj nazwę firmy (2-200 znaków), kod firmy (2-63: a-z, cyfry, myślnik) oraz e-mail (max 100 znaków).');
        }
        $policyErrors = PasswordPolicy::errors($password, $confirmation);
        if ($policyErrors) {
            throw new BadRequestHttpException(implode(' ', $policyErrors));
        }

        $uuid = Uuid::uuid4()->toString();
        $now = new \DateTimeImmutable('now');
        $trialEnds = $now->modify('+30 days');
        $nowSql = $now->format('Y-m-d H:i:s');
        $tenant = [
            'uuid' => $uuid,
            'slug' => $slug,
            'name' => $name,
            'contact_email' => $email,
            'database_name' => 'ec_tenant_'.str_replace('-', '', $uuid),
            'status' => 'active',
            'provisioning' => 'pending',
            'plan' => 'trial',
            'trial_ends_at' => $trialEnds->format('Y-m-d H:i:s'),
            'version' => 1,
            'created_at' => $nowSql,
            'updated_at' => $nowSql,
        ];

        try {
            $this->control->db()->transactional(function () use ($tenant, $uuid, $email): void {
                $this->control->db()->insert('saas_tenant', $tenant);
                $this->audit($uuid, self::SYSTEM_ACTOR, 'trial_created', 'Publiczny trial 30 dni; właściciel: '.$email);
            });
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            throw new ConflictHttpException('Kod firmy jest już zajęty. Wybierz inny.');
        }

        try {
            $this->provisioner->provision($tenant, $email, $password);
            $this->audit($uuid, self::SYSTEM_ACTOR, 'provisioned', 'Trial: baza i konto administratora gotowe.');
        } catch (\Throwable $error) {
            $this->logger->error('Trial provisioning failed.', [
                'tenantUuid' => $uuid,
                'errorType' => $error::class,
                'errorCode' => (string) $error->getCode(),
                'errorMessage' => $error->getMessage(),
                'errorFile' => $error->getFile().':'.$error->getLine(),
            ]);
            $this->audit($uuid, self::SYSTEM_ACTOR, 'provisioning_failed', 'Trial: błąd przygotowania bazy: '.$error->getMessage());
            throw new BadRequestHttpException('Nie udało się przygotować konta. Spróbuj ponownie lub napisz na kontakt@eventcater.pl.');
        }

        $detail = $this->detail($uuid);
        if (($detail['provisioning'] ?? '') !== 'ready') {
            throw new BadRequestHttpException('Konto nie jest gotowe. Skontaktuj się z kontakt@eventcater.pl.');
        }

        $mailSent = true;
        try {
            $this->trialMailer->sendOwnerCredentials($email, $name, $slug, $password, $trialEnds);
            $this->trialMailer->sendOperatorNotice($name, $slug, $email, $trialEnds);
        } catch (\Throwable $error) {
            $mailSent = false;
            $this->logger->error('Trial created but mail failed.', ['tenantUuid' => $uuid, 'errorMessage' => $error->getMessage()]);
        }

        return [
            'uuid' => $uuid,
            'name' => $name,
            'slug' => $slug,
            'email' => $email,
            'password' => $password,
            'trialEndsAt' => $trialEnds->format(\DateTimeInterface::ATOM),
            'loginUrl' => '/login?tenant='.rawurlencode($slug),
            'status' => 'active',
            'provisioning' => 'ready',
            'plan' => 'trial',
            'mailSent' => $mailSent,
        ];
    }

    public function detail(string $uuid): array
    {
        $rows = array_filter($this->list(), fn (array $row) => $row['uuid'] === $uuid);
        $tenant = array_values($rows)[0] ?? throw new NotFoundHttpException('Nie znaleziono klienta.');
        $tenant['history'] = $this->control->db()->fetchAllAssociative('SELECT a.action,a.reason,a.created_at AS createdAt,u.email AS actor FROM saas_audit a LEFT JOIN saas_administrator u ON u.uuid=a.actor_uuid WHERE a.tenant_uuid=? ORDER BY a.created_at DESC,a.uuid', [$uuid]);

        return $tenant;
    }

    public function changeStatus(string $uuid, array $input, string $actor): array
    {
        $status = $input['status'] ?? '';
        $reason = trim((string) ($input['reason'] ?? ''));
        $version = $input['version'] ?? null;
        if (!is_string($status) || !TenantStatus::tryFrom($status) || '' === $reason || mb_strlen($reason) > 1000 || !is_int($version)) {
            throw new BadRequestHttpException('Wymagany status, aktualna wersja i uzasadnienie (do 1000 znaków).');
        }
        $db = $this->control->db();
        $db->transactional(function () use ($db, $uuid, $status, $reason, $version, $actor): void {
            $tenant = $db->fetchAssociative('SELECT * FROM saas_tenant WHERE uuid=? FOR UPDATE', [$uuid]) ?: throw new NotFoundHttpException('Nie znaleziono klienta.');
            if ((int) $tenant['version'] !== $version) {
                throw new ConflictHttpException('Dane zostały zmienione. Odśwież widok.');
            }
            if (!TenantStatus::from($tenant['status'])->canTransitionTo(TenantStatus::from($status), 'ready' === $tenant['provisioning']) && 'deleted' === $tenant['status']) {
                throw new ConflictHttpException('Usuniętego klienta najpierw przywróć do nieaktywnego.');
            }
            if ('active' === $status && 'ready' !== $tenant['provisioning']) {
                throw new ConflictHttpException('Baza klienta nie jest gotowa.');
            }
            if ($tenant['status'] === $status) {
                return;
            }
            $fields = ['status' => $status, 'version' => $version + 1, 'updated_at' => date('Y-m-d H:i:s')];
            // Paying / reactivating after trial: mark as paid plan so reminders stop.
            if ('active' === $status && ($tenant['plan'] ?? '') === 'trial' && 'suspended' === $tenant['status']) {
                $fields['plan'] = 'paid';
                $fields['trial_ends_at'] = null;
            }
            $db->update('saas_tenant', $fields, ['uuid' => $uuid]);
            $this->audit($uuid, $actor, $tenant['status'].' → '.$status, $reason);
        });

        return $this->detail($uuid);
    }

    /** Drop the tenant database and the control-plane row. Only a status of deleted may be purged, which frees the slug. */
    public function purge(string $uuid, string $confirmSlug, string $actor): void
    {
        if (!preg_match('/^[0-9a-f-]{36}$/Di', $uuid)) {
            throw new BadRequestHttpException('Nieprawidłowy identyfikator.');
        }
        $db = $this->control->db();
        $tenant = $db->fetchAssociative('SELECT * FROM saas_tenant WHERE uuid=?', [$uuid]) ?: throw new NotFoundHttpException('Nie znaleziono klienta.');
        if ($tenant['slug'] === 'default' || $tenant['uuid'] === '00000000-0000-4000-8000-000000000001') {
            throw new BadRequestHttpException('Firmy domyślnej nie można usunąć.');
        }
        if ($tenant['status'] !== 'deleted') {
            throw new ConflictHttpException('Trwałe usunięcie jest możliwe tylko przy statusie Usunięty.');
        }
        if ($confirmSlug !== $tenant['slug']) {
            throw new BadRequestHttpException('Wpisz aktualny kod firmy, aby potwierdzić trwałe usunięcie.');
        }

        $database = (string) ($tenant['database_name'] ?? '');
        $controlDatabase = (string) $db->getDatabase();
        if ($database !== '' && $database !== $controlDatabase && preg_match('/^ec_tenant_[a-f0-9]{32}$/D', $database)) {
            $db->executeStatement('DROP DATABASE IF EXISTS '.$db->getDatabasePlatform()->quoteSingleIdentifier($database));
        }

        $db->transactional(function () use ($db, $uuid): void {
            $locked = $db->fetchAssociative('SELECT status, slug FROM saas_tenant WHERE uuid=? FOR UPDATE', [$uuid]) ?: throw new NotFoundHttpException('Nie znaleziono klienta.');
            if ($locked['status'] !== 'deleted' || $locked['slug'] === 'default') {
                throw new ConflictHttpException('Trwałe usunięcie jest możliwe tylko przy statusie Usunięty.');
            }
            $db->executeStatement('DELETE FROM saas_audit WHERE tenant_uuid=?', [$uuid]);
            if (1 !== $db->delete('saas_tenant', ['uuid' => $uuid, 'status' => 'deleted'])) {
                throw new ConflictHttpException('Nie udało się usunąć rekordu firmy.');
            }
        });
        $this->logger->info('Tenant purged.', ['tenantUuid' => $uuid, 'slug' => $tenant['slug'], 'actor' => $actor]);
    }

    private function audit(string $tenant, string $actor, string $action, string $reason): void
    {
        $this->control->db()->insert('saas_audit', ['uuid' => Uuid::uuid4()->toString(), 'tenant_uuid' => $tenant, 'actor_uuid' => $actor, 'action' => $action, 'reason' => $reason, 'created_at' => date('Y-m-d H:i:s')]);
    }
}
