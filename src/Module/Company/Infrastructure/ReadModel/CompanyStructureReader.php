<?php

declare(strict_types=1);

namespace App\Module\Company\Infrastructure\ReadModel;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CompanyStructureReader
{
    public function __construct(private Connection $connection)
    {
    }

    public function search(string $phrase, int $page, int $pageSize, bool $departments, bool $employees): array
    {
        $parts = ["SELECT 'company' AS type, c.uuid, c.full_name AS name, c.short_name AS subtitle, c.active, c.full_name AS companyName, NULL AS departmentName, 0 AS sort_order FROM company c WHERE c.deleted_at IS NULL"];
        if ($departments) {
            $parts[] = "SELECT 'department' AS type, d.uuid, d.name, d.internal_code AS subtitle, d.active, c.full_name AS companyName, d.name AS departmentName, 1 AS sort_order FROM department d JOIN company c ON c.uuid = d.company_uuid AND c.deleted_at IS NULL WHERE d.deleted_at IS NULL";
        }
        if ($departments && $employees) {
            $parts[] = "SELECT 'employee' AS type, e.uuid, TRIM(CONCAT(e.first_name, ' ', e.last_name)) AS name, p.name AS subtitle, e.active, c.full_name AS companyName, d.name AS departmentName, 2 AS sort_order FROM employee e JOIN department d ON d.uuid = e.department_uuid AND d.company_uuid = e.company_uuid AND d.deleted_at IS NULL JOIN company c ON c.uuid = d.company_uuid AND c.deleted_at IS NULL LEFT JOIN position p ON p.uuid = e.position_uuid AND p.deleted_at IS NULL WHERE e.deleted_at IS NULL";
        }
        $parameters = ['phrase' => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $phrase).'%'];
        $contactMatch = "contact_match.data LIKE :phrase ESCAPE '!'";
        $phoneDigits = preg_replace('/[^0-9]/', '', $phrase);
        if (preg_match('/^\+?[0-9\s().-]+$/u', $phrase) && strlen($phoneDigits) >= 2) {
            $contactMatch .= " OR (contact_match.type = 'phone' AND REGEXP_REPLACE(contact_match.data, '[^0-9]', '') LIKE :phonePhrase)";
            $parameters['phonePhrase'] = '%'.$phoneDigits.'%';
        }
        $sql = 'SELECT * FROM ('.implode(' UNION ALL ', $parts).") nodes WHERE (name LIKE :phrase ESCAPE '!' OR subtitle LIKE :phrase ESCAPE '!' OR EXISTS (
            SELECT 1 FROM contact contact_match WHERE contact_match.deleted_at IS NULL AND contact_match.active = 1
            AND contact_match.type IN ('phone', 'email')
            AND ((nodes.type = 'company' AND contact_match.company_uuid = nodes.uuid)
                OR (nodes.type = 'department' AND contact_match.department_uuid = nodes.uuid)
                OR (nodes.type = 'employee' AND contact_match.employee_uuid = nodes.uuid))
            AND (".$contactMatch.')
        ))';
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ('.$sql.') matches', $parameters);
        $offset = ($page - 1) * $pageSize;
        $items = $this->connection->fetchAllAssociative($sql.' ORDER BY sort_order, name, uuid LIMIT '.$pageSize.' OFFSET '.$offset, $parameters);
        foreach ($items as &$item) {
            $item['active'] = (bool) $item['active'];
            unset($item['sort_order']);
        }

        return ['items' => $items, 'total' => $total, 'page' => $page, 'hasMore' => $offset + count($items) < $total];
    }

    public function employeePreview(string $uuid): array
    {
        $employee = $this->connection->fetchAssociative('SELECT e.uuid, e.first_name AS firstName, e.last_name AS lastName FROM employee e JOIN department d ON d.uuid = e.department_uuid AND d.company_uuid = e.company_uuid AND d.deleted_at IS NULL JOIN company c ON c.uuid = e.company_uuid AND c.deleted_at IS NULL WHERE e.uuid = ? AND e.deleted_at IS NULL', [$uuid]);
        if (!$employee) throw new NotFoundHttpException('Nie znaleziono pracownika.');
        $contacts = $this->connection->fetchAllAssociative("SELECT type, data FROM contact WHERE employee_uuid = ? AND deleted_at IS NULL AND active = 1 AND type IN ('phone', 'email') ORDER BY type, created_at, uuid", [$uuid]);
        $employee['phones'] = array_values(array_map(static fn ($contact) => $contact['data'], array_filter($contacts, static fn ($contact) => $contact['type'] === 'phone')));
        $employee['emails'] = array_values(array_map(static fn ($contact) => $contact['data'], array_filter($contacts, static fn ($contact) => $contact['type'] === 'email')));

        return $employee;
    }

    public function path(string $type, string $uuid): array
    {
        $sql = match ($type) {
            'company' => "SELECT 'company' AS type, uuid, full_name AS name, short_name AS subtitle, active, uuid AS company_uuid, NULL AS department_uuid FROM company WHERE uuid = :uuid AND deleted_at IS NULL",
            'department' => "SELECT 'department' AS type, d.uuid, d.name, d.internal_code AS subtitle, d.active, d.company_uuid, d.uuid AS department_uuid FROM department d JOIN company c ON c.uuid = d.company_uuid AND c.deleted_at IS NULL WHERE d.uuid = :uuid AND d.deleted_at IS NULL",
            'employee' => "SELECT 'employee' AS type, e.uuid, TRIM(CONCAT(e.first_name, ' ', e.last_name)) AS name, p.name AS subtitle, e.active, e.company_uuid, e.department_uuid FROM employee e JOIN department d ON d.uuid = e.department_uuid AND d.company_uuid = e.company_uuid AND d.deleted_at IS NULL JOIN company c ON c.uuid = e.company_uuid AND c.deleted_at IS NULL LEFT JOIN position p ON p.uuid = e.position_uuid AND p.deleted_at IS NULL WHERE e.uuid = :uuid AND e.deleted_at IS NULL",
        };
        $node = $this->connection->fetchAssociative($sql, ['uuid' => $uuid]);
        if (!$node) {
            throw new NotFoundHttpException('Nie znaleziono elementu struktury firmy.');
        }

        $companies = $this->connection->fetchAllAssociative("WITH RECURSIVE ancestors AS (
            SELECT uuid, company_uuid, full_name, short_name, active, 0 AS depth FROM company WHERE uuid = :uuid AND deleted_at IS NULL
            UNION ALL
            SELECT parent.uuid, parent.company_uuid, parent.full_name, parent.short_name, parent.active, ancestors.depth + 1 FROM company parent JOIN ancestors ON parent.uuid = ancestors.company_uuid WHERE parent.deleted_at IS NULL AND ancestors.depth < 63
        ) SELECT 'company' AS type, uuid, full_name AS name, short_name AS subtitle, active FROM ancestors ORDER BY depth DESC", ['uuid' => $node['company_uuid']]);
        $departments = [];
        if (null !== $node['department_uuid']) {
            $departments = $this->connection->fetchAllAssociative("WITH RECURSIVE ancestors AS (
                SELECT uuid, department_uuid, company_uuid, name, internal_code, active, 0 AS depth FROM department WHERE uuid = :uuid AND deleted_at IS NULL
                UNION ALL
                SELECT parent.uuid, parent.department_uuid, parent.company_uuid, parent.name, parent.internal_code, parent.active, ancestors.depth + 1 FROM department parent JOIN ancestors ON parent.uuid = ancestors.department_uuid AND parent.company_uuid = ancestors.company_uuid WHERE parent.deleted_at IS NULL AND ancestors.depth < 63
            ) SELECT 'department' AS type, uuid, name, internal_code AS subtitle, active FROM ancestors ORDER BY depth DESC", ['uuid' => $node['department_uuid']]);
        }
        $nodes = array_merge($companies, $departments, 'employee' === $type ? [$node] : []);
        $seen = [];
        foreach ($nodes as &$item) {
            $key = $item['type'].':'.$item['uuid'];
            if (isset($seen[$key])) {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('Struktura firmy zawiera zapętlone powiązania.');
            }
            $seen[$key] = true;
            $item['active'] = (bool) $item['active'];
            unset($item['company_uuid'], $item['department_uuid']);
        }

        return ['items' => $nodes];
    }

    public function children(string $type, ?string $uuid, int $page, int $pageSize, string $phrase, bool $departments, bool $employees): array
    {
        $parameters = [];
        if ('root' !== $type) {
            $parentSql = 'company' === $type
                ? 'SELECT uuid FROM company WHERE uuid = :uuid AND deleted_at IS NULL'
                : 'SELECT d.uuid FROM department d JOIN company c ON c.uuid = d.company_uuid AND c.deleted_at IS NULL WHERE d.uuid = :uuid AND d.deleted_at IS NULL';
            if (!$this->connection->fetchOne($parentSql, ['uuid' => $uuid])) {
                throw new NotFoundHttpException('Nie znaleziono gałęzi struktury firmy.');
            }
            $parameters['uuid'] = $uuid;
        }

        $parts = [];
        if ('department' !== $type) {
            $condition = 'root' === $type
                ? '(c.company_uuid IS NULL OR NOT EXISTS (SELECT 1 FROM company parent WHERE parent.uuid = c.company_uuid AND parent.deleted_at IS NULL))'
                : 'c.company_uuid = :uuid';
            $parts[] = "SELECT 'company' AS type, c.uuid, c.full_name AS name, c.short_name AS subtitle, c.active, 0 AS sort_order FROM company c WHERE c.deleted_at IS NULL AND ".$condition;
        }
        if ('company' === $type && $departments) {
            $parts[] = "SELECT 'department' AS type, d.uuid, d.name, d.internal_code AS subtitle, d.active, 1 AS sort_order FROM department d WHERE d.deleted_at IS NULL AND d.company_uuid = :uuid AND (d.department_uuid IS NULL OR NOT EXISTS (SELECT 1 FROM department parent WHERE parent.uuid = d.department_uuid AND parent.company_uuid = d.company_uuid AND parent.deleted_at IS NULL))";
        }
        if ('department' === $type) {
            $parts[] = "SELECT 'department' AS type, d.uuid, d.name, d.internal_code AS subtitle, d.active, 1 AS sort_order FROM department d JOIN department parent ON parent.uuid = d.department_uuid AND parent.company_uuid = d.company_uuid WHERE d.deleted_at IS NULL AND d.department_uuid = :uuid";
            if ($employees) {
                $parts[] = "SELECT 'employee' AS type, e.uuid, TRIM(CONCAT(e.first_name, ' ', e.last_name)) AS name, p.name AS subtitle, e.active, 0 AS sort_order FROM employee e JOIN department d ON d.uuid = e.department_uuid AND d.company_uuid = e.company_uuid LEFT JOIN position p ON p.uuid = e.position_uuid AND p.deleted_at IS NULL WHERE e.deleted_at IS NULL AND e.department_uuid = :uuid";
            }
        }

        $sql = 'SELECT * FROM ('.implode(' UNION ALL ', $parts).') nodes';
        if ('' !== $phrase) {
            $sql .= " WHERE (name LIKE :phrase ESCAPE '!' OR subtitle LIKE :phrase ESCAPE '!')";
            $parameters['phrase'] = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $phrase).'%';
        }
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ('.$sql.') matches', $parameters);
        $offset = ($page - 1) * $pageSize;
        $items = $this->connection->fetchAllAssociative($sql.' ORDER BY sort_order, name, uuid LIMIT '.$pageSize.' OFFSET '.$offset, $parameters);

        return [
            'items' => array_map(static fn (array $item): array => [
                'type' => $item['type'],
                'uuid' => $item['uuid'],
                'name' => $item['name'],
                'subtitle' => $item['subtitle'],
                'active' => (bool) $item['active'],
            ], $items),
            'total' => $total,
            'page' => $page,
            'hasMore' => $offset + count($items) < $total,
        ];
    }
}
