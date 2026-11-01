<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\Console;

use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Service\User\LoginPhone;
use App\Module\Company\Domain\Service\User\UserFactory;
use App\Module\Saas\Infrastructure\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:employees:provision-accounts', description: 'Create missing employee phone accounts in one tenant without resetting existing passwords. Default is a dry run.')]
final class ProvisionEmployeeAccountsCommand extends Command
{
    public function __construct(private readonly TenantContext $tenant, private readonly EntityManagerInterface $em, private readonly UserFactory $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Required tenant slug')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Create missing accounts after validation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $slug = $input->getOption('tenant');
        if (!is_string($slug) || $slug === '') {
            $output->writeln('<error>Wskaż firmę przez --tenant.</error>');

            return Command::INVALID;
        }
        $this->tenant->select($slug);
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $employees = $connection->fetchAllAssociative("SELECT e.uuid, e.pesel,
                (SELECT c.data FROM contact c WHERE c.employee_uuid = e.uuid AND c.type = 'phone' AND c.deleted_at IS NULL AND c.active = 1 ORDER BY c.created_at, c.uuid LIMIT 1) AS phone,
                (SELECT c.data FROM contact c WHERE c.employee_uuid = e.uuid AND c.type = 'email' AND c.deleted_at IS NULL AND c.active = 1 ORDER BY c.created_at, c.uuid LIMIT 1) AS email
                FROM employee e LEFT JOIN user u ON u.employee_uuid = e.uuid
                WHERE e.deleted_at IS NULL AND e.active = 1 AND u.uuid IS NULL ORDER BY e.uuid FOR UPDATE");
            $phones = [];
            $emails = [];
            foreach ($employees as &$employee) {
                $employee['phone'] = LoginPhone::normalize((string) $employee['phone']);
                if (!preg_match('/^[0-9]{11}$/D', $employee['pesel'])) {
                    throw new \RuntimeException('Nieprawidłowy PESEL pracownika. Popraw dane przed utworzeniem kont.');
                }
                $phone = $employee['phone'];
                if (isset($phones[$phone]) || $connection->fetchOne('SELECT uuid FROM user WHERE login_phone = ?', [$phone])) {
                    throw new \RuntimeException('Powtarzający się telefon logowania. Popraw dane przed utworzeniem kont.');
                }
                $phones[$phone] = true;
                $email = strtolower(trim((string) $employee['email']));
                $employee['email'] = $email === '' ? null : $email;
                if ($email !== '') {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || isset($emails[$email]) || $connection->fetchOne('SELECT uuid FROM user WHERE email = ?', [$email])) {
                        throw new \RuntimeException('Nieprawidłowy lub powtarzający się e-mail. Popraw dane przed utworzeniem kont.');
                    }
                    $emails[$email] = true;
                }
            }
            unset($employee);
            if ($input->getOption('execute')) {
                foreach ($employees as $employee) {
                    $user = $this->users->createEmployee($employee['email'], $employee['phone'], $employee['pesel']);
                    $user->setEmployee($this->em->find(Employee::class, $employee['uuid']));
                    $this->em->persist($user);
                }
                $this->em->flush();
                $connection->commit();
            } else {
                $connection->rollBack();
            }
            $output->writeln(sprintf('%s: %d brakujących kont (%s). Istniejące konta i hasła pozostają bez zmian.', $slug, count($employees), $input->getOption('execute') ? 'utworzono' : 'podgląd, bez zapisu'));

            return Command::SUCCESS;
        } catch (\Throwable $error) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->em->clear();
            $message = $error instanceof \InvalidArgumentException || $error instanceof \RuntimeException && !$error instanceof \Doctrine\DBAL\Exception ? $error->getMessage() : 'Nie udało się utworzyć kont. Wszystkie zmiany wycofano.';
            $output->writeln('<error>'.$message.'</error>');

            return Command::FAILURE;
        }
    }
}
