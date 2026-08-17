<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Exception;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use Symfony\Component\HttpFoundation\Response;

final class NotificationMultipleRecipientNotFoundException extends \Exception
{
    public static function fromNotificationUUIDs(
        MessageService $messageService,
        array $notificationUUIDs,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            $messageService->get(
                'notification.recipient.multiple.notExists',
                [':uuids' => implode(', ', $notificationUUIDs)],
                'notifications'
            ),
            previous: $previous
        );
    }

    public function __construct(
        string $message = 'notification.recipient.multiple.notExists',
        int $code = Response::HTTP_NOT_FOUND,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
