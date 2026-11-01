<?php

declare(strict_types=1);

namespace App\Module\System\Infrastructure\Preference;

use App\Module\System\Domain\Enum\ModuleEnum;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class DashboardPreferenceService
{
    public function __construct(private Connection $connection, private PreferenceService $preferences)
    {
    }

    public function snapshot(string $userUUID): array
    {
        $row = $this->preferences->row($userUUID, 'dashboard');

        return ['order' => $row['payload']['order'] ?? [], 'version' => $row['version']];
    }

    public function save(string $userUUID, array $input): void
    {
        $modules = array_column(ModuleEnum::cases(), 'value');
        if (array_diff(array_keys($input), ['version', 'order']) || !is_int($input['version'] ?? null) || $input['version'] < 0
            || !is_array($input['order'] ?? null) || !array_is_list($input['order']) || count($input['order']) > count($modules)) {
            throw new UnprocessableEntityHttpException('Nieprawidłowa kolejność kafelków.');
        }
        $seen = [];
        foreach ($input['order'] as $module) {
            if (!is_string($module) || !in_array($module, $modules, true) || isset($seen[$module])) {
                throw new UnprocessableEntityHttpException('Nieprawidłowy lub powtórzony kafelek.');
            }
            $seen[$module] = true;
        }
        try {
            $this->connection->transactional(function () use ($userUUID, $input): void {
                $version = $this->connection->fetchOne("SELECT version FROM application_preference WHERE owner = ? AND resource = 'dashboard' FOR UPDATE", [$userUUID]);
                if ((int) $version !== $input['version']) {
                    throw new ConflictHttpException('Układ zmieniono w innej karcie. Odśwież ustawienia.');
                }
                $payload = json_encode(['order' => $input['order']], JSON_THROW_ON_ERROR);
                if ($version === false) {
                    $this->connection->insert('application_preference', ['owner' => $userUUID, 'resource' => 'dashboard', 'payload' => $payload, 'version' => 1]);
                } else {
                    $this->connection->executeStatement("UPDATE application_preference SET payload = ?, version = version + 1 WHERE owner = ? AND resource = 'dashboard'", [$payload, $userUUID]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('Układ zmieniono w innej karcie. Odśwież ustawienia.');
        }
    }
}
