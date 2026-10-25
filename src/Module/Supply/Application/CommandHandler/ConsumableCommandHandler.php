<?php

declare(strict_types=1);

namespace App\Module\Supply\Application\CommandHandler;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Supply\Application\Command\ChangeConsumableActive;
use App\Module\Supply\Application\Command\CreateConsumable;
use App\Module\Supply\Application\Command\UpdateConsumable;
use App\Module\Supply\Domain\Entity\ConsumableItem;
use App\Module\Supply\Domain\Entity\ConsumableCategory;
use App\Module\Supply\Domain\Repository\ConsumableCategoryRepository;
use App\Module\Supply\Domain\Repository\ConsumableRepository;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ConsumableCommandHandler
{
    public function __construct(private ConsumableRepository $items, private Connection $db, private ConsumableCategoryRepository $categories)
    {
    }
    private function assertCodeAvailable(string $code, string $exceptUuid): void
    {
        if ((int) $this->db->fetchOne('SELECT COUNT(*) FROM consumable_item WHERE code = ? AND uuid <> ?', [strtoupper(trim($code)), $exceptUuid]) > 0) {
            throw new PlanningConflict('Consumable code already exists.');
        }
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function create(CreateConsumable $command): void
    {
        $this->assertCodeAvailable($command->code, $command->uuid);
        $this->items->add(ConsumableItem::create($command->uuid, $command->code, $command->name, $this->category($command->categoryUuid, $command->category), $command->unit, $command->description));
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function update(UpdateConsumable $command): void
    {
        $item = $this->items->getForUpdate($command->uuid, $command->version);
        $this->assertCodeAvailable($command->code, $command->uuid);
        if ($item->unit() !== $command->unit && $this->db->fetchOne('SELECT uuid FROM event_preparation_line WHERE consumable_uuid = ? LIMIT 1 FOR UPDATE', [$command->uuid]) !== false) {
            throw new BusinessRuleViolation('Unit cannot change while a consumable is referenced by an event.');
        }
        $item->replaceDetails($command->code, $command->name, $this->category($command->categoryUuid, $command->category), $command->unit, $command->description);
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function active(ChangeConsumableActive $command): void
    {
        $this->items->getForUpdate($command->uuid, $command->version)->changeActive($command->active);
    }

    private function category(?string $uuid, ?string $name): ConsumableCategory
    {
        if ($uuid !== null) {
            return $this->categories->getForUpdate($uuid);
        }
        if ($name === null) {
            throw new BusinessRuleViolation('Wybierz kategorię produktu.');
        }
        $existing = $this->categories->findByName($name);
        if ($existing !== null) {
            return $this->categories->getForUpdate($existing->uuid());
        }
        $category = new ConsumableCategory(Uuid::uuid4()->toString(), $name);
        $this->categories->add($category);
        return $category;
    }
}
