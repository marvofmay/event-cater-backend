<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Infrastructure\Persistance\Doctrine\Message\Writer;

use App\Module\System\Notification\Domain\Entity\NotificationMessage;
use App\Module\System\Notification\Domain\Interface\Message\NotificationMessageWriterInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationMessage>
 */
class NotificationMessageWriterRepository extends ServiceEntityRepository implements NotificationMessageWriterInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationMessage::class);
    }

    public function save(NotificationMessage $notificationMessage): void
    {
        $this->getEntityManager()->persist($notificationMessage);
        $this->getEntityManager()->flush();
    }

    public function delete(NotificationMessage $notificationMessage): void
    {
        $this->getEntityManager()->remove($notificationMessage);
        $this->getEntityManager()->flush();
    }
}
