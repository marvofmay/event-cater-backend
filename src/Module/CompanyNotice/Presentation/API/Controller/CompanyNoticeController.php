<?php

declare(strict_types=1);

namespace App\Module\CompanyNotice\Presentation\API\Controller;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Company\Domain\Entity\User;
use App\Module\CompanyNotice\Application\CompanyNoticeService;
use App\Module\CompanyNotice\Infrastructure\Storage\CompanyNoticeMediaStorage;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/company-notices', requirements: ['uuid' => '[0-9a-fA-F-]{36}', 'imageUuid' => '[0-9a-fA-F-]{36}'])]
final class CompanyNoticeController extends PlanningController
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
            $name = trim(($employee->getFirstName() ?? '').' '.($employee->getLastName() ?? ''));
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

    private function requireLogin(): void
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
    }

    #[Route('', methods: ['GET'])]
    public function list(CompanyNoticeService $notices): JsonResponse
    {
        // Tablica /info: każdy zalogowany użytkownik tenanta może czytać ogłoszenia.
        $this->requireLogin();

        return $this->resource(['items' => $notices->list()]);
    }

    #[Route('/{uuid}', methods: ['GET'])]
    public function get(string $uuid, CompanyNoticeService $notices): JsonResponse
    {
        $this->requireLogin();

        return $this->resource($notices->get(PlanningPayload::identifier($uuid)));
    }

    #[Route('/{uuid}/images/{imageUuid}', methods: ['GET'])]
    public function image(string $uuid, string $imageUuid, CompanyNoticeService $notices, CompanyNoticeMediaStorage $storage): BinaryFileResponse
    {
        $this->requireLogin();
        $image = $notices->image(PlanningPayload::identifier($uuid), PlanningPayload::identifier($imageUuid));
        $response = new BinaryFileResponse($storage->path($image->storedName()));
        $response->headers->set('Content-Type', $image->mimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $image->fileName(), 'notice-image');

        return $response;
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, CompanyNoticeService $notices): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::COMPANY_NOTICES);
        $user = $this->actorUser();
        $cover = $request->files->get('cover');
        if (!$cover instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            throw new BusinessRuleViolation('Zdjęcie wiodące jest wymagane.');
        }
        $galleryFiles = $request->files->get('gallery', []);
        if (!is_array($galleryFiles)) {
            $galleryFiles = $galleryFiles ? [$galleryFiles] : [];
        }
        $gallery = array_values(array_filter(
            $galleryFiles,
            static fn ($file) => $file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile,
        ));

        return $this->resource($notices->create(
            (string) $request->request->get('title', ''),
            (string) $request->request->get('body', ''),
            $cover,
            $gallery,
            $user->getUUID()->toString(),
            $this->actorName($user),
        ), 201);
    }

    #[Route('/{uuid}', methods: ['POST'])]
    public function update(string $uuid, Request $request, CompanyNoticeService $notices): JsonResponse
    {
        // multipart update uses POST (many clients cannot send files with PUT)
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::COMPANY_NOTICES);
        $cover = $request->files->get('cover');
        $coverFile = $cover instanceof \Symfony\Component\HttpFoundation\File\UploadedFile ? $cover : null;
        $galleryFiles = $request->files->get('gallery', []);
        if (!is_array($galleryFiles)) {
            $galleryFiles = $galleryFiles ? [$galleryFiles] : [];
        }
        $gallery = array_values(array_filter(
            $galleryFiles,
            static fn ($file) => $file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile,
        ));
        $remove = $request->request->all('removeImageUuids');
        if ($remove === []) {
            $single = $request->request->all()['removeImageUuids'] ?? null;
            $remove = is_array($single) ? $single : [];
        }
        $remove = array_values(array_filter(array_map(static fn ($value) => is_string($value) ? $value : '', $remove)));

        return $this->resource($notices->update(
            PlanningPayload::identifier($uuid),
            (string) $request->request->get('title', ''),
            (string) $request->request->get('body', ''),
            $coverFile,
            $gallery,
            $remove,
        ));
    }

    #[Route('/{uuid}', methods: ['DELETE'])]
    public function delete(string $uuid, CompanyNoticeService $notices): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::DELETE, AccessEnum::COMPANY_NOTICES);
        $notices->delete(PlanningPayload::identifier($uuid));

        return $this->resource(['deleted' => true]);
    }
}
