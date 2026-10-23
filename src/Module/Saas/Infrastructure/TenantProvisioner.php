<?php

declare(strict_types=1);

namespace App\Module\Saas\Infrastructure;

use App\Module\Company\Domain\Service\User\UserFactory;
use App\Module\System\Application\Console\DefaultData\AddRecordToUserTableCommand;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class TenantProvisioner
{
    public function __construct(private ControlPlane $control, private Connection $connection, private EntityManagerInterface $em, private UserFactory $users, #[AutowireIterator('app.command.initialize-system-default-data')] private iterable $seeders)
    {
    }

    public function provision(array $tenant, string $email, #[\SensitiveParameter] string $password): void
    {
        $database = $tenant['database_name'];
        if (!preg_match('/^ec_tenant_[a-f0-9]{32}$/D', $database)) {
            throw new \LogicException('Invalid database identifier.');
        }
        try {
            $this->control->db()->executeStatement('CREATE DATABASE '.$this->connection->getDatabasePlatform()->quoteSingleIdentifier($database).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->connection->executeStatement('USE '.$this->connection->getDatabasePlatform()->quoteSingleIdentifier($database));
            $this->em->clear();
            (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
            foreach ($this->seeders as $command) {
                if ($command instanceof AddRecordToUserTableCommand) {
                    continue;
                }
                if (0 !== $command->run(new ArrayInput([]), new NullOutput())) {
                    throw new \RuntimeException('Reference data initialization failed.');
                }
            }
            $owner = $this->users->create($email, $password);
            $owner->makeTenantAdministrator();
            $this->em->persist($owner);
            $this->em->flush();
            // SchemaTool creates current metadata; record all historical migrations as the baseline.
            $this->connection->executeStatement('CREATE TABLE doctrine_migration_versions (version VARCHAR(191) NOT NULL PRIMARY KEY, executed_at DATETIME DEFAULT NULL, execution_time INT DEFAULT NULL)');
            foreach (glob(__DIR__.'/../../../../migrations/Version*.php') as $file) {
                $this->connection->insert('doctrine_migration_versions', ['version' => 'DoctrineMigrations\\'.basename($file, '.php'), 'executed_at' => date('Y-m-d H:i:s'), 'execution_time' => 0]);
            }
            $this->control->db()->update('saas_tenant', ['provisioning' => 'ready', 'updated_at' => date('Y-m-d H:i:s')], ['uuid' => $tenant['uuid']]);
        } catch (\Throwable $error) {
            $this->control->db()->update('saas_tenant', ['provisioning' => 'failed', 'updated_at' => date('Y-m-d H:i:s')], ['uuid' => $tenant['uuid']]);
            throw $error;
        } finally {
            $this->em->clear();
            $this->connection->executeStatement('USE '.$this->connection->getDatabasePlatform()->quoteSingleIdentifier($this->control->defaultDatabase()));
        }
    }
}
