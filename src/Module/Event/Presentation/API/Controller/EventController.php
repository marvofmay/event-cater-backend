<?php

declare(strict_types=1);

namespace App\Module\Event\Presentation\API\Controller;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Event\Application\Command\AssignEventStaff;
use App\Module\Event\Application\Command\AttachEventMedia;
use App\Module\Event\Application\Command\ChangeEventStatus;
use App\Module\Event\Application\Command\CreateEvent;
use App\Module\Event\Application\Command\RemoveEventEquipment;
use App\Module\Event\Application\Command\RemoveEventMedia;
use App\Module\Event\Application\Command\RemoveEventStaff;
use App\Module\Event\Application\Command\ReplaceEventContacts;
use App\Module\Event\Application\Command\ReplaceEventMenu;
use App\Module\Event\Application\Command\SetEventEquipment;
use App\Module\Event\Application\Command\UpdateEvent;
use App\Module\Event\Application\Query\GetEvent;
use App\Module\Event\Application\Query\GetEventHistory;
use App\Module\Event\Application\Query\GetEventMedia;
use App\Module\Event\Application\Query\ListEvents;
use App\Module\Event\Application\Query\SearchEventEmployees;
use App\Module\Event\Application\Service\EventNotificationAudienceResolver;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Enum\EventFunction;
use App\Module\Event\Domain\Enum\EventStatus;
use App\Module\Event\Domain\ValueObject\EventSchedule;
use App\Module\Event\Infrastructure\Storage\EventMediaStorage;
use App\Module\Event\Presentation\API\EventPayloadMapper;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/events', requirements: ['uuid' => '[0-9a-fA-F-]{36}', 'employeeUuid' => '[0-9a-fA-F-]{36}', 'equipmentUuid' => '[0-9a-fA-F-]{36}', 'attachmentUuid' => '[0-9a-fA-F-]{36}'])]
final class EventController extends PlanningController
{
    private function allowChange(PermissionEnum $permission): void
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        $this->denyAccessUnlessGranted($permission, AccessEnum::EVENTS);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::EVENTS);
        $body = PlanningPayload::json($request);
        $uuid = Uuid::uuid4()->toString();
        $this->execute(new CreateEvent($uuid, EventPayloadMapper::details($body), $this->actor()));
        return $this->eventResource($uuid, 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::EVENTS);
        $from = $request->query->has('from') ? $request->query->getString('from') : null;
        $to = $request->query->has('to') ? $request->query->getString('to') : null;
        foreach ([$from, $to] as $date) {
            if ($date !== null) {
                new EventSchedule($date);
            }
        }
        if ($from !== null && $to !== null && $from > $to) {
            throw new BusinessRuleViolation('from must not exceed to.');
        }
        $status = $request->query->getString('status');
        if ($status !== '' && !EventStatus::tryFrom($status)) {
            throw new BusinessRuleViolation('Unknown event status.');
        }
        $viewerEmployeeUuid = null;
        $staffEmployeeUuid = null;
        $packerEmployeeUuid = null;
        $user = $this->getUser();
        if ($user instanceof \App\Module\Company\Domain\Entity\User) {
            $viewerEmployeeUuid = $user->getEmployeeUUID();
        }
        if ($request->query->getBoolean('mine')) {
            if ($viewerEmployeeUuid === null) {
                return $this->resource(PlanningPayload::page($request)->result([], 0));
            }
            $staffEmployeeUuid = $viewerEmployeeUuid;
        }
        $preparationPhase = null;
        if ($request->query->getBoolean('packing')) {
            if ($viewerEmployeeUuid === null) {
                return $this->resource(PlanningPayload::page($request)->result([], 0));
            }
            $packerEmployeeUuid = $viewerEmployeeUuid;
            $phase = $request->query->getString('preparationPhase');
            if (in_array($phase, ['pending', 'confirmed'], true)) {
                $preparationPhase = $phase;
            } elseif ($phase !== '') {
                throw new BusinessRuleViolation('Unknown preparationPhase. Use pending or confirmed.');
            }
        }
        return $this->resource($this->ask(new ListEvents(PlanningPayload::page($request), $from, $to, $status ?: null, PlanningPayload::search($request), $staffEmployeeUuid, $packerEmployeeUuid, $viewerEmployeeUuid, $preparationPhase)));
    }

    #[Route('/functions', methods: ['GET'], priority: 10)]
    public function functions(): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        $labels = ['driver' => 'Kierowca', 'waiter' => 'Kelner / kelnerka', 'logistics' => 'Logistyka', 'chef' => 'Kucharz', 'coordinator' => 'Koordynator'];
        return $this->resource(['items' => array_map(static fn (EventFunction $f) => ['code' => $f->value, 'name' => $labels[$f->value]], EventFunction::cases())]);
    }

    #[Route('/staff-options', methods: ['GET'], priority: 10)]
    public function staffOptions(Request $request): JsonResponse
    {
        $this->allowChange(PermissionEnum::ASSIGN_EVENT_STAFF);
        return $this->resource(['items' => $this->ask(new SearchEventEmployees(PlanningPayload::search($request)))]);
    }

    #[Route('/{uuid}', methods: ['GET'])]
    public function get(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        return $this->eventResource(PlanningPayload::identifier($uuid));
    }

    private function eventResource(string $uuid, int $status = 200): JsonResponse
    {
        $data = $this->ask(new GetEvent($uuid));
        $data['involvement'] = $this->viewerInvolvement($data);

        return $this->resource($data, $status);
    }

    /**
     * @param array<string, mixed> $event
     * @return array{onStaff: bool, isPacker: bool, myFunctions: list<string>}
     */
    private function viewerInvolvement(array $event): array
    {
        $user = $this->getUser();
        $employeeUuid = $user instanceof \App\Module\Company\Domain\Entity\User ? $user->getEmployeeUUID() : null;
        if ($employeeUuid === null) {
            return ['onStaff' => false, 'isPacker' => false, 'myFunctions' => []];
        }
        $functions = [];
        $onStaff = false;
        foreach ($event['staff'] ?? [] as $person) {
            if (($person['employeeUuid'] ?? null) === $employeeUuid) {
                $onStaff = true;
                $functions = is_array($person['functions'] ?? null) ? $person['functions'] : [];
                break;
            }
        }
        $isPacker = ($event['preparation']['responsibleUuid'] ?? null) === $employeeUuid;

        return ['onStaff' => $onStaff, 'isPacker' => $isPacker, 'myFunctions' => $functions];
    }

    #[Route('/{uuid}', methods: ['PUT'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $this->allowChange(PermissionEnum::UPDATE);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new UpdateEvent($uuid, $body->version(), EventPayloadMapper::details($body), $this->actor()));
        return $this->eventResource($uuid);
    }

    #[Route('/{uuid}/contacts', methods: ['PUT'])]
    public function contacts(string $uuid, Request $request): JsonResponse
    {
        $this->allowChange(PermissionEnum::UPDATE);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new ReplaceEventContacts($uuid, $body->version(), EventPayloadMapper::contacts($body), $this->actor()));
        return $this->eventResource($uuid);
    }

    #[Route('/{uuid}/notification-recipients', methods: ['GET'])]
    public function notificationRecipients(string $uuid, EventNotificationAudienceResolver $audience, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        $uuid = PlanningPayload::identifier($uuid);
        $event = $em->find(CateringEvent::class, $uuid);
        if (!$event instanceof CateringEvent) {
            throw new \App\Common\Application\Exception\PlanningResourceNotFound('Event not found.');
        }

        return $this->resource($audience->describe($event));
    }

    #[Route('/{uuid}/notification-recipients', methods: ['PUT'])]
    public function updateNotificationRecipients(string $uuid, Request $request, EventNotificationAudienceResolver $audience, EntityManagerInterface $em): JsonResponse
    {
        $this->allowChange(PermissionEnum::UPDATE);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $event = $em->find(CateringEvent::class, $uuid);
        if (!$event instanceof CateringEvent) {
            throw new \App\Common\Application\Exception\PlanningResourceNotFound('Event not found.');
        }
        $excluded = array_map(
            static fn (mixed $value): string => PlanningPayload::identifier(is_string($value) ? $value : ''),
            $body->list('excludedEmployeeUuids', 200),
        );
        $extra = array_map(
            static fn (mixed $value): string => PlanningPayload::identifier(is_string($value) ? $value : ''),
            $body->list('extraEmployeeUuids', 200),
        );
        $channels = $event->notificationChannels();
        if ($body->has('channelsInherited') && $body->boolean('channelsInherited')) {
            $channels = null;
        } elseif ($body->has('channels')) {
            $channelBody = $body->object('channels');
            $channels = [
                'internal' => $channelBody->boolean('internal'),
                'email' => $channelBody->boolean('email'),
                'sms' => $channelBody->boolean('sms'),
            ];
        }
        $audience->replaceOverrides($event, $excluded, $extra, $channels);

        return $this->resource($audience->describe($event));
    }

    #[Route('/{uuid}/menu', methods: ['PUT'])]
    public function menu(string $uuid, Request $request): JsonResponse
    {
        $this->allowChange(PermissionEnum::PLAN_EVENT_MENU);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new ReplaceEventMenu($uuid, $body->version(), EventPayloadMapper::menu($body), $this->actor()));
        return $this->eventResource($uuid);
    }

    #[Route('/{uuid}/status', methods: ['POST'])]
    public function status(string $uuid, Request $request): JsonResponse
    {
        $this->allowChange(PermissionEnum::UPDATE);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $status = EventStatus::tryFrom($body->text('status')) ?? throw new BusinessRuleViolation('Unknown event status.');
        if ($status === EventStatus::CONFIRMED) {
            $this->denyAccessUnlessGranted(PermissionEnum::CONFIRM_EVENT, AccessEnum::EVENTS);
        }
        if ($status === EventStatus::CANCELLED) {
            $this->denyAccessUnlessGranted(PermissionEnum::DELETE, AccessEnum::EVENTS);
        }
        $this->execute(new ChangeEventStatus($uuid, $body->version(), $status, $this->actor()));
        return $this->eventResource($uuid);
    }

    #[Route('/{uuid}/staff/{employeeUuid}', methods: ['PUT'])]
    public function assignStaff(string $uuid, string $employeeUuid, Request $request): JsonResponse
    {
        $this->allowChange(PermissionEnum::ASSIGN_EVENT_STAFF);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $functions = array_map(static function ($value): EventFunction {
            if (!is_string($value) || !($function = EventFunction::tryFrom($value))) {
                throw new BusinessRuleViolation('Unknown event function.');
            }
            return $function;
        }, $body->list('functions', count(EventFunction::cases())));
        $this->execute(new AssignEventStaff($uuid, $body->version(), PlanningPayload::identifier($employeeUuid), $functions, $body->dateTime('startsAt', true), $body->dateTime('endsAt', true), $body->text('notes', false), $this->actor()));
        return $this->eventResource($uuid);
    }

    #[Route('/{uuid}/staff/{employeeUuid}', methods: ['DELETE'])]
    public function removeStaff(string $uuid, string $employeeUuid, Request $request): JsonResponse
    {
        $this->allowChange(PermissionEnum::ASSIGN_EVENT_STAFF);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new RemoveEventStaff($uuid, $body->version(), PlanningPayload::identifier($employeeUuid), $this->actor()));
        return $this->eventResource($uuid);
    }

    #[Route('/{uuid}/equipment/{equipmentUuid}', methods: ['PUT'])]
    public function equipment(string $uuid, string $equipmentUuid, Request $request): JsonResponse
    {
        $this->allowChange(PermissionEnum::PLAN_EVENT_EQUIPMENT);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new SetEventEquipment($uuid, $body->version(), PlanningPayload::identifier($equipmentUuid), $body->integer('quantity'), $body->text('notes', false), $this->actor()));
        return $this->eventResource($uuid);
    }

    #[Route('/{uuid}/equipment/{equipmentUuid}', methods: ['DELETE'])]
    public function removeEquipment(string $uuid, string $equipmentUuid, Request $request): JsonResponse
    {
        $this->allowChange(PermissionEnum::PLAN_EVENT_EQUIPMENT);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new RemoveEventEquipment($uuid, $body->version(), PlanningPayload::identifier($equipmentUuid), $this->actor()));
        return $this->eventResource($uuid);
    }

    #[Route('/{uuid}/history', methods: ['GET'])]
    public function history(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        return $this->resource($this->ask(new GetEventHistory(PlanningPayload::identifier($uuid), PlanningPayload::page($request))));
    }

    #[Route('/{uuid}/attachments', methods: ['POST'])]
    public function upload(string $uuid, Request $request, EventMediaStorage $storage): JsonResponse
    {
        $this->allowChange(PermissionEnum::ADD_EVENT_MEDIA);
        $uuid = PlanningPayload::identifier($uuid);
        $version = $request->request->getString('version');
        if (!ctype_digit($version) || (int) $version < 1) {
            throw new BusinessRuleViolation('A positive version is required.');
        }
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BusinessRuleViolation('Multipart file field is required.');
        }
        $description = $request->request->getString('description');
        if (mb_strlen($description) > 4000) {
            throw new BusinessRuleViolation('Description exceeds 4000 characters.');
        }
        $stored = $storage->store($file);
        try {
            $this->execute(new AttachEventMedia($uuid, (int) $version, $stored['fileName'], $stored['storedName'], $stored['extension'], $stored['mimeType'], $stored['size'], $description ?: null, $this->actor()));
        } catch (\Throwable $error) {
            $storage->discard($stored['storedName']);
            throw $error;
        }
        return $this->eventResource($uuid, 201);
    }

    #[Route('/{uuid}/attachments/{attachmentUuid}', methods: ['GET'])]
    public function download(string $uuid, string $attachmentUuid, EventMediaStorage $storage): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EVENTS);
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW_EVENT_MEDIA, AccessEnum::EVENTS);
        $media = $this->ask(new GetEventMedia(PlanningPayload::identifier($uuid), PlanningPayload::identifier($attachmentUuid)));
        $response = new BinaryFileResponse($storage->path($media['file_path']));
        $response->headers->set('Content-Type', $media['mime_type']);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $media['file_name'], 'event-media');
        return $response;
    }

    #[Route('/{uuid}/attachments/{attachmentUuid}', methods: ['DELETE'])]
    public function removeMedia(string $uuid, string $attachmentUuid, Request $request): JsonResponse
    {
        $this->allowChange(PermissionEnum::DELETE_EVENT_MEDIA);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new RemoveEventMedia($uuid, $body->version(), PlanningPayload::identifier($attachmentUuid), $this->actor()));
        return $this->eventResource($uuid);
    }
}
