<?php

declare(strict_types=1);

namespace App\Module\Saas\Presentation\Console;

use App\Module\Saas\Infrastructure\ControlPlane;
use App\Module\Saas\Infrastructure\TenantContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:saas:seed-tenant-notifications',
    description: 'Seed notification events/channels/templates into tenant databases (default: all ready tenants except default)',
)]
final class SeedTenantNotificationsCommand extends Command
{
    private const COMMANDS = [
        'app:add-record-to-notification-channel-setting-table',
        'app:add-record-to-notification-event-setting-table',
        'app:add-record-to-notification-template-setting-table',
    ];

    public function __construct(
        private readonly ControlPlane $control,
        private readonly TenantContext $tenant,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Limit to one tenant slug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            $output->writeln('<error>Application is not available</error>');

            return Command::FAILURE;
        }

        $tenants = $this->control->db()->fetchAllAssociative(
            "SELECT slug FROM saas_tenant WHERE provisioning='ready' AND slug<>'default' AND status='active' ORDER BY slug",
        );
        foreach ($tenants as $tenant) {
            $slug = $tenant['slug'];
            if ($input->getOption('tenant') && $slug !== $input->getOption('tenant')) {
                continue;
            }
            $output->writeln('');
            $output->writeln('<info>Seeding notifications for tenant: '.$slug.'</info>');
            $this->tenant->select($slug);
            foreach (self::COMMANDS as $name) {
                $result = $application->find($name)->run(new ArrayInput([]), $output);
                if ($result !== Command::SUCCESS) {
                    $output->writeln('<error>Failed: '.$name.' on '.$slug.'</error>');

                    return $result;
                }
            }
        }

        return Command::SUCCESS;
    }
}
