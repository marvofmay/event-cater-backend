<?php

declare(strict_types=1);

namespace App\Module\Event\Presentation\API\Controller;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Event\Application\Command\ChangePreparationTemplateActive;
use App\Module\Event\Application\Command\CreatePreparationTemplate;
use App\Module\Event\Application\Command\UpdatePreparationTemplate;
use App\Module\Event\Application\Query\GetPreparationTemplate;
use App\Module\Event\Application\Query\ListPreparationTemplates;
use App\Module\Event\Application\Query\SearchPreparationOptions;
use App\Module\Event\Presentation\API\PreparationTemplatePayload;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PreparationTemplateController extends PlanningController
{
    private function authorizeItems(array $lines): void
    {
        foreach (array_unique(array_column($lines, 'kind')) as $kind) {
            $this->denyAccessUnlessGranted(PermissionEnum::LIST, $kind === 'equipment' ? AccessEnum::EQUIPMENT : AccessEnum::CONSUMABLES);
        }
    }

    #[Route('/api/preparation-options', methods: ['GET'])]
    public function options(Request $request): JsonResponse
    {
        $kind = $request->query->getString('kind', 'all');
        if (!in_array($kind, ['all', 'equipment', 'consumable'], true)) {
            throw new BusinessRuleViolation('Unknown catalog kind.');
        }
        if ($kind !== 'consumable') {
            $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::EQUIPMENT);
        }
        if ($kind !== 'equipment') {
            $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::CONSUMABLES);
        }
        return $this->resource($this->ask(new SearchPreparationOptions($kind, PlanningPayload::search($request))));
    }

    #[Route('/api/preparation-templates', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::PREPARATION_TEMPLATES);
        $body = PlanningPayload::json($request);
        $lines = PreparationTemplatePayload::lines($body);
        $this->authorizeItems($lines);
        $uuid = Uuid::uuid4()->toString();
        $this->execute(new CreatePreparationTemplate($uuid, $body->text('name'), $body->text('description', false), $lines));
        return $this->resource($this->ask(new GetPreparationTemplate($uuid)), 201);
    }

    #[Route('/api/preparation-templates', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::PREPARATION_TEMPLATES);
        $active = match ($request->query->getString('active', 'true')) {
            'true' => true, 'false' => false, 'all' => null, default => throw new BusinessRuleViolation('active must be true, false or all.')
        };
        return $this->resource($this->ask(new ListPreparationTemplates(PlanningPayload::page($request), PlanningPayload::search($request), $active)));
    }

    #[Route('/api/preparation-templates/{uuid}', methods: ['GET'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function get(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::PREPARATION_TEMPLATES);
        return $this->resource($this->ask(new GetPreparationTemplate(PlanningPayload::identifier($uuid))));
    }

    #[Route('/api/preparation-templates/{uuid}', methods: ['PUT'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::PREPARATION_TEMPLATES);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $lines = PreparationTemplatePayload::lines($body);
        $this->authorizeItems($lines);
        $this->execute(new UpdatePreparationTemplate($uuid, $body->version(), $body->text('name'), $body->text('description', false), $lines));
        return $this->resource($this->ask(new GetPreparationTemplate($uuid)));
    }

    #[Route('/api/preparation-templates/{uuid}/active', methods: ['PUT'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function active(string $uuid, Request $request): JsonResponse
    {
        $body = PlanningPayload::json($request);
        $active = $body->boolean('active');
        $this->denyAccessUnlessGranted($active ? PermissionEnum::RESTORE : PermissionEnum::DELETE, AccessEnum::PREPARATION_TEMPLATES);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new ChangePreparationTemplateActive($uuid, $body->version(), $active));
        return $this->resource($this->ask(new GetPreparationTemplate($uuid)));
    }
}
