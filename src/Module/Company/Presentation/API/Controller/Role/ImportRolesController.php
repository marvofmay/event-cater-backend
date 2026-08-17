<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Role;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Facade\ImportRolesFacade;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;

#[ErrorChannel(MonologChannelEnum::IMPORT)]
final class ImportRolesController extends AbstractController
{
    public function __construct(
        private readonly ImportRolesFacade $importRolesFacade,
        private readonly MessageService $messageService
    ) {
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/api/roles/import', name: 'api.roles.import', methods: ['POST'])]
    public function __invoke(#[MapUploadedFile] ?UploadedFile $file): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::IMPORT,
            AccessEnum::ROLES,
            $this->messageService->get('accessDenied')
        );

        if (!$file) {
            throw new Exception(
                $this->messageService->get('role.import.file.required', [], 'roles'),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->importRolesFacade->import($file);

        $responseData = ['message' => $result['message']];
        if (!empty($result['errors'])) {
            $responseData['errors'] = $result['errors'];
        }

        return new JsonResponse(
            $responseData,
            $result['success'] ? Response::HTTP_CREATED : Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
