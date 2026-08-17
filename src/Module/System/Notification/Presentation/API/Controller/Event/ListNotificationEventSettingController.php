<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Presentation\API\Controller\Event;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\System\Application\Event\LogFileEvent;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use App\Module\System\Notification\Application\Query\Event\ListNotificationEventSettingQuery;
use App\Module\System\Notification\Domain\DTO\Event\ListNotificationEventSettingQueryDTO;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class ListNotificationEventSettingController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[Autowire(service: 'query.bus')] private readonly MessageBusInterface $queryBus,
        private readonly MessageService $messageService,
    ) {
    }

    #[Route('/api/settings/notification-events', name: 'api.settings.notification-events.list', methods: ['GET'])]
    public function list(#[MapQueryString] ListNotificationEventSettingQueryDTO $dto): Response
    {
        try {
            $this->denyAccessUnlessGranted(
                PermissionEnum::LIST,
                AccessEnum::NOTIFICATION_EVENTS,
                $this->messageService->get('accessDenied')
            );

            $data = $this->dispatchQuery($dto);

            return $this->successResponse($data);
        } catch (\Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function dispatchQuery(ListNotificationEventSettingQueryDTO $dto): array
    {
        try {
            $handledStamp = $this->queryBus->dispatch(new ListNotificationEventSettingQuery($dto));

            return $handledStamp->last(HandledStamp::class)->getResult();
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious();
        }
    }

    private function successResponse(array $data): JsonResponse
    {
        return new JsonResponse(['data' => $data], Response::HTTP_OK);
    }

    private function errorResponse(\Throwable $exception): JsonResponse
    {
        $message = sprintf(
            '%s. %s',
            $this->messageService->get('notification.events.list.error', [], 'notifications'),
            $exception->getMessage()
        );

        $this->eventBus->dispatch(new LogFileEvent($message, LogLevel::ERROR, MonologChannelEnum::EVENT_LOG));

        $code = $exception->getCode() ?: Response::HTTP_BAD_REQUEST;

        return new JsonResponse(['message' => $message], $code);
    }
}
