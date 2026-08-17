<?php

declare(strict_types=1);

namespace App\Module\Company\Application\CommandHandler\Employee;

use App\Common\Domain\Abstract\CommandHandlerAbstract;
use App\Common\Domain\Service\EventStore\EventStoreCreator;
use App\Common\Domain\Trait\HandleEventStoreTrait;
use App\Module\Company\Application\Command\Employee\ChangeEmployeePersonalDataCommand;
use App\Module\Company\Domain\Aggregate\Employee\EmployeeAggregate;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\FirstName;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\LastName;
use App\Module\Company\Domain\Interface\Employee\EmployeeAggregateReaderInterface;
use App\Module\System\Domain\ValueObject\UserUUID;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class ChangeEmployeePersonalDataCommandHandler extends CommandHandlerAbstract
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

    public function __invoke(ChangeEmployeePersonalDataCommand $command): void
    {
        $user = $this->security->getUser();
        $loggedUserUUID = $user->getUuid()->toString();
        $employeeUUID = $user->getEmployee()->getUUID()->toString();

        $employeeAggregate = $this->employeeAggregateReaderRepository->getEmployeeAggregateByUUID(
            EmployeeUUID::fromString($employeeUUID)
        );

        $employeeAggregate->changePersonalData(
            firstName: FirstName::fromString($command->firstName),
            lastName: LastName::fromString($command->lastName),
            loggedUserUUID: UserUUID::fromString($loggedUserUUID)
        );

        $events = $employeeAggregate->pullEvents();
        foreach ($events as $event) {
            $this->handleEvent($event, EmployeeAggregate::class);
        }
    }
}
