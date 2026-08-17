<?php

namespace App\Module\System\Application\EventSubscriber;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Module\System\Domain\Interface\EventLog\EventLogCreatorInterface;
use App\Module\System\Domain\Interface\EventLog\LoggableEventInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

final readonly class GenericEventLoggerSubscriber
{
    public function __construct(
        protected EntityManagerInterface $em,
        protected SerializerInterface $serializer,
        private ServiceProviderInterface $loggers,
        protected Security $security,
        private EventLogCreatorInterface $eventLogCreator,
        protected EventDispatcherInterface $dispatcher,
    ) {
    }

    public function onLoggableEvent(LoggableEventInterface $event): void
    {
        $this->log($event::class, $event->getEntityClass(), $event->getData());
    }

    protected function log(string $eventClass, string $entityClass, mixed $data): void
    {
        $jsonData = $this->serializeData($data);
        $user = $this->security->getUser();

        $this->logToFile($eventClass, $entityClass, $jsonData, $user);
        $this->saveToDatabase($eventClass, $entityClass, $jsonData, $user);
    }

    private function serializeData(mixed $data): string
    {
        return $this->serializer->serialize($data, 'json', [
            'circular_reference_handler' => fn ($object) => method_exists($object, 'getUUID') ? $object->getUUID() : spl_object_id($object),
        ]);
    }

    private function logToFile(string $eventClass, string $entityClass, string $jsonData, ?UserInterface $user): void
    {
        $logger = $this->loggers->has(MonologChannelEnum::EVENT_LOG->value)
            ? $this->loggers->get(MonologChannelEnum::EVENT_LOG->value)
            : $this->loggers->get(MonologChannelEnum::MAIN->value);

        $logger->info('----------------- EVENT LOG -----------------');
        $logger->info("event: $eventClass");
        $logger->info("entity: $entityClass");
        $logger->info("data: $jsonData");
        $logger->info('userUUID: ' . $user?->getUUID());
        $logger->info('---------------------------------------------');
    }

    private function saveToDatabase(string $eventClass, string $entityClass, string $jsonData, ?UserInterface $user): void
    {
        $this->eventLogCreator->create(
            eventClass: $eventClass,
            entityClass: $entityClass,
            jsonData: $jsonData,
            user: $user,
        );
    }
}
