<?php

declare(strict_types=1);

namespace App\Module\Messaging\Domain\Repository;

use App\Module\Messaging\Domain\Entity\UserPushToken;

interface PushTokenRepository
{
    public function upsert(string $userUuid, string $token, string $platform): void;

    public function removeToken(string $userUuid, string $token): void;

    /**
     * @param list<string> $userUuids
     *
     * @return list<UserPushToken>
     */
    public function findByUserUuids(array $userUuids): array;

    public function removeByToken(string $token): void;
}
