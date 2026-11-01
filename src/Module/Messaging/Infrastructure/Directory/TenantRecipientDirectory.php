<?php

declare(strict_types=1);

namespace App\Module\Messaging\Infrastructure\Directory;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Messaging\Application\Port\RecipientDirectory;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class TenantRecipientDirectory implements RecipientDirectory
{
    private const string NAME = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', employee.first_name, employee.last_name)), ''), account.email, 'Użytkownik')";
    private const string SOURCE = " FROM user account LEFT JOIN employee ON employee.uuid = account.employee_uuid
        WHERE account.deleted_at IS NULL AND (account.tenant_administrator = 1 OR (
            employee.active = 1 AND employee.deleted_at IS NULL
            AND EXISTS (SELECT 1 FROM role role_record WHERE role_record.uuid = employee.role_uuid AND role_record.deleted_at IS NULL)
            AND EXISTS (
                SELECT 1 FROM role_access ra
                JOIN access access_record ON access_record.uuid = ra.access_uuid AND access_record.name = 'messages' AND access_record.active = 1 AND access_record.deleted_at IS NULL
                JOIN module module_record ON module_record.uuid = access_record.module_uuid AND module_record.name = 'system' AND module_record.active = 1 AND module_record.deleted_at IS NULL
                JOIN role_access_permission rap ON rap.role_uuid = ra.role_uuid AND rap.access_uuid = ra.access_uuid AND rap.deleted_at IS NULL
                JOIN permission permission_record ON permission_record.uuid = rap.permission_uuid AND permission_record.name IN ('list', 'view') AND permission_record.active = 1 AND permission_record.deleted_at IS NULL
                WHERE ra.role_uuid = employee.role_uuid AND ra.deleted_at IS NULL
                GROUP BY ra.role_uuid HAVING COUNT(DISTINCT permission_record.name) = 2
            )
        ))";

    public function __construct(private Connection $db)
    {
    }

    public function search(string $phrase): array
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $phrase).'%';
        return $this->db->fetchAllAssociative('SELECT account.uuid, '.self::NAME.' AS name'.self::SOURCE." AND (".self::NAME." LIKE ? ESCAPE '!' OR account.email LIKE ? ESCAPE '!' OR account.login_phone LIKE ? ESCAPE '!') ORDER BY name, account.uuid LIMIT 20", [$pattern, $pattern, $pattern]);
    }

    public function resolve(array $uuids): array
    {
        if (count($uuids) < 1 || count($uuids) > 100) {
            throw new BusinessRuleViolation('Wybierz od 1 do 100 odbiorców.');
        }
        $rows = $this->db->fetchAllAssociative('SELECT account.uuid, '.self::NAME.' AS name'.self::SOURCE.' AND account.uuid IN (?)', [$uuids], [ArrayParameterType::STRING]);
        if (count($rows) !== count($uuids)) {
            throw new BusinessRuleViolation('Co najmniej jeden odbiorca jest niedostępny lub nie ma dostępu do wiadomości w tej firmie. Odśwież listę odbiorców.');
        }
        return $rows;
    }

    public function senderName(string $uuid): string
    {
        return (string) $this->db->fetchOne('SELECT '.self::NAME.' FROM user account LEFT JOIN employee ON employee.uuid = account.employee_uuid WHERE account.uuid = ?', [$uuid]);
    }
}
