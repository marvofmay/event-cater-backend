<?php

declare(strict_types=1);

namespace App\Module\Company\Application\CommandHandler\Department;

use App\Common\Domain\Abstract\CommandHandlerAbstract;
use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\EventStore\EventStoreCreator;
use App\Common\Domain\Trait\ClassNameExtractorTrait;
use App\Common\Domain\Trait\HandleEventStoreTrait;
use App\Module\Company\Application\Command\Department\DeleteMultipleDepartmentsCommand;
use App\Module\Company\Domain\Aggregate\Department\DepartmentAggregate;
use App\Module\Company\Domain\Aggregate\Department\ValueObject\DepartmentUUID;
use App\Module\Company\Domain\Event\Department\DepartmentMultipleDeletedEvent;
use App\Module\Company\Domain\Interface\Department\DepartmentAggregateReaderInterface;
use App\Module\System\Application\Event\LogFileEvent;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteMultipleDepartmentsCommandHandler extends CommandHandlerAbstract
{
    use HandleEventStoreTrait;
    use ClassNameExtractorTrait;

    public function __construct(
        private readonly DepartmentAggregateReaderInterface $departmentAggregateReaderRepository,
        private readonly EventStoreCreator $eventStoreCreator,
        private readonly Security $security,
        private readonly SerializerInterface $serializer,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[AutowireIterator(tag: 'app.department.delete_multiple.validator')] protected iterable $validators,
    ) {
    }

    public function __invoke(DeleteMultipleDepartmentsCommand $command): void
    {
        $this->validate($command);

        $deletedUUIDs = [];
        foreach ($command->departmentsUUIDs as $departmentUUID) {
            $uuid = DepartmentUUID::fromString($departmentUUID);
            $departmentAggregate = $this->departmentAggregateReaderRepository->getDepartmentAggregateByUUID($uuid);
            $departmentAggregate->delete();

            $events = $departmentAggregate->pullEvents();
            foreach ($events as $event) {
                $this->handleEvent($event, DepartmentAggregate::class);
            }

            $deletedUUIDs[] = $uuid->toString();
        }

        $user = $this->security->getUser();
        $userUUID = $user->getUuid();

        $multiEvent = new DepartmentMultipleDeletedEvent($deletedUUIDs);
        $message = sprintf(
            'uuid: %s, eventClass: %s, aggregateClass: %s, data: %s, userUUID: %s',
            implode(',', $deletedUUIDs),
            $this->getShortClassName($multiEvent::class),
            DepartmentAggregate::class,
            $this->serializer->serialize($multiEvent, 'json'),
            $userUUID
        );

        $this->eventBus->dispatch(new LogFileEvent($message, LogLevel::INFO, MonologChannelEnum::EVENT_STORE));
    }
}
