<?php

declare(strict_types=1);

namespace App\Module\Facebook\Application;

final readonly class PublishEventFacebookPost
{
    /**
     * @param list<string> $attachmentUuids
     * @param list<array{path: string, mimeType: string}> $uploadedImages
     */
    public function __construct(
        public string $eventUuid,
        public string $userUuid,
        public string $message,
        public array $attachmentUuids = [],
        public array $uploadedImages = [],
    ) {
    }
}
