<?php

namespace App\Module\System\Application\EventHandler;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Module\System\Application\Event\LogFileEvent;
use Psr\Log\LogLevel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Service\ServiceProviderInterface;

#[AsMessageHandler(bus: 'event.bus')]
final readonly class LogFileEventHandler
{
    public function __construct(private ServiceProviderInterface $loggers)
    {
    }

    public function __invoke(LogFileEvent $event): void
    {
        $logger = $this->loggers->has($event->channel->value)
            ? $this->loggers->get($event->channel->value)
            : $this->loggers->get(MonologChannelEnum::MAIN->value);

        match ($event->level) {
            LogLevel::DEBUG => $logger->debug($event->message),
            LogLevel::NOTICE => $logger->notice($event->message),
            LogLevel::WARNING => $logger->warning($event->message),
            LogLevel::ERROR => $logger->error($event->message),
            LogLevel::CRITICAL => $logger->critical($event->message),
            LogLevel::ALERT => $logger->alert($event->message),
            LogLevel::EMERGENCY => $logger->emergency($event->message),
            default => $logger->info($event->message),
        };
    }
}
