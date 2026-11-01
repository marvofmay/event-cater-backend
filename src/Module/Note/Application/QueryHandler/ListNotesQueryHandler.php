<?php

declare(strict_types=1);

namespace App\Module\Note\Application\QueryHandler;

use App\Common\Application\Factory\TransformerFactory;
use App\Common\Application\QueryHandler\ListQueryHandlerAbstract;
use App\Common\Domain\Enum\TimeStampableEntityFieldEnum;
use App\Common\Domain\Interface\ListQueryInterface;
use App\Module\Note\Application\Event\NoteListedEvent;
use App\Module\Note\Application\Query\ListNotesQuery;
use App\Module\Note\Domain\Entity\Note;
use App\Module\Note\Domain\Enum\NoteEntityFieldEnum;
use App\Module\Note\Domain\Enum\NoteEntityRelationFieldEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListNotesQueryHandler extends ListQueryHandlerAbstract
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected TransformerFactory $transformerFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[AutowireIterator(tag: 'app.note.query.list.validator')] protected iterable $validators,
    ) {
        parent::__construct($entityManager, $transformerFactory);
    }

    public function __invoke(ListNotesQuery $query): array
    {
        $this->validate($query);

        $this->eventDispatcher->dispatch(new NoteListedEvent([$query]));

        return $this->handle($query);
    }

    public function getEntityClass(): string
    {
        return Note::class;
    }

    public function getAlias(): string
    {
        return Note::ALIAS;
    }

    public function getDefaultOrderBy(): string
    {
        return NoteEntityFieldEnum::PRIORITY->value;
    }

    public function getAllowedFilters(): array
    {
        return [
            NoteEntityFieldEnum::TITLE->value,
            NoteEntityFieldEnum::CONTENT->value,
            NoteEntityFieldEnum::PRIORITY->value,
            TimeStampableEntityFieldEnum::CREATED_AT->value,
            TimeStampableEntityFieldEnum::UPDATED_AT->value,
            TimeStampableEntityFieldEnum::DELETED_AT->value,
            NoteEntityRelationFieldEnum::USER->value
        ];
    }

    public function getPhraseSearchColumns(): array
    {
        return [
            NoteEntityFieldEnum::TITLE->value,
            NoteEntityFieldEnum::CONTENT->value,
            NoteEntityFieldEnum::PRIORITY->value,
        ];
    }

    public function getRelations(): array
    {
        return Note::getRelations();
    }

    public function handle(ListQueryInterface $query): array
    {
        $alias = $this->getAlias();

        $qb = $this->createBaseQueryBuilder();
        $qb = $this->setFilters($qb, $query->getFilters());

        $entityClass = $qb->getRootEntities()[0];
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $idField = $metadata->getSingleIdentifierFieldName();

        // -----------------------------------------
        // TOTAL
        // -----------------------------------------
        $total = (clone $qb)
            ->select("COUNT($alias.$idField)")
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        // -----------------------------------------
        // SORTING
        // -----------------------------------------
        $orderByField = $query->getOrderBy();
        $orderDirection = strtoupper($query->getOrderDirection() ?? 'DESC');

        // CASE dla priority
        $priorityOrderExpr = "CASE $alias.priority
        WHEN 'high' THEN 3
        WHEN 'medium' THEN 2
        WHEN 'low' THEN 1
        ELSE 0
    END";

        // -----------------------------------------
        // IDS (pagination-safe)
        // -----------------------------------------
        $qbIds = (clone $qb)->select("$alias.$idField");

        // DOMYŚLNE: priority DESC + createdAt DESC
        if ($orderByField === null) {
            $qbIds
                ->orderBy($priorityOrderExpr, 'DESC')
                ->addOrderBy("$alias.createdAt", 'DESC');
        }
        // PRIORITY (zawsze + createdAt DESC)
        elseif ($orderByField === NoteEntityFieldEnum::PRIORITY->value) {
            $qbIds
                ->orderBy($priorityOrderExpr, $orderDirection)
                ->addOrderBy("$alias.createdAt", 'DESC');
        }
        // INNE POLA
        else {
            $field = str_contains($orderByField, '.')
                ? $orderByField
                : "$alias.$orderByField";

            $qbIds->orderBy($field, $orderDirection);
        }

        $qbIds->addOrderBy("$alias.$idField", 'ASC');

        $ids = $qbIds
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->getLimit())
            ->getQuery()
            ->getScalarResult();

        if (empty($ids)) {
            return [
                'total' => (int) $total,
                'page'  => $query->getPage(),
                'limit' => $query->getLimit(),
                'items' => [],
            ];
        }

        $ids = array_map(static fn ($row) => array_values($row)[0], $ids);

        // -----------------------------------------
        // FETCH ENTITIES
        // -----------------------------------------
        $qb = $this->createBaseQueryBuilder()
            ->andWhere($qb->expr()->in("$alias.$idField", ':ids'))
            ->setParameter('ids', $ids);

        foreach ($query->getIncludes() as $relation) {
            if (in_array($relation, $this->getRelations(), true)) {
                $qb->leftJoin("$alias.$relation", $relation)
                    ->addSelect($relation);
            }
        }

        // MUSI być to samo sortowanie
        $qb->add('orderBy', $qbIds->getDQLPart('orderBy'));

        $items = $qb->getQuery()->getResult();

        return [
            'total' => (int) $total,
            'page'  => $query->getPage(),
            'limit' => $query->getLimit(),
            'items' => $this->transformIncludes($items, $query->getIncludes()),
        ];
    }
}
