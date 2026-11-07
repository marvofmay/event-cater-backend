<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Interface\Message;

use App\Common\Domain\Interface\NotifiableEventInterface;

interface NotificationResolveInterface
{
    public function resolve(NotifiableEventInterface $notifiableEvent): void;

    /**
     * @param array<string, scalar|null> $payload
     * @param list<object>               $recipients      User entities
     * @param list<string>|null          $allowedChannels null = wszystkie aktywne szablony; lista = filtr kanałów
     */
    public function publish(string $eventName, array $payload, array $recipients, ?array $allowedChannels = null): void;

    public function renderTemplate(string $template, array $payload): string;
}
