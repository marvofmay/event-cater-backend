<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Employee;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Facade\ImportEmployeesFacade;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;

#[ErrorChannel(MonologChannelEnum::IMPORT)]
class ImportEmployeesController extends AbstractController
{
    public function __construct(
        private readonly ImportEmployeesFacade $importEmployeesFacade,
        private readonly MessageService $messageService
    ) {
    }

    #[Route(path: '/api/employees/import', name: 'api.employees.import', methods: ['POST'])]
    public function __invoke(#[MapUploadedFile] ?UploadedFile $file): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::IMPORT,
            AccessEnum::EMPLOYEES,
            $this->messageService->get('accessDenied')
        );

        if (!$file) {
            return new JsonResponse(
                ['message' => $this->messageService->get('employee.import.file.required', [], 'employees')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->importEmployeesFacade->enqueue($file);

        return new JsonResponse([
            'success' => true,
            'message' => $this->messageService->get('employee.import.enqueued', [], 'employees'),
        ], Response::HTTP_ACCEPTED);
    }
}
