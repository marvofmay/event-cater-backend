<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Service;

use App\Module\Company\Domain\Entity\User;
use App\Module\System\Infrastructure\Preference\PreferenceService;
use App\Module\System\Notification\Domain\Interface\Event\NotificationEventInterface;
use App\Module\System\Notification\Domain\Interface\Event\NotificationEventSettingCreatorInterface;
use App\Module\System\Notification\Domain\Interface\Event\NotificationEventSettingReaderInterface;
use App\Module\System\Notification\Domain\Interface\Channel\NotificationChannelSettingReaderInterface;
use App\Module\System\Notification\Domain\Interface\Message\NotificationMessageCreatorInterface;
use App\Module\System\Notification\Domain\Service\Channel\NotificationChannelDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Powiadamia pracownika o obsadzeniu na wydarzeniu albo o wyznaczeniu do kompletacji.
 * Obsada i kompletacja to niezależne role (można mieć jedną, obie albo tylko kompletację poza obsadą).
 * Wysyłka idzie wszystkimi kanałami włączonymi w firmie, z uwzględnieniem preferencji użytkownika.
 */
final readonly class EventAssignmentNotifier
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationEventSettingReaderInterface $eventSettings,
        private NotificationEventSettingCreatorInterface $eventSettingCreator,
        private NotificationChannelSettingReaderInterface $channelSettings,
        private NotificationMessageCreatorInterface $messages,
        private NotificationChannelDispatcher $dispatcher,
        private PreferenceService $preferences,
        private LoggerInterface $logger,
    ) {
    }

    public function staffAssigned(string $employeeUuid, string $eventTitle, string $eventDate, array $functionLabels): void
    {
        $functions = $functionLabels === [] ? '' : ' Twoje funkcje: '.implode(', ', $functionLabels).'.';
        $this->notify(
            'EventStaffAssigned',
            $employeeUuid,
            'Obsadzono Cię na wydarzeniu',
            sprintf('Zostałeś przypisany do obsady wydarzenia „%s” (%s).%s', $eventTitle, $eventDate, $functions),
        );
    }

    public function packerAssigned(string $employeeUuid, string $eventTitle, string $eventDate, bool $alsoOnStaff): void
    {
        $extra = $alsoOnStaff
            ? ' Jesteś też w obsadzie tego wydarzenia.'
            : ' Nie jesteś w obsadzie wydarzenia - masz dostęp do listy kompletacji.';
        $this->notify(
            'EventPackerAssigned',
            $employeeUuid,
            'Wyznaczono Cię do kompletacji',
            sprintf('Odpowiadasz za skompletowanie kateringu na wydarzenie „%s” (%s).%s', $eventTitle, $eventDate, $extra),
        );
    }

    private function notify(string $eventName, string $employeeUuid, string $title, string $content): void
    {
        try {
            $user = $this->em->createQueryBuilder()
                ->select('u')
                ->from(User::class, 'u')
                ->where('IDENTITY(u.employee) = :employeeUuid')
                ->setParameter('employeeUuid', $employeeUuid)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            if (!$user instanceof User) {
                return;
            }

            $eventDefinition = new class($eventName) implements NotificationEventInterface {
                public function __construct(private string $name)
                {
                }

                public function getName(): string
                {
                    return $this->name;
                }

                public function getLabel(): string
                {
                    return 'notification.event.'.$this->name;
                }
            };

            $eventSetting = $this->eventSettings->getByEventName($eventDefinition);
            if ($eventSetting === null) {
                $this->eventSettingCreator->create($eventDefinition, true);
                $eventSetting = $this->eventSettings->getByEventName($eventDefinition);
            }
            if ($eventSetting === null || !$eventSetting->isEnabled()) {
                return;
            }

            foreach ($this->channelSettings->getAll() as $channel) {
                if (!$channel->isEnabled()) {
                    continue;
                }
                $recipients = $this->preferences->filterRecipients($channel->getChannelCode(), [$user]);
                if ($recipients === []) {
                    continue;
                }

                $messageUuid = $this->messages->create($eventSetting, $channel, null, $title, $content, $recipients);
                try {
                    $this->dispatcher->dispatch($eventSetting, $channel, $recipients, $title, $content, [
                        'eventName' => $eventName,
                        'employeeUuid' => $employeeUuid,
                        'notificationMessageUUID' => $messageUuid,
                    ]);
                } catch (\Throwable $pushError) {
                    $this->logger->warning('Event assignment notification saved, but channel push failed', [
                        'eventName' => $eventName,
                        'employeeUuid' => $employeeUuid,
                        'channel' => $channel->getChannelCode(),
                        'exception' => $pushError,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Event assignment notification failed', [
                'eventName' => $eventName,
                'employeeUuid' => $employeeUuid,
                'exception' => $e,
            ]);
        }
    }
}
