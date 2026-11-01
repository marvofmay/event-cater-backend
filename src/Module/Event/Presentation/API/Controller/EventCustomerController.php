<?php

declare(strict_types=1);

namespace App\Module\Event\Presentation\API\Controller;

use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Event\Application\Command\CreateEventCustomer;
use App\Module\Event\Application\Query\GetEventCustomer;
use App\Module\Event\Application\Query\SearchEventCustomers;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class EventCustomerController extends PlanningController
{
    private function allow(bool $write): void
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $allowed = $this->isGranted(PermissionEnum::CREATE, AccessEnum::EVENTS) || $this->isGranted(PermissionEnum::UPDATE, AccessEnum::EVENTS);
        if (!$allowed && ($write || !$this->isGranted(PermissionEnum::VIEW, AccessEnum::EVENTS))) {
            throw $this->createAccessDeniedException();
        }
    }

    #[Route('/api/event-customers', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->allow(false);
        return $this->resource($this->ask(new SearchEventCustomers(PlanningPayload::search($request))));
    }

    #[Route('/api/event-customers', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->allow(true);
        $body = PlanningPayload::json($request);
        $uuid = Uuid::uuid4()->toString();
        $this->execute(new CreateEventCustomer($uuid, $body->text('name'), $body->text('nip', false), $body->text('regon', false), $body->text('krs', false), $body->text('address', false), $body->text('phone', false), $body->text('email', false), $body->text('city', false), $body->text('street', false), $body->text('postalCode', false), $body->has('country') ? $body->text('country', false) : 'Polska'));
        return $this->resource($this->ask(new GetEventCustomer($uuid)), 201);
    }
}
