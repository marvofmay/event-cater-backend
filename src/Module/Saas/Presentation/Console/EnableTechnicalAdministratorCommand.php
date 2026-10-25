<?php

declare(strict_types=1);

namespace App\Module\Saas\Presentation\Console;

use App\Module\Saas\Infrastructure\ControlPlane;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:saas:enable-technical-administrator', description: 'Enable central technical access using an existing administrator account and its current password.')]
final class EnableTechnicalAdministratorCommand extends Command
{
    public function __construct(private readonly ControlPlane $control)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = strtolower(trim($input->getArgument('email')));
        $db = $this->control->db();
        $admin = $db->fetchAssociative('SELECT uuid FROM saas_administrator WHERE email=?', [$email]);
        if ($admin) {
            $db->update('saas_administrator', ['technical_access' => 1], ['uuid' => $admin['uuid']]);
        } else {
            $user = $db->fetchAssociative('SELECT password FROM user WHERE email=? AND tenant_administrator=1 AND employee_uuid IS NULL AND deleted_at IS NULL', [$email]);
            if (!$user) {
                $output->writeln('<error>An existing tenant administrator without an employee is required.</error>');

                return Command::FAILURE;
            }
            $db->insert('saas_administrator', ['uuid' => Uuid::uuid4()->toString(), 'email' => $email, 'password' => $user['password'], 'active' => 1, 'technical_access' => 1]);
        }
        $output->writeln('<info>Central technical access enabled; the existing password is preserved.</info>');

        return Command::SUCCESS;
    }
}
