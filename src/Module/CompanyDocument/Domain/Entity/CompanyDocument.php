<?php

declare(strict_types=1);

namespace App\Module\CompanyDocument\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'company_document')]
#[ORM\Index(name: 'company_document_category', columns: ['category'])]
#[ORM\Index(name: 'company_document_created', columns: ['created_at'])]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]
class CompanyDocument
{
    public const CATEGORIES = [
        'contracts',
        'haccp',
        'allergens',
        'instructions',
        'prices',
        'event_templates',
        'hr',
        'other',
    ];

    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(length: 40)]
    private string $category;

    #[ORM\Column(length: 80)]
    private string $storedName;

    #[ORM\Column(length: 150)]
    private string $fileName;

    #[ORM\Column(length: 120)]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    private int $size;

    #[ORM\Column(length: 36)]
    private string $createdByUserUuid;

    #[ORM\Column(length: 250)]
    private string $createdByName;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        string $title,
        ?string $description,
        string $category,
        string $storedName,
        string $fileName,
        string $mimeType,
        int $size,
        string $createdByUserUuid,
        string $createdByName,
    ) {
        $this->uuid = Uuid::uuid4()->toString();
        $this->title = $title;
        $this->description = $description;
        $this->category = $category;
        $this->storedName = $storedName;
        $this->fileName = $fileName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->createdByUserUuid = $createdByUserUuid;
        $this->createdByName = $createdByName;
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function storedName(): string
    {
        return $this->storedName;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function fileName(): string
    {
        return $this->fileName;
    }

    public function replaceMeta(string $title, ?string $description, string $category): void
    {
        $this->title = $title;
        $this->description = $description;
        $this->category = $category;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function replaceFile(string $storedName, string $fileName, string $mimeType, int $size): void
    {
        $this->storedName = $storedName;
        $this->fileName = $fileName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'fileName' => $this->fileName,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'createdByName' => $this->createdByName,
            'createdAt' => $this->createdAt->format(\DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(\DATE_ATOM),
        ];
    }
}
