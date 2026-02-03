<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Entity;

use App\Common\Domain\Trait\AttributesEntityTrait;
use App\Common\Domain\Trait\RelationsEntityTrait;
use App\Common\Domain\Trait\TimeStampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'access')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Access
{
    use TimeStampableTrait;
    use AttributesEntityTrait;
    use RelationsEntityTrait;

    public const string COLUMN_UUID = 'uuid';
    public const string COLUMN_NAME = 'name';
    public const string COLUMN_DESCRIPTION = 'description';
    public const string COLUMN_ACTIVE = 'active';
    public const string COLUMN_CREATED_AT = 'createdAt';
    public const string COLUMN_UPDATED_AT = 'updatedAt';
    public const string COLUMN_DELETED_AT = 'deletedAt';
    public const string ALIAS = 'access';
    public const string RELATION_MODULE = 'module';

    #[ORM\ManyToOne(targetEntity: Module::class, inversedBy: 'accesses')]
    #[ORM\JoinColumn(name: 'module_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private Module $module;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private UuidInterface $uuid;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Assert\NotBlank]
    private bool $active;

    #[ORM\OneToMany(targetEntity: RoleAccess::class, mappedBy: 'access', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $roleAccesses;

    #[ORM\OneToMany(
        targetEntity: RoleAccessPermission::class,
        mappedBy: 'access',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $accessPermissions;

    public function __construct()
    {
        $this->roleAccesses = new ArrayCollection();
        $this->accessPermissions = new ArrayCollection();
    }

    public function getModule(): Module
    {
        return $this->module;
    }

    public function setModule(Module $module): void
    {
        $this->module = $module;
    }

    public function getUUID(): UuidInterface
    {
        return $this->{self::COLUMN_UUID};
    }

    public function getName(): string
    {
        return $this->{self::COLUMN_NAME};
    }

    public function setName(string $name): void
    {
        $this->{self::COLUMN_NAME} = $name;
    }

    public function getDescription(): ?string
    {
        return $this->{self::COLUMN_DESCRIPTION};
    }

    public function setDescription(?string $description): void
    {
        $this->{self::COLUMN_DESCRIPTION} = $description;
    }

    public function getActive(): bool
    {
        return $this->{self::COLUMN_ACTIVE};
    }

    public function setActive(bool $active): void
    {
        $this->{self::COLUMN_ACTIVE} = $active;
    }

    public function getAccessPermissions(): Collection
    {
        return $this->accessPermissions;
    }

    public function getPermissions(): Collection
    {
        $permissions = $this->accessPermissions->map(fn (RoleAccessPermission $rap) => $rap->getPermission())->toArray();

        return new ArrayCollection(array_unique($permissions, SORT_REGULAR));
    }
}
