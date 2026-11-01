<?php

declare(strict_types=1);

namespace App\Module\Company\Application\QueryHandler\Employee;

use App\Common\Application\Factory\TransformerFactory;
use App\Common\Application\QueryHandler\ListQueryHandlerAbstract;
use App\Module\Company\Application\Event\Employee\EmployeeListedEvent;
use App\Module\Company\Application\Query\Employee\ListEmployeesQuery;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Infrastructure\ReadModel\EmployeeAvatarReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'query.bus')]
final class ListEmployeesQueryHandler extends ListQueryHandlerAbstract
{
    public function __construct(
        public EntityManagerInterface $entityManager,
        protected TransformerFactory $transformerFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EmployeeAvatarReader $avatars,
    ) {
        parent::__construct($entityManager, $transformerFactory);
    }

    public function __invoke(ListEmployeesQuery $query): array
    {
        $this->eventDispatcher->dispatch(new EmployeeListedEvent([$query]));

        $result = $this->handle($query);
        $avatars = $this->avatars->forEmployees(array_column($result['items'], 'uuid'));
        foreach ($result['items'] as &$employee) {
            $employee['avatar'] = $avatars[$employee['uuid']] ?? null;
        }

        return $result;
    }

    public function getEntityClass(): string
    {
        return Employee::class;
    }

    public function getAlias(): string
    {
        return Employee::ALIAS;
    }

    public function getDefaultOrderBy(): string
    {
        return Employee::COLUMN_CREATED_AT;
    }

    public function getAllowedFilters(): array
    {
        return [
            Employee::COLUMN_FIRST_NAME,
            Employee::COLUMN_LAST_NAME,
            Employee::COLUMN_ACTIVE,
            Employee::COLUMN_CREATED_AT,
            Employee::COLUMN_UPDATED_AT,
            Employee::COLUMN_DELETED_AT,
            Employee::RELATION_COMPANY,
            Employee::RELATION_DEPARTMENT,
            Employee::RELATION_ROLE,
            Employee::RELATION_POSITION,
            Employee::RELATION_CONTRACT_TYPE,
        ];
    }

    public function getPhraseSearchColumns(): array
    {
        return [
            Employee::COLUMN_FIRST_NAME,
            Employee::COLUMN_LAST_NAME,
        ];
    }

    public function getRelations(): array
    {
        return Employee::getRelations();
    }
}
