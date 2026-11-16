<?php

declare(strict_types=1);

namespace App\Common\Domain\Entity;

use App\Module\Company\Domain\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'event_store')]
#[ORM\Index(name: 'aggregate_uuid', columns: ['aggregate_uuid'])]
#[ORM\Index(name: 'aggregate_type', columns: ['aggregate_type'])]
#[ORM\Index(name: 'user_uuid', columns: ['user_uuid'])]
class EventStore
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private UuidInterface $uuid;

    #[ORM\Column(name: 'aggregate_uuid', type: 'uuid')]
    private string $aggregateUUID;

    #[ORM\Column(name: 'aggregate_type', type: 'string', length: 255)]
    private string $aggregateType;

    #[ORM\Column(name: 'aggregate_class', type: 'string', length: 255)]
    private string $aggregateClass;

    #[ORM\Column(type: 'json')]
    private string $payload;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'created_at_us', type: 'integer', options: ['unsigned' => true])]
    private int $createdAtUs;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_uuid', referencedColumnName: 'uuid', nullable: true)]
    private UserInterface $user;

    public function __construct(
        string $aggregateUUID,
        string $aggregateType,
        string $aggregateClass,
        string $payload,
        UserInterface $user,
    ) {
        $this->aggregateUUID = $aggregateUUID;
        $this->aggregateType = $aggregateType;
        $this->aggregateClass = $aggregateClass;
        $this->payload = $payload;
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
        $this->createdAtUs = (int) $this->createdAt->format('u');
    }

    public function getUUID(): UuidInterface
    {
        return $this->uuid;
    }

    public function getAggregateUUID(): string
    {
        return $this->aggregateUUID;
    }

    public function getAggregateType(): string
    {
        return $this->aggregateType;
    }

    public function getAggregateClass(): string
    {
        return $this->aggregateClass;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
