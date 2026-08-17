<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Presentation\API\Controller\Message;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use App\Module\System\Notification\Application\Command\Message\MarkAsReadNotificationCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[ErrorChannel(MonologChannelEnum::EVENT_LOG)]
final class MarkNotificationMessageAsReadController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
        private readonly MessageService $messageService,
    ) {
    }

    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    #[Route(
        path: '/api/notification-messages/{uuid}/read',
        name: 'api.notification_message.mark_as_read',
        requirements: ['uuid' => '[0-9a-fA-F-]{36}'],
        methods: ['PATCH']
    )]
    public function __invoke(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            attribute: PermissionEnum::VIEW,
            subject: AccessEnum::NOTIFICATION_MESSAGES,
            message: $this->messageService->get('accessDenied')
        );

        try {
            $this->commandBus->dispatch(new MarkAsReadNotificationCommand($uuid));
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }

        return new JsonResponse(
            data: ['message' => $this->messageService->get(
                key: 'notification.markAsRead.success',
                domain: 'notifications'
            )],
            status: Response::HTTP_OK
        );
    }
}
