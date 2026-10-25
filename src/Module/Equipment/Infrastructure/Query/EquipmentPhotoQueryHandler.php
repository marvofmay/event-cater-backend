<?php

declare(strict_types=1);

namespace App\Module\Equipment\Infrastructure\Query;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Equipment\Application\Query\GetEquipmentPhoto;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class EquipmentPhotoQueryHandler
{
    public function __construct(private Connection $db)
    {
    }

    public function __invoke(GetEquipmentPhoto $query): array
    {
        $photo = $this->db->fetchAssociative('SELECT name, stored_name, mime_type FROM equipment_photo WHERE equipment_uuid = ? AND uuid = ?', [$query->equipmentUuid, $query->photoUuid]);
        if (!$photo) {
            throw new PlanningResourceNotFound('Nie znaleziono zdjęcia tego sprzętu.');
        }
        return $photo;
    }
}
