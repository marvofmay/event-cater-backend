<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Entity;

use App\Common\Domain\Trait\AttributesEntityTrait;
use App\Common\Domain\Trait\RelationsEntityTrait;
use App\Common\Domain\Trait\TimeStampableTrait;
use App\Module\Company\Domain\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'notification_recipient')]
#[ORM\Index(name: 'idx_user_uuid', columns: ['user_uuid'])]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class NotificationRecipient
{
    use TimeStampableTrait;
    use TimeStampableTrait;
    use AttributesEntityTrait;
    use RelationsEntityTrait;

    public const string ALIAS = 'notification_recipient';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\ManyToOne(targetEntity: NotificationMessage::class, inversedBy: 'recipients')]
    #[ORM\JoinColumn(name: 'message_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private NotificationMessage $message;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notificationRecipients')]
    #[ORM\JoinColumn(
        name: 'user_uuid',
        referencedColumnName: 'uuid',
        nullable: false,
        onDelete: 'CASCADE'
    )]
    private UserInterface $user;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $receivedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    private function __construct()
    {
    }

    public static function create(NotificationMessage $message, UserInterface $user): self
    {
        $self = new self();
        $self->uuid = Uuid::uuid4();
        $self->message = $message;
        $self->user = $user;
        $self->receivedAt = new \DateTime();
        $message->addRecipient($self);

        return $self;
    }

    public function markAsRead(): void
    {
        $this->readAt = new \DateTime();
    }

    public function getUUID(): UuidInterface
    {
        return $this->uuid;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function getReceivedAt(): ?\DateTimeInterface
    {
        return $this->receivedAt;
    }

    public function getMessage(): NotificationMessage
    {
        return $this->message;
    }
}
