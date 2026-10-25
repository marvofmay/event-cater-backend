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

Konta SaaS są osobne i domyślnie nie dają dostępu do danych firm. Jawna flaga `saas_administrator.technical_access` pozwala administratorowi technicznemu logować się do aktywnych tenantów przez `/login`, przy użyciu centralnego hasła i wybraniu firmy. Lokalna tożsamość techniczna powstaje dopiero po poprawnym logowaniu i nie jest powiązana z pracownikiem firmy. JWT pozostaje przypisany do jednego tenanta. Wyłączenie centralnego konta lub flagi dostępu blokuje także istniejące tokeny techniczne. Sam e-mail nie nadaje uprawnień.

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

`php bin/console app:saas:enable-technical-administrator adres@firma.pl` nadaje jawny dostęp techniczny istniejącemu centralnemu administratorowi. Jeśli nie ma konta centralnego, tworzy je z istniejącego administratora dawnej bazy, zachowując jego hasło. Wymaga konta bez powiązania z pracownikiem i z flagą administratora firmy. Nie kopiuje danych logowania do nowych tenantów podczas ich zakładania.

`php bin/console app:saas:adopt-legacy-tenant mapa-smakow "Mapa Smaków" adres@firma.pl` pokazuje plan przeniesienia. Po wykonaniu backupu i zatrzymaniu workerów opcja `--execute` kopiuje wszystkie tabele biznesowe wraz z identyfikatorami i kluczami obcymi do osobnej bazy, sprawdza liczby wierszy, kopiuje prywatne media i zapisuje audyt. Tabele `saas_*` pozostają wyłącznie centralne. Dotychczasowy `default` staje się nieaktywny, a jego baza i pliki pozostają kopią źródłową. Polecenie obsługuje pierwsze wydzielenie danych z `default`; odmawia łączenia z istniejącym tenantem. Nie nadpisuje ani nie usuwa istniejących danych przy błędzie.

`php bin/console app:company:seed-catering-positions mapa-smakow UUID-FIRMY` dodaje 25 stanowisk cateringowych i wiąże je z istniejącymi działami Kuchnia, Logistyka, Czystość i higiena, Zarząd oraz Obsługa. Istniejące stanowiska są wykorzystywane ponownie, także przy innym zapisie wielkości liter. Pozostałe stanowiska pozostają zachowane.

Lokalne dane zostały przeniesione do tenanta **Mapa Smaków**, kod **`mapa-smakow`**. Konto **`admin.ec@gmail.com`** ma jawny centralny dostęp techniczny. Logowanie do aplikacji: `/login?tenant=mapa-smakow`; panel zarządzania SaaS: `/saas`. Dotychczasowe hasło konta pozostaje aktualne.

Messenger zapisuje `TenantStamp` przy wysyłaniu i wybiera właściwą bazę przed transakcją obsługi. Wiadomości bez firmy, z nieaktualną wersją dostępu albo z zablokowanego klienta są odrzucane bez wykonania. Starszą kolejkę sprzed wdrożenia trzeba opróżnić przed aktualizacją lub przejrzeć ręcznie. Retry po zmianie statusu wymaga świadomego ponownego zlecenia operacji z aktualnym kontekstem. To nie jest mechanizm automatycznego wznawiania zadań po reaktywacji.

Nowe zdjęcia wydarzeń mają katalog UUID firmy poza `public`; metadane i pobrania są autoryzowane w bazie tej firmy. Firma `default` zachowuje dotychczasowe ścieżki. Importy i nowe awatary mają prefiks firmy. Awatary pozostają publicznymi zdjęciami profilowymi zgodnie z dotychczasowym mechanizmem. Pamięć podręczna referencji ORM jest rozdzielona według bazy i czyszczona po obsłudze wiadomości.

Po wdrożeniu kolejnych migracji biznesowych: najpierw kopie zapasowe, potem migracja centralnej bazy, `php bin/console app:saas:migrate-tenants` (lista oczekujących zmian), następnie `--execute`. Opcjonalnie `--tenant kod-firmy`. Klienci zawieszeni i nieaktywni również otrzymują migracje; logicznie usunięci są pomijani. Nie kierować zwykłego polecenia migracji na dowolnie wskazaną bazę z panelu. Nowe bazy mają zapisany punkt startowy aktualnych migracji. Przyszłe migracje dotyczące wyłącznie tabel `saas_*` muszą warunkowo pomijać bazy biznesowe. Przy generowaniu diff nie usuwać ręcznie zarządzanych tabel kontrolnych `saas_*`.

Backup obejmuje centralny rejestr, wszystkie bazy klientów i katalogi plików. Przywrócenie pojedynczego klienta wymaga zgodnych danych i plików, bez podmieniania UUID firmy. Ten etap nie wdraża płatności, abonamentów, samodzielnej rejestracji ani trwałego kasowania danych. Przed wystawieniem publicznym pozostaje konfiguracja produkcyjnego HTTPS, dozwolonych originów, sekretów oraz polityki backupu i monitorowania.
