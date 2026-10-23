<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Query;

use App\Module\Event\Application\Query\GetEventVenue;
use App\Module\Event\Application\Query\ListEventVenues;
use App\Module\Event\Domain\Repository\VenueRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class VenueQueryHandler
{
    public function __construct(private VenueRepository $venues, private Connection $db)
    {
    }
    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetEventVenue $q): array
    {
        return $this->venues->get($q->uuid)->toArray();
    }
    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListEventVenues $q): array
    {
        $where = ['1=1'];
        $params = [];
        if ($q->active !== null) {
            $where[] = 'active = ?';
            $params[] = (int)$q->active;
        }
        if ($q->search !== '') {
            $where[] = "(name LIKE ? ESCAPE '!' OR JSON_UNQUOTE(JSON_EXTRACT(location, '$.city')) LIKE ? ESCAPE '!' OR JSON_UNQUOTE(JSON_EXTRACT(location, '$.street')) LIKE ? ESCAPE '!')";
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q->search).'%';
            array_push($params, $pattern, $pattern, $pattern);
        }
        $condition = implode(' AND ', $where);
        $total = (int)$this->db->fetchOne('SELECT COUNT(*) FROM event_venue WHERE '.$condition, $params);
        $rows = $this->db->fetchAllAssociative('SELECT uuid, name, location, service_mode, active, version FROM event_venue WHERE '.$condition.' ORDER BY name, uuid LIMIT '.$q->page->limit.' OFFSET '.$q->page->offset(), $params);
        $items = array_map(static fn (array $r) => ['uuid' => $r['uuid'], 'name' => $r['name'], 'location' => json_decode($r['location'], true, flags: JSON_THROW_ON_ERROR), 'serviceMode' => $r['service_mode'], 'active' => (bool)$r['active'], 'version' => (int)$r['version']], $rows);
        return $q->page->result($items, $total);
    }
}
