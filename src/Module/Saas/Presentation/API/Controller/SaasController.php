<?php

declare(strict_types=1);

namespace App\Module\Saas\Presentation\API\Controller;

use App\Module\Saas\Application\SaasService;
use App\Module\Saas\Application\SubscriptionPaymentService;
use App\Module\Saas\Infrastructure\TpayRejectedException;
use App\Module\Saas\Infrastructure\TpayTemporaryException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AutoconfigureTag('controller.service_arguments')]
#[Route('/api/saas')]
final readonly class SaasController
{
    public function __construct(
        private SaasService $service,
        private SubscriptionPaymentService $payments,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/tenant-options', methods: ['GET'])]
    public function options(): JsonResponse
    {
        return new JsonResponse(['items' => $this->service->tenantOptions()]);
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

    #[Route('/trial', methods: ['POST'])]
    public function trial(Request $r): JsonResponse
    {
        return new JsonResponse($this->service->createTrial($r->toArray(), $r->getClientIp() ?? ''), 201);
    }

    #[Route('/payments/config', methods: ['GET'])]
    public function paymentConfig(): JsonResponse
    {
        return new JsonResponse($this->payments->config());
    }

    #[Route('/payments', methods: ['POST'])]
    public function startPayment(Request $r): JsonResponse
    {
        $data = $r->toArray();

        return new JsonResponse($this->payments->start(
            (string) ($data['slug'] ?? ''),
            (string) ($data['email'] ?? ''),
            $r->getClientIp() ?? '',
        ), 201);
    }

    #[Route('/payments/{uuid}', methods: ['GET'], requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
    public function paymentStatus(string $uuid): JsonResponse
    {
        return new JsonResponse($this->payments->status($uuid));
    }

    #[Route('/payments/tpay/notify', methods: ['POST'])]
    public function tpayNotify(Request $r): Response
    {
        try {
            $this->payments->handleNotification($r->getContent(), $r->headers->get('X-JWS-Signature'), $r->request->all());

            return new Response('TRUE', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        } catch (TpayRejectedException $error) {
            $this->logger->warning('Rejected Tpay notification.', ['reason' => $error->getMessage()]);

            return new Response('FALSE', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        } catch (TpayTemporaryException $error) {
            $this->logger->error('Tpay notification could not be checked.', ['reason' => $error->getMessage()]);

            return new Response('FALSE', 503, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
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

    #[Route('/tenants/{uuid}', methods: ['DELETE'])]
    public function purge(Request $r, string $uuid): JsonResponse
    {
        $actor = $this->service->authenticate($r);
        $data = $r->toArray();
        $this->service->purge($uuid, (string) ($data['confirmSlug'] ?? ''), $actor);

        return new JsonResponse(null, 204);
    }
}
