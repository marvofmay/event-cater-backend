<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\CommandHandler;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Equipment\Application\Command\ChangeEquipmentActive;
use App\Module\Equipment\Application\Command\CreateEquipmentItem;
use App\Module\Equipment\Application\Command\UpdateEquipmentItem;
use App\Module\Equipment\Domain\Entity\EquipmentItem;
use App\Module\Equipment\Domain\Repository\EquipmentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EquipmentCommandHandler
{
    public function __construct(private EquipmentRepository $items, private Connection $connection)
    {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function create(CreateEquipmentItem $command): void
    {
        $this->items->assertCodeAvailable($command->code, $command->uuid);
        $this->items->add(EquipmentItem::create($command->uuid, $command->code, $command->name, $command->category, $command->unit, $command->description));
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function update(UpdateEquipmentItem $command): void
    {
        $item = $this->items->getForUpdate($command->uuid, $command->version);
        $this->items->assertCodeAvailable($command->code, $command->uuid);
        if ($item->unit() !== $command->unit && $this->connection->fetchOne('SELECT uuid FROM event_equipment_requirement WHERE equipment_uuid = ? LIMIT 1 FOR UPDATE', [$command->uuid]) !== false) {
            throw new BusinessRuleViolation('Unit cannot change while equipment is referenced by an event.');
        }
        $item->replaceDetails($command->code, $command->name, $command->category, $command->unit, $command->description);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function changeActive(ChangeEquipmentActive $command): void
    {
        $this->items->getForUpdate($command->uuid, $command->version)->changeActive($command->active);
    }
}
