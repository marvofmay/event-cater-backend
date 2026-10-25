<?php

declare(strict_types=1);

namespace App\Module\Supply\Presentation\API\Controller;

use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Supply\Application\Command\DeleteConsumableCategory;
use App\Module\Supply\Application\Command\SaveConsumableCategory;
use App\Module\Supply\Application\Query\GetConsumableCategory;
use App\Module\Supply\Application\Query\ListConsumableCategories;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/consumable-categories', requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
final class ConsumableCategoryController extends PlanningController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::CONSUMABLES);
        return $this->resource($this->ask(new ListConsumableCategories(PlanningPayload::page($request), PlanningPayload::search($request))));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::CONSUMABLES);
        $body = PlanningPayload::json($request);
        $uuid = Uuid::uuid4()->toString();
        $this->execute(new SaveConsumableCategory($uuid, $body->text('name')));
        return $this->resource($this->ask(new GetConsumableCategory($uuid)), 201);
    }

    #[Route('/{uuid}', methods: ['PUT'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::CONSUMABLES);
        $body = PlanningPayload::json($request);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new SaveConsumableCategory($uuid, $body->text('name'), $body->version()));
        return $this->resource($this->ask(new GetConsumableCategory($uuid)));
    }

    #[Route('/{uuid}', methods: ['DELETE'])]
    public function delete(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::DELETE, AccessEnum::CONSUMABLES);
        $body = PlanningPayload::json($request);
        $replacementCategoryUuid = $body->uuid('replacementCategoryUuid', false);
        if ($replacementCategoryUuid !== null) {
            $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::CONSUMABLES);
        }
        $this->execute(new DeleteConsumableCategory(PlanningPayload::identifier($uuid), $body->version(), $replacementCategoryUuid));
        return $this->resource(['success' => true]);
    }
}
