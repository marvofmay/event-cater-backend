<?php

declare(strict_types=1);

namespace App\Module\Support\Presentation\API\Controller;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Support\Application\Command\OpenTicket;
use App\Module\Support\Application\SupportDesk;
use App\Module\Support\Infrastructure\CurrentReporter;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/support')]
final class SupportTicketController extends AbstractController
{
    public function __construct(private readonly SupportDesk $desk, private readonly CurrentReporter $reporters)
    {
    }

    #[Route('/meta', methods: ['GET'])]
    public function meta(): JsonResponse
    {
        return $this->json(['categories' => $this->desk->categories(), 'statuses' => $this->desk->statuses()], headers: $this->private());
    }

    #[Route('/tickets', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(['items' => $this->desk->listMine($this->reporters->require())], headers: $this->private());
    }

    #[Route('/tickets', methods: ['POST'])]
    public function open(Request $request): JsonResponse
    {
        $reporter = $this->reporters->require();
        $uuid = $this->desk->open(new OpenTicket(
            $reporter,
            (string) $request->request->get('category', ''),
            (string) $request->request->get('subject', ''),
            (string) $request->request->get('message', ''),
            $this->uploads($request),
        ));

        return $this->json(['uuid' => $uuid, 'message' => 'Zgłoszenie zostało wysłane.'], 201, $this->private());
    }

    #[Route('/tickets/{uuid}', requirements: ['uuid' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function detail(string $uuid): JsonResponse
    {
        return $this->json($this->desk->getMine($this->reporters->require(), $this->uuid($uuid)), headers: $this->private());
    }

    #[Route('/tickets/{uuid}/replies', requirements: ['uuid' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function reply(string $uuid, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $message = is_array($body) ? (string) ($body['message'] ?? '') : '';

        return $this->json($this->desk->replyAsUser($this->reporters->require(), $this->uuid($uuid), $message), headers: $this->private());
    }

    #[Route('/tickets/{uuid}/attachments/{attachmentUuid}', requirements: ['uuid' => '[0-9a-fA-F-]{36}', 'attachmentUuid' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function download(string $uuid, string $attachmentUuid): BinaryFileResponse
    {
        return $this->image($this->desk->downloadForUser($this->reporters->require(), $this->uuid($uuid), $this->uuid($attachmentUuid)));
    }

    /** @return list<UploadedFile> */
    private function uploads(Request $request): array
    {
        $files = $request->files->get('attachments', []);
        if ($files instanceof UploadedFile) {
            return [$files];
        }
        if (!is_array($files)) {
            throw new BusinessRuleViolation('Nieprawidłowe załączniki.');
        }
        $uploads = [];
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                throw new BusinessRuleViolation('Nieprawidłowy załącznik.');
            }
            $uploads[] = $file;
        }

        return $uploads;
    }

    private function uuid(string $value): string
    {
        if (!Uuid::isValid($value)) {
            throw new BusinessRuleViolation('Nieprawidłowy identyfikator.');
        }

        return Uuid::fromString($value)->toString();
    }

    /** @param array{path: string, name: string, mime: string} $file */
    private function image(array $file): BinaryFileResponse
    {
        $response = new BinaryFileResponse($file['path']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $file['name'], 'zalacznik.'.pathinfo($file['name'], PATHINFO_EXTENSION));
        $response->headers->set('Content-Type', $file['mime']);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "sandbox; default-src 'none'");
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /** @return array<string, string> */
    private function private(): array
    {
        return ['Cache-Control' => 'private, no-store'];
    }
}
