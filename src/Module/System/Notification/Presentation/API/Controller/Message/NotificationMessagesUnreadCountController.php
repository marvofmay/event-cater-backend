<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Presentation\API\Controller\Message;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use App\Module\System\Notification\Application\Query\Message\NotificationMessagesUnreadCountQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[ErrorChannel(MonologChannelEnum::EVENT_LOG)]
final class NotificationMessagesUnreadCountController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'query.bus')] private readonly MessageBusInterface $queryBus,
        private readonly MessageService $messageService,
    ) {
    }

    #[Route('/api/notification-messages/unread-count', name: 'api.notification-messages.unread-count', methods: ['GET'])]
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::NOTIFICATION_MESSAGES, $this->messageService->get('accessDenied'));

        try {
            $stamp = $this->queryBus->dispatch(new NotificationMessagesUnreadCountQuery())->last(HandledStamp::class);
            $data = ['unreadCount' => $stamp->getResult()];
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious();
        }

        return new JsonResponse(['data' => $data], Response::HTTP_OK);
    }
}
