<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Position;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Command\Position\DeleteMultiplePositionsCommand;
use App\Module\Company\Application\DTO\Position\DeleteMultipleDTO;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
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
final class DeleteMultiplePositionsController extends AbstractController
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
    #[Route(path: '/api/positions/multiple', name: 'api.positions.delete_multiple', methods: ['DELETE'])]
    public function __invoke(#[MapRequestPayload] DeleteMultipleDTO $deleteMultipleDTO): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::DELETE,
            AccessEnum::POSITIONS,
            $this->messageService->get('accessDenied')
        );

        try {
            $this->commandBus->dispatch(new DeleteMultiplePositionsCommand($deleteMultipleDTO->positionsUUIDs));
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        return new JsonResponse(
            ['message' => $this->messageService->get('position.delete.multiple.success', [], 'positions')],
            Response::HTTP_OK
        );
    }
}
