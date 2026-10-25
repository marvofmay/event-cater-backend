# Dostępy i uprawnienia ról

Edycja znajduje się w `/manage/roles/list`, pod przyciskiem klucza przy roli. Ustawienia dotyczą wyłącznie aktualnego tenanta. Nazwy Pracownik, Administrator i Superadministrator nie nadają automatycznych ani dziedziczonych uprawnień. Każdą rolę konfiguruje się osobno.

## Model danych i słowniki

| Element | Znaczenie |
|---|---|
| `module` / `ModuleEnum` | Grupa funkcjonalna, np. firma, wydarzenia, sprzęt, kuchnia |
| `access` / `AccessEnum` | Sekcja modułu, np. `company.positions`, `events.events`, `kitchen.orders` |
| `permission` / `PermissionEnum` | Czynność, np. `list`, `view`, `create`, `update`, `delete`, `import` |
| `role_access` | Udostępnienie sekcji roli |
| `role_access_permission` | Konkretna czynność w sekcji, przypisana roli |
| `PermissionEntityFieldEnum` | Nazwy pól encji uprawnienia (`uuid`, `name`, `description`, `active`), a nie lista czynności |

Lista dozwolonych czynności każdej sekcji pochodzi z `AccessEnum::allowedPermissions()`. API nie pozwala przypisać np. importu do notatek ani obsługi kuchni do stanowisk. Słowniki nie zawierają uprawnień typu `edit` i `preview`: właściwe wartości to `update` i `view`, używane również przez interfejs.

Samo zaznaczenie dostępu włącza sekcję, ale nie daje prawa do wykonywania wszystkich operacji. Czynności zaznacza się oddzielnie. Sekcje nawigacyjne, np. Zarządzanie, nie mają dodatkowych czynności. Menedżer plików pozostaje widokiem demonstracyjnym bez zapisu na serwerze; nie deklarujemy dla niego niewdrożonych praw do operacji na plikach.

Wydarzenia obejmują oddzielne prawa do menu, zespołu, sprzętu, mediów, kompletacji, załadunku, wydania i akceptowania braków. Kuchnia rozdziela planowanie, przekazanie do produkcji, przygotowanie, wydanie potraw, rewizje, anulowanie oraz PDF. Niektóre operacje wymagają kilku praw: np. edycja menu wymaga podglądu wydarzenia i planowania menu; zaplanowanie produkcji kuchni wymaga również podglądu wydarzenia. API egzekwuje te zależności.

## Odczyt i zapis

- `GET /api/accesses`: aktywne sekcje aktywnych modułów i ich dostępne czynności.
- `GET /api/roles/{uuid}`: zapisane dostępy oraz uprawnienia tej roli.
- `PUT /api/roles/{uuid}/accesses`: pełne zastąpienie konfiguracji w jednej transakcji.

Przykładowa struktura żądania:

```json
{
  "accesses": [
    {
      "accessUUID": "00000000-0000-4000-8000-000000000001",
      "permissionsUUIDs": ["00000000-0000-4000-8000-000000000002"]
    }
  ]
}
```

Identyfikatory pochodzą ze słownika aktualnej firmy. Pusta lista `accesses` odbiera wszystkie dostępy i czynności, a puste `permissionsUUIDs` zachowuje sekcję bez czynności. Błędne, nieistniejące i nieaktywne pozycje oraz niedozwolone kombinacje są odrzucane kodem 422 bez częściowego zapisu. Usuwanie dostępu usuwa również jego uprawnienia. Ponowny zapis nie tworzy duplikatów; odebrane prawa można ponownie nadać.

Zmiana listy sekcji wymaga `roles.assign_access_to_role`. Zmiana czynności w zachowanych sekcjach wymaga `roles.assign_permission_to_access_role`. Operator z samym pierwszym prawem może odebrać całą sekcję wraz z jej czynnościami, ale nie może dopisywać czynności. Operator z samym drugim prawem może edytować czynności, ale nie zmienia listy sekcji. Formularz blokuje odpowiednie pola; API niezależnie sprawdza te ograniczenia. Brak prawa do zarządzania zwraca 403, a rola spoza wybranej firmy nie jest dostępna.

Dotychczasowe oddzielne endpointy `POST .../accesses` oraz `POST .../accesses/permissions` pozostają zgodne z wcześniejszym API. Formularz korzysta z jednego żądania PUT, aby wyeliminować częściowy zapis między dwoma żądaniami.

## Egzekwowanie praw i sesje

Votery sprawdzają aktualną bazę firmy, aktywność słowników, dostęp roli do sekcji i przypisaną czynność. Osierocony wpis `role_access_permission` bez `role_access` nie daje prawa do operacji. Wycofanie praw obowiązuje również dla wcześniej wystawionego JWT.

`GET /api/auth/authorization` zwraca bieżące moduły, dostępy i czynności zalogowanego użytkownika. Frontend odświeża te dane po zalogowaniu, po zapisaniu praw i po powrocie do karty, dzięki czemu nie opiera widoczności przycisków wyłącznie na starych danych JWT. API nadal sprawdza każdą operację niezależnie od interfejsu.

Konto techniczne SaaS i indywidualne konta właścicieli oznaczone jako administratorzy tenanta zachowują jawny dostęp administracyjny. Rola pracownicza „Superadministrator” nie oznacza dostępu do panelu SaaS ani do innych firm. Zmiany konfiguracji rejestrują istniejące zdarzenia przypisywania dostępów i uprawnień.

Wdrożenie formularza nie nadaje automatycznie praw istniejącym rolom. Operator wybiera je zgodnie z obowiązkami pracowników.
