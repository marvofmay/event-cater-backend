<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Service\User;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class UserPersonalInfoProvider
{
    public function getUserInfo(UserInterface $user): array
    {
        return [
            'uuid'  => $user->getUUID()->toString(),
            'email' => $user->getEmail(),
        ];
    }

    public function getEmployeeInfo(UserInterface $user): array
    {
        $employeeUUID = $user->getEmployee()?->getUUID();
        $firstName = $user->getEmployee()?->getFirstName();
        $lastName = $user->getEmployee()?->getLastName();

        return [
            'uuid'      => $employeeUUID,
            'firstName' => $firstName,
            'lastName'  => $lastName,
        ];
    }

    public function getRoles(UserInterface $user): array
    {
        return $user->getRoles();
    }
}
