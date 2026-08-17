<?php

declare(strict_types=1);

namespace App\Module\Company\Application\CommandHandler\Employee;

use App\Common\Domain\Abstract\CommandHandlerAbstract;
use App\Common\Domain\Service\EventStore\EventStoreCreator;
use App\Common\Domain\Trait\HandleEventStoreTrait;
use App\Module\Company\Application\Command\Employee\UpdateEmployeeCommand;
use App\Module\Company\Domain\Aggregate\Company\ValueObject\CompanyUUID;
use App\Module\Company\Domain\Aggregate\Department\ValueObject\DepartmentUUID;
use App\Module\Company\Domain\Aggregate\Employee\EmployeeAggregate;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\ContractTypeUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmploymentFrom;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmploymentTo;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\FirstName;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\LastName;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\PESEL;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\PositionUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\RoleUUID;
use App\Module\Company\Domain\Aggregate\ValueObject\Address;
use App\Module\Company\Domain\Aggregate\ValueObject\Emails;
use App\Module\Company\Domain\Aggregate\ValueObject\Phones;
use App\Module\Company\Domain\Interface\Employee\EmployeeAggregateReaderInterface;
use App\Module\System\Domain\ValueObject\UserUUID;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateEmployeeCommandHandler extends CommandHandlerAbstract
{
    use HandleEventStoreTrait;

    public function __construct(
        private readonly EventStoreCreator $eventStoreCreator,
        private readonly Security $security,
        private readonly SerializerInterface $serializer,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EmployeeAggregateReaderInterface $employeeAggregateReaderRepository,
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[AutowireIterator(tag: 'app.employee.update.validator')] protected iterable $validators,
    ) {
    }

    public function __invoke(UpdateEmployeeCommand $command): void
    {
        $this->validate($command);

        $user = $this->security->getUser();
        $loggedUserUUID = $user->getUuid()->toString();

        $employeeAggregate = $this->employeeAggregateReaderRepository->getEmployeeAggregateByUUID(
            EmployeeUUID::fromString($command->employeeUUID)
        );

        $employeeAggregate->update(
            FirstName::fromString($command->firstName),
            LastName::fromString($command->lastName),
            PESEL::fromString($command->pesel),
            EmploymentFrom::fromString($command->employmentFrom),
            CompanyUUID::fromString($command->companyUUID),
            DepartmentUUID::fromString($command->departmentUUID),
            PositionUUID::fromString($command->positionUUID),
            ContractTypeUUID::fromString($command->contractTypeUUID),
            RoleUUID::fromString($command->roleUUID),
            Emails::fromArray([$command->email]),
            Address::fromDTO($command->address),
            UserUUID::fromString($loggedUserUUID),
            $command->externalCode,
            $command->internalCode,
            $command->active,
            Phones::fromArray($command->phones),
            $command->parentEmployeeUUID ? EmployeeUUID::fromString($command->parentEmployeeUUID) : null,
            $command->employmentTo ? EmploymentTo::fromString($command->employmentTo, EmploymentFrom::fromString($command->employmentFrom)) : null,
        );

        $events = $employeeAggregate->pullEvents();
        foreach ($events as $event) {
            $this->handleEvent($event, EmployeeAggregate::class);
        }
    }
}
