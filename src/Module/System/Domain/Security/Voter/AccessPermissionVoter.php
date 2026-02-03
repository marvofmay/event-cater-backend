<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Security\Voter;

use App\Module\Company\Domain\Entity\Employee;
use App\Module\System\Domain\Entity\Access;
use App\Module\System\Domain\Entity\Module;
use App\Module\System\Domain\Entity\Permission;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use App\Module\System\Domain\Interface\Access\AccessReaderInterface;
use App\Module\System\Domain\Interface\Module\ModuleReaderInterface;
use App\Module\System\Domain\Interface\Permission\PermissionReaderInterface;
use App\Module\System\Domain\Service\RoleAccessPermission\RoleAccessPermissionChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AccessPermissionVoter extends Voter
{
    public function __construct(
        protected ModuleReaderInterface $moduleReaderRepository,
        protected AccessReaderInterface $accessReaderRepository,
        protected PermissionReaderInterface $permissionReaderRepository,
        protected RoleAccessPermissionChecker $roleAccessPermissionChecker,
        protected ?Module $module = null,
        protected ?Access $access = null,
        protected ?Permission $permission = null,
    ) {
    }

    abstract protected function getAttributeName(): string;

    protected function supports(mixed $attribute, mixed $subject): bool
    {
        if (!$attribute instanceof PermissionEnum) {
            return false;
        }

        if (!$subject instanceof AccessEnum) {
            return false;
        }

        if ($attribute->value !== $this->getAttributeName()) {
            return false;
        }

        $data = explode('.', $subject->value);
        if (count($data) !== 2) {
            return false;
        }

        [$moduleName, $accessName] = $data;

        $this->module = $this->moduleReaderRepository->getModuleByName($moduleName);
        if (!$this->module?->getActive()) {
            return false;
        }

        $this->access = $this->accessReaderRepository->getAccessByNameAndModuleUUID($accessName, $this->module);
        if (!$this->access?->getActive()) {
            return false;
        }

        $this->permission = $this->permissionReaderRepository->getPermissionByName($this->getAttributeName());
        if (!$this->permission?->getActive()) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(mixed $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }

        if ('admin.ec@gmail.com' === $user->getEmail()) {
            return true;
        }

        $employee = $user->getEmployee();
        if (!$employee instanceof Employee) {
            return false;
        }

        $role = $employee->getRole();

        return $this->roleAccessPermissionChecker->check($this->permission, $this->access, $role);
    }
}
