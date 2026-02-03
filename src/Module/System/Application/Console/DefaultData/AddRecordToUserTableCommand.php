<?php

declare(strict_types=1);

namespace App\Module\System\Application\Console\DefaultData;

use App\Module\Company\Domain\Entity\User;
use App\Module\Company\Domain\Service\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AsCommand(name: 'app:add-record-to-user-table')]
#[AutoconfigureTag('app.command.initialize-system-default-data', ['priority' => -100])]
class AddRecordToUserTableCommand extends Command
{
    private const string DESCRIPTION = 'Fills the User table with default data';
    private const string HELP = 'This command allows you to populate the User table with default data.';
    private const string SUCCESS_MESSAGE = 'User table has been filled with data successfully!';
    private const string INFO_EXISTS = 'Default user already exists. No changes made.';
    private const string CHECKING_INFO = 'Checking if default user exists...';
    private const string DEFAULT_EMAIL = 'admin.ec@gmail.com';
    private const string DEFAULT_PASSWORD = 'Admin123!';

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly UserFactory $userFactory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::DESCRIPTION)
            ->setHelp(self::HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(self::CHECKING_INFO);

        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy([User::COLUMN_EMAIL => self::DEFAULT_EMAIL]);

        if (null !== $existingUser) {
            $output->writeln(sprintf('<comment>%s</comment>', self::INFO_EXISTS));

            return Command::SUCCESS;
        }

        $user = $this->userFactory->create(self::DEFAULT_EMAIL, self::DEFAULT_PASSWORD);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln(sprintf('<info>%s</info>', self::SUCCESS_MESSAGE));

        return Command::SUCCESS;
    }
}
