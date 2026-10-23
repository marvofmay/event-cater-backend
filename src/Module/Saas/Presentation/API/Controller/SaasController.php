<?php

declare(strict_types=1);

namespace App\Module\Saas\Presentation\API\Controller;

use App\Module\Saas\Application\SaasService;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[AutoconfigureTag('controller.service_arguments')]
#[Route('/api/saas')]
final readonly class SaasController
{
    public function __construct(private SaasService $service)
    {
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $r): JsonResponse
    {
        $data = $r->toArray();

        return new JsonResponse($this->service->login((string) ($data['email'] ?? ''), (string) ($data['password'] ?? ''), $r->getClientIp() ?? ''));
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $r): JsonResponse
    {
        $this->service->logout($r);

        return new JsonResponse(null, 204);
    }

    #[Route('/tenants', methods: ['GET'])]
    public function list(Request $r): JsonResponse
    {
        $this->service->authenticate($r);

        return new JsonResponse(['items' => $this->service->list()]);
    }

    #[Route('/tenants', methods: ['POST'])]
    public function create(Request $r): JsonResponse
    {
        $actor = $this->service->authenticate($r);

        return new JsonResponse($this->service->create($r->toArray(), $actor), 201);
    }

    #[Route('/tenants/{uuid}', methods: ['GET'])]
    public function detail(Request $r, string $uuid): JsonResponse
    {
        $this->service->authenticate($r);

        return new JsonResponse($this->service->detail($uuid));
    }

    #[Route('/tenants/{uuid}/status', methods: ['PATCH'])]
    public function status(Request $r, string $uuid): JsonResponse
    {
        $actor = $this->service->authenticate($r);

        return new JsonResponse($this->service->changeStatus($uuid, $r->toArray(), $actor));
    }
}
