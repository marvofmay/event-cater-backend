<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Infrastructure\Persistance\Doctrine\Recipient\Reader;

use App\Module\System\Notification\Domain\Channel\InternalNotificationChannel;
use App\Module\System\Notification\Domain\Entity\NotificationRecipient;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientReaderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationRecipient>
 */
final class NotificationRecipientReaderRepository extends ServiceEntityRepository implements NotificationRecipientReaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationRecipient::class);
    }

    public function getUnreadNotificationRecipientByUUID(string $uuid): NotificationRecipient
    {
        return $this->createQueryBuilder(NotificationRecipient::ALIAS)
            ->innerJoin(
                NotificationRecipient::ALIAS . '.message',
                'nm'
            )
            ->innerJoin(
                'nm.channel',
                'ncs'
            )
            ->andWhere(NotificationRecipient::ALIAS . '.uuid = :uuid')
            ->andWhere(NotificationRecipient::ALIAS . '.readAt IS NULL')
            ->andWhere('ncs.channelCode = :channelCode')
            ->setParameter('uuid', $uuid)
            ->setParameter('channelCode', InternalNotificationChannel::getChanelCode())
            ->getQuery()
            ->getSingleResult();
    }

    public function getUnreadNotificationRecipientsByUUIDs(array $uuids): array
    {
        if (empty($uuids)) {
            return [];
        }

        return $this->createQueryBuilder(NotificationRecipient::ALIAS)
            ->innerJoin(
                NotificationRecipient::ALIAS . '.message',
                'nm'
            )
            ->innerJoin(
                'nm.channel',
                'ncs'
            )
            ->andWhere(NotificationRecipient::ALIAS . '.uuid IN (:uuids)')
            ->andWhere(NotificationRecipient::ALIAS . '.readAt IS NULL')
            ->andWhere('ncs.channelCode = :channelCode')
            ->setParameter('uuids', $uuids)
            ->setParameter('channelCode', InternalNotificationChannel::getChanelCode())
            ->getQuery()
            ->getResult();
    }

    public function countUnreadNotificationMessagesForUser(string $userUUID): int
    {
        return (int) $this->createQueryBuilder(NotificationRecipient::ALIAS)
            ->select('COUNT(' . NotificationRecipient::ALIAS . '.uuid)')
            ->innerJoin(
                NotificationRecipient::ALIAS . '.message',
                'nm'
            )
            ->innerJoin(
                'nm.channel',
                'ncs'
            )
            ->andWhere(NotificationRecipient::ALIAS . '.user = :userUUID')
            ->andWhere(NotificationRecipient::ALIAS . '.readAt IS NULL')
            ->andWhere('ncs.channelCode = :channelCode')
            ->setParameter('userUUID', $userUUID)
            ->setParameter('channelCode', InternalNotificationChannel::getChanelCode())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
