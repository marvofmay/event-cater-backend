<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'password_reset_request')]
#[ORM\Index(name: 'password_reset_user', columns: ['user_uuid'])]
class PasswordResetRequest
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column(length: 36, unique: true)]
    private string $requestUuid;

    #[ORM\Column(length: 36)]
    private string $userUuid;

    #[ORM\Column]
    private int $credentialVersion;

    #[ORM\Column(length: 100)]
    private string $email;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;
}
