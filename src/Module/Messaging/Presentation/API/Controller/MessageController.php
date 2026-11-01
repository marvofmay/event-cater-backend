<?php

declare(strict_types=1);

namespace App\Module\Messaging\Presentation\API\Controller;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Messaging\Application\Command\MarkMessageRead;
use App\Module\Messaging\Application\Command\SendMessage;
use App\Module\Messaging\Application\Query\GetMessage;
use App\Module\Messaging\Application\Query\ListMessages;
use App\Module\Messaging\Application\Query\SearchMessageRecipients;
use App\Module\Messaging\Application\Query\UnreadMessageCount;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/messages', requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
final class MessageController extends PlanningController
{
    #[Route('', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::MESSAGES);
        $body = PlanningPayload::json($request);
        $uuid = $body->uuid('requestUuid', false) ?? Uuid::uuid4()->toString();
        $recipients = array_map(static function (mixed $value): string {
            if (!is_string($value)) {
                throw new BusinessRuleViolation('Nieprawidłowy odbiorca wiadomości.');
            }
            return PlanningPayload::identifier($value);
        }, $body->list('recipientUuids', 100));
        $this->execute(new SendMessage($uuid, $this->actor(), $body->text('title'), $body->text('content'), $recipients, $body->uuid('replyToUuid', false)));
        return $this->resource(['uuid' => $uuid], 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::MESSAGES);
        $unread = $request->query->getString('unread', 'false');
        if (!in_array($unread, ['true', 'false'], true)) {
            throw new BusinessRuleViolation('Nieprawidłowy filtr nieprzeczytanych wiadomości.');
        }
        return $this->resource($this->ask(new ListMessages($this->actor(), PlanningPayload::page($request), $request->query->getString('folder', 'inbox'), $unread === 'true', PlanningPayload::search($request), $request->query->getString('sortBy', 'createdAt'), $request->query->getString('sortDirection', 'desc'))));
    }

    #[Route('/delete', methods: ['POST'], priority: 10)]
    public function deleteMany(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::DELETE, AccessEnum::MESSAGES);
        $body = PlanningPayload::json($request);
        $uuids = array_map(static function (mixed $uuid): string {
            if (!is_string($uuid)) throw new BusinessRuleViolation('Nieprawidłowy identyfikator wiadomości.');
            return PlanningPayload::identifier($uuid);
        }, $body->list('uuids', 100));
        $this->execute(new \App\Module\Messaging\Application\Command\DeleteMessages($uuids, $this->actor(), $body->text('folder')));
        return $this->resource(['message' => 'Usunięto wiadomości z Twojej skrzynki.']);
    }

    #[Route('/{uuid}', methods: ['DELETE'])]
    public function delete(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::DELETE, AccessEnum::MESSAGES);
        $this->execute(new \App\Module\Messaging\Application\Command\DeleteMessages([PlanningPayload::identifier($uuid)], $this->actor(), $request->query->getString('folder', 'inbox')));
        return $this->resource(['message' => 'Usunięto wiadomość z Twojej skrzynki.']);
    }

    #[Route('/recipients', methods: ['GET'], priority: 10)]
    public function recipients(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::MESSAGES);
        return $this->resource($this->ask(new SearchMessageRecipients(PlanningPayload::search($request))));
    }

    #[Route('/unread-count', methods: ['GET'], priority: 10)]
    public function unread(): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::MESSAGES);
        return $this->resource($this->ask(new UnreadMessageCount($this->actor())));
    }

    #[Route('/{uuid}', methods: ['GET'])]
    public function get(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::MESSAGES);
        return $this->resource($this->ask(new GetMessage(PlanningPayload::identifier($uuid), $this->actor())));
    }

    #[Route('/{uuid}/read', methods: ['PATCH'])]
    public function read(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::MESSAGES);
        $this->execute(new MarkMessageRead(PlanningPayload::identifier($uuid), $this->actor()));
        return $this->resource($this->ask(new GetMessage($uuid, $this->actor())));
    }
}
