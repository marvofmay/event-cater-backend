<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\QueryHandler\Message;

use App\Common\Application\Factory\TransformerFactory;
use App\Common\Application\QueryHandler\ListQueryHandlerAbstract;
use App\Common\Domain\Enum\TimeStampableEntityFieldEnum;
use App\Common\Domain\Interface\ListQueryInterface;
use App\Module\System\Notification\Application\Event\Message\NotificationMessageListedEvent;
use App\Module\System\Notification\Application\Query\Message\ListNotificationMessagesQuery;
use App\Module\System\Notification\Domain\Channel\InternalNotificationChannel;
use App\Module\System\Notification\Domain\Entity\NotificationChannelSetting;
use App\Module\System\Notification\Domain\Entity\NotificationMessage;
use App\Module\System\Notification\Domain\Entity\NotificationRecipient;
use App\Module\System\Notification\Domain\Enum\NotificationMessageEntityFieldEnum;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListNotificationMessagesQueryHandler extends ListQueryHandlerAbstract
{
    public function __construct(
        private readonly Security $security,
        protected EntityManagerInterface $entityManager,
        protected TransformerFactory $transformerFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($entityManager, $transformerFactory);
    }

    public function __invoke(ListNotificationMessagesQuery $query): array
    {
        $this->validate($query);

        $this->eventDispatcher->dispatch(new NotificationMessageListedEvent([$query]));

        return $this->handle($query);
    }

    public function getEntityClass(): string
    {
        return NotificationRecipient::class;
    }

    public function getAlias(): string
    {
        return NotificationRecipient::ALIAS;
    }

    public function getDefaultOrderBy(): string
    {
        return TimeStampableEntityFieldEnum::CREATED_AT->value;
    }

    public function getAllowedFilters(): array
    {
        return [
            NotificationMessageEntityFieldEnum::TITLE->value,
            NotificationMessageEntityFieldEnum::CONTENT->value,
            TimeStampableEntityFieldEnum::CREATED_AT->value,
            TimeStampableEntityFieldEnum::UPDATED_AT->value,
            TimeStampableEntityFieldEnum::DELETED_AT->value,
        ];
    }

    public function getPhraseSearchColumns(): array
    {
        return [
            NotificationMessageEntityFieldEnum::TITLE->value,
            NotificationMessageEntityFieldEnum::CONTENT->value,
        ];
    }

    public function getRelations(): array
    {
        return NotificationRecipient::getRelations();
    }

    public function handle(ListQueryInterface $query): array
    {
        $user = $this->security->getUser();

        if (!$user) {
            return [
                'total' => 0,
                'page' => $query->getPage(),
                'limit' => $query->getLimit(),
                'items' => [],
            ];
        }

        $alias = $this->getAlias();
        $messageAlias = NotificationMessage::ALIAS;
        $channelAlias = NotificationChannelSetting::ALIAS;

        $baseQb = $this->entityManager->createQueryBuilder();
        $baseQb = $this->setFilters($baseQb, $query->getFilters());

        $baseQb
            ->from(NotificationRecipient::class, $alias)
            ->innerJoin("$alias.message", $messageAlias)
            ->innerJoin("$messageAlias.channel", $channelAlias)
            ->andWhere("$alias.user = :user")
            ->andWhere("$channelAlias.channelCode = :channelCode")
            ->setParameter('user', $user)
            ->setParameter(
                'channelCode',
                InternalNotificationChannel::getChanelCode()
            );

        $total = (clone $baseQb)
            ->resetDQLPart('orderBy')
            ->select("COUNT($alias.uuid)")
            ->getQuery()
            ->getSingleScalarResult();

        $orderByField = $query->getOrderBy();
        $orderDirection = strtoupper($query->getOrderDirection() ?? 'DESC');

        $allowedMessageFields = ['title'];

        $readOrderExpr = "CASE WHEN $alias.readAt IS NULL THEN 0 ELSE 1 END";

        $qbIds = (clone $baseQb)
            ->resetDQLPart('select')
            ->select("$alias.uuid");

        if ($orderByField === null) {
            $qbIds
                ->addSelect("$readOrderExpr AS HIDDEN read_status")
                ->orderBy('read_status', 'ASC')
                ->addOrderBy("$alias.receivedAt", 'DESC');
        } elseif ($orderByField === 'receivedAt') {
            $qbIds->orderBy("$alias.receivedAt", $orderDirection);
        } elseif (in_array($orderByField, $allowedMessageFields, true)) {
            $qbIds->orderBy("$messageAlias.$orderByField", $orderDirection);
        } else {
            $qbIds->orderBy("$alias.receivedAt", 'DESC');
        }

        $ids = $qbIds
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->getLimit())
            ->getQuery()
            ->getScalarResult();

        if (!$ids) {
            return [
                'total' => (int) $total,
                'page' => $query->getPage(),
                'limit' => $query->getLimit(),
                'items' => [],
            ];
        }

        $ids = array_column($ids, 'uuid');

        $qb = $this->entityManager->createQueryBuilder()
            ->select($alias, $messageAlias)
            ->from(NotificationRecipient::class, $alias)
            ->innerJoin("$alias.message", $messageAlias)
            ->andWhere("$alias.uuid IN (:ids)")
            ->setParameter('ids', $ids);

        if ($orderByField === null) {
            $qb
                ->addSelect("$readOrderExpr AS HIDDEN read_status")
                ->orderBy('read_status', 'ASC')
                ->addOrderBy("$alias.receivedAt", 'DESC');

        } elseif ($orderByField === 'receivedAt') {
            $qb->orderBy("$alias.receivedAt", $orderDirection);

        } elseif (in_array($orderByField, $allowedMessageFields, true)) {
            $qb->orderBy("$messageAlias.$orderByField", $orderDirection);

        } else {
            $qb->orderBy("$alias.receivedAt", 'DESC');
        }

        $items = $qb->getQuery()->getResult();

        return [
            'total' => (int) $total,
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
            'items' => $this->transformIncludes($items, []),
        ];
    }

    public function setFilters(QueryBuilder $qb, array $filters): QueryBuilder
    {
        $messageAlias = NotificationMessage::ALIAS;
        foreach ($filters as $field => $value) {
            if ($value === null || !in_array($field, $this->getAllowedFilters(), true)) {
                continue;
            }

            if (is_bool($value)) {
                $qb->andWhere("$messageAlias.$field = :$field")
                    ->setParameter($field, $value);
                continue;
            }

            if (is_numeric($value)) {
                $qb->andWhere("$messageAlias.$field = :$field")
                    ->setParameter($field, $value);
                continue;
            }

            if (is_array($value) && isset($value['from'], $value['to'])) {
                if ($value['from'] !== null) {
                    $qb->andWhere("$messageAlias.$field >= :{$field}_from")
                        ->setParameter("{$field}_from", $value['from']);
                }
                if ($value['to'] !== null) {
                    $qb->andWhere("$messageAlias.$field <= :{$field}_to")
                        ->setParameter("{$field}_to", $value['to']);
                }
                continue;
            }

            $qb->andWhere(
                $qb->expr()->like(
                    "LOWER($messageAlias.$field)",
                    ":$field"
                )
            )->setParameter($field, '%' . strtolower((string) $value) . '%');
        }

        if (array_key_exists('deleted', $filters)) {
            if ((int) $filters['deleted'] === 0) {
                $qb->andWhere("$messageAlias.deletedAt IS NULL");
            } elseif ((int) $filters['deleted'] === 1) {
                $this->entityManager->getFilters()->disable('soft_delete');
                $qb->andWhere("$messageAlias.deletedAt IS NOT NULL");
            }
        } else {
            $qb->andWhere("$messageAlias.deletedAt IS NULL");
        }

        if (!empty($filters['phrase'])) {
            $expr = $qb->expr();

            $qb->andWhere(
                $expr->orX(
                    $expr->like("LOWER($messageAlias.title)", ':phrase'),
                    $expr->like("LOWER($messageAlias.content)", ':phrase'),
                )
            )->setParameter('phrase', '%' . strtolower($filters['phrase']) . '%');
        }

        return $qb;
    }
}
