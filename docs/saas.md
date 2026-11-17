# SaaS: firmy, dostęp i superadministracja

Jedna aplikacja i osobna baza MySQL każdej firmy. Chroni również istniejące moduły używające własnego SQL — izolacja nie zależy od pamiętania o `tenant_id` w każdym zapytaniu. Kod klienta (`slug`) wybiera bazę wyłącznie z centralnego rejestru, nigdy z nazwy podanej w żądaniu.

## Model

Centralna baza wskazana przez `DATABASE_URL` zawiera `saas_tenant`, `saas_administrator`, `saas_session`, `saas_audit` oraz dotychczasową firmę `default`. Jej istniejące dane nie są kopiowane ani usuwane. Nowe firmy otrzymują bazy `ec_tenant_<UUID bez myślników>`, wszystkie moduły biznesowe, słowniki i jedno indywidualne konto administratora firmy. Nie otrzymują domyślnego konta demonstracyjnego. Ten sam e-mail może istnieć w różnych firmach.

| Status | Dostęp pracowników | Znaczenie |
|---|---|---|
| `inactive` | zablokowany | Nowy klient, zakończona współpraca lub przygotowanie wdrożenia |
| `active` | dozwolony przy bazie `ready` | Klient korzysta z aplikacji |
| `suspended` | zablokowany | Czasowa blokada, np. rozliczenie lub zgłoszenie klienta |
| `deleted` | zablokowany | Usunięcie logiczne, dane pozostają; przywrócenie najpierw do `inactive` |

Stan techniczny `provisioning`: `pending`, `ready`, `failed`. Klienta z nieudaną instalacją nie można aktywować. Utworzenie rejestru i instalacja bazy są rozdzielone, ponieważ DDL MySQL nie zapewnia transakcji obejmującej cały proces. Błąd nie usuwa bazy, jest widoczny w panelu i wymaga diagnostyki operatora. Automatyczne niszczenie lub przebudowanie częściowo przygotowanej bazy nie jest dostępne.

Każda rzeczywista zmiana statusu wymaga powodu i oczekiwanej wersji (`409` przy konflikcie), zapisuje autora i unieważnia dotychczasowe tokeny. Zawieszenie sprawdzane jest przy każdym żądaniu. Aktywacja nie przywraca starych sesji. Równoległe żądanie rozpoczęte przed blokadą może się zakończyć.

## API i aplikacja mobilna

Konfiguracja dostępów i czynności dla ról pracowniczych: [dostępy i uprawnienia ról](role-authorization.md).

Żądania biznesowe, w tym `/api/login`, wysyłają `X-Tenant: kod-firmy`. Domyślna wartość to `default`. JWT zawiera `tenantUuid`, `tenantSlug`, `tenantVersion`; API sprawdza powiązanie tokena z firmą przed załadowaniem użytkownika. Stare tokeny sprzed wdrożenia SaaS wymagają ponownego logowania.

Frontend ma pole „Kod firmy” z listą nazw aktywnych firm. Pobiera wyłącznie `{slug,name}` z publicznego `/api/saas/tenant-options`; lista pomija `default`. Nie loguje automatycznie do `default`; zapamiętuje ostatnio wybraną firmę i obsługuje `/login?tenant=kod-firmy`. Proxy Next przekazuje wybraną firmę z cookie `tenant_slug` do API; cookie służy wyłącznie routowaniu, nie autoryzacji. Aplikacja mobilna może przesyłać nagłówek bez cookie i używać istniejących prywatnych endpointów zdjęć wydarzenia. Identyfikatory rekordów oraz uprawnienia sprawdzane są w bazie wybranej firmy.

Aktywne konto z panelu `/saas` loguje się tym samym adresem i hasłem do każdej aktywnej firmy przez `/login`. W tej firmie jest pracownikiem technicznym: nie jest powiązane z kartą pracownika i ma pełne uprawnienia, tak jak dawniej adres `admin.ec@gmail.com`. Sam adres nie nadaje praw — liczy się aktywne konto w `saas_administrator`. Lokalna tożsamość powstaje dopiero po poprawnym logowaniu. JWT pozostaje przypisany do jednej firmy. Wyłączenie centralnego konta blokuje także istniejące tokeny techniczne.

Panel `/saas` korzysta z losowych sesji ważnych godzinę, zapisywanych w bazie wyłącznie jako SHA-256. Przeglądarka przechowuje tę sesję w `sessionStorage`, oddzielnie od biznesowego JWT. Wylogowanie unieważnia sesję po stronie serwera. Logowanie SaaS ma limit 5 prób/minutę dla e-maila i adresu IP. Hasła kont klientów i SaaS są haszowane; brak domyślnego hasła SaaS.

