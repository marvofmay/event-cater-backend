<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\CommandHandler;

use App\Module\Equipment\Application\Command\DeleteEquipmentLocation;
use App\Module\Equipment\Application\Command\SaveEquipmentLocation;
use App\Module\Equipment\Domain\Entity\EquipmentLocation;
use App\Module\Equipment\Domain\Repository\EquipmentLocationRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EquipmentLocationCommandHandler
{
    public function __construct(private EquipmentLocationRepository $locations, private Connection $connection)
    {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function save(SaveEquipmentLocation $command): void
    {
        $location = $command->version === null
            ? new EquipmentLocation($command->uuid, $command->name)
            : $this->locations->getForUpdate($command->uuid, $command->version);
        $this->locations->assertNameAvailable($command->name, $command->uuid);
        $location->rename($command->name);
        if ($command->version === null) {
            $this->locations->add($location);
        }
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function delete(DeleteEquipmentLocation $command): void
    {
        $location = $this->locations->getForUpdate($command->uuid, $command->version);
        $this->connection->executeStatement(
            'UPDATE equipment_item SET storage_location_uuid = NULL, version = version + 1, updated_at = ? WHERE storage_location_uuid = ?',
            [gmdate('Y-m-d H:i:s'), $command->uuid],
        );
        $this->locations->remove($location);
    }
}
