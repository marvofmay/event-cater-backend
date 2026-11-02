<?php

declare(strict_types=1);

namespace App\Module\Facebook\Application;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Infrastructure\Storage\EventMediaStorage;
use App\Module\Facebook\Domain\Entity\FacebookPostLog;
use App\Module\Facebook\Infrastructure\FacebookGraphClient;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PublishEventFacebookPostHandler
{
    public function __construct(
        private Connection $db,
        private EntityManagerInterface $em,
        private EventMediaStorage $storage,
        private FacebookGraphClient $graph,
        private FacebookConnectionService $connections,
    ) {
    }

    public function __invoke(PublishEventFacebookPost $command): array
    {
        $message = trim($command->message);
        if ($message === '' || mb_strlen($message) > 5000) {
            throw new BusinessRuleViolation('Treść posta jest wymagana (max. 5000 znaków).');
        }
        $event = $this->db->fetchAssociative('SELECT uuid, title, status FROM catering_event WHERE uuid = ?', [$command->eventUuid]);
        if (!$event) {
            throw new PlanningResourceNotFound('Nie znaleziono wydarzenia.');
        }
        if (($event['status'] ?? '') === 'cancelled') {
            throw new BusinessRuleViolation('Nie można publikować postów dla anulowanego wydarzenia.');
        }
        $paths = $this->resolveImagePaths($command->eventUuid, $command->attachmentUuids, $command->uploadedImages);
        if (count($paths) > 10) {
            throw new BusinessRuleViolation('Można dołączyć maksymalnie 10 zdjęć.');
        }
        $connection = $this->connections->requireConnection();
        $token = $this->connections->pageAccessToken($connection);
        try {
            $postId = $this->publish($connection->pageId(), $token, $message, $paths);
            $log = new FacebookPostLog($command->eventUuid, $command->userUuid, 'published', $postId);
            $this->em->persist($log);
            $this->em->flush();

            return $log->toArray();
        } catch (\Throwable $error) {
            $log = new FacebookPostLog($command->eventUuid, $command->userUuid, 'failed', null, $error->getMessage());
            $this->em->persist($log);
            $this->em->flush();
            throw $error;
        }
    }

    /**
     * @param list<string> $attachmentUuids
     * @param list<array{path: string, mimeType: string}> $uploadedImages
     * @return list<string>
     */
    private function resolveImagePaths(string $eventUuid, array $attachmentUuids, array $uploadedImages): array
    {
        $paths = [];
        foreach ($attachmentUuids as $attachmentUuid) {
            $row = $this->db->fetchAssociative(
                'SELECT f.file_path, a.mime_type FROM event_attachment a INNER JOIN file f ON f.uuid = a.file_uuid AND f.deleted_at IS NULL WHERE a.event_uuid = ? AND a.uuid = ?',
                [$eventUuid, $attachmentUuid],
            );
            if (!$row) {
                throw new BusinessRuleViolation('Wybrany załącznik nie należy do tego wydarzenia.');
            }
            if (!str_starts_with((string) $row['mime_type'], 'image/')) {
                throw new BusinessRuleViolation('Do posta Facebook można dołączyć tylko zdjęcia (nie filmy).');
            }
            $paths[] = $this->storage->path((string) $row['file_path']);
        }
        foreach ($uploadedImages as $upload) {
            if (!str_starts_with($upload['mimeType'], 'image/')) {
                throw new BusinessRuleViolation('Do posta Facebook można dołączyć tylko zdjęcia (nie filmy).');
            }
            $paths[] = $upload['path'];
        }

        return $paths;
    }

    /** @param list<string> $paths */
    private function publish(string $pageId, string $token, string $message, array $paths): string
    {
        if ($paths === []) {
            return $this->graph->publishFeed($pageId, $token, $message);
        }
        if (count($paths) === 1) {
            return $this->graph->publishPhoto($pageId, $token, $paths[0], $message, true);
        }
        $media = [];
        foreach ($paths as $path) {
            $media[] = $this->graph->publishPhoto($pageId, $token, $path, '', false);
        }

        return $this->graph->publishFeed($pageId, $token, $message, $media);
    }
}
