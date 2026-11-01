<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Service\User;

use App\Module\Company\Domain\Entity\User;
use App\Module\Company\Domain\Interface\User\UserFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserFactory implements UserFactoryInterface
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function create(string $email, string $plainPassword): User
    {
        $user = User::create($email);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        return $user;
    }

    public function createEmployee(?string $email, string $phone, #[\SensitiveParameter] string $pesel): User
    {
        $user = User::create($email);
        $user->setLoginPhone(LoginPhone::normalize($phone));
        $user->setPassword($this->passwordHasher->hashPassword($user, $pesel));
        $user->requirePasswordChange();

        return $user;
    }
}
