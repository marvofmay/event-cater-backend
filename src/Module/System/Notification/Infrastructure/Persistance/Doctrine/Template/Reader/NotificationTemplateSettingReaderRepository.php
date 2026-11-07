<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Infrastructure\Persistance\Doctrine\Template\Reader;

use App\Module\System\Notification\Domain\Entity\NotificationChannelSetting;
use App\Module\System\Notification\Domain\Entity\NotificationEventSetting;
use App\Module\System\Notification\Domain\Entity\NotificationTemplateSetting;
use App\Module\System\Notification\Domain\Enum\NotificationChannelSettingEntityFieldEnum;
use App\Module\System\Notification\Domain\Enum\NotificationEventSettingEntityFieldEnum;
use App\Module\System\Notification\Domain\Enum\NotificationTemplateSettingEntityFieldEnum;
use App\Module\System\Notification\Domain\Enum\NotificationTemplateSettingEntityRelationFieldEnum;
use App\Module\System\Notification\Domain\Interface\Template\NotificationTemplateSettingReaderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationTemplateSetting>
 */
class NotificationTemplateSettingReaderRepository extends ServiceEntityRepository implements NotificationTemplateSettingReaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationTemplateSetting::class);
    }

    public function getAll(): Collection
    {
        return new ArrayCollection($this->findAll());
    }

    public function getByEventNameChannelCodeAndDefault(string $eventName, string $channelCode, bool $searchDefault): ?NotificationTemplateSetting
    {
        return $this->createQueryBuilder(NotificationTemplateSetting::ALIAS)
            ->join(NotificationTemplateSetting::ALIAS . '.' . NotificationTemplateSettingEntityRelationFieldEnum::EVENT->value, NotificationEventSetting::ALIAS)
            ->join(NotificationTemplateSetting::ALIAS. '.' . NotificationTemplateSettingEntityRelationFieldEnum::CHANNEL->value, NotificationChannelSetting::ALIAS)
            ->where(NotificationEventSetting::ALIAS. '.' . NotificationEventSettingEntityFieldEnum::EVENT_NAME->value . ' = :eventName')
            ->andWhere(NotificationChannelSetting::ALIAS. '.' . NotificationChannelSettingEntityFieldEnum::CHANNEL_CODE->value . ' = :channelCode')
            ->andWhere(NotificationTemplateSetting::ALIAS. '.'. NotificationTemplateSettingEntityFieldEnum::IS_DEFAULT->value .' = :isDefault')
            ->setParameters(new ArrayCollection([
                new Parameter('eventName', $eventName),
                new Parameter('channelCode', $channelCode),
                new Parameter('isDefault', $searchDefault),
            ]))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getActiveByEventName(string $eventName): Collection
    {
        return new ArrayCollection($this->createQueryBuilder(NotificationTemplateSetting::ALIAS)
            ->join(NotificationTemplateSetting::ALIAS . '.' . NotificationTemplateSettingEntityRelationFieldEnum::EVENT->value, NotificationEventSetting::ALIAS)
            ->join(NotificationTemplateSetting::ALIAS. '.' . NotificationTemplateSettingEntityRelationFieldEnum::CHANNEL->value, NotificationChannelSetting::ALIAS)
            ->where(NotificationEventSetting::ALIAS. '.' . NotificationEventSettingEntityFieldEnum::EVENT_NAME->value . ' = :eventName')
            ->andWhere(NotificationEventSetting::ALIAS. '.'. NotificationEventSettingEntityFieldEnum::ENABLED->value .' = :eventEnabled')
            ->andWhere(NotificationTemplateSetting::ALIAS. '.'. NotificationTemplateSettingEntityFieldEnum::IS_ACTIVE->value .' = :isActive')
            ->andWhere(NotificationChannelSetting::ALIAS. '.'. NotificationChannelSettingEntityFieldEnum::ENABLED->value .' = :isEnabled')
            ->setParameters(new ArrayCollection([
                new Parameter('eventName', $eventName),
                new Parameter('eventEnabled', true),
                new Parameter('isActive', true),
                new Parameter('isEnabled', true),
            ]))
            ->getQuery()
            ->getResult());
    }
}
