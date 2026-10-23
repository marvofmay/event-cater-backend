<?php

declare(strict_types=1);

namespace App\Module\Saas\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class TenantContext implements \Symfony\Contracts\Service\ResetInterface
{
    private ?array $tenant = null;

    public function __construct(private ControlPlane $control, private Connection $connection, private EntityManagerInterface $em)
    {
    }

    public function reset(): void
    {
        $this->em->clear();
        $this->connection->close();
        $this->tenant = null;
    }

    public function current(): array
    {
        return $this->tenant ?? $this->control->tenant('default') ?? throw new AccessDeniedHttpException('Tenant unavailable.');
    }

    public function select(string $slug): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/D', $slug)) {
            throw new AccessDeniedHttpException('Invalid tenant.');
        }
        $tenant = $this->control->tenant($slug);
        if (!$tenant || 'active' !== $tenant['status'] || 'ready' !== $tenant['provisioning']) {
            throw new AccessDeniedHttpException('Tenant unavailable or inactive.');
        }
        if ($this->connection->isTransactionActive()) {
            throw new \LogicException('Cannot switch tenant during a transaction.');
        }
        $this->em->clear();
        $this->connection->executeStatement('USE '.$this->connection->getDatabasePlatform()->quoteSingleIdentifier($tenant['database_name']));
        $this->tenant = $tenant;
    }

    public function matches(array $claims): bool
    {
        $tenant = $this->current();

        return ($claims['tenantUuid'] ?? null) === $tenant['uuid'] && (int) ($claims['tenantVersion'] ?? 0) === (int) $tenant['version'];
    }
}
