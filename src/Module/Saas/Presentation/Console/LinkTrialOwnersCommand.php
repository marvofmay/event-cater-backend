<?php

declare(strict_types=1);

namespace App\Module\Saas\Presentation\Console;

use App\Module\Saas\Infrastructure\ControlPlane;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:saas:link-trial-owners', description: 'Keep trial founders as company technical accounts and remove a placeholder employee if one was created.')]
final class LinkTrialOwnersCommand extends Command
{
    public function __construct(
        private ControlPlane $control,
        private Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->control->db()->fetchAllAssociative(
            "SELECT slug,name,database_name FROM saas_tenant WHERE provisioning='ready' AND slug<>'default' AND status<>'deleted'"
        );
        $platform = $this->connection->getDatabasePlatform();
        foreach ($rows as $row) {
            $database = (string) $row['database_name'];
            if (!preg_match('/^ec_tenant_[a-f0-9]{32}$/D', $database)) {
                $output->writeln('Pominięto '.$row['slug']);
                continue;
            }
            try {
                $this->connection->executeStatement('USE '.$platform->quoteSingleIdentifier($database));
                $removed = $this->removePlaceholder($output, (string) $row['slug']);
                $output->writeln(($removed ? 'Cofnięto kartę pracownika: ' : 'Pracownik techniczny firmy: ').$row['slug'].' ('.$row['name'].')');
            } catch (\Throwable $error) {
                $output->writeln('Błąd '.$row['slug'].': '.$error->getMessage());
            }
        }
        $this->connection->executeStatement('USE '.$platform->quoteSingleIdentifier($this->control->defaultDatabase()));

        return Command::SUCCESS;
    }

    private function removePlaceholder(OutputInterface $output, string $slug): bool
    {
        $companyUuid = $this->connection->fetchOne(
            "SELECT c.uuid FROM company c
             INNER JOIN employee e ON e.company_uuid = c.uuid
             WHERE c.nip = '5250001233' AND c.regon = '123456785'
               AND e.first_name = 'Właściciel' AND e.pesel = '44051401359'
               AND (SELECT COUNT(*) FROM employee other_employee WHERE other_employee.company_uuid = c.uuid) = 1
             LIMIT 1"
        );
        if (!$companyUuid) {
            return false;
        }
        $employeeUuid = $this->connection->fetchOne('SELECT uuid FROM employee WHERE company_uuid = ?', [$companyUuid]);
        $this->connection->beginTransaction();
        try {
            $this->connection->executeStatement('UPDATE user SET employee_uuid = NULL WHERE employee_uuid = ?', [$employeeUuid]);
            $this->connection->executeStatement('DELETE FROM address WHERE company_uuid = ?', [$companyUuid]);
            $this->connection->executeStatement('DELETE FROM employee WHERE uuid = ?', [$employeeUuid]);
            $this->connection->executeStatement('DELETE FROM department WHERE company_uuid = ?', [$companyUuid]);
            $this->connection->executeStatement('DELETE FROM company WHERE uuid = ?', [$companyUuid]);
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            $output->writeln('Nie udało się cofnąć karty '.$slug.': '.$error->getMessage());

            return false;
        }

        return true;
    }
}
