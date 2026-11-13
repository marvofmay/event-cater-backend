<?php

declare(strict_types=1);

namespace App\Module\Facebook\Infrastructure\Persistence;

use App\Module\Facebook\Domain\Entity\FacebookPageConnection;
use App\Module\Facebook\Domain\Repository\FacebookPageConnectionRepository;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineFacebookPageConnectionRepository implements FacebookPageConnectionRepository
{
    private const string SINGLETON = '00000000-0000-4000-8000-000000000001';

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function get(): ?FacebookPageConnection
    {
        try {
            $connection = $this->em->find(FacebookPageConnection::class, self::SINGLETON);
        } catch (TableNotFoundException) {
            return null;
        } catch (\Throwable $error) {
            // Missing migration / schema on older tenants should not 500 the settings page.
            if (str_contains($error->getMessage(), 'facebook_page_connection') || str_contains($error->getMessage(), "doesn't exist")) {
                return null;
            }
            throw $error;
        }
        if ($connection === null || $connection->pageId() === '') {
            return null;
        }

        return $connection;
    }

    public function save(FacebookPageConnection $connection): void
    {
        $this->em->persist($connection);
        $this->em->flush();
    }

    public function clear(): void
    {
        try {
            $connection = $this->em->find(FacebookPageConnection::class, self::SINGLETON);
        } catch (TableNotFoundException) {
            return;
        }
        if ($connection === null) {
            return;
        }
        $this->em->remove($connection);
        $this->em->flush();
    }

    public function getOrCreate(): FacebookPageConnection
    {
        $connection = $this->em->find(FacebookPageConnection::class, self::SINGLETON);
        if ($connection === null) {
            $connection = new FacebookPageConnection();
            $this->em->persist($connection);
        }

        return $connection;
    }
}
