<?php

declare(strict_types=1);

namespace App\Module\Saas\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Fixed control-plane connection: never follows the request's selected business database. */
final class ControlPlane
{
    private ?Connection $connection = null;

    public function __construct(private Connection $business, #[Autowire('%kernel.environment%')] private string $environment)
    {
    }

    public function db(): Connection
    {
        return $this->connection ??= DriverManager::getConnection($this->business->getParams());
    }

    public function defaultDatabase(): string
    {
        return (string) $this->db()->getDatabase();
    }

    public function technicalAdministrator(string $email): ?array
    {
        if (!$this->db()->createSchemaManager()->tablesExist(['saas_administrator'])) {
            return null;
        }

        return $this->db()->fetchAssociative('SELECT uuid,email,password,active FROM saas_administrator WHERE email=? AND technical_access=1', [$email]) ?: null;
    }

    public function tenant(string $slug): ?array
    {
        if (!$this->db()->createSchemaManager()->tablesExist(['saas_tenant'])) {
            // SchemaTool-based tests rebuild business metadata without raw control-plane migrations.
            if ($this->environment !== 'test') {
                throw new ServiceUnavailableHttpException(null, 'SaaS registry is not initialized. Run database migrations.');
            }
            if ('default' !== $slug) {
                return null;
            }

            return ['uuid' => '00000000-0000-4000-8000-000000000001', 'slug' => 'default', 'name' => 'Firma domyślna', 'database_name' => $this->defaultDatabase(), 'status' => 'active', 'provisioning' => 'ready', 'version' => 1];
        }

        return $this->db()->fetchAssociative('SELECT * FROM saas_tenant WHERE slug = ?', [$slug]) ?: null;
    }
}
