<?php

declare(strict_types=1);

namespace App\Common\Infrastructure\Cache;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Module\System\Application\Event\LogFileEvent;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\MessageBusInterface;

final class EntityReferenceCache implements \Symfony\Contracts\Service\ResetInterface
{
    private array $cache = [];

    public function __construct(#[Autowire(service: 'event.bus')] private MessageBusInterface $eventBus, private ?Connection $connection = null)
    {
    }

    public function get(string $className, string $identifier, callable $loader): ?object
    {
        $key = ($this->connection?->getDatabase() ?? 'default').':'.$className.':'.$identifier;
        if (!isset($this->cache[$key]) || empty($this->cache[$key])) {
            $this->logInFile($className, $identifier, 'method getCache');
            $this->cache[$key] = $loader($identifier);
        }

        return $this->cache[$key];
    }

    public function set(object $entity): void
    {
        $className = get_class($entity);
        $uuid = $entity->getUUID()->toString();
        $key = ($this->connection?->getDatabase() ?? 'default').':'.$className.':'.$uuid;

        if (!isset($this->cache[$key])) {
            $this->logInFile($className, $uuid, 'method setCache');
            $this->cache[$key] = $entity;
        }
    }

    private function logInFile(string $className, string $uuid, ?string $additionalInfo = null): void
    {
        $this->eventBus->dispatch(
            new LogFileEvent(
                $className.':'.$uuid.' not exists in cache - '.$additionalInfo,
                LogLevel::INFO,
                MonologChannelEnum::LOCAL_CACHE
            )
        );
    }

    public function reset(): void { $this->clear(); }

    public function clear(): void
    {
        $this->cache = [];
    }
}
