<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Exception;

use App\Common\Domain\Service\MessageTranslator\MessageService;
use Symfony\Component\HttpFoundation\Response;

final class NotificationRecipientNotFoundException extends \Exception
{
    public static function fromNotificationUUID(
        MessageService $messageService,
        string $notificationUUID,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            $messageService->get(
                'notification.recipient.notExists',
                [':uuid' => $notificationUUID],
                'notifications'
            ),
            previous: $previous
        );
    }

    public function __construct(
        string $message = 'notification.recipient.notExists',
        int $code = Response::HTTP_NOT_FOUND,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
