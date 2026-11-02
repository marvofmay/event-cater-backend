<?php

declare(strict_types=1);

namespace App\Module\Facebook\Domain\Repository;

use App\Module\Facebook\Domain\Entity\FacebookPageConnection;

interface FacebookPageConnectionRepository
{
    public function get(): ?FacebookPageConnection;

    public function save(FacebookPageConnection $connection): void;

    public function clear(): void;
}
