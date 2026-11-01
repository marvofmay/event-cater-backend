<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Query;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Event\Application\Query\GetEventCustomer;
use App\Module\Event\Application\Query\SearchEventCustomers;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EventCustomerQueryHandler
{
    public function __construct(private Connection $db)
    {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetEventCustomer $query): array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM event_customer WHERE uuid = ?', [$query->uuid]) ?: throw new PlanningResourceNotFound('Nie znaleziono kontrahenta.');
        return self::project($row);
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function search(SearchEventCustomers $query): array
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query->search).'%';
        $source = " FROM event_customer WHERE name LIKE ? ESCAPE '!' OR nip LIKE ? ESCAPE '!' OR regon LIKE ? ESCAPE '!' OR krs LIKE ? ESCAPE '!' OR phone LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!'";
        $parameters = array_fill(0, 6, $pattern);
        $items = array_map(self::project(...), $this->db->fetchAllAssociative('SELECT *'.$source.' ORDER BY name, uuid LIMIT '.$query->page->limit.' OFFSET '.$query->page->offset(), $parameters));
        return $query->page->result($items, (int) $this->db->fetchOne('SELECT COUNT(*)'.$source, $parameters));
    }

    private static function project(array $row): array
    {
        $row['postalCode'] = $row['postal_code'];
        unset($row['postal_code']);
        return $row;
    }
}
