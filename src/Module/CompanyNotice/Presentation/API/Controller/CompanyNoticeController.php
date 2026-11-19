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

    private function canManage(): bool
    {
        return $this->isGranted(PermissionEnum::CREATE, AccessEnum::COMPANY_NOTICES)
            || $this->isGranted(PermissionEnum::UPDATE, AccessEnum::COMPANY_NOTICES)
            || $this->isGranted(PermissionEnum::DELETE, AccessEnum::COMPANY_NOTICES);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request, CompanyNoticeService $notices): JsonResponse
    {
        // Tablica /info: każdy zalogowany użytkownik tenanta widzi opublikowane ogłoszenia.
        $this->requireLogin();
        $manage = $this->canManage();

        return $this->resource(['items' => $notices->list($manage, $manage ? $request->query->getString('status') : 'published')]);
    }

    #[Route('/{uuid}', methods: ['GET'])]
    public function get(string $uuid, CompanyNoticeService $notices): JsonResponse
    {
        $this->requireLogin();

        return $this->resource($notices->get(PlanningPayload::identifier($uuid), $this->canManage()));
    }

    #[Route('/{uuid}/images/{imageUuid}', methods: ['GET'])]
    public function image(string $uuid, string $imageUuid, CompanyNoticeService $notices, CompanyNoticeMediaStorage $storage): BinaryFileResponse
    {
        $this->requireLogin();
        $image = $notices->image(PlanningPayload::identifier($uuid), PlanningPayload::identifier($imageUuid), $this->canManage());
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
            $this->publishAt($request, true),
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

        $uuid = PlanningPayload::identifier($uuid);
        $current = $notices->get($uuid, true);

        return $this->resource($notices->update(
            $uuid,
            (string) $request->request->get('title', ''),
            (string) $request->request->get('body', ''),
            $coverFile,
            $gallery,
            $remove,
            $request->request->has('publishNow') || $request->request->has('publishAt')
                ? $this->publishAt($request, false)
                : new \DateTimeImmutable($current['publishAt']),
            $request->request->has('active') ? $this->flag($request, 'active') : (bool) $current['active'],
            $request->request->has('archived') ? $this->flag($request, 'archived') : $current['archivedAt'] !== null,
        ));
    }

    private function publishAt(Request $request, bool $defaultImmediate): \DateTimeImmutable
    {
        $immediate = $this->flag($request, 'publishNow', $defaultImmediate);
        if ($immediate) {
            return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }
        $raw = trim((string) $request->request->get('publishAt', ''));
        if ($raw === '') {
            throw new BusinessRuleViolation('Podaj datę i godzinę publikacji.');
        }
        try {
            return (new \DateTimeImmutable($raw))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new BusinessRuleViolation('Data publikacji jest niepoprawna.');
        }
    }

    private function flag(Request $request, string $key, bool $default = false): bool
    {
        if (!$request->request->has($key)) {
            return $default;
        }
        $value = strtolower(trim((string) $request->request->get($key)));
        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no', 'off', ''], true)) {
            return false;
        }
        throw new BusinessRuleViolation('Pole '.$key.' musi mieć wartość tak albo nie.');
    }

    #[Route('/{uuid}', methods: ['DELETE'])]
    public function delete(string $uuid, CompanyNoticeService $notices): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::DELETE, AccessEnum::COMPANY_NOTICES);
        $notices->delete(PlanningPayload::identifier($uuid));

        return $this->resource(['deleted' => true]);
    }
}
