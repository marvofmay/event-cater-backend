<?php

declare(strict_types=1);

namespace App\Module\System\Application\Event;

use App\Common\Domain\Enum\MonologChannelEnum;
use Psr\Log\LogLevel;

final readonly class LogFileEvent
{
    public function __construct(
        public string $message,
        public string $level = LogLevel::ALERT,
        public MonologChannelEnum $channel = MonologChannelEnum::MAIN,
    ) {
    }
}
