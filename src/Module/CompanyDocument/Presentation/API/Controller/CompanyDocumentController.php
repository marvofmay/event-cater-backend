<?php

declare(strict_types=1);

namespace App\Module\CompanyDocument\Presentation\API\Controller;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Company\Domain\Entity\User;
use App\Module\CompanyDocument\Application\CompanyDocumentService;
use App\Module\CompanyDocument\Domain\Entity\CompanyDocument;
use App\Module\CompanyDocument\Infrastructure\Storage\CompanyDocumentStorage;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/company-documents', requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
final class CompanyDocumentController extends PlanningController
{
    private function actorUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function actorName(User $user): string
    {
        $employee = $user->getEmployee();
        if ($employee !== null) {
            $name = trim($employee->getFirstName().' '.$employee->getLastName());
            if ($name !== '') {
                return $name;
            }
        }
        if ($user->getSaasAdministratorUuid() !== null) {
            return 'Administrator techniczny SaaS';
        }
        if ($user->isTenantAdministrator()) {
            return 'Administrator firmy';
        }

        return $user->getUserIdentifier();
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request, CompanyDocumentService $documents): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->resource([
            'items' => $documents->list($request->query->getString('category', 'all')),
            'categories' => CompanyDocument::CATEGORIES,
        ]);
    }

    #[Route('/{uuid}/file', methods: ['GET'])]
    public function download(string $uuid, CompanyDocumentService $documents, CompanyDocumentStorage $storage): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $document = $documents->get(PlanningPayload::identifier($uuid));
        $response = new BinaryFileResponse($storage->path($document->storedName()));
        $response->headers->set('Content-Type', $document->mimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document->fileName(), 'company-document');

        return $response;
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, CompanyDocumentService $documents): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::DOCUMENTS);
        $user = $this->actorUser();
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BusinessRuleViolation('Plik dokumentu jest wymagany.');
        }

        $description = $request->request->get('description');

        return $this->resource($documents->create(
            (string) $request->request->get('title', ''),
            is_string($description) ? $description : null,
            (string) $request->request->get('category', 'other'),
            $file,
            $user->getUUID()->toString(),
            $this->actorName($user),
        ), 201);
    }

    #[Route('/{uuid}', methods: ['POST'])]
    public function update(string $uuid, Request $request, CompanyDocumentService $documents): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::DOCUMENTS);
        $file = $request->files->get('file');
        $fileUpload = $file instanceof UploadedFile ? $file : null;

        $description = $request->request->get('description');

        return $this->resource($documents->update(
            PlanningPayload::identifier($uuid),
            (string) $request->request->get('title', ''),
            is_string($description) ? $description : null,
            (string) $request->request->get('category', 'other'),
            $fileUpload,
        ));
    }

    #[Route('/{uuid}', methods: ['DELETE'])]
    public function delete(string $uuid, CompanyDocumentService $documents): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::DELETE, AccessEnum::DOCUMENTS);
        $documents->delete(PlanningPayload::identifier($uuid));

        return $this->resource(['deleted' => true]);
    }
}
