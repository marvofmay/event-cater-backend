<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Application\QueryHandler\Template;

use App\Common\Application\Factory\TransformerFactory;
use App\Common\Application\QueryHandler\ListQueryHandlerAbstract;
use App\Common\Domain\Enum\TimeStampableEntityFieldEnum;
use App\Module\System\Notification\Application\Event\Template\NotificationTemplateSettingListedEvent;
use App\Module\System\Notification\Application\Query\Template\ListNotificationTemplateSettingQuery;
use App\Module\System\Notification\Domain\Entity\NotificationTemplateSetting;
use App\Module\System\Notification\Domain\Enum\NotificationTemplateSettingEntityFieldEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'query.bus')]
final class ListNotificationTemplateSettingQueryHandler extends ListQueryHandlerAbstract
{
    public function __construct(
        public EntityManagerInterface $entityManager,
        protected TransformerFactory $transformerFactory,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($entityManager, $transformerFactory);
    }

    public function __invoke(ListNotificationTemplateSettingQuery $query): array
    {
        $this->eventDispatcher->dispatch(new NotificationTemplateSettingListedEvent([$query]));
        $dto = $query->getQueryDTO();
        if ($query->getPage() < 1 || $query->getLimit() < 1 || $query->getLimit() > 500) {
            throw new \App\Common\Domain\Exception\BusinessRuleViolation('Dozwolony rozmiar strony to 1–500 rekordów, a numer strony musi być dodatni.');
        }
        $alias = $this->getAlias();
        $builder = $this->createBaseQueryBuilder()
            ->innerJoin($alias.'.event', 'notificationEvent')->addSelect('notificationEvent')
            ->innerJoin($alias.'.channel', 'notificationChannel')->addSelect('notificationChannel');
        foreach (['eventName' => 'notificationEvent.eventName', 'channelCode' => 'notificationChannel.channelCode'] as $field => $column) {
            if ($dto->$field !== null) {
                $builder->andWhere($column.' = :'.$field)->setParameter($field, $dto->$field);
            }
        }
        foreach (['isDefault', 'isActive'] as $field) {
            if ($dto->$field !== null) {
                $builder->andWhere($alias.'.'.$field.' = :'.$field)->setParameter($field, $dto->$field);
            }
        }
        foreach (['title', 'content'] as $field) {
            if ($dto->$field !== null) {
                $builder->andWhere($alias.'.'.$field.' LIKE :'.$field)->setParameter($field, '%'.$dto->$field.'%');
            }
        }
        if ($dto->phrase !== null && $dto->phrase !== '') {
            $builder->andWhere('notificationEvent.eventName LIKE :phrase OR notificationChannel.channelCode LIKE :phrase OR '.$alias.'.title LIKE :phrase OR '.$alias.'.content LIKE :phrase')->setParameter('phrase', '%'.$dto->phrase.'%');
        }
        $total = (int) (clone $builder)->select('COUNT('.$alias.'.uuid)')->getQuery()->getSingleScalarResult();
        $sortFields = ['eventName' => 'notificationEvent.eventName', 'channelCode' => 'notificationChannel.channelCode'];
        foreach (['title', 'content', 'isDefault', 'isActive', 'createdAt', 'updatedAt'] as $field) {
            $sortFields[$field] = $alias.'.'.$field;
        }
        if (!isset($sortFields[$query->getOrderBy()]) || !in_array(strtoupper($query->getOrderDirection()), ['ASC', 'DESC'], true)) {
            throw new \App\Common\Domain\Exception\BusinessRuleViolation('Nieprawidłowe sortowanie szablonów.');
        }
        $items = $builder->orderBy($sortFields[$query->getOrderBy()], strtoupper($query->getOrderDirection()))
            ->addOrderBy($alias.'.uuid', 'ASC')->setFirstResult($query->getOffset())->setMaxResults($query->getLimit())->getQuery()->getResult();
        return ['items' => $this->transformIncludes($items, []), 'total' => $total, 'page' => $query->getPage(), 'limit' => $query->getLimit()];
    }

    public function getEntityClass(): string
    {
        return NotificationTemplateSetting::class;
    }

    public function getAlias(): string
    {
        return NotificationTemplateSetting::ALIAS;
    }

    public function getDefaultOrderBy(): string
    {
        return TimeStampableEntityFieldEnum::CREATED_AT->value;
    }

    public function getAllowedFilters(): array
    {
        return [
            NotificationTemplateSettingEntityFieldEnum::EVENT_NANE->value,
            NotificationTemplateSettingEntityFieldEnum::CHANNEL_CODE->value,
            NotificationTemplateSettingEntityFieldEnum::TITLE->value,
            NotificationTemplateSettingEntityFieldEnum::CONTENT->value,
            NotificationTemplateSettingEntityFieldEnum::IS_DEFAULT->value,
            NotificationTemplateSettingEntityFieldEnum::IS_ACTIVE->value,
            TimeStampableEntityFieldEnum::CREATED_AT->value,
            TimeStampableEntityFieldEnum::UPDATED_AT->value,
            TimeStampableEntityFieldEnum::UPDATED_AT->value,
        ];
    }

    public function getPhraseSearchColumns(): array
    {
        return [
            NotificationTemplateSettingEntityFieldEnum::EVENT_NANE->value,
            NotificationTemplateSettingEntityFieldEnum::CHANNEL_CODE->value,
            NotificationTemplateSettingEntityFieldEnum::TITLE->value,
            NotificationTemplateSettingEntityFieldEnum::CONTENT->value,
            NotificationTemplateSettingEntityFieldEnum::IS_DEFAULT->value,
            NotificationTemplateSettingEntityFieldEnum::IS_ACTIVE->value,
        ];
    }

    public function getRelations(): array
    {
        return NotificationTemplateSetting::getRelations();
    }
}
