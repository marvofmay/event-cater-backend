# Wydarzenia i sprzęt — API i zasady domenowe

Wdrożony zakres obejmuje organizację wydarzeń oraz katalog i zapotrzebowanie na sprzęt. Kontrakt HTTP: [OpenAPI 3.0](event-equipment.openapi.json). Frontend nie jest częścią tej zmiany.

## Uruchomienie i aktualizacja

W środowisku z PHP 8.4 i zależnościami projektu:

```sh
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:initialize-system-default-data --no-interaction
```

Druga komenda rejestruje nowe moduły, dostępy i uprawnienia przez istniejący mechanizm inicjalizacji. Administrator korzysta z dotychczasowego mechanizmu dostępu; pozostałym rolom należy nadać wymagane uprawnienia. Zmiana nie przyznaje nowych dostępów wszystkim użytkownikom.

Dostępy: `events.events`, `equipment.equipment`. Zwykłe operacje używają `create`, `view`, `list`, `update`, `delete`, `restore`. Dodatkowe uprawnienia wydarzeń:

- `confirm_event` — potwierdzanie (wraz z `update`);
- `assign_event_staff` — wybór i przypisywanie pracowników;
- `plan_event_menu` — edycja menu;
- `plan_event_equipment` — edycja zapotrzebowania;
- `manage_event_media` — dodawanie i odpinanie załączników.

Wybór sprzętu przez autocomplete wymaga `list` dla katalogu sprzętu. Odczyt wydarzeń i materiałów wymaga `view`. Edycja istniejącego wydarzenia i jego elementów wymaga również `view`, oprócz uprawnienia do danej operacji. Obowiązuje istniejący globalny model uprawnień aplikacji; przypisanie do wydarzenia samo nie przyznaje dostępu systemowego ani dostępu ograniczonego do konkretnej firmy.

Dockerfile ładuje `config/php/event-media.ini`: upload 100 MiB, POST 110 MiB, pamięć procesu 256 MiB. Dla innych serwerów należy zastosować te limity PHP i odpowiedni limit reverse proxy. Dysk `storage/event-media` musi być zapisywalny przez aplikację i znajdować się poza katalogiem publicznym. W bieżącym środowisku chmurowym API uruchamia się z tymi ustawieniami przez lokalny compose.

## Przykładowy przepływ

1. `POST /api/equipment`: `{"code":"TABLE-01","name":"Stół cateringowy","category":"meble","unit":"piece"}`.
2. `POST /api/events`: `{"title":"Bankiet firmowy","date":"2026-12-31"}`. Powstaje szkic z `uuid`, numerem `EVT-…` i `version: 1`.
3. Uzupełnij podstawowe dane przez `PUT /api/events/{uuid}`. Jest to zastąpienie danych podstawowych: pominięte pola opcjonalne zostaną wyczyszczone; kontakty, menu, obsada i sprzęt mają osobne endpointy i pozostają bez zmian.
4. `PUT /api/events/{uuid}/contacts`: `{"version":1,"contacts":[{"name":"Anna Kowalska","phone":"+48 123 456 789","function":"Organizator"}]}`.
5. `PUT /api/events/{uuid}/menu`: aktualna `version` i `items`, np. `[{"name":"Krem pomidorowy","portions":120,"portionSize":"250 ml","allergens":["milk"]}]`.
6. `GET /api/events/staff-options?q=Anna`, następnie `PUT /api/events/{uuid}/staff/{employeeUuid}`: aktualna `version`, `functions: ["driver","logistics"]`, `startsAt`, `endsAt` i opcjonalne `notes`.
7. `GET /api/equipment/options?q=stół`, następnie `PUT /api/events/{uuid}/equipment/{equipmentUuid}`: `{"version":4,"quantity":12}`. To zapotrzebowanie, bez blokowania stanu magazynu.
8. `POST /api/events/{uuid}/status`: aktualna `version` i `status`.
9. `POST /api/events/{uuid}/attachments`: multipart `version`, `file`, opcjonalnie `description`.

Każdy udany zapis zwraca reprezentację z nową `version`. Do kolejnego żądania należy użyć tej wartości. Podane wyżej numery wersji są przykładami. Konflikt wersji daje HTTP 409; błędna reguła biznesowa lub typ pola — 422; wadliwy JSON — 400; brak rekordu — 404. W JSON ilości i wersje muszą być liczbami całkowitymi, a nie napisami. Usuwanie pozycji podrzędnych odbywa się przez DELETE z ciałem JSON zawierającym `version`.

## Reguły wydarzenia

