<?php

declare(strict_types=1);

namespace App\Module\Supply\Application\CommandHandler;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Supply\Application\Command\DeleteConsumableCategory;
use App\Module\Supply\Application\Command\SaveConsumableCategory;
use App\Module\Supply\Domain\Entity\ConsumableCategory;
use App\Module\Supply\Domain\Repository\ConsumableCategoryRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ConsumableCategoryCommandHandler
{
    public function __construct(private ConsumableCategoryRepository $categories, private Connection $connection)
    {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function save(SaveConsumableCategory $command): void
    {
        $category = $command->version === null
            ? new ConsumableCategory($command->uuid, $command->name)
            : $this->categories->getForUpdate($command->uuid, $command->version);
        $this->categories->assertNameAvailable($command->name, $command->uuid);
        $category->rename($command->name);
        if ($command->version === null) {
            $this->categories->add($category);
        }
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function delete(DeleteConsumableCategory $command): void
    {
        $category = $this->categories->getForUpdate($command->uuid, $command->version);
        if ($command->replacementCategoryUuid !== null) {
            if ($command->replacementCategoryUuid === $command->uuid) {
                throw new BusinessRuleViolation('Wybierz inną kategorię docelową.');
            }
            $replacement = $this->categories->getForUpdate($command->replacementCategoryUuid);
            $this->connection->executeStatement(
                'UPDATE consumable_item SET category_uuid = ?, version = version + 1, updated_at = ? WHERE category_uuid = ?',
                [$replacement->uuid(), gmdate('Y-m-d H:i:s'), $command->uuid],
            );
        }
        if ($this->connection->fetchOne('SELECT uuid FROM consumable_item WHERE category_uuid = ? LIMIT 1 FOR UPDATE', [$command->uuid]) !== false) {
            throw new BusinessRuleViolation('Ta kategoria zawiera produkty, także archiwalne. Wybierz kategorię docelową w polu „Przenieś produkty do kategorii” i ponów usunięcie.');
        }
        $this->categories->remove($category);
    }
}
