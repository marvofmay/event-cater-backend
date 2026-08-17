<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Department;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Facade\ImportDepartmentsFacade;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;

#[ErrorChannel(MonologChannelEnum::IMPORT)]
class ImportDepartmentsController extends AbstractController
{
    public function __construct(
        private readonly ImportDepartmentsFacade $importDepartmentsFacade,
        private readonly MessageService $messageService
    ) {
    }

    #[Route(path: '/api/departments/import', name: 'api.departments.import', methods: ['POST'])]
    public function __invoke(#[MapUploadedFile] ?UploadedFile $file): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::IMPORT,
            AccessEnum::DEPARTMENTS,
            $this->messageService->get('accessDenied')
        );

        if (!$file) {
            return new JsonResponse(
                ['message' => $this->messageService->get('department.import.file.required', [], 'departments')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->importDepartmentsFacade->enqueue($file);

        return new JsonResponse([
            'success' => true,
            'message' => $this->messageService->get('department.import.enqueued', [], 'departments'),
        ], Response::HTTP_ACCEPTED);
    }
}
