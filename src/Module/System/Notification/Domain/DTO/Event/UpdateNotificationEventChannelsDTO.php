<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\DTO\Event;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateNotificationEventChannelsDTO
{
    /**
     * @param array<string, bool> $channels
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('array')]
        public array $channels = [],
    ) {
    }
}
