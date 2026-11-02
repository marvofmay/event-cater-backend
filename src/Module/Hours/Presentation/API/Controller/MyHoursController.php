<?php

declare(strict_types=1);

namespace App\Module\Hours\Presentation\API\Controller;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Company\Domain\Entity\User;
use App\Module\Hours\Application\Command\DeleteWorkHours;
use App\Module\Hours\Application\Command\ReportWorkHours;
use App\Module\Hours\Application\Command\UpdateWorkHours;
use App\Module\Hours\Application\Query\ListMyHours;
use App\Module\Hours\Domain\Entity\WorkHoursEntry;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/my-hours', requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
final class MyHoursController extends PlanningController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::LIST, AccessEnum::MY_HOURS);
        [$employeeUuid] = $this->employeeContext();
        return $this->resource($this->ask(new ListMyHours(
            $employeeUuid,
            $request->query->getString('from', (new \DateTimeImmutable('first day of this month', new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d')),
            $request->query->getString('to', (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d')),
        )));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::MY_HOURS);
        [$employeeUuid, $employeeName, $userUuid] = $this->employeeContext();
        $body = PlanningPayload::json($request);
        $uuid = $body->uuid('requestUuid', false) ?? Uuid::uuid4()->toString();
        $planned = $body->integer('plannedMinutes', false) ?? 0;
        $kind = $planned > 0 ? WorkHoursEntry::KIND_ADJUSTMENT : WorkHoursEntry::KIND_MANUAL;
        $explicitKind = $body->text('kind', false);
        if ($explicitKind !== null) {
            $kind = $explicitKind;
        }
        $this->execute(new ReportWorkHours(
            $uuid,
            $userUuid,
            $employeeUuid,
            $employeeName,
            $body->text('date') ?? '',
            $body->integer('minutes') ?? 0,
            $body->text('comment') ?? '',
            $kind,
            $body->uuid('eventUuid', false),
        ));
        return $this->resource(['uuid' => $uuid], 201);
    }

    #[Route('/{uuid}', methods: ['PUT'])]
    public function update(string $uuid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::UPDATE, AccessEnum::MY_HOURS);
        [$employeeUuid] = $this->employeeContext();
        $body = PlanningPayload::json($request);
        $this->execute(new UpdateWorkHours(
            PlanningPayload::identifier($uuid),
            $employeeUuid,
            $body->integer('minutes') ?? 0,
            $body->text('comment') ?? '',
        ));
        return $this->resource(['uuid' => $uuid]);
    }

    #[Route('/{uuid}', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::DELETE, AccessEnum::MY_HOURS);
        [$employeeUuid] = $this->employeeContext();
        $this->execute(new DeleteWorkHours(PlanningPayload::identifier($uuid), $employeeUuid));
        return $this->resource(['message' => 'Usunięto wpis godzin.']);
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function employeeContext(): array
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $employee = $user->getEmployee();
        if ($employee === null) {
            throw new BusinessRuleViolation('Konto nie jest powiązane z kartoteką pracownika.');
        }
        $name = trim($employee->getFirstName().' '.$employee->getLastName());
        return [$employee->getUUID()->toString(), $name !== '' ? $name : 'Pracownik', $user->getUUID()->toString()];
    }
}
