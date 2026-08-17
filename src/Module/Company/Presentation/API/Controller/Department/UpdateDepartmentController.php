<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Department;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Command\Department\UpdateDepartmentCommand;
use App\Module\Company\Application\DTO\Department\UpdateDTO;
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

#[ErrorChannel(MonologChannelEnum::EVENT_STORE)]
final class UpdateDepartmentController extends AbstractController
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
        path: '/api/departments/{uuid}',
        name: 'api.department.update',
        requirements: ['uuid' => '[0-9a-fA-F-]{36}'],
        methods: ['PUT']
    )]
    public function __invoke(string $uuid, #[MapRequestPayload] UpdateDTO $updateDTO): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::UPDATE,
            AccessEnum::DEPARTMENTS,
            $this->messageService->get('accessDenied')
        );

        try {
            $this->commandBus->dispatch(new UpdateDepartmentCommand(
                $uuid,
                $updateDTO->name,
                $updateDTO->internalCode,
                $updateDTO->description,
                $updateDTO->active,
                $updateDTO->companyUUID,
                $updateDTO->parentDepartmentUUID,
                $updateDTO->phones,
                $updateDTO->emails,
                $updateDTO->websites,
                $updateDTO->address
            ));
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious();
        }

        return new JsonResponse(
            ['message' => $this->messageService->get('department.update.success', [], 'departments')],
            Response::HTTP_OK
        );
    }
}
