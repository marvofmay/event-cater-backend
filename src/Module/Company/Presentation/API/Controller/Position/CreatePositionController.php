<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Position;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Command\Position\CreatePositionCommand;
use App\Module\Company\Application\DTO\Position\CreateDTO;
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
final class CreatePositionController extends AbstractController
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
    #[Route(path: '/api/positions', name: 'api.positions.create', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreateDTO $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::CREATE,
            AccessEnum::POSITIONS,
            $this->messageService->get('accessDenied')
        );

        try {
            $this->commandBus->dispatch(new CreatePositionCommand(
                name: $dto->name,
                description: $dto->description,
                active: $dto->active,
                departmentsUUIDs: $dto->departmentsUUIDs,
            ));
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }

        return new JsonResponse(
            ['message' => $this->messageService->get('position.add.success', [], 'positions')],
            Response::HTTP_CREATED
        );
    }
}
