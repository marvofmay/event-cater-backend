<?php

declare(strict_types=1);

namespace App\Module\Note\Presentation\API\Controller;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Note\Application\Command\DeleteNoteCommand;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
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
final class DeleteNoteController extends AbstractController
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
        path: '/api/users/notes/{uuid}',
        name: 'api.users.notes.delete',
        requirements: ['uuid' => '[0-9a-fA-F-]{36}'],
        methods: ['DELETE']
    )]
    public function __invoke(string $uuid): Response
    {
        $this->denyAccessUnlessGranted(
            attribute: PermissionEnum::DELETE,
            subject: AccessEnum::NOTES,
            message: $this->messageService->get('accessDenied')
        );

        try {
            $this->commandBus->dispatch(new DeleteNoteCommand($uuid));
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }

        return new JsonResponse(
            data: ['message' => $this->messageService->get('note.delete.success', [], 'notes')],
            status: Response::HTTP_OK
        );
    }
}
