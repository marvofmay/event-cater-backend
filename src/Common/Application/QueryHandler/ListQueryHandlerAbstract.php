<?php

declare(strict_types=1);

namespace App\Common\Application\QueryHandler;

use App\Common\Application\Factory\TransformerFactory;
use App\Common\Domain\Interface\ListQueryHandlerInterface;
use App\Common\Domain\Interface\ListQueryInterface;
use App\Common\Domain\Interface\QueryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

abstract class ListQueryHandlerAbstract implements ListQueryHandlerInterface
{
    protected iterable $validators = [];

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected TransformerFactory $transformerFactory,
    ) {
    }

    abstract public function getEntityClass(): string;

    abstract public function getAlias(): string;

    abstract public function getDefaultOrderBy(): string;

    abstract public function getAllowedFilters(): array;

    abstract public function getRelations(): array;

    abstract public function getPhraseSearchColumns(): array;

    public function handle(ListQueryInterface $query): array
    {
        $alias = $this->getAlias();

        $baseQB = $this->createBaseQueryBuilder();
        $baseQB = $this->setFilters($baseQB, $query->getFilters());

        $entityClass = $baseQB->getRootEntities()[0];
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $identifier = $metadata->getSingleIdentifierFieldName();

        // całkowita liczba rekordów
        $total = (clone $baseQB)
            ->resetDQLPart('orderBy')
            ->select("COUNT($alias.$identifier)")
            ->getQuery()
            ->getSingleScalarResult();

        // ustawienie pola i kierunku sortowania
        $orderByField = $query->getOrderBy() ?? $this->getDefaultOrderBy();
        $orderDirection = strtoupper($query->getOrderDirection() ?? 'ASC');
        if (!str_contains($orderByField, '.')) {
            $orderByField = "$alias.$orderByField";
        }

        // pobranie tylko ID w kolejności sortowania
        $idsQuery = (clone $baseQB)->select("$alias.$identifier");
        $ids = $this->applySorting($idsQuery, $orderByField, $orderDirection)
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->getLimit())
            ->getQuery()
            ->getScalarResult();

        if (empty($ids)) {
            return [
                'total' => (int)$total,
                'page'  => $query->getPage(),
                'limit' => $query->getLimit(),
                'items' => [],
            ];
        }

        $ids = array_map(fn ($row) => array_values($row)[0], $ids);

        // pobranie pełnych encji wg wybranych ID
        $qb = $this->createBaseQueryBuilder();
        $qb->andWhere($qb->expr()->in("$alias.$identifier", ':ids'))
            ->setParameter('ids', $ids);

        // dołączenie relacji
        foreach ($query->getIncludes() as $relation) {
            if (in_array($relation, $this->getRelations(), true)) {
                $qb->leftJoin("$alias.$relation", $relation)
                    ->addSelect($relation);
            }
        }

        // zachowanie sortowania wg zapytania
        $this->applySorting($qb, $orderByField, $orderDirection);

        $items = $qb->getQuery()->getResult();

        return [
            'total' => (int)$total,
            'page'  => $query->getPage(),
            'limit' => $query->getLimit(),
            'items' => $this->transformIncludes($items, $query->getIncludes()),
        ];
    }

    protected function applySorting(QueryBuilder $qb, string $field, string $direction): QueryBuilder
    {
        return $qb->orderBy($field, $direction);
    }

    public function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->entityManager
            ->getRepository($this->getEntityClass())
            ->createQueryBuilder($this->getAlias());
    }

    public function setFilters(QueryBuilder $qb, array $filters): QueryBuilder
    {
        $alias = $this->getAlias();
        $allowed = $this->getAllowedFilters();

        foreach ($filters as $field => $value) {
            if ($value === null) {
                continue;
            }

            if (str_ends_with($field, 'UUID')) {
                $relationName = lcfirst(substr($field, 0, -4));
                if (!in_array($relationName, $allowed, true)) {
                    continue;
                }
                if (!in_array($relationName, $qb->getAllAliases(), true)) {
                    $qb->leftJoin("$alias.$relationName", $relationName);
                }
                $qb->andWhere("$relationName.uuid = :$field")->setParameter($field, $value);
                continue;
            }

            if (!in_array($field, $allowed, true)) {
                continue;
            }

            if (is_bool($value) || in_array($field, ['active'], true)) {
                $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($boolValue !== null) {
                    $qb->andWhere("$alias.$field = :$field")->setParameter($field, $boolValue);
                }
                continue;
            }

            if (is_numeric($value)) {
                $qb->andWhere("$alias.$field = :$field")->setParameter($field, $value);
                continue;
            }

            if (is_array($value) && isset($value['from'], $value['to'])) {
                if ($value['from'] !== null) {
                    $qb->andWhere("$alias.$field >= :{$field}_from")->setParameter("{$field}_from", $value['from']);
                }
                if ($value['to'] !== null) {
                    $qb->andWhere("$alias.$field <= :{$field}_to")->setParameter("{$field}_to", $value['to']);
                }
                continue;
            }

            $qb->andWhere($qb->expr()->like("LOWER($alias.$field)", ":$field"))
                ->setParameter($field, '%' . strtolower($value) . '%');
        }

        $deletedCol = "$alias.deletedAt";
        if (array_key_exists('deleted', $filters)) {
            switch ($filters['deleted']) {
                case 0:
                    $qb->andWhere($qb->expr()->isNull($deletedCol));
                    break;
                case 1:
                    $this->entityManager->getFilters()->disable('soft_delete');
                    $qb->andWhere($qb->expr()->isNotNull($deletedCol));
                    break;
            }
        } else {
            $qb->andWhere($qb->expr()->isNull($deletedCol));
        }

        if (!empty($filters['phrase'])) {
            $columns = $this->getPhraseSearchColumns();
            $expr = $qb->expr();
            $or = [];
            foreach ($columns as $col) {
                $or[] = $expr->like("LOWER($alias.$col)", ':phrase');
            }
            if ($or) {
                $qb->andWhere(call_user_func_array([$expr, 'orX'], $or))
                    ->setParameter('phrase', '%' . strtolower($filters['phrase']) . '%');
            }
        }

        return $qb;
    }

    public function getTransformer(): object
    {
        return $this->transformerFactory->createForHandler(static::class);
    }

    public function transformIncludes(array $items, array $includes): array
    {
        $transformer = $this->getTransformer();
        return array_map(fn ($item) => $this->transformItem($transformer, $item, $includes), $items);
    }

    public function transformItem($transformer, $item, array $includes): array
    {
        if (!method_exists($transformer, 'transformToArray')) {
            throw new \RuntimeException('Transformer must implement transformToArray()');
        }
        return $transformer->transformToArray($item, $includes);
    }

    protected function validate(QueryInterface $query): void
    {
        foreach ($this->validators as $validator) {
            if (method_exists($validator, 'supports') && $validator->supports($query)) {
                $validator->validate($query);
            }
        }
    }
}
