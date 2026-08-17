<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Infrastructure\Persistance\Doctrine\Recipient\Writer;

use App\Module\System\Notification\Domain\Entity\NotificationRecipient;
use App\Module\System\Notification\Domain\Interface\Recipient\NotificationRecipientWriterInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationRecipient>
 */
final class NotificationRecipientWriterRepository extends ServiceEntityRepository implements NotificationRecipientWriterInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationRecipient::class);
    }

    public function save(NotificationRecipient $notificationRecipient): void
    {
        $this->getEntityManager()->persist($notificationRecipient);
        $this->getEntityManager()->flush();
    }

    public function delete(NotificationRecipient $notificationRecipient): void
    {
        $this->getEntityManager()->remove($notificationRecipient);
        $this->getEntityManager()->flush();
    }
}
