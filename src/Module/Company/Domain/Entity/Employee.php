<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Entity;

use App\Common\Domain\Trait\AttributesEntityTrait;
use App\Common\Domain\Trait\RelationsEntityTrait;
use App\Common\Domain\Trait\TimeStampableTrait;
use App\Module\Company\Domain\Enum\Contact\ContactTypeEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'employee')]
#[ORM\Index(name: 'external_code', columns: ['external_code'])]
#[ORM\Index(name: 'internal_code', columns: ['internal_code'])]
#[ORM\Index(name: 'first_name', columns: ['first_name'])]
#[ORM\Index(name: 'last_name', columns: ['last_name'])]
#[ORM\Index(name: 'pesel', columns: ['pesel'])]
#[ORM\Index(name: 'employment_from', columns: ['employment_from'])]
#[ORM\Index(name: 'employment_to', columns: ['employment_to'])]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]
class Employee
{
    use TimeStampableTrait;
    use AttributesEntityTrait;
    use RelationsEntityTrait;

    public const string COLUMN_UUID = 'uuid';
    public const string COLUMN_EXTERNAL_CODE = 'externalCode';
    public const string COLUMN_COMPANY_UUID = 'companyUUID';
    public const string COLUMN_DEPARTMENT_UUID = 'departmentUUID';
    public const string COLUMN_SUPERIOR_UUID = 'superiorUUID';
    public const string COLUMN_POSITION_UUID = 'positionUUID';
    public const string COLUMN_CONTRACT_TYPE_UUID = 'contractTypeUUID';
    public const string COLUMN_ROLE_UUID = 'roleUUID';
    public const string COLUMN_FIRST_NAME = 'firstName';
    public const string COLUMN_LAST_NAME = 'lastName';
    public const string COLUMN_PESEL = 'pesel';
    public const string COLUMN_INTERNAL_CODE = 'internalCode';
    public const string COLUMN_EMPLOYMENT_FROM = 'employmentFrom';
    public const string COLUMN_EMPLOYMENT_TO = 'employmentTo';
    public const string COLUMN_ACTIVE = 'active';
    public const string COLUMN_CREATED_AT = 'createdAt';
    public const string COLUMN_UPDATED_AT = 'updatedAt';
    public const string COLUMN_DELETED_AT = 'deletedAt';
    public const string RELATION_COMPANY = 'company';
    public const string RELATION_DEPARTMENT = 'department';
    public const string RELATION_ROLE = 'role';
    public const string RELATION_POSITION = 'position';
    public const string RELATION_CONTRACT_TYPE = 'contractType';
    public const string RELATION_PARENT_EMPLOYEE = 'parentEmployee';
    public const string RELATION_NOTES = 'notes';
    public const string RELATION_CONTACTS = 'contacts';
    public const string RELATION_FILES = 'files';
    public const string RELATION_IMPORTS = 'imports';
    public const string RELATION_EVENT_LOGS = 'eventLogs';

