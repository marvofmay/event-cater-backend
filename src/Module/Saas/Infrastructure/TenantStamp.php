<?php

declare(strict_types=1);

namespace App\Module\Saas\Infrastructure;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class TenantStamp implements StampInterface
{
    public function __construct(public string $slug, public int $version)
    {
    }
}
