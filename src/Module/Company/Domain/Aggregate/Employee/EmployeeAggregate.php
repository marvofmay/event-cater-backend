<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Aggregate\Employee;

use App\Common\Domain\Abstract\AggregateRootAbstract;
use App\Common\Domain\Interface\DomainEventInterface;
use App\Common\Domain\Trait\ClassNameExtractorTrait;
use App\Module\Company\Domain\Aggregate\Company\ValueObject\CompanyUUID;
use App\Module\Company\Domain\Aggregate\Department\ValueObject\DepartmentUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\ContractTypeUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmploymentFrom;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmploymentTo;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\FirstName;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\LastName;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\PESEL;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\PositionUUID;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\RoleUUID;
use App\Module\Company\Domain\Aggregate\ValueObject\Address;
use App\Module\Company\Domain\Aggregate\ValueObject\Emails;
use App\Module\Company\Domain\Aggregate\ValueObject\Phones;
use App\Module\Company\Domain\Event\Employee\EmployeeChangedAddressEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeChangedContactEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeChangedPersonalDataEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeCreatedEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeDeletedEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeRestoredEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeChangedAvatarEvent;
use App\Module\Company\Domain\Event\Employee\EmployeeUpdatedEvent;
use App\Module\System\Domain\ValueObject\UserUUID;

class EmployeeAggregate extends AggregateRootAbstract
{
    use ClassNameExtractorTrait;

    private EmployeeUUID   $uuid;
    private ?EmployeeUUID  $parentEmployeeUUID = null;
    private FirstName      $firstName;
    private LastName       $lastName;
    private PESEL          $pesel;
    private EmploymentFrom $employmentFrom;
    private CompanyUUID    $companyUUID;

    private DepartmentUUID   $departmentUUID;
    private PositionUUID     $positionUUID;
    private ContractTypeUUID $contractTypeUUID;
    private RoleUUID         $roleUUID;
    private Emails           $emails;
    private Address          $address;
    private ?string          $externalCode = null;
    private ?string          $internalCode = null;
    private ?EmploymentTo    $employmentTo = null;
    private bool             $active       = true;
    private ?Phones          $phones       = null;
    private bool             $deleted      = false;
    private UserUUID         $loggedUserUUID;
    private string $avatarType = 'default';
    private ?string $defaultAvatar = null;
    private ?string $avatarPath = null;

    public static function create(
        FirstName $firstName,
        LastName $lastName,
        PESEL $pesel,
        EmploymentFrom $employmentFrom,
        CompanyUUID $companyUUID,
        DepartmentUUID $departmentUUID,
        PositionUUID $positionUUID,
        ContractTypeUUID $contractTypeUUID,
        RoleUUID $roleUUID,
        Emails $emails,
        Address $address,
        UserUUID $loggedUserUUID,
        ?string $externalCode = null,
        ?string $internalCode = null,
        ?bool $active = true,
        ?Phones $phones = null,
        ?EmployeeUUID $parentEmployeeUUID = null,
        ?EmploymentTo $employmentTo = null,
        ?EmployeeUUID $uuid = null,
    ): self {
        $aggregate = new self();

        $aggregate->record(
            new EmployeeCreatedEvent(
                $uuid ?? EmployeeUUID::generate(),
                $firstName,
                $lastName,
                $pesel,
                $employmentFrom,
                $companyUUID,
                $departmentUUID,
                $positionUUID,
                $contractTypeUUID,
                $roleUUID,
                $emails,
                $address,
                $loggedUserUUID,
                $active,
                $externalCode,
                $internalCode,
                $phones,
                $parentEmployeeUUID,
                $employmentTo,
            )
        );

        return $aggregate;
    }

    public function update(
        FirstName $firstName,
        LastName $lastName,
        PESEL $pesel,
        EmploymentFrom $employmentFrom,
        CompanyUUID $companyUUID,
        DepartmentUUID $departmentUUID,
        PositionUUID $positionUUID,
        ContractTypeUUID $contractTypeUUID,
        RoleUUID $roleUUID,
        Emails $emails,
        Address $address,
        UserUUID $loggedUserUUID,
        ?string $externalCode = null,
        ?string $internalCode = null,
        ?bool $active = true,
        ?Phones $phones = null,
        ?EmployeeUUID $parentEmployeeUUID = null,
        ?EmploymentTo $employmentTo = null,
    ): self {
        if ($this->deleted) {
            throw new \DomainException('Cannot update a deleted employee.');
        }

        $this->record(
            new EmployeeUpdatedEvent(
                $this->uuid,
                $firstName,
                $lastName,
                $pesel,
                $employmentFrom,
                $companyUUID,
                $departmentUUID,
                $positionUUID,
                $contractTypeUUID,
                $roleUUID,
                $emails,
                $address,
                $loggedUserUUID,
                $active,
                $externalCode,
                $internalCode,
                $phones,
                $parentEmployeeUUID,
                $employmentTo,
            )
        );

        return $this;
    }

    public function delete(): self
    {
        $this->record(new EmployeeDeletedEvent($this->uuid));

        return $this;
    }

