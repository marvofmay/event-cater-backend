<?php

declare(strict_types=1);

namespace App\Module\Saas\Infrastructure;

use App\Module\Company\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class TenantUserProvider implements UserProviderInterface
{
    public function __construct(private ControlPlane $control, private EntityManagerInterface $em)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $email = strtolower(trim($identifier));
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        $admin = $this->control->technicalAdministrator($email);
        if ($admin) {
            if (!$admin['active'] || $user?->getEmployee() !== null) {
                throw new UserNotFoundException();
            }
            $user ??= User::create($email);
            $user->makeTechnicalAdministrator($admin['uuid']);
            $user->setPassword($admin['password']);

            return $user;
        }
        if (!$user || $user->getSaasAdministratorUuid() !== null) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, User::class, true);
    }
}
