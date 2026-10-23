<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\CommandHandler;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Equipment\Application\Command\DeleteEquipmentCategory;
use App\Module\Equipment\Application\Command\SaveEquipmentCategory;
use App\Module\Equipment\Domain\Entity\EquipmentCategory;
use App\Module\Equipment\Domain\Repository\EquipmentCategoryRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EquipmentCategoryCommandHandler
{
    public function __construct(private EquipmentCategoryRepository $categories, private Connection $connection)
    {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function save(SaveEquipmentCategory $command): void
    {
        $category = $command->version === null
            ? new EquipmentCategory($command->uuid, $command->name)
            : $this->categories->getForUpdate($command->uuid, $command->version);
        $this->categories->assertNameAvailable($command->name, $command->uuid);
        $category->rename($command->name);
        if ($command->version === null) {
            $this->categories->add($category);
        }
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function delete(DeleteEquipmentCategory $command): void
    {
        $category = $this->categories->getForUpdate($command->uuid, $command->version);
        if ($this->connection->fetchOne('SELECT uuid FROM equipment_item WHERE category_uuid = ? LIMIT 1 FOR UPDATE', [$command->uuid]) !== false) {
            throw new BusinessRuleViolation('Nie można usunąć kategorii przypisanej do sprzętu, także archiwalnego. Najpierw zmień kategorię tych pozycji.');
        }
        $this->categories->remove($category);
    }
}
