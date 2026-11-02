<?php

declare(strict_types=1);

namespace App\Module\CompanyNotice\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'company_notice')]
#[ORM\Index(name: 'company_notice_created', columns: ['created_at'])]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]
class CompanyNotice
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $body;

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

    /** @var Collection<int, CompanyNoticeImage> */
    #[ORM\OneToMany(targetEntity: CompanyNoticeImage::class, mappedBy: 'notice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $images;

    public function __construct(string $title, string $body, string $createdByUserUuid, string $createdByName)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->title = $title;
        $this->body = $body;
        $this->createdByUserUuid = $createdByUserUuid;
        $this->createdByName = $createdByName;
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->images = new ArrayCollection();
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function update(string $title, string $body): void
    {
        $this->title = $title;
        $this->body = $body;
        $this->touch();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function addImage(CompanyNoticeImage $image): void
    {
        $this->images->add($image);
        $this->touch();
    }

    public function removeImage(CompanyNoticeImage $image): void
    {
        $this->images->removeElement($image);
        $this->touch();
    }

    /** @return list<CompanyNoticeImage> */
    public function images(): array
    {
        return array_values($this->images->toArray());
    }

    public function cover(): ?CompanyNoticeImage
    {
        foreach ($this->images as $image) {
            if ($image->isCover()) {
                return $image;
            }
        }

        return null;
    }

    /** @return list<CompanyNoticeImage> */
    public function gallery(): array
    {
        return array_values(array_filter($this->images(), static fn (CompanyNoticeImage $image) => !$image->isCover()));
    }

    public function toArray(): array
    {
        $cover = $this->cover();

        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'body' => $this->body,
            'createdByName' => $this->createdByName,
            'createdAt' => $this->createdAt->format(\DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(\DATE_ATOM),
            'cover' => $cover?->toArray(),
            'gallery' => array_map(static fn (CompanyNoticeImage $image) => $image->toArray(), $this->gallery()),
        ];
    }
}
