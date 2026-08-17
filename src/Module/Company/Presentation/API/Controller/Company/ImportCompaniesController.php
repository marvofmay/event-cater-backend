<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Company;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Facade\ImportCompaniesFacade;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;

#[ErrorChannel(MonologChannelEnum::IMPORT)]
final class ImportCompaniesController extends AbstractController
{
    public function __construct(
        private readonly ImportCompaniesFacade $importCompaniesFacade,
        private readonly MessageService $messageService
    ) {
    }

    #[Route(path: '/api/companies/import', name: 'api.companies.import', methods: ['POST'])]
    public function __invoke(#[MapUploadedFile] ?UploadedFile $file): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::IMPORT,
            AccessEnum::COMPANIES,
            $this->messageService->get('accessDenied')
        );

        if (!$file) {
            return new JsonResponse(
                ['message' => $this->messageService->get('company.import.file.required', [], 'companies')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->importCompaniesFacade->enqueue($file);

        return new JsonResponse([
            'message' => $this->messageService->get('company.import.enqueued', [], 'companies'),
        ], Response::HTTP_ACCEPTED);
    }
}
