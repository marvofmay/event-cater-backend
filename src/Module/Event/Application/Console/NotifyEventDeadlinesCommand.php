<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Console;

use App\Module\Event\Application\Service\EventFanOutNotifier;
use App\Module\Event\Domain\Entity\EventPreparationList;
use App\Module\Event\Domain\Enum\PreparationStatus;
use App\Module\Kitchen\Domain\Entity\KitchenOrder;
use App\Module\Kitchen\Domain\Enum\KitchenStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:notify-event-deadlines', description: 'Send reminders for upcoming preparation deadlines and kitchen due dates')]
final class NotifyEventDeadlinesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventFanOutNotifier $notifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('within-hours', null, InputOption::VALUE_REQUIRED, 'How many hours ahead to remind', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hours = max(1, (int) $input->getOption('within-hours'));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $windowStart = $now->modify('-1 hour');
        $until = $now->modify('+'.$hours.' hours');
        $prepSent = 0;
        $kitchenSent = 0;

        /** @var list<EventPreparationList> $lists */
        $lists = $this->em->createQueryBuilder()
            ->select('l')
            ->from(EventPreparationList::class, 'l')
            ->where('l.deadlineAt IS NOT NULL')
            ->andWhere('l.deadlineAt <= :until')
            ->andWhere('l.deadlineAt >= :now')
            ->andWhere('l.deadlineReminderSentAt IS NULL')
            ->andWhere('l.status != :loaded')
            ->setParameter('until', $until)
            ->setParameter('now', $windowStart)
            ->setParameter('loaded', PreparationStatus::LOADED)
            ->getQuery()
            ->getResult();

        foreach ($lists as $list) {
            $deadline = $list->deadlineAt();
            if ($deadline === null) {
                continue;
            }
            $event = $list->event();
            if (in_array($event->status()->value, ['draft', 'cancelled', 'completed'], true)) {
                continue;
            }
            $this->notifier->preparationDeadlineReminder(
                $event->uuid(),
                $deadline->format('Y-m-d H:i'),
            );
            $list->markDeadlineReminderSent($now);
            ++$prepSent;
        }

        /** @var list<KitchenOrder> $orders */
        $orders = $this->em->createQueryBuilder()
            ->select('o')
            ->from(KitchenOrder::class, 'o')
            ->where('o.dueReminderSentAt IS NULL')
            ->andWhere('o.status NOT IN (:done)')
            ->setParameter('done', [KitchenStatus::HANDED_OVER, KitchenStatus::CANCELLED])
            ->getQuery()
            ->getResult();

        foreach ($orders as $order) {
            $dueAt = $order->dueAt();
            if ($dueAt === null) {
                continue;
            }
            $dueUtc = $dueAt->setTimezone(new \DateTimeZone('UTC'));
            if ($dueUtc < $windowStart || $dueUtc > $until) {
                continue;
            }
            $this->notifier->kitchenDueReminder(
                $order->eventUuid(),
                $dueUtc->format('Y-m-d H:i'),
            );
            $order->markDueReminderSent($now);
            ++$kitchenSent;
        }

        $this->em->flush();
        $output->writeln(sprintf(
            'Reminders sent: preparation=%d, kitchen=%d (within %dh).',
            $prepSent,
            $kitchenSent,
            $hours,
        ));

        return Command::SUCCESS;
    }
}
