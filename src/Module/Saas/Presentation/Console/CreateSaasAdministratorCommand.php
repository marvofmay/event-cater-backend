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
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:saas:create-administrator', description: 'Create a separate SaaS administrator. Password is never accepted as a command-line argument.')]
final class CreateSaasAdministratorCommand extends Command
{
    public function __construct(private readonly ControlPlane $control)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED)->addOption('password-stdin', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim($input->getArgument('email')));
        $password = $input->getOption('password-stdin') ? rtrim(stream_get_contents(STDIN), "\r\n") : $io->askHidden('Hasło (12–72 znaki)');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180 || !is_string($password) || strlen($password) < 12 || strlen($password) > 72) {
            $io->error('Nieprawidłowy e-mail lub długość hasła.');

            return Command::FAILURE;
        }
        try {
            $this->control->db()->insert('saas_administrator', ['uuid' => Uuid::uuid4()->toString(), 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT), 'active' => true]);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            $io->error('Administrator już istnieje.');

            return Command::FAILURE;
        }
        $io->success('Utworzono administratora SaaS.');

        return Command::SUCCESS;
    }
}
