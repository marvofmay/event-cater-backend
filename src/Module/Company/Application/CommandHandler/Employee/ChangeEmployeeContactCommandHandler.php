<?php

declare(strict_types=1);

namespace App\Module\Company\Application\CommandHandler\Employee;

use App\Common\Domain\Abstract\CommandHandlerAbstract;
use App\Common\Domain\Service\EventStore\EventStoreCreator;
use App\Common\Domain\Trait\HandleEventStoreTrait;
use App\Module\Company\Application\Command\Employee\ChangeEmployeeContactCommand;
use App\Module\Company\Domain\Aggregate\Employee\EmployeeAggregate;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Aggregate\ValueObject\Phones;
use App\Module\Company\Domain\Interface\Employee\EmployeeAggregateReaderInterface;
use App\Module\System\Domain\ValueObject\UserUUID;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class ChangeEmployeeContactCommandHandler extends CommandHandlerAbstract
{
    use HandleEventStoreTrait;

    public function __construct(
        private readonly EventStoreCreator $eventStoreCreator,
        private readonly Security $security,
        private readonly SerializerInterface $serializer,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EmployeeAggregateReaderInterface $employeeAggregateReaderRepository,
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(ChangeEmployeeContactCommand $command): void
    {
        $user = $this->security->getUser();
        $loggedUserUUID = $user->getUuid()->toString();
        $employeeUUID = $user->getEmployee()->getUUID()->toString();

        $employeeAggregate = $this->employeeAggregateReaderRepository->getEmployeeAggregateByUUID(
            EmployeeUUID::fromString($employeeUUID)
        );

        $employeeAggregate->changeContact(
            loggedUserUUID: UserUUID::fromString($loggedUserUUID),
            phones: Phones::fromArray($command->phones)
        );

        $events = $employeeAggregate->pullEvents();
        foreach ($events as $event) {
            $this->handleEvent($event, EmployeeAggregate::class);
        }
    }
}
