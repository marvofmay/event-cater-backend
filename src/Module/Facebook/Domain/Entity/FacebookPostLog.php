<?php

declare(strict_types=1);

namespace App\Module\Facebook\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'facebook_post_log')]
#[ORM\Index(name: 'facebook_post_log_event', columns: ['event_uuid', 'published_at'])]
class FacebookPostLog
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;

    #[ORM\Column(length: 36)]
    private string $eventUuid;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $facebookPostId;

    #[ORM\Column(length: 36)]
    private string $publishedByUserUuid;

    #[ORM\Column(length: 32)]
    private string $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $publishedAt;

    public function __construct(string $eventUuid, string $userUuid, string $status, ?string $facebookPostId = null, ?string $errorMessage = null)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->eventUuid = $eventUuid;
        $this->publishedByUserUuid = $userUuid;
        $this->status = $status;
        $this->facebookPostId = $facebookPostId;
        $this->errorMessage = $errorMessage;
        $this->publishedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'eventUuid' => $this->eventUuid,
            'facebookPostId' => $this->facebookPostId,
            'status' => $this->status,
            'errorMessage' => $this->errorMessage,
            'publishedAt' => $this->publishedAt->format(\DATE_ATOM),
            'permalink' => $this->facebookPostId ? 'https://www.facebook.com/'.$this->facebookPostId : null,
        ];
    }
}
