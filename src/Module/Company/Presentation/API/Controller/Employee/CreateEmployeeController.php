<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Employee;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Command\Employee\CreateEmployeeCommand;
use App\Module\Company\Application\DTO\Employee\CreateDTO;
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
final class CreateEmployeeController extends AbstractController
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
    #[Route(path: '/api/employees', name: 'api.employees.create', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreateDTO $createDTO): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::CREATE,
            AccessEnum::EMPLOYEES,
            $this->messageService->get('accessDenied')
        );

        try {
            $this->commandBus->dispatch(new CreateEmployeeCommand(
                $createDTO->companyUUID,
                $createDTO->departmentUUID,
                $createDTO->positionUUID,
                $createDTO->contractTypeUUID,
                $createDTO->roleUUID,
                $createDTO->parentEmployeeUUID,
                $createDTO->externalCode,
                $createDTO->internalCode,
                $createDTO->email,
                $createDTO->firstName,
                $createDTO->lastName,
                $createDTO->pesel,
                $createDTO->employmentFrom,
                $createDTO->employmentTo,
                $createDTO->active,
                $createDTO->phones,
                $createDTO->address
            ));
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        return new JsonResponse(
            ['message' => $this->messageService->get('employee.add.success', [], 'employees')],
            Response::HTTP_CREATED
        );
    }
}