| Metoda | Endpoint | Działanie |
|---|---|---|
| POST | `/api/saas/login` | `{email,password}` → `{token,expiresIn,email}` |
| POST | `/api/saas/logout` | Unieważnienie bieżącej sesji |
| GET | `/api/saas/tenants` | Lista klientów |
| POST | `/api/saas/tenants` | `{name,slug,ownerEmail,ownerPassword}` → nowy nieaktywny klient i stan instalacji |
| GET | `/api/saas/tenants/{uuid}` | Klient i historia |
| PATCH | `/api/saas/tenants/{uuid}/status` | `{status,version,reason}` |

Wszystkie endpointy SaaS oprócz logowania i `/api/saas/tenant-options` wymagają `Authorization: Bearer <sesja SaaS>`. JWT administratora firmy nie daje dostępu do SaaS. Sesja SaaS nie działa w API biznesowym. `201` przy tworzeniu oznacza zapisanie klienta; należy sprawdzić `provisioning`, zanim zaproponuje się aktywację.

## Instalacja

1. Zainstalować zależności Composer i uruchomić migracje centralnej bazy: `php bin/console doctrine:migrations:migrate --no-interaction`.
2. Operator MySQL nadaje aplikacji uprawnienia do generowanych baz: [przykład SQL](sql/saas-provisioning-grants.sql). Nie używać konta root w aplikacji.
3. Utworzyć osobnego superadministratora: `php bin/console app:saas:create-administrator operator@firma.pl`. Hasło jest pytaniem ukrytym. Automatyzacja może użyć `--password-stdin`; nie podawać hasła w argumentach procesu.
4. Otworzyć frontend `/saas`, zalogować się, dodać klienta i po gotowości bazy ustawić `active` z uzasadnieniem.
5. W `/login` użyć kodu klienta i przekazanego administratorowi firmy konta.

Dotychczasowy administrator techniczny firmy `default` otrzymuje jawną flagę `user.tenant_administrator`. Konta bez pracownika nie dostają już automatycznie wszystkich uprawnień; sam adres e-mail nie nadaje uprawnień administratora. Pole nie jest wystawione w formularzach edycji użytkownika.

## Kolejki, pliki i utrzymanie

### Administrator techniczny i przejęcie danych `default`

`php bin/console app:saas:enable-technical-administrator adres@firma.pl` tworzy konto centralne z istniejącego administratora firmy, jeśli go jeszcze nie ma, i zachowuje jego hasło. Aktywne konto z panelu SaaS wchodzi do firm bez osobnej flagi. Polecenie wymaga konta bez powiązania z pracownikiem i z flagą administratora firmy. Nie kopiuje danych logowania do nowych tenantów podczas ich zakładania.

`php bin/console app:saas:adopt-legacy-tenant mapa-smakow "Mapa Smaków" adres@firma.pl` pokazuje plan przeniesienia. Po wykonaniu backupu i zatrzymaniu workerów opcja `--execute` kopiuje wszystkie tabele biznesowe wraz z identyfikatorami i kluczami obcymi do osobnej bazy, sprawdza liczby wierszy, kopiuje prywatne media i zapisuje audyt. Tabele `saas_*` pozostają wyłącznie centralne. Dotychczasowy `default` staje się nieaktywny, a jego baza i pliki pozostają kopią źródłową. Polecenie obsługuje pierwsze wydzielenie danych z `default`; odmawia łączenia z istniejącym tenantem. Nie nadpisuje ani nie usuwa istniejących danych przy błędzie.

`php bin/console app:company:seed-catering-positions mapa-smakow UUID-FIRMY` dodaje 25 stanowisk cateringowych i wiąże je z istniejącymi działami Kuchnia, Logistyka, Czystość i higiena, Zarząd oraz Obsługa. Istniejące stanowiska są wykorzystywane ponownie, także przy innym zapisie wielkości liter. Pozostałe stanowiska pozostają zachowane.

Lokalne dane zostały przeniesione do tenanta **Mapa Smaków**, kod **`mapa-smakow`**. Logowanie do aplikacji: `/login?tenant=mapa-smakow`; panel zarządzania SaaS: `/saas`. Ten sam login i hasło z panelu wchodzą do aktywnej firmy.

