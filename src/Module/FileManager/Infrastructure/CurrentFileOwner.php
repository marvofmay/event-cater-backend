<?php

declare(strict_types=1);

namespace App\Module\FileManager\Infrastructure;

use App\Module\Company\Domain\Entity\User;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class CurrentFileOwner
{
    public function __construct(private TokenStorageInterface $tokens)
    {
    }

    public function uuid(): string
    {
        $user = $this->tokens->getToken()?->getUser();
        if (!$user instanceof User) throw new AccessDeniedException('Zaloguj się, aby otworzyć swoje pliki.');
        return $user->getUUID()->toString();
    }

    public static function rootFor(string $owner): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'event-cater:private-files:'.$owner)->toString();
    }
}
