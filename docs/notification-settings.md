# Ustawienia powiadomień

`/settings/notifications` zawiera globalne ustawienia bieżącej firmy,
podzielone na cztery zakładki:

- **Kanały** — dostępność SMS, e-maili i wiadomości systemowych (globalnie).
- **Tytuły i treści** — zdarzenia powiadomień, switche kanałów per zdarzenie (tylko kanały włączone globalnie) oraz edycja treści.
- **SMS / Brevo** — klucz API, nadawca i lokalna pula / limit SMS.
- **Lista** — liczba rekordów na stronie.

Ustawienia osobiste w `/me/settings` pozostają oddzielne od globalnej edycji
szablonów.

## API

- `GET /api/settings/notification-events` — lista zdarzeń z `label`, `enabled`, `channels[]`, `variables[]`.
- `PUT /api/settings/notification-events/{eventName}/channels` — `{ channels: { internal?: bool, email?: bool, sms?: bool } }` aktywuje/dezaktywuje szablony dla pary zdarzenie–kanał.
- `GET|PUT /api/settings/notification-templates` — edycja treści wariantu (default/custom).
- `GET|PUT /api/events/{uuid}/notification-recipients` — nadpisania odbiorców fan-outu wydarzenia (`excludedEmployeeUuids`, `extraEmployeeUuids`).

Skuteczna wysyłka wymaga: zdarzenie włączone ∧ aktywny szablon kanału ∧ kanał globalnie on ∧ preferencja użytkownika (`internal` bez opt-out).

## Zdarzenia kateringowe

Osobiste (bez listy wydarzenia): `EventStaffAssigned`, `EventPackerAssigned`, `KitchenResponsibleAssigned`.

Fan-out (z extra/excluded na karcie wydarzenia): `EventCreated`, `EventUpdated`, `EventStatusChanged`, `EventMenuChanged`, `PreparationStatusChanged`, `KitchenOrderReleased`, `KitchenOrderReady`, `KitchenOrderHandedOver`.

`EventCreated` nie idzie przy zapisie podstawowych danych. Na karcie wydarzenia, w zakładkach Zespół, Kompletacja i Powiadomienia, przełącznik „Wyślij powiadomienia” wysyła je raz (`POST /api/events/{uuid}/creation-notice`) do koordynatora, obsady, osoby kompletującej, kuchni i dodatkowo wskazanych osób. Drugie wywołanie nic nie wysyła. Dopóki to powiadomienie nie pójdzie, osobiste komunikaty o przypisaniu też czekają, żeby nie zdublować wiadomości. Po wysłaniu nowa obsada dostaje już tylko swoje powiadomienie o przypisaniu. Wydarzenia utworzone wcześniej są oznaczone jako wysłane.

Na karcie wydarzenia zakładka **Powiadomienia** pozwala:
- odznaczyć osoby z listy domyślnej i dodać dodatkowych odbiorców,
- nadpisać kanały (SMS / e-mail / systemowe) dla tego wydarzenia albo dziedziczyć ustawienia firmowe typu zdarzenia.

Dodatkowe zdarzenia: `EventStaffRemoved`, `KitchenRevisionProposed` / `Accepted` / `Rejected`, `PreparationDeadlineReminder`, `KitchenDueReminder`.

Przypomnienia o terminach: komenda `php bin/console app:notify-event-deadlines` (domyślnie okno 24 h). W docker-compose działa usługa `worker-event-deadline-reminders` co godzinę.
