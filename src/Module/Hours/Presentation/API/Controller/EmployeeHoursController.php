<?php

declare(strict_types=1);

namespace App\Module\Hours\Presentation\API\Controller;

use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Hours\Application\Query\ListEmployeeHours;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/employee-hours')]
final class EmployeeHoursController extends PlanningController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::EMPLOYEE_HOURS);
        $employeeUuid = $request->query->getString('employeeUuid', '');
        return $this->resource($this->ask(new ListEmployeeHours(
            $request->query->getString('from', (new \DateTimeImmutable('first day of this month', new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d')),
            $request->query->getString('to', (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d')),
            $employeeUuid !== '' ? PlanningPayload::identifier($employeeUuid) : null,
            PlanningPayload::search($request),
        )));
    }
}