    public const string ALIAS = 'employee';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $externalCode = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'employees')]
    #[ORM\JoinColumn(name: 'company_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private ?Company $company;

    #[ORM\ManyToOne(targetEntity: Department::class, inversedBy: 'employees')]
    #[ORM\JoinColumn(name: 'department_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private ?Department $department;

    #[ORM\ManyToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(name: 'employee_uuid', referencedColumnName: 'uuid', onDelete: 'CASCADE')]
    private ?Employee $parentEmployee = null;

    #[ORM\ManyToOne(targetEntity: Position::class, inversedBy: 'employees')]
    #[ORM\JoinColumn(name: 'position_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\ManyToOne(targetEntity: ContractType::class, inversedBy: 'employees')]
    #[ORM\JoinColumn(name: 'contract_type_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private ContractType $contractType;

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'employees')]
    #[ORM\JoinColumn(name: 'role_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private Role $role;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: false)]
    #[Assert\NotBlank()]
    private string $firstName;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: false)]
    private string $lastName = '';

    #[ORM\Column(type: Types::STRING, length: 11, nullable: false)]
    #[Assert\NotBlank()]
    private string $pesel;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPeselFake = false;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $internalCode;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
    private ?\DateTimeInterface $employmentFrom;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $employmentTo = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = false;

    #[ORM\OneToOne(targetEntity: User::class, mappedBy: 'employee', cascade: ['persist', 'remove'])]
    private ?User $user = null;

    #[ORM\OneToMany(targetEntity: Contact::class, mappedBy: 'employee', cascade: ['persist', 'remove'])]
    private Collection $contacts;

    #[ORM\OneToOne(targetEntity: Address::class, mappedBy: 'employee', cascade: ['persist', 'remove'])]
    private ?Address $address = null;

    public function __construct()
    {
        $this->contacts = new ArrayCollection();
    }

    public function getUUID(): UuidInterface
    {
        return $this->{self::COLUMN_UUID};
    }

    public function setUUID(string $uuid): void
    {
        $this->uuid = Uuid::fromString($uuid);
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
        if ($user && $user->getEmployee() !== $this) {
            $user->setEmployee($this);
        }
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): void
    {
        $this->department = $department;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function setPosition(Position $position): void
    {
        $this->position = $position;
    }

    public function getContractType(): ContractType
    {
        return $this->contractType;
    }

    public function setContractType(ContractType $contractType): void
    {
        $this->contractType = $contractType;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): void
    {
        $this->role = $role;
    }

    public function getParentEmployee(): ?Employee
    {
        return $this->parentEmployee;
    }

    public function setParentEmployee(?Employee $parentEmployee): void
    {
        $this->parentEmployee = $parentEmployee;
    }

    public function getExternalCode(): ?string
    {
        return $this->{self::COLUMN_EXTERNAL_CODE};
    }

    public function setExternalUUID(?string $externalCode): void
    {
        $this->{self::COLUMN_EXTERNAL_CODE} = $externalCode;
    }

    public function getFirstName(): string
    {
        return $this->{self::COLUMN_FIRST_NAME};
    }

    public function setFirstName(string $firstName): void
    {
        $this->{self::COLUMN_FIRST_NAME} = $firstName;
    }

    public function getLastName(): string
    {
        return $this->{self::COLUMN_LAST_NAME};
    }

    public function setLastName(string $lastName): void
    {
        $this->{self::COLUMN_LAST_NAME} = $lastName;
    }

    public function getPESEL(): string
    {
        return $this->{self::COLUMN_PESEL};
    }

    public function setPESEL(string $pesel): void
    {
        $this->{self::COLUMN_PESEL} = $pesel;
    }

    public function isPeselFake(): bool
    {
        return $this->isPeselFake;
    }

    public function setIsPeselFake(bool $isPeselFake): void
    {
        $this->isPeselFake = $isPeselFake;
    }

    public function getInternalCode(): ?string
    {
        return $this->{self::COLUMN_INTERNAL_CODE};
    }

    public function setInternalCode(?string $internalCode): void
    {
        $this->{self::COLUMN_INTERNAL_CODE} = $internalCode;
    }

    public function getEmploymentFrom(): \DateTimeInterface
    {
        return $this->{self::COLUMN_EMPLOYMENT_FROM};
    }

    public function setEmploymentFrom(\DateTimeInterface $employmentFrom): void
    {
        $this->{self::COLUMN_EMPLOYMENT_FROM} = $employmentFrom;
    }

    public function getEmploymentTo(): ?\DateTimeInterface
    {
        return $this->{self::COLUMN_EMPLOYMENT_TO};
    }

    public function setEmploymentTo(?\DateTimeInterface $employmentTo): void
    {
        $this->{self::COLUMN_EMPLOYMENT_TO} = $employmentTo;
    }

    public function getActive(): bool
    {
        return $this->{self::COLUMN_ACTIVE};
    }

    public function setActive(bool $active): void
    {
        $this->{self::COLUMN_ACTIVE} = $active;
    }

    public function getContacts(?ContactTypeEnum $type = null): Collection
    {
        if (null === $type) {
            return $this->contacts;
        }

        return $this->contacts->filter(fn (Contact $contact) => $contact->getType() === $type->value);
    }

    public function addContact(Contact $contact): void
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts[] = $contact;
            $contact->setEmployee($this);
        }
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): void
    {
        $this->address = $address;
        $address->setEmployee($this);
    }
}