- Szkic wymaga tytułu i poprawnej daty `YYYY-MM-DD`. Opis, adres, liczba gości i harmonogram mogą być uzupełniane później.
- Daty/czasy wymagają RFC3339 z dokładnością do pełnej sekundy (bez ułamków) i jawnym offsetem, np. `2026-12-31T18:00:00+01:00`. Domyślna strefa IANA: `Europe/Warsaw`. Baza przechowuje czasy w UTC; API odczytu zwraca offset UTC, a `timezone` pozwala przedstawić czas lokalny.
- `readyAt <= startsAt < endsAt`. Koniec może wypaść następnego dnia. Lokalna data rozpoczęcia musi odpowiadać `date`.
- Statusy: `draft → planned → confirmed → in_progress → completed`. Z `planned` można wrócić do `draft`. Każdy nieterminalny status może przejść do `cancelled`.
- Potwierdzenie wymaga pełnego harmonogramu, ulicy, miasta, kodu pocztowego, dodatniej liczby gości, aktywnego koordynatora i minimum jednego kontaktu. Dane te muszą pozostać kompletne również po potwierdzeniu.
- Zakończone i anulowane wydarzenia zachowują historię i są nieedytowalne. Wyjątek: do zakończonego wydarzenia można dodawać/odpinać media jako dokumentację realizacji.
- Lista/kalendarz: `GET /api/events?from=2026-12-01&to=2026-12-31&status=planned&q=bankiet&page=1&limit=20`. Zakres dotyczy daty rozpoczęcia wydarzenia, nie dowolnego przecięcia przedziałów. Maksymalnie 100 rekordów na stronę.

## Pracownicy

Funkcje `driver`, `waiter`, `logistics`, `chef`, `coordinator` są osobnym słownikiem (`GET /api/events/functions`), niezależnym od `Company/Role` i uprawnień. Jedno przypisanie pracownika do wydarzenia zawiera kilka unikalnych funkcji i jawny przedział pracy, obejmujący również logistykę.

Pracownik musi być aktywny, a termin mieścić się w okresie zatrudnienia. Nakładające się przydziały do innych niezakończonych i nieanulowanych wydarzeń powodują 409. Przedziały są półotwarte: zakończenie jednego przydziału może równać się początkowi drugiego. Przypisania blokują czas również w szkicach. Wskazanie koordynatora w danych wydarzenia samo nie tworzy przedziału pracy — do planowania jego dostępności należy dodać przydział z funkcją `coordinator`.

Transakcja blokuje wiersz wydarzenia i pracownika. Kontrola konfliktów używa bieżącego odczytu z blokadą, aby działała również przy wcześniejszym snapshotcie MySQL REPEATABLE READ. Potwierdzenie ponownie sprawdza aktywność i dostępność zespołu. Zakleszczenie/konkurencyjny zapis daje 409; klient odświeża dane i ponawia świadomie zapis.

## Sprzęt i menu

Kategorie sprzętu to osobny słownik: `GET/POST /api/equipment-categories`, `PUT/DELETE /api/equipment-categories/{uuid}`. Zapis i usunięcie wymagają aktualnej `version`. Obowiązują uprawnienia katalogu `equipment` (`list`, `create`, `update`, `delete`). Nazwy są unikalne. Usunięcie używanej kategorii wymaga opcjonalnego pola `replacementCategoryUuid` wskazującego inną istniejącą kategorię oraz dodatkowego uprawnienia `equipment.update`. Sprzęt aktywny i archiwalny zostaje przeniesiony atomowo do kategorii docelowej; jego wersje rosną, a stany i powiązania z wydarzeniami pozostają zachowane. Bez kategorii docelowej używana kategoria nie zostanie usunięta (422). Zmiana nazwy kategorii jest od razu widoczna w katalogu sprzętu.

Sprzęt przyjmuje `categoryUuid`, opcjonalne `slangName` (do 200 znaków) oraz `stockQuantity` — posiadaną ilość w jednostce pozycji, nieujemną liczbę całkowitą (domyślnie 0). Brak ilości w aktualizacji zachowuje jej dotychczasową wartość. Wyszukiwanie katalogu i podpowiedzi uwzględnia nazwę slangową, a filtr `categoryUuid` wybiera kategorię. Dotychczasowe tekstowe `category` pozostaje obsługiwane dla starszych klientów. Migracja `Version20261023200000` przenosi istniejące kategorie do słownika bez utraty powiązań. Nieznane dotychczas ilości zaczynają od 0 i wymagają uzupełnienia. Ilość posiadana jest ewidencją; zapotrzebowania wydarzeń jej nie pomniejszają i nie rezerwują sprzętu.

