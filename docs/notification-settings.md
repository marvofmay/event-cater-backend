# Ustawienia powiadomień

`/settings/notifications` zawiera globalne ustawienia bieżącej firmy,
podzielone na cztery zakładki:

- **Kanały** — dostępność SMS, e-maili i wiadomości systemowych.
- **Tytuły i treści** — edycja szablonów dla istniejących zdarzeń powiadomień.
- **SMS / Brevo** — klucz API, nadawca i lokalna pula / limit SMS.
- **Lista** — liczba rekordów na stronie.

Ustawienia osobiste w `/me/settings` pozostają oddzielne od globalnej edycji
szablonów. Szablony są pobierane dopiero po otwarciu ich zakładki. Przełączanie
głównych zakładek nie usuwa roboczej treści. Zmiana zdarzenia, kanału lub wariantu
przy niezapisanym szkicu wymaga potwierdzenia odrzucenia zmian.

Zakładki osobistych ustawień list zarządzania wymagają dostępu `manage` oraz
dostępu i uprawnienia `list` do konkretnej listy. Ukrycie zakładek nie jest
jedyną ochroną: API preferencji katalogów wymaga tego samego uprawnienia
listowania, którego reguły obejmują nadrzędny dostęp do zarządzania.
Po odebraniu dostępu blokowane są zarówno GET, jak i PUT preferencji tej listy.
Osobiste kanały powiadomień pozostają dostępne wszystkim zalogowanym użytkownikom,
ale bez `notifications.list` nie ma pola liczby rekordów ani możliwości zapisania
jej nadpisania przez API. Można nadal zapisać kanały z `pageSize: null`.

Edytor korzysta z istniejącego API:

- `GET /api/settings/notification-templates` — uprawnienie `notification_templates.list`.
- `PUT /api/settings/notification-templates` — uprawnienie `notification_templates.settings`.

Zapis określa `eventName`, `channelCode`, `title`, `content`, `searchDefault`
(wariant podstawowy / własny) i `markAsActive`. Włączenie jednego wariantu wyłącza
drugi dla tej samej pary zdarzenie–kanał w jednej transakcji. Blokada rekordu
zdarzenia porządkuje równoległe zmiany aktywnego wariantu. Wyłączenie wszystkich
wariantów zatrzymuje wiadomości dla tej pary. Dostępność kanału i preferencje
odbiorcy nadal ograniczają rzeczywistą wysyłkę.

Obecnie rejestr obsługuje wyniki importów firm, oddziałów i pracowników.
Dostępne zmienne to `{{importKind}}` i `{{importStatus}}`. Podgląd używa
przykładowych wartości i niczego nie wysyła. Tytuł ma do 255 znaków, a treść
do 500. W SMS wysyłana jest tylko treść, bez tytułu.

Lista API zwraca poprawne pole `eventName`; historyczne `eventNane` jest nadal
obecne jako alias zgodności. Obsługiwane są filtry zdarzenia, kanału, wariantu,
aktywności, tytułu, treści i frazy oraz strony po maksymalnie 100 rekordów.
Aktualnie katalog ma 18 szablonów (3 zdarzenia × 3 kanały × 2 warianty).
Nie zmieniono wiadomości odzyskiwania hasła ani wiadomości powitalnych, które
nie należą do tego rejestru szablonów.
