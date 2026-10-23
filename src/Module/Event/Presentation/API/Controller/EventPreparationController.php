<?php

declare(strict_types=1);

namespace App\Module\Event\Presentation\API\Controller;

use App\Common\Domain\Exception\PlanningConflict;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Event\Application\Command\ApplyPreparationTemplate;
use App\Module\Event\Application\Command\ApproveEventPreparation;
use App\Module\Event\Application\Command\ConfigureEventPreparation;
use App\Module\Event\Application\Command\ConfigurePreparationLine;
use App\Module\Event\Application\Command\ConfirmPreparationLoaded;
use App\Module\Event\Application\Command\MarkPreparationReady;
use App\Module\Event\Application\Command\RecordPreparationProgress;
use App\Module\Event\Application\Command\RemoveEventConsumable;
use App\Module\Event\Application\Command\SetEventConsumable;
use App\Module\Event\Application\Command\StartEventPreparation;
use App\Module\Event\Application\Query\GetEventPreparation;
use App\Module\Event\Application\Query\GetPreparationTemplate;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/events/{uuid}/preparation', requirements: ['uuid' => '[0-9a-fA-F-]{36}', 'itemUuid' => '[0-9a-fA-F-]{36}', 'lineUuid' => '[0-9a-fA-F-]{36}'])]
final class EventPreparationController extends PlanningController
{
    private function allow(PermissionEnum $permission): void
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        $this->denyAccessUnlessGranted($permission, AccessEnum::EVENTS);
    }

    private function result(string $uuid): JsonResponse
    {
        return $this->resource($this->ask(new GetEventPreparation($uuid)));
    }

    #[Route('/staff-options', methods: ['GET'], priority: 10)]
    public function staffOptions(Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PLAN_EVENT_PREPARATION);
        return $this->resource(['items' => $this->ask(new \App\Module\Event\Application\Query\SearchEventEmployees(PlanningPayload::search($request)))]);
    }
    #[Route('', methods: ['GET'])]
    public function get(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        return $this->result(PlanningPayload::identifier($uuid));
    }

    #[Route('', methods: ['PUT'])]
    public function configure(string $uuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PLAN_EVENT_PREPARATION);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new ConfigureEventPreparation($uuid, $body->version(), $body->uuid('responsibleUuid', false), $body->dateTime('deadlineAt'), $body->text('notes', false), $this->actor()));
        return $this->result($uuid);
    }

    #[Route('/consumables/{itemUuid}', methods: ['PUT'])]
    public function consumable(string $uuid, string $itemUuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PLAN_EVENT_PREPARATION);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new SetEventConsumable($uuid, $body->version(), PlanningPayload::identifier($itemUuid), $body->quantity('requiredQuantity'), $this->actor()));
        return $this->result($uuid);
    }

    #[Route('/consumables/{itemUuid}', methods: ['DELETE'])]
    public function removeConsumable(string $uuid, string $itemUuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PLAN_EVENT_PREPARATION);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new RemoveEventConsumable($uuid, $body->version(), PlanningPayload::identifier($itemUuid), $this->actor()));
        return $this->result($uuid);
    }

    #[Route('/lines/{lineUuid}', methods: ['PUT'])]
    public function configureLine(string $uuid, string $lineUuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PLAN_EVENT_PREPARATION);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new ConfigurePreparationLine($uuid, $body->version(), PlanningPayload::identifier($lineUuid), $body->uuid('responsibleUuid', false), $body->text('sourceLocation', false), $body->text('instructions', false), $this->actor()));
        return $this->result($uuid);
    }

    #[Route('/lines/{lineUuid}/progress', methods: ['PUT'])]
    public function progress(string $uuid, string $lineUuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        if ($body->has('preparedQuantity')) {
            $this->allow(PermissionEnum::PREPARE_EVENT_CATERING);
        }
        if ($body->has('loadedQuantity')) {
            $this->allow(PermissionEnum::LOAD_EVENT_CATERING);
        }
        $this->execute(new RecordPreparationProgress($uuid, $body->version(), PlanningPayload::identifier($lineUuid), $body->quantity('preparedQuantity', false), $body->quantity('loadedQuantity', false), $body->text('correctionReason', false), $this->actor()));
        return $this->result($uuid);
    }

    #[Route('/approve', methods: ['POST'])]
    public function approve(string $uuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PLAN_EVENT_PREPARATION);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new ApproveEventPreparation($uuid, $body->version(), $this->actor()));
        return $this->result($uuid);
    }

    #[Route('/start', methods: ['POST'])]
    public function start(string $uuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PREPARE_EVENT_CATERING);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new StartEventPreparation($uuid, $body->version(), $this->actor()));
        return $this->result($uuid);
    }

    #[Route('/ready', methods: ['POST'])]
    public function ready(string $uuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PREPARE_EVENT_CATERING);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new MarkPreparationReady($uuid, $body->version(), $this->actor()));
        return $this->result($uuid);
    }

    #[Route('/confirm-loaded', methods: ['POST'])]
    public function confirmLoaded(string $uuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::DISPATCH_EVENT_CATERING);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $reason = $body->text('acceptShortagesReason', false);
        if ($reason !== null) {
            $this->allow(PermissionEnum::ACCEPT_PREPARATION_SHORTAGES);
        }
        $this->execute(new ConfirmPreparationLoaded($uuid, $body->version(), $reason, $this->actor()));
        return $this->result($uuid);
    }

    #[Route('/apply-template', methods: ['POST'])]
    public function apply(string $uuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PLAN_EVENT_PREPARATION);
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::PREPARATION_TEMPLATES);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $templateUuid = $body->uuid('templateUuid');
        $templateVersion = $body->integer('templateVersion');
        $snapshot = $this->ask(new GetPreparationTemplate($templateUuid));
        // Authorize exactly the revision the handler will use, preventing a concurrent template change from widening permissions.
        if ($snapshot['version'] !== $templateVersion) {
            throw new PlanningConflict('Stale template version.');
        }
        if (in_array('equipment', array_column($snapshot['lines'], 'kind'), true)) {
            $this->allow(PermissionEnum::PLAN_EVENT_EQUIPMENT);
        }
        $this->execute(new ApplyPreparationTemplate($uuid, $body->version(), $templateUuid, $templateVersion, $this->actor()));
        return $this->result($uuid);
    }
}
