<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Role;

use App\Module\Company\Domain\Service\Role\RoleAuthorizationUpdater;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateRoleAuthorizationController extends AbstractController
{
    #[Route('/api/roles/{uuid}/accesses', methods: ['PUT'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function __invoke(string $uuid, Request $request, RoleAuthorizationUpdater $updater): JsonResponse
    {
        if (!$this->isGranted(PermissionEnum::ASSIGN_ACCESS_TO_ROLE, AccessEnum::ROLES) && !$this->isGranted(PermissionEnum::ASSIGN_PERMISSION_TO_ACCESS_ROLE, AccessEnum::ROLES)) {
            throw new AccessDeniedHttpException('Brak uprawnień do zarządzania dostępem roli.');
        }
        $updater->update($uuid, $request->toArray()['accesses'] ?? null);

        return $this->json(['message' => 'Dostępy i uprawnienia roli zostały zapisane.']);
    }
}
