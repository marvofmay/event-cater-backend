<?php

declare(strict_types=1);

namespace App\Common\Domain\Trait;

use App\Common\Domain\Entity\EventStore;
use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\EventStore\EventStoreCreator;
use App\Module\System\Application\Event\LogFileEvent;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

trait HandleEventStoreTrait
{
    private readonly EventStoreCreator $eventStoreCreator;
    private readonly Security $security;
    private readonly SerializerInterface $serializer;
    private readonly EventDispatcherInterface $eventDispatcher;
    private readonly MessageBusInterface $eventBus;

    private function handleEvent(object $event, string $aggregateClass): void
    {
        $user = $this->security->getUser();
        $userUUID = $user->getUuid();
        $serializedEvent = $this->serializer->serialize($event, 'json');

        $message = sprintf(
            'uuid: %s, eventClass: %s, aggregateClass: %s, data: %s, userUUID: %s',
            $event->uuid->toString(),
            $event::class,
            $aggregateClass,
            $serializedEvent,
            $userUUID
        );

        $this->eventStoreCreator->create(
            new EventStore(
                $event->uuid->toString(),
                $event::class,
                $aggregateClass,
                $serializedEvent,
                $user
            )
        );

        $this->eventDispatcher->dispatch($event);
        $this->eventBus->dispatch(new LogFileEvent($message, LogLevel::INFO, MonologChannelEnum::EVENT_STORE));
    }
}
