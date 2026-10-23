<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\Query;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Query\GetEventPreparation;
use App\Module\Event\Application\Query\GetPreparationTemplate;
use App\Module\Event\Application\Query\ListPreparationTemplates;
use App\Module\Event\Application\Query\SearchPreparationOptions;
use App\Module\Event\Domain\Entity\EventPreparationList;
use App\Module\Event\Domain\Entity\PreparationTemplate;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class PreparationQueryHandler
{
    public function __construct(private EntityManagerInterface $em, private Connection $db)
    {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetEventPreparation $query): array
    {
        $list = $this->em->createQueryBuilder()->select('p', 'event', 'lines', 'equipment')
            ->from(EventPreparationList::class, 'p')->join('p.event', 'event')->leftJoin('p.lines', 'lines')->leftJoin('lines.equipment', 'equipment')
            ->where('event.uuid = :uuid')->setParameter('uuid', $query->uuid)->getQuery()->getOneOrNullResult();
        if (!$list) {
            throw new PlanningResourceNotFound('Preparation list not found.');
        }
        return $list->toArray();
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function template(GetPreparationTemplate $query): array
    {
        $template = $this->em->find(PreparationTemplate::class, $query->uuid);
        if (!$template) {
            throw new PlanningResourceNotFound('Preparation template not found.');
        }
        return $template->toArray();
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function templates(ListPreparationTemplates $query): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($query->active !== null) {
            $where[] = 'active = ?';
            $params[] = (int) $query->active;
        }
        if ($query->search !== '') {
            $where[] = "name LIKE ? ESCAPE '!'";
            $params[] = self::pattern($query->search);
        }
        $condition = implode(' AND ', $where);
        $count = (int) $this->db->fetchOne('SELECT COUNT(*) FROM preparation_template WHERE '.$condition, $params);
        $rows = $this->db->fetchAllAssociative('SELECT uuid, name, description, active, version, JSON_LENGTH(template_items) AS line_count FROM preparation_template WHERE '.$condition.' ORDER BY name, uuid LIMIT '.$query->page->limit.' OFFSET '.$query->page->offset(), $params);
        return $query->page->result(array_map(static fn (array $row) => ['uuid' => $row['uuid'], 'name' => $row['name'], 'description' => $row['description'], 'active' => (bool) $row['active'], 'version' => (int) $row['version'], 'lineCount' => (int) $row['line_count']], $rows), $count);
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function options(SearchPreparationOptions $query): array
    {
        if (!in_array($query->kind, ['all', 'equipment', 'consumable'], true)) {
            throw new BusinessRuleViolation('Unknown catalog kind.');
        }
        $parts = [];
        $params = [];
        foreach (['equipment' => 'equipment_item', 'consumable' => 'consumable_item'] as $kind => $table) {
            if ($query->kind !== 'all' && $query->kind !== $kind) {
                continue;
            }
            $category = $kind === 'equipment' ? 'category.name' : 'item.category';
            $slang = $kind === 'equipment' ? 'item.slang_name' : 'NULL';
            $join = $kind === 'equipment' ? ' JOIN equipment_category category ON category.uuid = item.category_uuid' : '';
            $slangSearch = $kind === 'equipment' ? " OR item.slang_name LIKE ? ESCAPE '!'" : '';
            $parts[] = "SELECT '".$kind."' AS kind, item.uuid AS itemUuid, item.code, item.name, ".$category.' AS category, item.unit, '.$slang.' AS slangName FROM '.$table.' item'.$join." WHERE item.active = 1 AND (item.name LIKE ? ESCAPE '!' OR item.code LIKE ? ESCAPE '!'".$slangSearch.')';
            array_push($params, self::pattern($query->search), self::pattern($query->search));
            if ($kind === 'equipment') {
                $params[] = self::pattern($query->search);
            }
        }
        return ['items' => $this->db->fetchAllAssociative('SELECT * FROM ('.implode(' UNION ALL ', $parts).') catalog ORDER BY name, kind, itemUuid LIMIT 20', $params)];
    }

    private static function pattern(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
    }
}
