<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Presentation\API\Controller\Message;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use App\Module\System\Notification\Application\Command\Message\DeleteMultipleNotificationsCommand;
use App\Module\System\Notification\Application\DTO\DeleteMultipleDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[ErrorChannel(MonologChannelEnum::EVENT_LOG)]
final class DeleteMultipleNotificationMessageController extends AbstractController
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
        path: '/api/notification-messages/multiple',
        name: 'api.notification_messages.delete_multiple',
        methods: ['DELETE']
    )]
    public function __invoke(#[MapRequestPayload] DeleteMultipleDTO $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::DELETE,
            AccessEnum::NOTIFICATION_MESSAGES,
            $this->messageService->get('accessDenied')
        );

        try {
            $this->commandBus->dispatch(new DeleteMultipleNotificationsCommand($dto->notificationsUUIDs));
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious();
        }

        return new JsonResponse(
            ['message' => $this->messageService->get('note.delete.multiple.success', [], 'notes')],
            Response::HTTP_OK
        );
    }
}
