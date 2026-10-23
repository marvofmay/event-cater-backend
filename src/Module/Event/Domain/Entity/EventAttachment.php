<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'event_attachment')]
class EventAttachment
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;
    #[ORM\ManyToOne(targetEntity: CateringEvent::class)]
    #[ORM\JoinColumn(name: 'event_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private CateringEvent $event;
    #[ORM\Column(length: 36, unique: true)]
    private string $fileUuid;
    #[ORM\Column(length: 100)]
    private string $mimeType;
    #[ORM\Column(type: 'integer')]
    private int $size;
    #[ORM\Column(length: 36)]
    private string $authorUuid;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(CateringEvent $event, string $fileUuid, string $mimeType, int $size, string $authorUuid, ?string $description)
    {
        if ($description !== null && mb_strlen($description) > 4000) {
            throw new BusinessRuleViolation('Attachment description exceeds 4000 characters.');
        }
        $this->uuid = Uuid::uuid4()->toString();
        $this->event = $event;
        $this->fileUuid = $fileUuid;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->authorUuid = $authorUuid;
        $this->description = $description;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'eventUuid' => $this->event->uuid(), 'fileUuid' => $this->fileUuid,
            'mimeType' => $this->mimeType, 'size' => $this->size, 'authorUuid' => $this->authorUuid,
            'description' => $this->description, 'createdAt' => $this->createdAt->format(DATE_ATOM)];
    }

}
