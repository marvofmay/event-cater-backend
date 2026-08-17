<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Presentation\API\Controller\Event;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\System\Application\Event\LogFileEvent;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use App\Module\System\Notification\Application\Command\Event\UpdateNotificationEventSettingsCommand;
use App\Module\System\Notification\Domain\DTO\Event\UpdateNotificationEventSettingDTO;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class UpdateNotificationEventSettingsController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
        private readonly MessageService $messageService,
    ) {
    }

    #[Route('/api/settings/notification-events', name: 'api.settings.notification-events.update', methods: ['PUT'])]
    public function create(#[MapRequestPayload] UpdateNotificationEventSettingDTO $dto): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted(
                PermissionEnum::SETTINGS,
                AccessEnum::NOTIFICATION_EVENTS,
                $this->messageService->get('accessDenied')
            );

            $this->dispatchCommand($dto);

            return $this->successResponse();
        } catch (\Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function dispatchCommand(UpdateNotificationEventSettingDTO $dto): void
    {
        try {
            $this->commandBus->dispatch(new UpdateNotificationEventSettingsCommand(eventNames: $dto->eventNames));
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious();
        }
    }

    private function successResponse(): JsonResponse
    {
        return new JsonResponse(
            ['message' => $this->messageService->get('notification.events.update.success', [], 'notifications')],
            Response::HTTP_CREATED
        );
    }

    private function errorResponse(\Throwable $exception): JsonResponse
    {
        $message = sprintf(
            '%s %s',
            $this->messageService->get('notification.events.update.error', [], 'notifications'),
            $exception->getMessage()
        );

        $this->eventBus->dispatch(new LogFileEvent($message, LogLevel::ERROR, MonologChannelEnum::EVENT_LOG));

        $code = $exception->getCode() ?: Response::HTTP_BAD_REQUEST;

        return new JsonResponse(['message' => $message], $code);
    }
}
