<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Presentation\API\Controller;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\Exception\PlanningConflict;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Kitchen\Application\Command\AcceptKitchenRevision;
use App\Module\Kitchen\Application\Command\CreateKitchenOrder;
use App\Module\Kitchen\Application\Command\ProposeKitchenRevision;
use App\Module\Kitchen\Application\Command\RecordKitchenProgress;
use App\Module\Kitchen\Application\Command\RejectKitchenRevision;
use App\Module\Kitchen\Application\Command\TransitionKitchenOrder;
use App\Module\Kitchen\Application\Command\UpdateKitchenDraft;
use App\Module\Kitchen\Application\Query\GetEventKitchenOrder;
use App\Module\Kitchen\Application\Query\GetKitchenHistory;
use App\Module\Kitchen\Application\Query\GetKitchenOrder;
use App\Module\Kitchen\Application\Query\GetKitchenRevision;
use App\Module\Kitchen\Application\Query\ListKitchenOrders;
use App\Module\Kitchen\Infrastructure\PDF\KitchenPdfRenderer;
use App\Module\Kitchen\Presentation\API\KitchenPayload;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class KitchenOrderController extends PlanningController
{
    private function allow(PermissionEnum $permission): void
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::KITCHEN_ORDERS);
        $this->denyAccessUnlessGranted($permission, AccessEnum::KITCHEN_ORDERS);
    }
    private function result(string $uuid, int $status = 200): JsonResponse
    {
        return $this->resource($this->ask(new GetKitchenOrder($uuid)), $status);
    }

    #[Route('/api/events/{eventUuid}/kitchen-orders', methods: ['POST'], requirements: ['eventUuid' => '[0-9a-fA-F-]{36}'])]
    public function create(string $eventUuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PLAN_KITCHEN_PRODUCTION);
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        $body = PlanningPayload::json($request);
        $uuid = Uuid::uuid4()->toString();
        $this->execute(new CreateKitchenOrder($uuid, PlanningPayload::identifier($eventUuid), KitchenPayload::eventVersion($body), $body->text('productionDate', false), $body->dateTime('dueAt'), $body->uuid('responsibleUuid', false), $body->text('notes', false), $this->actor(), $body->text('serviceMode', false) ?? 'off_site'));
        $response = $this->result($uuid, 201);
        $response->headers->set('Location', '/api/kitchen-orders/'.$uuid);
        return $response;
    }
    #[Route('/api/events/{eventUuid}/kitchen-orders', methods: ['GET'], requirements: ['eventUuid' => '[0-9a-fA-F-]{36}'])]
    public function forEvent(string $eventUuid): JsonResponse
    {
        $this->allow(PermissionEnum::VIEW);
        return $this->resource($this->ask(new GetEventKitchenOrder(PlanningPayload::identifier($eventUuid))));
    }
    #[Route('/api/kitchen-orders/staff-options', methods: ['GET'], priority: 10)]
    public function staffOptions(Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PLAN_KITCHEN_PRODUCTION);
        return $this->resource($this->ask(new \App\Module\Kitchen\Application\Query\SearchKitchenStaff(PlanningPayload::search($request))));
    }
    #[Route('/api/kitchen-orders', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::LIST);
        $date = $request->query->getString('date');
        return $this->resource($this->ask(new ListKitchenOrders($date, $request->query->has('status') ? $request->query->getString('status') : null, PlanningPayload::page($request))));
    }
    #[Route('/api/kitchen-orders/{uuid}', methods: ['GET'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function get(string $uuid): JsonResponse
    {
        $this->allow(PermissionEnum::VIEW);
        return $this->result(PlanningPayload::identifier($uuid));
    }

    #[Route('/api/kitchen-orders/{uuid}', methods: ['PUT'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PLAN_KITCHEN_PRODUCTION);
        $body = PlanningPayload::json($request);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new UpdateKitchenDraft($uuid, $body->version(), KitchenPayload::eventVersion($body), KitchenPayload::plan($body), $this->actor()));
        return $this->result($uuid);
    }
    #[Route('/api/kitchen-orders/{uuid}/revisions', methods: ['POST'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function propose(string $uuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::PLAN_KITCHEN_PRODUCTION);
        $body = PlanningPayload::json($request);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new ProposeKitchenRevision($uuid, $body->version(), KitchenPayload::eventVersion($body), KitchenPayload::plan($body), $body->text('reason'), $this->actor()));
        return $this->result($uuid);
    }
    #[Route('/api/kitchen-orders/{uuid}/revisions/accept', methods: ['POST'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function accept(string $uuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::ACCEPT_KITCHEN_REVISION);
        $body = PlanningPayload::json($request);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new AcceptKitchenRevision($uuid, $body->version(), $this->actor()));
        return $this->result($uuid);
    }
    #[Route('/api/kitchen-orders/{uuid}/revisions/reject', methods: ['POST'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function reject(string $uuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::ACCEPT_KITCHEN_REVISION);
        $body = PlanningPayload::json($request);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new RejectKitchenRevision($uuid, $body->version(), $body->text('reason'), $this->actor()));
        return $this->result($uuid);
    }
    #[Route('/api/kitchen-orders/{uuid}/lines/{lineUuid}/progress', methods: ['PUT'], requirements: ['uuid' => '[0-9a-fA-F-]{36}', 'lineUuid' => '[0-9a-fA-F-]{36}'])]
    public function progress(string $uuid, string $lineUuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::VIEW);
        $body = PlanningPayload::json($request);
        $uuid = PlanningPayload::identifier($uuid);
        if ($body->has('preparedPortions')) {
            $this->allow(PermissionEnum::PREPARE_KITCHEN_MEALS);
        }
        if ($body->has('handedOverPortions')) {
            $this->allow(PermissionEnum::HAND_OVER_KITCHEN_MEALS);
        }
        $this->execute(new RecordKitchenProgress($uuid, $body->version(), PlanningPayload::identifier($lineUuid), $body->integer('preparedPortions', false), $body->integer('handedOverPortions', false), $body->text('correctionReason', false), $this->actor()));
        return $this->result($uuid);
    }
    #[Route('/api/kitchen-orders/{uuid}/{action}', methods: ['POST'], requirements: ['uuid' => '[0-9a-fA-F-]{36}', 'action' => 'release|start|ready|hand-over|cancel'])]
    public function transition(string $uuid, string $action, Request $request): JsonResponse
    {
        $permission = match($action) {
            'release' => PermissionEnum::RELEASE_KITCHEN_PRODUCTION,
            'start', 'ready' => PermissionEnum::PREPARE_KITCHEN_MEALS,
            'hand-over' => PermissionEnum::HAND_OVER_KITCHEN_MEALS,
            'cancel' => PermissionEnum::CANCEL_KITCHEN_ORDER,
            default => throw new BusinessRuleViolation('Unknown kitchen action.'),
        };
        $this->allow($permission);
        $body = PlanningPayload::json($request);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new TransitionKitchenOrder($uuid, $body->version(), $action, $body->text('reason', false), $this->actor()));
        return $this->result($uuid);
    }
    #[Route('/api/kitchen-orders/{uuid}/history', methods: ['GET'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function history(string $uuid, Request $request): JsonResponse
    {
        $this->allow(PermissionEnum::VIEW);
        return $this->resource($this->ask(new GetKitchenHistory(PlanningPayload::identifier($uuid), PlanningPayload::page($request))));
    }
    #[Route('/api/kitchen-orders/{uuid}/revisions/{number}', methods: ['GET'], requirements: ['uuid' => '[0-9a-fA-F-]{36}', 'number' => '[1-9][0-9]{0,8}'])]
    public function revision(string $uuid, int $number): JsonResponse
    {
        $this->allow(PermissionEnum::VIEW);
        return $this->resource($this->ask(new GetKitchenRevision(PlanningPayload::identifier($uuid), $number)));
    }
    #[Route('/api/kitchen-orders/{uuid}/pdf', methods: ['GET'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function pdf(string $uuid, Request $request, KitchenPdfRenderer $pdf): Response
    {
        $this->allow(PermissionEnum::PDF);
        $uuid = PlanningPayload::identifier($uuid);
        $order = $this->ask(new GetKitchenOrder($uuid));
        $number = $request->query->getString('revision', (string)$order['revision']);
        if (!ctype_digit($number) || strlen($number) > 9 || (int)$number < 1) {
            throw new PlanningConflict('Release a kitchen revision before printing.');
        }
        $revision = $this->ask(new GetKitchenRevision($uuid, (int)$number));
        return new Response($pdf->render($revision), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$order['number'].'-r'.$number.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
