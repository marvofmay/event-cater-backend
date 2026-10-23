<?php

declare(strict_types=1);

namespace App\Module\Event\Presentation\API\Controller;

use App\Common\Application\DTO\Page;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Event\Application\Command\ApplyEventVenue;
use App\Module\Event\Application\Command\ChangeEventVenueActive;
use App\Module\Event\Application\Command\CreateVenueFromEvent;
use App\Module\Event\Application\Command\SaveEventVenue;
use App\Module\Event\Application\Query\GetEvent;
use App\Module\Event\Application\Query\GetEventVenue;
use App\Module\Event\Application\Query\ListEventVenues;
use App\Module\Event\Domain\ValueObject\EventLocation;
use App\Module\Event\Presentation\API\EventPayloadMapper;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class EventVenueController extends PlanningController
{
    #[Route('/api/event-venues', methods: ['POST'])]
    #[Route('/api/event-venues/{uuid}', methods: ['PUT'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function save(Request $request, ?string $uuid = null): JsonResponse
    {
        $this->denyAccessUnlessGranted($uuid === null ? PermissionEnum::CREATE : PermissionEnum::UPDATE, AccessEnum::EVENT_VENUES);
        $body = PlanningPayload::json($request);
        $version = $uuid === null ? null : $body->version();
        $uuid = $uuid === null ? Uuid::uuid4()->toString() : PlanningPayload::identifier($uuid);
        $l = $body->object('location');
        $this->execute(new SaveEventVenue($uuid, $version, $body->text('name'), new EventLocation($l->text('venue', false), $l->text('street'), $l->text('city'), $l->text('postalCode'), $l->text('instructions', false)), EventPayloadMapper::contacts($body), $body->text('notes', false), $body->text('serviceMode', false) ?? 'off_site'));
        return $this->resource($this->ask(new GetEventVenue($uuid)), $version === null ? 201 : 200);
    }
    #[Route('/api/event-venues', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::EVENT_VENUES);
        $active = match($request->query->getString('active', 'true')) {
            'true' => true, 'false' => false, 'all' => null, default => throw new BusinessRuleViolation('active must be true, false or all.')
        };
        return $this->resource($this->ask(new ListEventVenues(PlanningPayload::page($request), PlanningPayload::search($request), $active)));
    }
    #[Route('/api/event-venues/options', methods: ['GET'], priority: 10)]
    public function options(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::EVENT_VENUES);
        $result = $this->ask(new ListEventVenues(new Page(1, 20), PlanningPayload::search($request), true));
        return $this->resource(['items' => $result['items']]);
    }
    #[Route('/api/event-venues/{uuid}', methods: ['GET'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function get(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENT_VENUES);
        return $this->resource($this->ask(new GetEventVenue(PlanningPayload::identifier($uuid))));
    }
    #[Route('/api/event-venues/{uuid}/active', methods: ['PUT'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function active(string $uuid, Request $request): JsonResponse
    {
        $body = PlanningPayload::json($request);
        $active = $body->boolean('active');
        $this->denyAccessUnlessGranted($active ? PermissionEnum::RESTORE : PermissionEnum::DELETE, AccessEnum::EVENT_VENUES);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new ChangeEventVenueActive($uuid, $body->version(), $active));
        return $this->resource($this->ask(new GetEventVenue($uuid)));
    }
    #[Route('/api/events/{uuid}/venue', methods: ['PUT'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function apply(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::EVENTS);
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENT_VENUES);
        $body = PlanningPayload::json($request);
        $uuid = PlanningPayload::identifier($uuid);
        $venueVersion = $body->integer('venueVersion');
        if ($venueVersion < 1) {
            throw new BusinessRuleViolation('venueVersion must be positive.');
        }
        $this->execute(new ApplyEventVenue($uuid, $body->version(), $body->uuid('venueUuid'), $venueVersion, $body->boolean('replaceContacts'), $this->actor()));
        return $this->resource($this->ask(new GetEvent($uuid)));
    }
    #[Route('/api/events/{uuid}/save-venue', methods: ['POST'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function fromEvent(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::EVENT_VENUES);
        $body = PlanningPayload::json($request);
        $venueUuid = Uuid::uuid4()->toString();
        $this->execute(new CreateVenueFromEvent(PlanningPayload::identifier($uuid), $body->version(), $venueUuid, $body->text('name'), $body->text('notes', false), $body->text('serviceMode', false) ?? 'off_site'));
        return $this->resource($this->ask(new GetEventVenue($venueUuid)), 201);
    }
}
