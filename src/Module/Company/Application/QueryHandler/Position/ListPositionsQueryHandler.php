<?php

declare(strict_types=1);

namespace App\Module\Company\Application\QueryHandler\Position;

use App\Common\Application\Factory\TransformerFactory;
use App\Common\Application\QueryHandler\ListQueryHandlerAbstract;
use App\Common\Domain\Enum\TimeStampableEntityFieldEnum;
use App\Module\Company\Application\Event\Position\PositionListedEvent;
use App\Module\Company\Application\Query\Position\ListPositionsQuery;
use App\Module\Company\Domain\Entity\Position;
use App\Module\Company\Domain\Entity\PositionDepartment;
use App\Module\Company\Domain\Enum\Position\PositionEntityFieldEnum;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'query.bus')]
final class ListPositionsQueryHandler extends ListQueryHandlerAbstract
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected TransformerFactory $transformerFactory,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($entityManager, $transformerFactory);
    }

    public function __invoke(ListPositionsQuery $query): array
    {
        $this->eventDispatcher->dispatch(new PositionListedEvent([$query]));

        return $this->handle($query);
    }

    public function getEntityClass(): string
    {
        return Position::class;
    }

    protected function applySorting(QueryBuilder $qb, string $field, string $direction): QueryBuilder
    {
        if ($field !== $this->getAlias().'.departments') {
            return parent::applySorting($qb, $field, $direction);
        }
        $departmentName = $this->entityManager->createQueryBuilder()
            ->select('MIN(sortDepartment.name)')
            ->from(PositionDepartment::class, 'sortAssignment')
            ->innerJoin('sortAssignment.department', 'sortDepartment')
            ->where('sortAssignment.position = '.$this->getAlias())
            ->andWhere('sortAssignment.deletedAt IS NULL')
            ->andWhere('sortDepartment.deletedAt IS NULL');

        return $qb->addSelect('('.$departmentName->getDQL().') AS HIDDEN departmentName')
            ->orderBy('departmentName', $direction)
            ->addOrderBy($this->getAlias().'.name', 'ASC')
            ->addOrderBy($this->getAlias().'.uuid', 'ASC');
    }

    public function getAlias(): string
    {
        return Position::ALIAS;
    }

    public function getDefaultOrderBy(): string
    {
        return TimeStampableEntityFieldEnum::CREATED_AT->value;
    }

    public function getAllowedFilters(): array
    {
        return [
            PositionEntityFieldEnum::NAME->value,
            PositionEntityFieldEnum::DESCRIPTION->value,
            TimeStampableEntityFieldEnum::CREATED_AT->value,
            TimeStampableEntityFieldEnum::UPDATED_AT->value,
            TimeStampableEntityFieldEnum::DELETED_AT->value,
        ];
    }

    public function getPhraseSearchColumns(): array
    {
        return [
            PositionEntityFieldEnum::NAME->value,
            PositionEntityFieldEnum::DESCRIPTION->value,
        ];
    }

    public function getRelations(): array
    {
        return Position::getRelations();
    }
}