### Miejsca przechowywania sprzętu

Biblioteka miejsc: `GET/POST /api/equipment-locations`, `PUT/DELETE /api/equipment-locations/{uuid}`. Nazwy są unikalne, mają do 200 znaków; zmiana i usunięcie wymagają `version`. Obowiązują uprawnienia katalogu sprzętu `list/create/update`; usunięcie wymaga `delete` i `update`, ponieważ odłącza miejsce od przypisanych pozycji, także archiwalnych, zwiększając ich wersje. Sam sprzęt pozostaje w katalogu.

Sprzęt przyjmuje opcjonalne `storageLocationUuid`, a odpowiedzi zawierają też aktualną nazwę w `storageLocation`. Pominięcie pola przy aktualizacji zachowuje dotychczasowe przypisanie; jawne `null` je usuwa. Lista obsługuje filtr `storageLocationUuid` oraz wyszukiwanie po nazwie miejsca. Biblioteka jest dostępna pod `/catering/equipment/locations` i przez przycisk „Miejsca sprzętu” w katalogu. Formularz sprzętu udostępnia wybór z biblioteki.

Migracja `Version20261024110000` dodaje bibliotekę oraz opcjonalne powiązanie. Istniejący sprzęt nie otrzymuje automatycznie przypisania. Komenda `php bin/console app:initialize-equipment-locations --no-interaction`, także uruchamiana przy inicjalizacji danych domyślnych, dodaje bez duplikatów dziewięć miejsc:

- pod schodami po stronie prawej (zmywaka);
- pod schodami po stronie lewej (toalet);
- za barem;
- w biurze;
- w magazynie po stronie stołów bilardowych;
- na zmywaku;
- w przejściu do kuchni;
- w szafie za drzwiami "western";
- w magazynie po prawej stronie za drzwiami "western".

Kod sprzętu jest unikalny i normalizowany do wielkich liter. Jednostki: `piece` (sztuka), `set` (komplet), `kit` (zestaw). Zestaw jest samodzielną pozycją, bez automatycznego rozbijania na elementy. Sprzęt można dezaktywować i przywrócić; autocomplete pokazuje wyłącznie aktywny katalog.

`DELETE /api/equipment/{uuid}` z aktualną `version` i uprawnieniem `equipment.delete` trwale usuwa nieużywany sprzęt (aktywny lub archiwalny). Powiązanie z wydarzeniem lub szablonem, także archiwalnym, blokuje operację (422). Dla używanego sprzętu pozostaje archiwizacja lub zmiana kategorii. Po usunięciu wszystkich sprzętów z kategorii można usunąć kategorię. Archiwizacja sprzętu sama nie zwalnia kategorii.

### Zdjęcia sprzętu

Pozycja katalogu może mieć do 20 zdjęć JPEG, PNG, WebP lub GIF, każde do 20 MiB. Formularz dodawania/edycji obsługuje wybór wielu plików oraz przeciąganie. Zdjęcia i zaznaczone usunięcia zapisują się po kliknięciu „Zapisz”. Lista sprzętu pokazuje miniaturę i akcję galerii. Przy błędzie jednego z uploadów formularz zachowuje utworzoną pozycję oraz już zapisane zdjęcia, umożliwiając ponowienie pozostałych.

- `POST /api/equipment/{uuid}/photos` — multipart `version`, `file`; wymaga `equipment.update`, zwraca sprzęt z nową wersją (201).
- `GET /api/equipment/{uuid}/photos/{photoUuid}` — prywatny podgląd, wymaga `equipment.view`.
- `DELETE /api/equipment/{uuid}/photos/{photoUuid}` — JSON `version`, wymaga `equipment.update`, zwraca sprzęt z nową wersją.

Odczyt sprzętu i jego lista zawierają `photos`: `uuid`, `name`, `mimeType`, `size`, `createdAt`. Pliki są poza katalogiem publicznym, w `storage/equipment-photos`, w osobnych folderach sprzętu i najemców. Typ jest wykrywany z zawartości, a obraz dodatkowo walidowany; SVG i filmy są odrzucane. Prywatna odpowiedź używa `inline`, `nosniff` i `private, no-store`. Powiązanie zdjęcia ze wskazanym sprzętem jest sprawdzane. Nieudany zapis usuwa przesłany plik; usunięcie zdjęcia lub trwałe usunięcie sprzętu sprząta jego pliki po udanym zapisie bazy. Migracja `Version20261024100000` tworzy tabelę galerii.

