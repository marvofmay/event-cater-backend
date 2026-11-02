<?php

declare(strict_types=1);

namespace App\Module\Messaging\Infrastructure\Persistence;

use App\Module\Messaging\Domain\Entity\UserPushToken;
use App\Module\Messaging\Domain\Repository\PushTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePushTokenRepository implements PushTokenRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function upsert(string $userUuid, string $token, string $platform): void
    {
        $existing = $this->em->getRepository(UserPushToken::class)->findOneBy(['token' => $token]);
        if ($existing instanceof UserPushToken) {
            $existing->reassign($userUuid, $platform);
        } else {
            $this->em->persist(new UserPushToken($userUuid, $token, $platform));
        }
        $this->em->flush();
    }

    public function removeToken(string $userUuid, string $token): void
    {
        $existing = $this->em->getRepository(UserPushToken::class)->findOneBy([
            'userUuid' => $userUuid,
            'token' => $token,
        ]);
        if ($existing instanceof UserPushToken) {
            $this->em->remove($existing);
            $this->em->flush();
        }
    }

    public function findByUserUuids(array $userUuids): array
    {
        if ($userUuids === []) {
            return [];
        }

        return $this->em->getRepository(UserPushToken::class)->createQueryBuilder('t')
            ->andWhere('t.userUuid IN (:users)')
            ->setParameter('users', $userUuids)
            ->getQuery()
            ->getResult();
    }

    public function removeByToken(string $token): void
    {
        $existing = $this->em->getRepository(UserPushToken::class)->findOneBy(['token' => $token]);
        if ($existing instanceof UserPushToken) {
            $this->em->remove($existing);
            $this->em->flush();
        }
    }
}
