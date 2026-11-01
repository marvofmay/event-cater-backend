<?php

declare(strict_types=1);

namespace App\Module\FileManager\Presentation\API\Controller;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\FileManager\Application\FileManager;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/file-manager')]
final class FileManagerController extends AbstractController
{
    public function __construct(private readonly FileManager $files)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::FILE_MANAGER);
        return $this->json($this->files->snapshot(), headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/folders', methods: ['POST'])]
    public function folder(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::FILE_MANAGER);
        $body = PlanningPayload::json($request);
        $uuid = $this->files->create($body->uuid('parentUuid', false) ?? FileManager::ROOT, $body->text('name'), $body->version(), $this->actor());
        return $this->saved($uuid, 201);
    }

    #[Route('/upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::FILE_MANAGER);
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) throw new BusinessRuleViolation('Wybierz plik do przesłania.');
        $version = $request->request->get('version');
        if (!is_string($version) || !ctype_digit($version) || strlen($version) > 9 || (int) $version < 1) throw new BusinessRuleViolation('Podaj poprawną wersję listy.');
        $parent = $request->request->get('parentUuid');
        $name = $request->request->get('name', $file->getClientOriginalName());
        if (!is_string($name) || ($parent !== null && !is_string($parent))) throw new BusinessRuleViolation('Nieprawidłowa nazwa lub folder.');
        $uuid = $this->files->create($parent ? PlanningPayload::identifier($parent) : FileManager::ROOT, $name, (int) $version, $this->actor(), $file);
        return $this->saved($uuid, 201);
    }

    #[Route('/{uuid}/rename', requirements: ['uuid' => '[0-9a-fA-F-]{36}'], methods: ['PATCH'])]
    public function rename(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::FILE_MANAGER);
        $body = PlanningPayload::json($request);
        $this->files->rename(PlanningPayload::identifier($uuid), $body->text('name'), $body->version(), $this->actor());
        return $this->saved($uuid);
    }

    #[Route('/transfer', methods: ['POST'])]
    public function transfer(Request $request): JsonResponse
    {
        $body = PlanningPayload::json($request);
        $operation = $body->text('operation');
        if (!in_array($operation, ['copy', 'move'], true)) throw new BusinessRuleViolation('Wybierz kopiowanie lub przenoszenie.');
        $this->denyAccessUnlessGranted($operation === 'copy' ? PermissionEnum::CREATE : PermissionEnum::UPDATE, AccessEnum::FILE_MANAGER);
        if ($operation === 'copy') $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::FILE_MANAGER);
        $this->files->transfer($this->uuids($body), $body->uuid('parentUuid', false) ?? FileManager::ROOT, $operation === 'copy', $body->version(), $this->actor());
        return $this->saved();
    }

    #[Route('/delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::DELETE, AccessEnum::FILE_MANAGER);
        $body = PlanningPayload::json($request);
        $this->files->delete($this->uuids($body), $body->version());
        return $this->saved();
    }

    #[Route('/{uuid}/download', requirements: ['uuid' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function download(string $uuid): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::FILE_MANAGER);
        $file = $this->files->download(PlanningPayload::identifier($uuid));
        $response = new BinaryFileResponse($file['path']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file['name'], 'download.'.pathinfo($file['name'], PATHINFO_EXTENSION));
        $response->headers->set('Content-Type', $file['mime']);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "sandbox; default-src 'none'");
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    private function uuids(PlanningPayload $body): array
    {
        return array_map(static function (mixed $uuid): string {
            if (!is_string($uuid)) throw new BusinessRuleViolation('Nieprawidłowy identyfikator.');
            return PlanningPayload::identifier($uuid);
        }, $body->list('uuids', 100));
    }

    private function actor(): string
    {
        return $this->getUser()->getUUID()->toString();
    }

    private function saved(?string $uuid = null, int $status = 200): JsonResponse
    {
        return $this->json(['uuid' => $uuid, 'message' => 'Zapisano zmiany.'], $status, ['Cache-Control' => 'private, no-store']);
    }
}