Zapotrzebowanie zapisuje nazwę, kod i jednostkę z chwili dodania. Zmiana nazwy w katalogu nie zmienia historycznego wydarzenia. Jednostki nie można zmienić, gdy sprzęt jest powiązany z wydarzeniem. Ilość jest dodatnią liczbą całkowitą. Nieaktywną pozycję można usunąć z zapotrzebowania, ale nie zwiększać jej ilości.

Menu jest niezależną migawką danego wydarzenia: nazwa, liczba porcji, sekcja, wielkość porcji, sposób podania, etykiety diet, jawnie deklarowane alergeny i uwagi. Dane nie są wyliczane z nazw potraw. Kontakty i menu są ograniczonymi kolekcjami wartości zapisanymi jako JSON w agregacie wydarzenia; obsada, zapotrzebowanie, media i historia mają osobne tabele.

## Media i historia

Dozwolone obrazy: JPEG, PNG, WebP i GIF do 20 MiB. Filmy: MP4, WebM i MOV do 100 MiB. Typ jest wykrywany z zawartości, nazwa na dysku jest losowa, a obrazy dodatkowo sprawdzane jako pliki graficzne. SVG i pliki wykonywalne nie są obsługiwane.

Pobieranie: `GET /api/events/{uuid}/attachments/{attachmentUuid}`, z JWT. Powiązanie z konkretnym wydarzeniem jest sprawdzane; odpowiedź ma `Content-Disposition: attachment`, `nosniff` i prywatne cache. API nie ujawnia ścieżki na dysku. Pliki korzystają z istniejącej encji `System/File`.

Nieudany zapis metadanych usuwa plik utworzony przez to żądanie. Odpięcie materiału usuwa jego dostęp przez wydarzenie, ale zachowuje prywatny plik i rekord `System/File`; polityka retencji i sprzątanie osieroconych plików wymagają osobnej decyzji operacyjnej. Nie ma automatycznej transkrypcji ani transkodowania filmów.

`GET /api/events/{uuid}/history` zwraca uporządkowane rewizje: aktor, czas, operacja i dane zmiany. Historia zapisuje się w tej samej transakcji co operacja. Przy odpinaniu mediów zachowuje identyfikator usuniętego powiązania; nie przechowuje binarnej zawartości.

## Architektura i testy

Oddzielne moduły `Event` i `Equipment`: Domain, Application (komendy i porty), Infrastructure (Doctrine, integracje, projekcje odczytu, dysk), Presentation/API. Komendy działają synchronicznie przez istniejący `command.bus` i transakcję Doctrine. Zapytania na `query.bus` zwracają projekcje DBAL. Integracje z pracownikami i katalogiem przechodzą przez porty. Nie ma event sourcingu ani dodatkowej bazy.

```sh
APP_ENV=test vendor/bin/phpunit
```

Testy funkcjonalne projektu odtwarzają schemat w osobnej bazie testowej i wymagają działającego MySQL oraz pozostałej konfiguracji testowej aplikacji. Nie uruchamiać ich na bazie deweloperskiej/produkcyjnej. Pełny zestaw ma limit pamięci 512 MiB w `phpunit.xml.dist`, ponieważ testy funkcjonalne wielokrotnie odtwarzają metadane i schemat Doctrine. Nowe testy obejmują cykl życia, nocne wydarzenia i DST, poprawność HTTP, autoryzację, historię i wersje, migawki sprzętu, konflikty obsady, odczyt po równoległym commicie oraz prywatne uploady.

Poza tym wydaniem pozostają stany magazynowe, rezerwacje, wydania/zwroty, szkody, katalog dań, duplikowanie wydarzeń, PDF i powiadomienia. Zapotrzebowanie nie gwarantuje dostępności sprzętu.

## Lista kompletacji

Zapotrzebowanie na sprzęt jest teraz automatycznie połączone z [listą przygotowania cateringu](preparation.md). Obejmuje ona także produkty zużywalne, postęp przygotowania i załadunku, odpowiedzialność, kontrolę braków oraz szablony. Niepusta lista wymaga potwierdzenia załadunku przed przejściem wydarzenia do `in_progress`.

Karty produkcyjne, dzienny plan kuchni i PDF: [Kuchnia](kitchen.md).

Baza powtarzających się miejsc: [Miejsca obsługi](venues.md). Ekrany obsługi są dostępne we frontendzie pod `/catering/events`, `/catering/equipment`, `/catering/supplies`, `/catering/templates`, `/catering/venues` i `/catering/kitchen`.
