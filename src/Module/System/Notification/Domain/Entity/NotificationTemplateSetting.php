<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Entity;

use App\Common\Domain\Trait\AttributesEntityTrait;
use App\Common\Domain\Trait\RelationsEntityTrait;
use App\Common\Domain\Trait\TimeStampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'notification_template_setting')]
#[ORM\Index(name: 'event_name', columns: ['event_name'])]
#[ORM\Index(name: 'channel_code', columns: ['channel_code'])]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class NotificationTemplateSetting
{
    use TimeStampableTrait;
    use AttributesEntityTrait;
    use RelationsEntityTrait;

    public const string ALIAS = 'notification_template_setting';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\ManyToOne(targetEntity: NotificationEventSetting::class, inversedBy: 'templates')]
    #[ORM\JoinColumn(name: 'event_name', referencedColumnName: 'event_name', nullable: false, onDelete: 'CASCADE')]
    private NotificationEventSetting $event;

    #[ORM\ManyToOne(targetEntity: NotificationChannelSetting::class, inversedBy: 'templates')]
    #[ORM\JoinColumn(name: 'channel_code', referencedColumnName: 'channel_code', nullable: false, onDelete: 'CASCADE')]
    private NotificationChannelSetting $channel;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDefault;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isActive;

    private function __construct()
    {
    }

    public static function create(
        NotificationEventSetting $event,
        NotificationChannelSetting $channel,
        string $title,
        string $content,
        bool $isDefault,
        bool $isActive
    ): self {
        $self = new self();
        $self->uuid = Uuid::uuid4();
        $self->event = $event;
        $self->channel = $channel;
        $self->title = $title;
        $self->content = $content;
        $self->isDefault = $isDefault;
        $self->isActive = $isActive;

        return $self;
    }

    public function getUUID(): UuidInterface
    {
        return $this->uuid;
    }

    public function getEvent(): NotificationEventSetting
    {
        return $this->event;
    }

    public function getChannel(): NotificationChannelSetting
    {
        return $this->channel;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }
    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function changeTitle(string $title): void
    {
        if ($this->title === $title) {
            return;
        }

        $this->title = $title;
    }

    public function changeContent(string $content): void
    {
        $this->content = $content;
    }

    public function markAsDefault(): void
    {
        $this->isDefault = true;
    }

    public function markAsCustom(): void
    {
        $this->isDefault = false;
    }

    public function activate(): void
    {
        if ($this->isActive) {
            return;
        }

        $this->isActive = true;
    }

    public function deactivate(): void
    {
        if (!$this->isActive) {
            return;
        }

        $this->isActive = false;
    }
}
