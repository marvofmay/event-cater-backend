<?php

declare(strict_types=1);

namespace App\Common\Infrastructure\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class HistoryActors
{
    public function __construct(private Connection $db)
    {
    }

    public function enrich(array $entries): array
    {
        if ($entries === []) {
            return [];
        }
        $actors = $this->db->fetchAllAssociative(
            "SELECT u.uuid, u.email, TRIM(CONCAT_WS(' ', e.first_name, e.last_name)) AS name FROM `user` u LEFT JOIN employee e ON e.uuid = u.employee_uuid WHERE u.uuid IN (?)",
            [array_values(array_unique(array_column($entries, 'actorUuid')))],
            [ArrayParameterType::STRING]
        );
        $names = [];
        foreach ($actors as $actor) {
            $names[$actor['uuid']] = $actor['name'] ?: ($actor['email'] ?: 'Użytkownik');
        }
        foreach ($entries as &$entry) {
            $entry['actorName'] = $names[$entry['actorUuid']] ?? 'Użytkownik niedostępny';
        }
        return $entries;
    }
}
