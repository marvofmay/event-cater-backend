<?php

declare(strict_types=1);

namespace App\Module\Equipment\Presentation\API\Controller;

use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Equipment\Application\Command\DeleteEquipmentLocation;
use App\Module\Equipment\Application\Command\SaveEquipmentLocation;
use App\Module\Equipment\Application\Query\GetEquipmentLocation;
use App\Module\Equipment\Application\Query\ListEquipmentLocations;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/equipment-locations', requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
final class EquipmentLocationController extends PlanningController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::EQUIPMENT);
        return $this->resource($this->ask(new ListEquipmentLocations(PlanningPayload::page($request), PlanningPayload::search($request))));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::EQUIPMENT);
        $body = PlanningPayload::json($request);
        $uuid = Uuid::uuid4()->toString();
        $this->execute(new SaveEquipmentLocation($uuid, $body->text('name')));
        return $this->resource($this->ask(new GetEquipmentLocation($uuid)), 201);
    }

    #[Route('/{uuid}', methods: ['PUT'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::EQUIPMENT);
        $body = PlanningPayload::json($request);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new SaveEquipmentLocation($uuid, $body->text('name'), $body->version()));
        return $this->resource($this->ask(new GetEquipmentLocation($uuid)));
    }

    #[Route('/{uuid}', methods: ['DELETE'])]
    public function delete(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::DELETE, AccessEnum::EQUIPMENT);
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::EQUIPMENT);
        $body = PlanningPayload::json($request);
        $this->execute(new DeleteEquipmentLocation(PlanningPayload::identifier($uuid), $body->version()));
        return $this->resource(['success' => true]);
    }
}
