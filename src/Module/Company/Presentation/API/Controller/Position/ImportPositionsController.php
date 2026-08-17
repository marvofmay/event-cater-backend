<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Position;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Facade\ImportPositionsFacade;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;

#[ErrorChannel(MonologChannelEnum::EVENT_LOG)]
final class ImportPositionsController extends AbstractController
{
    public function __construct(
        private readonly ImportPositionsFacade $importPositionsFacade,
        private readonly MessageService $messageService
    ) {
    }

    #[Route(path: '/api/positions/import', name: 'api.positions.import', methods: ['POST'])]
    public function __invoke(#[MapUploadedFile] ?UploadedFile $file): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::IMPORT,
            AccessEnum::POSITIONS,
            $this->messageService->get('accessDenied')
        );

        if (!$file) {
            return new JsonResponse(
                ['message' => $this->messageService->get('position.import.file.required', [], 'positions')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $result = $this->importPositionsFacade->import($file);

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
