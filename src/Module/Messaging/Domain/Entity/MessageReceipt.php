<?php

declare(strict_types=1);

namespace App\Module\Messaging\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'direct_message_receipt')]
#[ORM\Index(name: 'direct_message_user_unread', columns: ['user_uuid', 'deleted_at', 'read_at', 'message_uuid'])]
class MessageReceipt
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: DirectMessage::class, inversedBy: 'receipts')]
    #[ORM\JoinColumn(name: 'message_uuid', referencedColumnName: 'uuid', onDelete: 'CASCADE')]
    private DirectMessage $message;
    #[ORM\Id] #[ORM\Column(length: 36)] private string $userUuid;
    #[ORM\Column(length: 300)] private string $recipientName;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $readAt = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(DirectMessage $message, string $userUuid, string $recipientName)
    {
        $this->message = $message;
        $this->userUuid = Uuid::fromString($userUuid)->toString();
        $this->recipientName = $recipientName;
    }

    public function markRead(): void
    {
        if ($this->deletedAt !== null) {
            throw new \App\Common\Domain\Exception\BusinessRuleViolation('Nie można oznaczyć usuniętej wiadomości jako przeczytanej.');
        }
        $this->readAt ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function userUuid(): string
    {
        return $this->userUuid;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
