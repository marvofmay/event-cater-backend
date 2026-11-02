<?php

declare(strict_types=1);

namespace App\Module\Facebook\Presentation\API\Controller;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Event\Infrastructure\Storage\EventMediaStorage;
use App\Module\Facebook\Application\PublishEventFacebookPost;
use App\Module\Facebook\Application\PublishEventFacebookPostHandler;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/events/{uuid}/facebook-posts', requirements: ['uuid' => '[0-9a-fA-F-]{36}'])]
final class EventFacebookPostController extends PlanningController
{
    #[Route('', methods: ['POST'])]
    public function publish(
        string $uuid,
        Request $request,
        EventMediaStorage $storage,
        PublishEventFacebookPostHandler $publisher,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(PermissionEnum::PUBLISH_FACEBOOK, AccessEnum::EVENTS);
        $uuid = PlanningPayload::identifier($uuid);
        $message = trim((string) $request->request->get('message', ''));
        $rawAttachments = $request->request->all('attachmentUuids');
        if (!is_array($rawAttachments)) {
            $single = $request->request->get('attachmentUuids');
            $rawAttachments = is_string($single) && $single !== '' ? [$single] : [];
        }
        $attachmentUuids = [];
        foreach ($rawAttachments as $attachmentUuid) {
            if (!is_string($attachmentUuid)) {
                throw new BusinessRuleViolation('Nieprawidłowy identyfikator załącznika.');
            }
            $attachmentUuids[] = PlanningPayload::identifier($attachmentUuid);
        }

        $uploads = [];
        $files = $request->files->get('files', []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        if (!is_array($files)) {
            $files = [];
        }
        try {
            foreach ($files as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }
                $stored = $storage->store($file);
                if (!str_starts_with($stored['mimeType'], 'image/')) {
                    $storage->discard($stored['storedName']);
                    throw new BusinessRuleViolation('Do posta Facebook można dołączyć tylko zdjęcia.');
                }
                $uploads[] = [
                    'path' => $storage->path($stored['storedName']),
                    'mimeType' => $stored['mimeType'],
                    'storedName' => $stored['storedName'],
                ];
            }
            $result = $publisher(new PublishEventFacebookPost(
                $uuid,
                $this->actor(),
                $message,
                $attachmentUuids,
                array_map(static fn (array $upload) => [
                    'path' => $upload['path'],
                    'mimeType' => $upload['mimeType'],
                ], $uploads),
            ));
        } catch (\Throwable $error) {
            foreach ($uploads as $upload) {
                $storage->discard($upload['storedName']);
            }
            throw $error;
        }
        foreach ($uploads as $upload) {
            $storage->discard($upload['storedName']);
        }

        return $this->resource($result, 201);
    }
}