Messenger zapisuje `TenantStamp` przy wysyłaniu i wybiera właściwą bazę przed transakcją obsługi. Wiadomości bez firmy, z nieaktualną wersją dostępu albo z zablokowanego klienta są odrzucane bez wykonania. Starszą kolejkę sprzed wdrożenia trzeba opróżnić przed aktualizacją lub przejrzeć ręcznie. Retry po zmianie statusu wymaga świadomego ponownego zlecenia operacji z aktualnym kontekstem. To nie jest mechanizm automatycznego wznawiania zadań po reaktywacji.

Nowe zdjęcia wydarzeń mają katalog UUID firmy poza `public`; metadane i pobrania są autoryzowane w bazie tej firmy. Firma `default` zachowuje dotychczasowe ścieżki. Importy i nowe awatary mają prefiks firmy. Awatary pozostają publicznymi zdjęciami profilowymi zgodnie z dotychczasowym mechanizmem. Pamięć podręczna referencji ORM jest rozdzielona według bazy i czyszczona po obsłudze wiadomości.

Po wdrożeniu kolejnych migracji biznesowych: najpierw kopie zapasowe, potem migracja centralnej bazy, `php bin/console app:saas:migrate-tenants` (lista oczekujących zmian), następnie `--execute`. Opcjonalnie `--tenant kod-firmy`. Klienci zawieszeni i nieaktywni również otrzymują migracje; logicznie usunięci są pomijani. Nie kierować zwykłego polecenia migracji na dowolnie wskazaną bazę z panelu. Nowe bazy mają zapisany punkt startowy aktualnych migracji. Przyszłe migracje dotyczące wyłącznie tabel `saas_*` muszą warunkowo pomijać bazy biznesowe. Przy generowaniu diff nie usuwać ręcznie zarządzanych tabel kontrolnych `saas_*`.

## Zgłoszenia pomocy

Pracownik firmy wysyła zgłoszenie z menu avatara („Pomoc”). Kategoria i temat są wymagane. Wiadomość jest wymagana, gdy nie ma załącznika. Dozwolone są PNG, JPG, JPEG i WebP, najwyżej 5 plików po 8 MB. Zgłoszenie zapisuje się w centralnej bazie (`support_ticket`, `support_message`, `support_attachment`), a nie w bazie firmy, więc właściciel SaaS widzi skrzynkę wszystkich klientów. Pliki leżą poza katalogiem publicznym, w `storage/support-<środowisko>/`.

Na pulpicie `/saas` kafelek „Zgłoszenia” pokazuje liczbę spraw nowych i w trakcie. Właściciel odczytuje wątek, nadaje status (`open`, `in_progress`, `waiting`, `resolved`, `closed`) i odpowiada. Autor widzi odpowiedź na tej samej stronie pomocy. Odpowiedź użytkownika przy statusie oczekującym, rozwiązanym lub zamkniętym wraca na „Nowe”.

| Metoda | Endpoint | Działanie |
|---|---|---|
| GET | `/api/support/meta` | Kategorie i statusy |
| GET, POST | `/api/support/tickets` | Lista własnych zgłoszeń, nowe zgłoszenie (JWT firmy) |
| GET | `/api/support/tickets/{uuid}` | Wątek autora |
| POST | `/api/support/tickets/{uuid}/replies` | Kolejna wiadomość autora |
| GET | `/api/saas/support/summary` | Liczniki na kafelek |
| GET | `/api/saas/support/tickets` | Skrzynka właściciela. Filtry: `status`, `category`, `tenant` (kod firmy), `q` |
| POST | `/api/saas/support/tickets/{uuid}/replies` | Odpowiedź, opcjonalnie ze statusem |
| PATCH | `/api/saas/support/tickets/{uuid}/status` | Sama zmiana statusu |

Migracja `Version20261117120000` tworzy tabele tylko tam, gdzie jest `saas_tenant`. Na bazach firm nie wykonuje zmian. Po wdrożeniu wystarczy migracja centralnej bazy i istniejące `app:saas:migrate-tenants --execute`. Nie ma nowych zmiennych środowiskowych.

Backup obejmuje centralny rejestr, wszystkie bazy klientów i katalogi plików. Przywrócenie pojedynczego klienta wymaga zgodnych danych i plików, bez podmieniania UUID firmy. Ten etap nie wdraża płatności, abonamentów, samodzielnej rejestracji ani trwałego kasowania danych. Przed wystawieniem publicznym pozostaje konfiguracja produkcyjnego HTTPS, dozwolonych originów, sekretów oraz polityki backupu i monitorowania.
