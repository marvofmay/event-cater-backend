<?php

declare(strict_types=1);

namespace App\Module\System\Presentation\API\Controller;

use App\Module\Company\Domain\Entity\User;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use App\Module\System\Infrastructure\Preference\PreferenceService;
use App\Module\System\Infrastructure\Preference\DashboardPreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class PreferenceController extends AbstractController
{
    private function access(string $resource): AccessEnum
    {
        return match ($resource) {
            'companies' => AccessEnum::COMPANIES, 'departments' => AccessEnum::DEPARTMENTS, 'employees' => AccessEnum::EMPLOYEES,
            'positions' => AccessEnum::POSITIONS, 'roles' => AccessEnum::ROLES, 'contractTypes' => AccessEnum::CONTRACT_TYPES,
            'industries' => AccessEnum::INDUSTRIES, 'notifications' => AccessEnum::NOTIFICATIONS,
            default => throw new NotFoundHttpException(),
        };
    }

    #[Route('/api/preferences', name: 'api.preferences.effective', methods: ['GET'])]
    public function effective(PreferenceService $preferences, DashboardPreferenceService $dashboard): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        $sizes = $preferences->pageSizes($user->getUUID()->toString());
        foreach ($sizes as $resource => $size) {
            if (!$this->isGranted(PermissionEnum::LIST, $this->access($resource))) {
                unset($sizes[$resource]);
            }
        }

        return $this->json(['data' => ['pageSizes' => $sizes, 'dashboardOrder' => $dashboard->snapshot($user->getUUID()->toString())['order']]], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/preferences/dashboard', name: 'api.preferences.dashboard', methods: ['GET', 'PUT'], priority: 10)]
    public function dashboard(Request $request, DashboardPreferenceService $preferences): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $userUUID = $this->getUser()->getUUID()->toString();
        if ($request->isMethod('PUT')) $preferences->save($userUUID, $request->toArray());

        return $this->json(['data' => $preferences->snapshot($userUUID), 'message' => 'Zapisano układ pulpitu.'], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/preferences/{resource}', name: 'api.preferences.resource', methods: ['GET', 'PUT'])]
    public function resource(string $resource, Request $request, PreferenceService $preferences): JsonResponse
    {
        $access = $this->access($resource);
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        $roleName = $user instanceof User ? $user->getEmployee()?->getRole()?->getName() : null;
        $admin = $user instanceof User && ($user->isTenantAdministrator() || $roleName === 'Superadministrator');
        $canConfigureList = $this->isGranted(PermissionEnum::LIST, $access);
        if ($resource !== 'notifications') {
            $this->denyAccessUnlessGranted(PermissionEnum::LIST, $access);
        }
        if ($request->isMethod('PUT')) {
            $input = $request->toArray();
            if (!in_array($input['scope'] ?? null, ['tenant', 'user'], true)) {
                throw new BadRequestHttpException('Wybierz zakres ustawień.');
            }
            if ('tenant' === $input['scope'] && !$admin) {
                throw $this->createAccessDeniedException('Tylko administrator firmy może zmieniać ustawienia nadrzędne.');
            }
            if ('tenant' === $input['scope']) $this->denyAccessUnlessGranted(PermissionEnum::LIST, $access);
            if ('user' === $input['scope'] && !$canConfigureList && ($input['pageSize'] ?? null) !== null) {
                throw $this->createAccessDeniedException('Nie masz uprawnienia do ustawień tej listy.');
            }
            $preferences->save($resource, 'tenant' === $input['scope'] ? 'tenant' : $user->getUUID()->toString(), $input);
        }

        return $this->json(['data' => $preferences->snapshot($resource, $user->getUUID()->toString(), $admin), 'message' => 'Zapisano ustawienia.'], headers: ['Cache-Control' => 'private, no-store']);
    }
}
