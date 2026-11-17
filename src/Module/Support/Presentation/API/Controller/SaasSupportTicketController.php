<?php

declare(strict_types=1);

namespace App\Module\Support\Presentation\API\Controller;

use App\Module\Saas\Application\SaasService;
use App\Module\Support\Application\Command\ChangeTicketStatus;
use App\Module\Support\Application\Command\ReplyToTicket;
use App\Module\Support\Application\SupportDesk;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/saas/support')]
final readonly class SaasSupportTicketController
{
    public function __construct(private SupportDesk $desk, private SaasService $saas)
    {
    }

    #[Route('/meta', methods: ['GET'])]
    public function meta(Request $request): JsonResponse
    {
        $this->saas->authenticate($request);

        return $this->json(['categories' => $this->desk->categories(), 'statuses' => $this->desk->statuses()]);
    }

    #[Route('/summary', methods: ['GET'])]
    public function summary(Request $request): JsonResponse
    {
        $this->saas->authenticate($request);

        return $this->json($this->desk->summary());
    }

    #[Route('/tickets', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->saas->authenticate($request);

        return $this->json(['items' => $this->desk->listForOperator(
            $this->query($request, 'status'),
            $this->query($request, 'category'),
            $this->query($request, 'q'),
            $this->query($request, 'tenant'),
        )]);
    }

    #[Route('/tickets/{uuid}', requirements: ['uuid' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function detail(Request $request, string $uuid): JsonResponse
    {
        $this->saas->authenticate($request);

        return $this->json($this->desk->getForOperator($this->uuid($uuid)));
    }

    #[Route('/tickets/{uuid}/replies', requirements: ['uuid' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function reply(Request $request, string $uuid): JsonResponse
    {
        $operator = $this->saas->authenticate($request);
        $body = $request->toArray();

        return $this->json($this->desk->replyAsOperator(new ReplyToTicket(
            $this->uuid($uuid),
            (string) ($body['message'] ?? ''),
            $operator,
            'Zespół EventCater',
            'operator',
            status: isset($body['status']) ? (string) $body['status'] : null,
        )));
    }

    #[Route('/tickets/{uuid}/status', requirements: ['uuid' => '[0-9a-fA-F-]{36}'], methods: ['PATCH'])]
    public function status(Request $request, string $uuid): JsonResponse
    {
        $this->saas->authenticate($request);
        $body = $request->toArray();

        return $this->json($this->desk->changeStatus(new ChangeTicketStatus($this->uuid($uuid), (string) ($body['status'] ?? ''))));
    }

    #[Route('/tickets/{uuid}/attachments/{attachmentUuid}', requirements: ['uuid' => '[0-9a-fA-F-]{36}', 'attachmentUuid' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function download(Request $request, string $uuid, string $attachmentUuid): BinaryFileResponse
    {
        $this->saas->authenticate($request);
        $file = $this->desk->downloadForOperator($this->uuid($uuid), $this->uuid($attachmentUuid));
        $response = new BinaryFileResponse($file['path']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $file['name'], 'zalacznik.'.pathinfo($file['name'], PATHINFO_EXTENSION));
        $response->headers->set('Content-Type', $file['mime']);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "sandbox; default-src 'none'");
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function query(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);

        return is_string($value) ? $value : null;
    }

    private function uuid(string $value): string
    {
        if (!Uuid::isValid($value)) {
            throw new BadRequestHttpException('Nieprawidłowy identyfikator.');
        }

        return Uuid::fromString($value)->toString();
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'private, no-store']);
    }
}
