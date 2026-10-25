<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\CommandHandler;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Equipment\Application\Command\ChangeEquipmentActive;
use App\Module\Equipment\Application\Command\CreateEquipmentItem;
use App\Module\Equipment\Application\Command\DeleteEquipmentItem;
use App\Module\Equipment\Application\Command\UpdateEquipmentItem;
use App\Module\Equipment\Domain\Entity\EquipmentItem;
use App\Module\Equipment\Domain\Entity\EquipmentCategory;
use App\Module\Equipment\Domain\Repository\EquipmentCategoryRepository;
use App\Module\Equipment\Domain\Repository\EquipmentLocationRepository;
use App\Module\Equipment\Domain\Repository\EquipmentRepository;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EquipmentCommandHandler
{
    public function __construct(private EquipmentRepository $items, private Connection $connection, private EquipmentCategoryRepository $categories, private EquipmentLocationRepository $locations)
    {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function create(CreateEquipmentItem $command): void
    {
        $this->items->assertCodeAvailable($command->code, $command->uuid);
        $category = $this->category($command->categoryUuid, $command->category);
        $item = EquipmentItem::create($command->uuid, $command->code, $command->name, $category, $command->unit, $command->description, $command->slangName, $command->stockQuantity);
        if ($command->storageLocationUuid !== null) {
            $item->changeStorageLocation($this->locations->getForUpdate($command->storageLocationUuid));
        }
        $this->items->add($item);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function update(UpdateEquipmentItem $command): void
    {
        $item = $this->items->getForUpdate($command->uuid, $command->version);
        $this->items->assertCodeAvailable($command->code, $command->uuid);
        if ($item->unit() !== $command->unit && $this->connection->fetchOne('SELECT uuid FROM event_equipment_requirement WHERE equipment_uuid = ? LIMIT 1 FOR UPDATE', [$command->uuid]) !== false) {
            throw new BusinessRuleViolation('Unit cannot change while equipment is referenced by an event.');
        }
        $item->replaceDetails($command->code, $command->name, $this->category($command->categoryUuid, $command->category), $command->unit, $command->description, $command->slangName, $command->stockQuantity);
        if ($command->storageLocationProvided || $command->storageLocationUuid !== null) {
            $item->changeStorageLocation($command->storageLocationUuid === null ? null : $this->locations->getForUpdate($command->storageLocationUuid));
        }
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function changeActive(ChangeEquipmentActive $command): void
    {
        $this->items->getForUpdate($command->uuid, $command->version)->changeActive($command->active);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function delete(DeleteEquipmentItem $command): void
    {
        $item = $this->items->getForUpdate($command->uuid, $command->version);
        if ($this->connection->fetchOne('SELECT uuid FROM event_equipment_requirement WHERE equipment_uuid = ? LIMIT 1 FOR UPDATE', [$command->uuid]) !== false) {
            throw new BusinessRuleViolation('Nie można trwale usunąć sprzętu przypisanego do wydarzenia. Możesz go zarchiwizować lub zmienić jego kategorię.');
        }
        $reference = json_encode(['kind' => 'equipment', 'itemUuid' => $command->uuid], JSON_THROW_ON_ERROR);
        if ($this->connection->fetchOne('SELECT uuid FROM preparation_template WHERE JSON_CONTAINS(template_items, ?) LIMIT 1 FOR UPDATE', [$reference]) !== false) {
            throw new BusinessRuleViolation('Nie można trwale usunąć sprzętu używanego w szablonie, także archiwalnym. Najpierw usuń pozycję z szablonu lub zmień kategorię sprzętu.');
        }
        $this->items->remove($item);
    }

    private function category(?string $uuid, ?string $name): EquipmentCategory
    {
        if ($uuid !== null) {
            return $this->categories->getForUpdate($uuid);
        }
        if ($name === null) {
            throw new BusinessRuleViolation('Wybierz kategorię sprzętu.');
        }
        $existing = $this->categories->findByName($name);
        if ($existing !== null) {
            return $this->categories->getForUpdate($existing->uuid());
        }
        $category = new EquipmentCategory(Uuid::uuid4()->toString(), $name);
        $this->categories->add($category);
        return $category;
    }
}
