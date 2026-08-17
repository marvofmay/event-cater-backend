<?php

declare(strict_types=1);

namespace App\Module\Note\Presentation\API\Controller;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Note\Application\DTO\NotesPDFQueryDTO;
use App\Module\Note\Application\Query\GetNotesPDFQuery;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[ErrorChannel(MonologChannelEnum::EVENT_LOG)]
final class NotesPDFController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'query.bus')] private readonly MessageBusInterface $queryBus,
        private readonly MessageService $messageService,
    ) {
    }

    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    #[Route(path: '/api/users/notes/pdf', name: 'api.users.notes.pdf', methods: ['GET'])]
    public function __invoke(#[MapQueryString] NotesPDFQueryDTO $dto): Response
    {
        $this->denyAccessUnlessGranted(
            attribute: PermissionEnum::PDF,
            subject:  AccessEnum::NOTES,
            message: $this->messageService->get('accessDenied')
        );

        try {
            $pdf = $this->queryBus->dispatch(new GetNotesPDFQuery($dto->uuids))
                ->last(HandledStamp::class)
                ->getResult();
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }

        return new Response(
            content: $pdf,
            status: Response::HTTP_OK,
            headers: [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="notes.pdf"',
            ]
        );
    }
}
