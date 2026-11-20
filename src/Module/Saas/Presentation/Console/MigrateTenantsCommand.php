<?php

declare(strict_types=1);

namespace App\Module\Saas\Presentation\Console;

use App\Module\Saas\Infrastructure\ControlPlane;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:saas:migrate-tenants', description: 'Apply business migrations to provisioned tenant databases, including suspended tenants. Default is a dry run.')]
final class MigrateTenantsCommand extends Command
{
    public function __construct(private readonly ControlPlane $control, #[Autowire('%kernel.project_dir%')] private readonly string $projectDirectory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Apply changes (take tenant backups first)')
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Limit to one tenant slug')
            ->addOption('continue-on-error', null, InputOption::VALUE_NONE, 'Continue with remaining tenants after a failure');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $continueOnError = (bool) $input->getOption('continue-on-error');
        $failures = [];
        $tenants = $this->control->db()->fetchAllAssociative("SELECT slug,database_name FROM saas_tenant WHERE provisioning='ready' AND slug<>'default' AND status<>'deleted' ORDER BY slug");
        foreach ($tenants as $tenant) {
            if ($input->getOption('tenant') && $tenant['slug'] !== $input->getOption('tenant')) {
                continue;
            }
            $database = (string) $tenant['database_name'];
            if (!preg_match('/^ec_tenant_[a-f0-9]{32}$/D', $database)) {
                throw new \RuntimeException('Invalid tenant database name for '.$tenant['slug'].'.');
            }
            $params = $this->control->db()->getParams();
            unset($params['url']);
            $params['dbname'] = $database;
            $connection = DriverManager::getConnection($params);
            try {
                $connection->executeStatement('USE '.$connection->getDatabasePlatform()->quoteSingleIdentifier($database));
                if ($connection->fetchOne('SELECT DATABASE()') !== $database) {
                    throw new \RuntimeException('Tenant migration for '.$tenant['slug'].' connected to the wrong database.');
                }
                $factory = DependencyFactory::fromConnection(new ConfigurationArray(['migrations_paths' => ['DoctrineMigrations' => $this->projectDirectory.'/migrations'], 'transactional' => false]), new ExistingConnection($connection));
                $factory->getMetadataStorage()->ensureInitialized();
                $plan = $factory->getMigrationPlanCalculator()->getPlanUntilVersion($factory->getVersionAliasResolver()->resolveVersionAlias('latest'));
                $output->writeln($tenant['slug'].': '.count($plan).' pending migrations'.($input->getOption('execute') ? '' : ' (dry run)'));
                if ($input->getOption('execute')) {
                    $factory->getMigrator()->migrate($plan, new MigratorConfiguration());
                }
            } catch (\Throwable $error) {
                $message = $error->getMessage();
                $output->writeln('<error>Migration failed for '.$tenant['slug'].' ('.get_class($error).'): '.$message.'</error>');
                $failures[] = (string) $tenant['slug'];
                if (!$continueOnError) {
                    $output->writeln('<error>Stopping.</error>');

                    return Command::FAILURE;
                }
                $output->writeln('<comment>Continuing with remaining tenants.</comment>');
            } finally {
                $connection->close();
            }
        }

        if ($failures !== []) {
            $output->writeln('<error>Tenant migration failures: '.implode(', ', $failures).'</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
