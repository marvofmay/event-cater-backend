<?php

declare(strict_types=1);

namespace App\Module\Note\Presentation\API\Controller;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Note\Application\Query\GetNotePDFQuery;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[ErrorChannel(MonologChannelEnum::EVENT_LOG)]
final class NotePDFController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageService $messageService,
    ) {
    }

    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    #[Route(path: '/api/users/notes/{uuid}/pdf', name: 'api.users.note.pdf', methods: ['GET'])]
    public function __invoke(string $uuid): Response
    {
        $this->denyAccessUnlessGranted(
            attribute: PermissionEnum::PDF,
            subject: AccessEnum::NOTES,
            message: $this->messageService->get('accessDenied')
        );

        try {
            $pdf = $this->queryBus->dispatch(new GetNotePDFQuery($uuid))->last(HandledStamp::class)->getResult();
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }

        return new Response(
            content: $pdf,
            status: Response::HTTP_OK,
            headers: [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="notes.pdf"',
            ]
        );
    }
}
