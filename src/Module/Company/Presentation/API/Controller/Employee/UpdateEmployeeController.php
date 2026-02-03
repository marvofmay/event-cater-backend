<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Employee;

use App\Common\Domain\Enum\MonologChanelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Command\Employee\UpdateEmployeeCommand;
use App\Module\Company\Application\DTO\Employee\UpdateDTO;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[ErrorChannel(MonologChanelEnum::EVENT_LOG)]
final class UpdateEmployeeController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
        private readonly MessageService $messageService,
    ) {
    }

    #[Route('/api/employees/{uuid}', name: 'api.employee.update', requirements: ['uuid' => '[0-9a-fA-F-]{36}'], methods: ['PUT'])]
    public function __invoke(string $uuid, #[MapRequestPayload] UpdateDTO $updateDTO): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::EMPLOYEES, $this->messageService->get('accessDenied'));

        try {
            $this->commandBus->dispatch(
                new UpdateEmployeeCommand(
                    $uuid,
                    $updateDTO->companyUUID,
                    $updateDTO->departmentUUID,
                    $updateDTO->positionUUID,
                    $updateDTO->contractTypeUUID,
                    $updateDTO->roleUUID,
                    $updateDTO->parentEmployeeUUID,
                    $updateDTO->externalCode,
                    $updateDTO->internalCode,
                    $updateDTO->email,
                    $updateDTO->firstName,
                    $updateDTO->lastName,
                    $updateDTO->pesel,
                    $updateDTO->employmentFrom,
                    $updateDTO->employmentTo,
                    $updateDTO->active,
                    $updateDTO->phones,
                    $updateDTO->address
                )
            );
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious();
        }

        return new JsonResponse(
            ['message' => $this->messageService->get('employee.update.success', [], 'employees')],
            Response::HTTP_OK
        );
    }
}
