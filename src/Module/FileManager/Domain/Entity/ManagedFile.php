<?php

declare(strict_types=1);

namespace App\Module\FileManager\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'managed_file')]
#[ORM\UniqueConstraint(name: 'managed_file_sibling', columns: ['parent_uuid', 'name_key'])]
#[ORM\Index(name: 'managed_file_blob', columns: ['blob_name'])]
#[ORM\Index(name: 'managed_file_owner', columns: ['owner_uuid', 'created_at', 'uuid'])]
class ManagedFile
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, options: ['fixed' => true])]
    private string $uuid;

    #[ORM\Column(name: 'owner_uuid', length: 36, nullable: true, options: ['fixed' => true])]
    private ?string $ownerUuid = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_uuid', referencedColumnName: 'uuid', nullable: true)]
    private ?self $parent = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(name: 'name_key', length: 64, options: ['fixed' => true])]
    private string $nameKey;

    #[ORM\Column(length: 10)]
    private string $kind;

    #[ORM\Column(name: 'blob_name', length: 80, nullable: true)]
    private ?string $blobName = null;

    #[ORM\Column(name: 'mime_type', length: 150, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    private string $size = '0';

    #[ORM\Column(options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'created_by', length: 36, nullable: true, options: ['fixed' => true])]
    private ?string $createdBy = null;

    #[ORM\Column(name: 'updated_by', length: 36, nullable: true, options: ['fixed' => true])]
    private ?string $updatedBy = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;
}
