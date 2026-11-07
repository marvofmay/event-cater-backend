<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Presentation\API\Controller\Event;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Module\System\Application\Event\LogFileEvent;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use App\Module\System\Notification\Application\Command\Event\UpdateNotificationEventChannelsCommand;
use App\Module\System\Notification\Domain\DTO\Event\UpdateNotificationEventChannelsDTO;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class UpdateNotificationEventChannelsController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
        private readonly MessageService $messageService,
    ) {
    }

    #[Route(
        '/api/settings/notification-events/{eventName}/channels',
        name: 'api.settings.notification-events.channels.update',
        methods: ['PUT'],
        requirements: ['eventName' => '[A-Za-z0-9_]+'],
    )]
    public function update(string $eventName, #[MapRequestPayload] UpdateNotificationEventChannelsDTO $dto): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted(
                PermissionEnum::SETTINGS,
                AccessEnum::NOTIFICATION_TEMPLATES,
                $this->messageService->get('accessDenied')
            );

            try {
                $this->commandBus->dispatch(new UpdateNotificationEventChannelsCommand($eventName, $dto->channels));
            } catch (HandlerFailedException $exception) {
                throw $exception->getPrevious() ?? $exception;
            }

            return new JsonResponse(
                ['message' => $this->messageService->get('notification.events.channels.update.success', [], 'notifications')],
                Response::HTTP_OK,
            );
        } catch (\Throwable $exception) {
            $message = sprintf(
                '%s %s',
                $this->messageService->get('notification.events.channels.update.error', [], 'notifications'),
                $exception->getMessage()
            );
            $this->eventBus->dispatch(new LogFileEvent($message, LogLevel::ERROR, MonologChannelEnum::EVENT_LOG));
            $code = match (true) {
                $exception instanceof AccessDeniedException => $this->getUser() === null ? Response::HTTP_UNAUTHORIZED : Response::HTTP_FORBIDDEN,
                $exception instanceof PlanningResourceNotFound => Response::HTTP_NOT_FOUND,
                $exception instanceof BusinessRuleViolation => Response::HTTP_UNPROCESSABLE_ENTITY,
                default => Response::HTTP_BAD_REQUEST,
            };

            return new JsonResponse(['message' => $message], $code);
        }
    }
}
