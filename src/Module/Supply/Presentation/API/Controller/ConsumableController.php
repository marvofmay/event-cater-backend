<?php

declare(strict_types=1);

namespace App\Module\Supply\Presentation\API\Controller;

use App\Common\Application\DTO\Page;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Supply\Application\Command\ChangeConsumableActive;
use App\Module\Supply\Application\Command\CreateConsumable;
use App\Module\Supply\Application\Command\UpdateConsumable;
use App\Module\Supply\Application\Query\GetConsumable;
use App\Module\Supply\Application\Query\ListConsumables;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/consumables', requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
final class ConsumableController extends PlanningController
{
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::CONSUMABLES);
        $body = PlanningPayload::json($request);
        $uuid = Uuid::uuid4()->toString();
        $this->execute(new CreateConsumable($uuid, $body->text('code'), $body->text('name'), $body->text('category'), $body->text('unit'), $body->text('description', false)));
        return $this->resource($this->ask(new GetConsumable($uuid)), 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::CONSUMABLES);
        $active = match ($request->query->getString('active', 'true')) {
            'true' => true, 'false' => false, 'all' => null,
            default => throw new BusinessRuleViolation('active must be true, false or all.'),
        };
        return $this->resource($this->ask(new ListConsumables(PlanningPayload::page($request), PlanningPayload::search($request), $active, $request->query->getString('category') ?: null)));
    }

    #[Route('/options', methods: ['GET'], priority: 10)]
    public function options(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::CONSUMABLES);
        $result = $this->ask(new ListConsumables(new Page(1, 20), PlanningPayload::search($request)));
        return $this->resource(['items' => array_map(static fn (array $r) => array_intersect_key($r, array_flip(['uuid', 'name', 'code', 'unit', 'category'])), $result['items'])]);
    }

    #[Route('/{uuid}', methods: ['GET'])]
    public function get(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::CONSUMABLES);
        return $this->resource($this->ask(new GetConsumable(PlanningPayload::identifier($uuid))));
    }

    #[Route('/{uuid}', methods: ['PUT'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::CONSUMABLES);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new UpdateConsumable($uuid, $body->version(), $body->text('code'), $body->text('name'), $body->text('category'), $body->text('unit'), $body->text('description', false)));
        return $this->resource($this->ask(new GetConsumable($uuid)));
    }

    #[Route('/{uuid}/active', methods: ['PUT'])]
    public function active(string $uuid, Request $request): JsonResponse
    {
        $body = PlanningPayload::json($request);
        $active = $body->boolean('active');
        $this->denyAccessUnlessGranted($active ? PermissionEnum::RESTORE : PermissionEnum::DELETE, AccessEnum::CONSUMABLES);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new ChangeConsumableActive($uuid, $body->version(), $active));
        return $this->resource($this->ask(new GetConsumable($uuid)));
    }
}
