<?php

declare(strict_types=1);

namespace App\Module\CompanyNotice\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'company_notice_image')]
#[ORM\Index(name: 'company_notice_image_notice', columns: ['notice_uuid'])]
class CompanyNoticeImage
{
    public const ROLE_COVER = 'cover';
    public const ROLE_GALLERY = 'gallery';

    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: CompanyNotice::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'notice_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private CompanyNotice $notice;

    #[ORM\Column(length: 80)]
    private string $storedName;

    #[ORM\Column(length: 150)]
    private string $fileName;

    #[ORM\Column(length: 64)]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    private int $size;

    #[ORM\Column(length: 16)]
    private string $role;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder;

    public function __construct(CompanyNotice $notice, string $storedName, string $fileName, string $mimeType, int $size, string $role, int $sortOrder)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->notice = $notice;
        $this->storedName = $storedName;
        $this->fileName = $fileName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->role = $role;
        $this->sortOrder = $sortOrder;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function notice(): CompanyNotice
    {
        return $this->notice;
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

    public function isCover(): bool
    {
        return $this->role === self::ROLE_COVER;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'fileName' => $this->fileName,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'role' => $this->role,
            'sortOrder' => $this->sortOrder,
        ];
    }
}