    public function restore(): self
    {
        if (!$this->deleted) {
            throw new \DomainException('Employee is not deleted.');
        }

        $this->record(new EmployeeRestoredEvent($this->uuid));

        return $this;
    }

    public function changeAvatar(
        string $avatarType,
        ?string $defaultAvatar,
        ?string $avatarPath,
        UserUUID $loggedUserUUID
    ): self {
        if ($this->deleted) {
            throw new \DomainException('Cannot change avatar of deleted employee.');
        }

        if ($avatarType === 'custom' && $avatarPath === null) {
            throw new \DomainException('Custom avatar requires avatar path.');
        }

        if ($avatarType === 'default') {
            $avatarPath = null;
        }

        $this->record(
            new EmployeeChangedAvatarEvent(
                uuid: $this->uuid,
                avatarType: $avatarType,
                userUUID:  $loggedUserUUID,
                defaultAvatar:  $defaultAvatar,
                avatarPath: $avatarPath,
            )
        );

        return $this;
    }

    public function changePersonalData(
        FirstName $firstName,
        LastName $lastName,
        UserUUID $loggedUserUUID
    ): self {
        if ($this->deleted) {
            throw new \DomainException('Cannot change personal data of deleted employee.');
        }

        $this->record(
            new EmployeeChangedPersonalDataEvent(
                uuid: $this->uuid,
                firstName: $firstName,
                lastName: $lastName,
                userUUID:  $loggedUserUUID
            )
        );

        return $this;
    }

    public function changeAddress(Address $address, UserUUID $loggedUserUUID): self
    {
        if ($this->deleted) {
            throw new \DomainException('Cannot change address a deleted employee.');
        }

        $this->record(
            new EmployeeChangedAddressEvent(
                uuid: $this->uuid,
                address: $address,
                userUUID:  $loggedUserUUID
            )
        );

        return $this;
    }

    public function changeContact(
        UserUUID $loggedUserUUID,
        ?Phones $phones = null
    ): self {
        if ($this->deleted) {
            throw new \DomainException('Cannot update a deleted employee.');
        }

        $this->record(
            new EmployeeChangedContactEvent(
                uuid: $this->uuid,
                userUUID: $loggedUserUUID,
                phones: $phones
            )
        );

        return $this;
    }


    protected function apply(DomainEventInterface $event): void
    {
        $method = 'apply' . $this->getShortClassName($event::class);

        if (method_exists($this, $method)) {
            $this->$method($event);
        }
    }

    private function applyEmployeeCreatedEvent(EmployeeCreatedEvent $event): void
    {
        $this->applyFullState($event);
    }

    private function applyFullState(EmployeeCreatedEvent | EmployeeUpdatedEvent $event): void
    {
        $this->uuid = $event->uuid;
        $this->firstName = $event->firstName;
        $this->lastName = $event->lastName;
        $this->pesel = $event->pesel;
        $this->employmentFrom = $event->employmentFrom;
        $this->companyUUID= $event->companyUUID;
        $this->departmentUUID = $event->departmentUUID;
        $this->positionUUID = $event->positionUUID;
        $this->contractTypeUUID = $event->contractTypeUUID;
        $this->roleUUID = $event->roleUUID;
        $this->emails = $event->emails;
        $this->address = $event->address;
        $this->loggedUserUUID = $event->loggedUserUUID;
        $this->active = $event->active;
        $this->externalCode = $event->externalCode;
        $this->internalCode = $event->internalCode;
        $this->phones = $event->phones;
        $this->parentEmployeeUUID = $event->parentEmployeeUUID;
        $this->employmentTo = $event->employmentTo;
    }

    private function applyEmployeeUpdatedEvent(EmployeeUpdatedEvent $event): void
    {
        $this->applyFullState($event);
    }

    public function applyEmployeeDeletedEvent(EmployeeDeletedEvent $event): void
    {
        $this->deleted = true;
    }

    public function applyEmployeeRestoredEvent(EmployeeRestoredEvent $event): void
    {
        $this->deleted = false;
    }

    public function applyEmployeeChangedAvatarEvent(EmployeeChangedAvatarEvent $event): void
    {
        $this->avatarType = $event->avatarType;
        $this->defaultAvatar = $event->defaultAvatar;
        $this->avatarPath = $event->avatarPath;
    }

    public function applyEmployeeChangedPersonalDataEvent(EmployeeChangedPersonalDataEvent $event): void
    {
        $this->uuid = $event->uuid;
        $this->firstName = $event->firstName;
        $this->lastName = $event->lastName;
    }
    public function applyEmployeeChangedAddressEvent(EmployeeChangedAddressEvent $event):void
    {
        $this->uuid = $event->uuid;
        $this->address = $event->address;
    }

    public function applyEmployeeChangedContactEvent(EmployeeChangedContactEvent $event): void
    {
        $this->uuid = $event->uuid;
        $this->phones = $event->phones;
    }

    public function getUUID(): EmployeeUUID
    {
        return $this->uuid;
    }

    public function getAvatarType(): string
    {
        return $this->avatarType;
    }

    public function getDefaultAvatar(): ?string
    {
        return $this->defaultAvatar;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }
}
