<?php

declare(strict_types=1);

namespace App\Module\Equipment\Presentation\API\Controller;

use App\Common\Application\DTO\Page;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Equipment\Application\Command\ChangeEquipmentActive;
use App\Module\Equipment\Application\Command\CreateEquipmentItem;
use App\Module\Equipment\Application\Command\DeleteEquipmentItem;
use App\Module\Equipment\Application\Command\UpdateEquipmentItem;
use App\Module\Equipment\Application\Command\AddEquipmentPhoto;
use App\Module\Equipment\Application\Command\RemoveEquipmentPhoto;
use App\Module\Equipment\Application\Query\GetEquipmentPhoto;
use App\Module\Equipment\Infrastructure\Storage\EquipmentPhotoStorage;
use App\Module\Equipment\Application\Query\GetEquipmentItem;
use App\Module\Equipment\Application\Query\ListEquipmentItems;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/equipment', requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
final class EquipmentController extends PlanningController
{
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::EQUIPMENT);
        $body = PlanningPayload::json($request);
        $uuid = Uuid::uuid4()->toString();
        $this->execute(new CreateEquipmentItem($uuid, $body->text('code'), $body->text('name'), $body->text('category', false), $body->text('unit'), $body->text('description', false), $body->uuid('categoryUuid', false), $body->text('slangName', false), $body->integer('stockQuantity', false) ?? 0, $body->uuid('storageLocationUuid', false)));
        return $this->resource($this->ask(new GetEquipmentItem($uuid)), 201);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::EQUIPMENT);
        $active = match ($request->query->getString('active', 'true')) {
            'true' => true, 'false' => false, 'all' => null,
            default => throw new BusinessRuleViolation('active must be true, false or all.'),
        };
        $location = $request->query->getString('storageLocationUuid');
        return $this->resource($this->ask(new ListEquipmentItems(PlanningPayload::page($request), PlanningPayload::search($request), $active, $request->query->getString('categoryUuid') ?: ($request->query->getString('category') ?: null), $location === '' ? null : PlanningPayload::identifier($location))));
    }

    #[Route('/options', methods: ['GET'], priority: 10)]
    public function options(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::EQUIPMENT);
        $result = $this->ask(new ListEquipmentItems(new Page(1, 20), PlanningPayload::search($request)));
        return $this->resource(['items' => array_map(static fn (array $row) => array_intersect_key($row, array_flip(['uuid', 'name', 'code', 'unit', 'category', 'categoryUuid', 'slangName', 'stockQuantity', 'storageLocation', 'storageLocationUuid'])), $result['items'])]);
    }

    #[Route('/{uuid}', methods: ['GET'])]
    public function get(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EQUIPMENT);
        return $this->resource($this->ask(new GetEquipmentItem(PlanningPayload::identifier($uuid))));
    }

    #[Route('/{uuid}', methods: ['PUT'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::EQUIPMENT);
        $uuid = PlanningPayload::identifier($uuid);
        $body = PlanningPayload::json($request);
        $this->execute(new UpdateEquipmentItem($uuid, $body->version(), $body->text('code'), $body->text('name'), $body->text('category', false), $body->text('unit'), $body->text('description', false), $body->uuid('categoryUuid', false), $body->text('slangName', false), $body->integer('stockQuantity', false), $body->uuid('storageLocationUuid', false), $body->has('storageLocationUuid')));
        return $this->resource($this->ask(new GetEquipmentItem($uuid)));
    }

    #[Route('/{uuid}', methods: ['DELETE'])]
    public function delete(string $uuid, Request $request, EquipmentPhotoStorage $storage): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::DELETE, AccessEnum::EQUIPMENT);
        $body = PlanningPayload::json($request);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new DeleteEquipmentItem($uuid, $body->version()));
        $storage->discardAll($uuid);
        return $this->resource(['success' => true]);
    }

    #[Route('/{uuid}/photos', methods: ['POST'])]
    public function uploadPhoto(string $uuid, Request $request, EquipmentPhotoStorage $storage): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::EQUIPMENT);
        $uuid = PlanningPayload::identifier($uuid);
        $version = $request->request->getString('version');
        if (!ctype_digit($version) || (int) $version < 1) {
            throw new BusinessRuleViolation('Podaj aktualną wersję sprzętu.');
        }
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BusinessRuleViolation('Wybierz plik zdjęcia.');
        }
        $stored = $storage->store($uuid, $file);
        try {
            $this->execute(new AddEquipmentPhoto($uuid, (int) $version, $stored['name'], $stored['storedName'], $stored['mimeType'], $stored['size']));
        } catch (\Throwable $error) {
            $storage->discard($uuid, $stored['storedName']);
            throw $error;
        }
        return $this->resource($this->ask(new GetEquipmentItem($uuid)), 201);
    }

    #[Route('/{uuid}/photos/{photoUuid}', methods: ['GET'], requirements: ['photoUuid' => '[0-9a-fA-F-]{36}'])]
    public function photo(string $uuid, string $photoUuid, EquipmentPhotoStorage $storage): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::EQUIPMENT);
        $uuid = PlanningPayload::identifier($uuid);
        $photo = $this->ask(new GetEquipmentPhoto($uuid, PlanningPayload::identifier($photoUuid)));
        $response = new BinaryFileResponse($storage->path($uuid, $photo['stored_name']));
        $response->headers->set('Content-Type', $photo['mime_type']);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $photo['name'], 'equipment-photo');
        return $response;
    }

    #[Route('/{uuid}/photos/{photoUuid}', methods: ['DELETE'], requirements: ['photoUuid' => '[0-9a-fA-F-]{36}'])]
    public function removePhoto(string $uuid, string $photoUuid, Request $request, EquipmentPhotoStorage $storage): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::EQUIPMENT);
        $uuid = PlanningPayload::identifier($uuid);
        $photoUuid = PlanningPayload::identifier($photoUuid);
        $body = PlanningPayload::json($request);
        $photo = $this->ask(new GetEquipmentPhoto($uuid, $photoUuid));
        $this->execute(new RemoveEquipmentPhoto($uuid, $body->version(), $photoUuid));
        $storage->discard($uuid, $photo['stored_name']);
        return $this->resource($this->ask(new GetEquipmentItem($uuid)));
    }

    #[Route('/{uuid}/active', methods: ['PUT'])]
    public function active(string $uuid, Request $request): JsonResponse
    {
        $body = PlanningPayload::json($request);
        $active = $body->boolean('active');
        $this->denyAccessUnlessGranted($active ? PermissionEnum::RESTORE : PermissionEnum::DELETE, AccessEnum::EQUIPMENT);
        $uuid = PlanningPayload::identifier($uuid);
        $this->execute(new ChangeEquipmentActive($uuid, $body->version(), $active));
        return $this->resource($this->ask(new GetEquipmentItem($uuid)));
    }
}
