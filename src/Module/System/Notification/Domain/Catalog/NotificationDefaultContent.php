<?php

declare(strict_types=1);

namespace App\Module\System\Notification\Domain\Catalog;

final class NotificationDefaultContent
{
    /**
     * @return array{title: string, content: string}|null
     */
    public static function forEvent(string $eventName): ?array
    {
        return self::all()[$eventName] ?? null;
    }

    /**
     * @return array<string, array{title: string, content: string}>
     */
    public static function all(): array
    {
        return [
            'CompanyImportedEvent' => [
                'title' => 'Import {{importKind}}',
                'content' => 'Import {{importKind}} zakończył się statusem: {{importStatus}}.',
            ],
            'DepartmentImportedEvent' => [
                'title' => 'Import {{importKind}}',
                'content' => 'Import {{importKind}} zakończył się statusem: {{importStatus}}.',
            ],
            'EmployeeImportedEvent' => [
                'title' => 'Import {{importKind}}',
                'content' => 'Import {{importKind}} zakończył się statusem: {{importStatus}}.',
            ],
            'EventStaffAssigned' => [
                'title' => 'Obsadzono Cię na wydarzeniu',
                'content' => 'Zostałeś przypisany do obsady wydarzenia „{{eventTitle}}” ({{eventDate}}).{{functions}}',
            ],
            'EventStaffRemoved' => [
                'title' => 'Usunięto Cię z obsady',
                'content' => 'Zostałeś usunięty z obsady wydarzenia „{{eventTitle}}” ({{eventDate}}).',
            ],
            'EventPackerAssigned' => [
                'title' => 'Wyznaczono Cię do kompletacji',
                'content' => 'Odpowiadasz za skompletowanie kateringu na wydarzenie „{{eventTitle}}” ({{eventDate}}).{{staffNote}}',
            ],
            'KitchenResponsibleAssigned' => [
                'title' => 'Wyznaczono Cię do kuchni',
                'content' => 'Odpowiadasz za produkcję kuchni wydarzenia „{{eventTitle}}” (termin: {{dueAt}}).',
            ],
            'EventCreated' => [
                'title' => 'Nowe wydarzenie kateringowe',
                'content' => 'Utworzono wydarzenie „{{eventTitle}}” ({{eventDate}}, {{location}}).',
            ],
            'EventUpdated' => [
                'title' => 'Zmiana szczegółów wydarzenia',
                'content' => 'Zaktualizowano wydarzenie „{{eventTitle}}” ({{eventDate}}). Zmiany: {{changedFields}}.',
            ],
            'EventStatusChanged' => [
                'title' => 'Zmiana statusu wydarzenia',
                'content' => 'Wydarzenie „{{eventTitle}}” ({{eventDate}}) ma nowy status: {{status}}.',
            ],
            'EventMenuChanged' => [
                'title' => 'Zmiana menu wydarzenia',
                'content' => 'Zaktualizowano menu wydarzenia „{{eventTitle}}” ({{eventDate}}). Plan kompletacji może wymagać odświeżenia.',
            ],
            'PreparationStatusChanged' => [
                'title' => 'Zmiana statusu kompletacji',
                'content' => 'Kompletacja wydarzenia „{{eventTitle}}” ma status: {{preparationStatus}}.',
            ],
            'PreparationPacked' => [
                'title' => 'Lista kompletacji złożona',
                'content' => 'Potwierdzono złożenie całej listy kompletacji wydarzenia „{{eventTitle}}” ({{eventDate}}). Komentarz: {{comment}}.',
            ],
            'KitchenOrderReleased' => [
                'title' => 'Wydano kartę do kuchni',
                'content' => 'Kartę kuchni wydarzenia „{{eventTitle}}” wydano do produkcji (status: {{kitchenStatus}}).',
            ],
            'KitchenOrderReady' => [
                'title' => 'Kuchnia gotowa',
                'content' => 'Produkcja kuchni wydarzenia „{{eventTitle}}” jest gotowa (status: {{kitchenStatus}}).',
            ],
            'KitchenOrderHandedOver' => [
                'title' => 'Przekazano produkcję kuchni',
                'content' => 'Produkcję kuchni wydarzenia „{{eventTitle}}” przekazano (status: {{kitchenStatus}}).',
            ],
            'KitchenRevisionProposed' => [
                'title' => 'Propozycja rewizji kuchni',
                'content' => 'Zaproponowano rewizję karty kuchni wydarzenia „{{eventTitle}}”. Powód: {{reason}}.',
            ],
            'KitchenRevisionAccepted' => [
                'title' => 'Zaakceptowano rewizję kuchni',
                'content' => 'Zaakceptowano rewizję karty kuchni wydarzenia „{{eventTitle}}”.',
            ],
            'KitchenRevisionRejected' => [
                'title' => 'Odrzucono rewizję kuchni',
                'content' => 'Odrzucono rewizję karty kuchni wydarzenia „{{eventTitle}}”. Powód: {{reason}}.',
            ],
            'PreparationDeadlineReminder' => [
                'title' => 'Zbliża się termin kompletacji',
                'content' => 'Termin kompletacji wydarzenia „{{eventTitle}}” to {{deadlineAt}}.',
            ],
            'KitchenDueReminder' => [
                'title' => 'Zbliża się termin kuchni',
                'content' => 'Termin produkcji kuchni wydarzenia „{{eventTitle}}” to {{dueAt}}.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function variablesFor(string $eventName): array
    {
        return match ($eventName) {
            'CompanyImportedEvent', 'DepartmentImportedEvent', 'EmployeeImportedEvent' => ['importKind', 'importStatus'],
            'EventStaffAssigned' => ['eventTitle', 'eventDate', 'functions'],
            'EventStaffRemoved' => ['eventTitle', 'eventDate'],
            'EventPackerAssigned' => ['eventTitle', 'eventDate', 'staffNote'],
            'KitchenResponsibleAssigned' => ['eventTitle', 'dueAt'],
            'EventCreated' => ['eventTitle', 'eventDate', 'location'],
            'EventUpdated' => ['eventTitle', 'eventDate', 'changedFields'],
            'EventStatusChanged' => ['eventTitle', 'eventDate', 'status'],
            'EventMenuChanged' => ['eventTitle', 'eventDate'],
            'PreparationStatusChanged' => ['eventTitle', 'preparationStatus'],
            'PreparationPacked' => ['eventTitle', 'eventDate', 'comment'],
            'KitchenOrderReleased', 'KitchenOrderReady', 'KitchenOrderHandedOver' => ['eventTitle', 'kitchenStatus'],
            'KitchenRevisionProposed', 'KitchenRevisionRejected' => ['eventTitle', 'reason'],
            'KitchenRevisionAccepted' => ['eventTitle'],
            'PreparationDeadlineReminder' => ['eventTitle', 'deadlineAt'],
            'KitchenDueReminder' => ['eventTitle', 'dueAt'],
            default => [],
        };
    }
}
