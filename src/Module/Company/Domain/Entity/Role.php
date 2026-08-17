<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Entity;

use App\Common\Domain\Trait\AttributesEntityTrait;
use App\Common\Domain\Trait\RelationsEntityTrait;
use App\Common\Domain\Trait\TimeStampableTrait;
use App\Module\Company\Domain\Enum\Role\RoleEntityFieldEnum;
use App\Module\System\Domain\Entity\Access;
use App\Module\System\Domain\Entity\Permission;
use App\Module\System\Domain\Entity\RoleAccess;
use App\Module\System\Domain\Entity\RoleAccessPermission;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'role')]
#[ORM\Index(name: 'name', columns: ['name'])]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Role
{
    use TimeStampableTrait;
    use AttributesEntityTrait;
    use RelationsEntityTrait;

    public const string ALIAS = 'role';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\OneToMany(targetEntity: Employee::class, mappedBy: 'role', cascade: ['persist', 'remove'])]
    private Collection $employees;

    #[ORM\OneToMany(targetEntity: RoleAccess::class, mappedBy: 'role', cascade: ['persist', 'remove'])]
    private Collection $roleAccesses;

    #[ORM\OneToMany(targetEntity: RoleAccessPermission::class, mappedBy: 'role', cascade: ['persist', 'remove'])]
    private Collection $accessPermissions;

    private function __construct()
    {
        $this->employees = new ArrayCollection();
        $this->roleAccesses = new ArrayCollection();
        $this->accessPermissions = new ArrayCollection();
    }

    public static function create(string $name, ?string $description = null): self
    {
        $self = new self();
        $self->uuid = Uuid::uuid4();
        $self->name = $name;
        $self->description = $description;

        return $self;
    }

    public function rename(string $newName): void
    {
        $this->name = $newName;
    }

    public function updateDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getUUID(): UuidInterface
    {
        return $this->{RoleEntityFieldEnum::UUID->value};
    }

    public function getName(): string
    {
        return $this->{RoleEntityFieldEnum::NAME->value};
    }

    public function getDescription(): ?string
    {
        return $this->{RoleEntityFieldEnum::DESCRIPTION->value};
    }

    public function getEmployees(): Collection
    {
        return $this->employees;
    }

    public function getRoleAccesses(): Collection
    {
        return $this->roleAccesses;
    }

    public function getAccesses(): Collection
    {
        return $this->getRoleAccesses()->map(fn (RoleAccess $ra) => $ra->getAccess());
    }

    public function getAccessPermissions(): Collection
    {
        return $this->accessPermissions;
    }

    public function addAccess(Access $access): void
    {
        foreach ($this->roleAccesses as $roleAccess) {
            if ($roleAccess->getAccess() === $access) {
                return;
            }
        }

        $roleAccess = new RoleAccess($this, $access);
        $this->roleAccesses->add($roleAccess);
    }

    public function removeAccess(Access $access): void
    {
        foreach ($this->roleAccesses as $roleAccess) {
            if ($roleAccess->getAccess() === $access) {
                $this->roleAccesses->removeElement($roleAccess);
                break;
            }
        }
    }

    public function removeAccesses(): void
    {
        $this->roleAccesses->clear();
    }

    public function addAccessPermission(Access $access, Permission $permission): void
    {
        $relation = new RoleAccessPermission($this, $access, $permission);
        $this->accessPermissions->add($relation);
    }

    public function removeAccessPermission(Access $access, Permission $permission): void
    {
        foreach ($this->accessPermissions as $relation) {
            if (
                $relation->getAccess() === $access &&
                $relation->getPermission() === $permission
            ) {
                $this->accessPermissions->removeElement($relation);
                break;
            }
        }
    }
}
