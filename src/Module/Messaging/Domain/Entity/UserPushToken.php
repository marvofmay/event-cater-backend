<?php

declare(strict_types=1);

namespace App\Module\Messaging\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'user_push_token')]
#[ORM\UniqueConstraint(name: 'user_push_token_token_unique', columns: ['token'])]
#[ORM\Index(name: 'user_push_token_user', columns: ['user_uuid'])]
class UserPushToken
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;

    #[ORM\Column(length: 36)]
    private string $userUuid;

    #[ORM\Column(length: 255)]
    private string $token;

    #[ORM\Column(length: 20)]
    private string $platform;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $userUuid, string $token, string $platform)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->userUuid = Uuid::fromString($userUuid)->toString();
        $this->token = trim($token);
        $this->platform = $platform;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getUserUuid(): string
    {
        return $this->userUuid;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function reassign(string $userUuid, string $platform): void
    {
        $this->userUuid = Uuid::fromString($userUuid)->toString();
        $this->platform = $platform;
        $this->touch();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
