<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\CommandHandler;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Equipment\Application\Command\AddEquipmentPhoto;
use App\Module\Equipment\Application\Command\RemoveEquipmentPhoto;
use App\Module\Equipment\Domain\Entity\EquipmentPhoto;
use App\Module\Equipment\Domain\Repository\EquipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class EquipmentPhotoCommandHandler
{
    public function __construct(private EquipmentRepository $items, private EntityManagerInterface $em)
    {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function add(AddEquipmentPhoto $command): void
    {
        $item = $this->items->getForUpdate($command->uuid, $command->version);
        $photos = $this->em->getConnection()->fetchFirstColumn('SELECT uuid FROM equipment_photo WHERE equipment_uuid = ? FOR UPDATE', [$command->uuid]);
        if (count($photos) >= 20) {
            throw new BusinessRuleViolation('Do jednej pozycji sprzętu można dodać maksymalnie 20 zdjęć.');
        }
        $this->em->persist(new EquipmentPhoto($item, $command->name, $command->storedName, $command->mimeType, $command->size));
        $item->recordPhotoChange();
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function remove(RemoveEquipmentPhoto $command): void
    {
        $item = $this->items->getForUpdate($command->uuid, $command->version);
        $photo = $this->em->getRepository(EquipmentPhoto::class)->findOneBy(['uuid' => $command->photoUuid, 'equipment' => $item]);
        if (!$photo) {
            throw new PlanningResourceNotFound('Nie znaleziono zdjęcia tego sprzętu.');
        }
        $this->em->remove($photo);
        $item->recordPhotoChange();
    }
}
