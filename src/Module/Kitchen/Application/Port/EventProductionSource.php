<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Application\Port;

interface EventProductionSource
{
    /** Current production-only snapshot; lock serializes commands against event changes. */
    public function get(string $eventUuid, bool $lock = false): array;
    /** @return array<string, array> */
    public function getMany(array $eventUuids): array;
}
