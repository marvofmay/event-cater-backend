# Adresy i usługi środowiska Event Cater

## Środowisko lokalne — Mapa Smaków (25.10.2026)

- Logowanie: http://localhost:3000/login?tenant=mapa-smakow — wybierz firmę **Mapa Smaków**, kod `mapa-smakow`.
- Stanowiska: http://localhost:3000/manage/positions/list — 25 aktywnych stanowisk cateringowych przypisanych do pięciu działów.
- Konto z panelu SaaS loguje się tym samym adresem i hasłem do każdej aktywnej firmy jako pracownik techniczny z pełnymi uprawnieniami. Panel: http://localhost:3000/saas.
- Wszystkie dotychczasowe dane biznesowe skopiowano do osobnej bazy Mapy Smaków z zachowaniem powiązań. Baza źródłowa pozostała zachowana; tenant `default` jest nieaktywny.
- Backup sprzed migracji: lokalny plik `var/backups/before-mapa-smakow-20261025.sql`, wyłączony z Git.

## Przypomnienia o terminach (kompletacja / kuchnia)

Komenda: `docker exec service-company php bin/console app:notify-event-deadlines`  
(okno domyślne 24 h; flaga `--within-hours=`). W `docker-compose` działa usługa `worker-event-deadline-reminders` (co godzinę). Szczegóły: [docs/notification-settings.md](docs/notification-settings.md).

## Odbiorcy zdarzeń z „Tytuły i treści”

Zakładka **Tytuły i treści** w `/settings/notifications` ustala tytuł, treść i włączone kanały. Odbiorcy są wyliczani w chwili zdarzenia. Pusta lista odbiorców oznacza, że wiadomość nie wychodzi — system nie wysyła jej do administratorów firmy.

### Kiedy wiadomość w ogóle dochodzi

1. Zdarzenie jest włączone w ustawieniach firmy.
2. Dla kanału (systemowy, e-mail, SMS) jest włączony szablon tego zdarzenia, a sam kanał jest włączony globalnie.
3. Odbiorca ma konto użytkownika powiązane z kartą pracownika. Pracownik bez konta nic nie dostaje.
4. Kanał systemowy dochodzi zawsze, gdy jest włączony w firmie. E-mail i SMS dochodzą, gdy odbiorca ich u siebie nie wyłączył. SMS wymaga też skonfigurowanego Brevo.
5. Przy wiadomościach o konkretnym wydarzeniu cateringowym liczą się jeszcze kanały z panelu **Powiadomienia wydarzenia** na karcie tego wydarzenia. Wyłączenie wszystkich kanałów na wydarzeniu zatrzymuje te wiadomości.

Jedna osoba pasująca do kilku ról dostaje daną wiadomość raz.

### Dwie listy odbiorców

**Wiadomość osobista** idzie do jednego wskazanego pracownika. Panel **Powiadomienia wydarzenia** jej nie przekierowuje: dopisanie albo wykluczenie kogoś na karcie wydarzenia tej wiadomości nie zmienia.

**Wiadomość do ról** buduje listę z ról przy tym wydarzeniu, a potem stosuje panel **Powiadomienia wydarzenia**:

| Rola | Kto to jest |
| --- | --- |
| Koordynator | pracownik ustawiony jako koordynator wydarzenia |
| Obsada | każdy pracownik dopisany do obsady tego wydarzenia |
| Kompletacja | pracownik wskazany jako odpowiedzialny za listę kompletacji |
| Kuchnia | pracownik wskazany jako odpowiedzialny na ostatniej karcie kuchni tego wydarzenia |
| Załadunek | osoby z obsady z funkcją kierowca albo logistyka |

Osoba odznaczona na karcie wydarzenia wypada ze wszystkich wiadomości do ról. Osoba dopisana jako dodatkowy odbiorca wchodzi do wszystkich wiadomości do ról tego wydarzenia, także tych, których jej rola normalnie nie obejmuje.

Część wiadomości do ról wychodzi tylko wtedy, gdy status wydarzenia to **zaplanowane**, **potwierdzone** albo **w trakcie**. Przy statusie **szkic**, **anulowane** albo **zakończone** te wiadomości są pomijane. Przy każdym zdarzeniu poniżej jest napisane, czy ten warunek obowiązuje.

**Utworzenie wydarzenia** nie wychodzi samo. Organizator wysyła je raz przełącznikiem „Wyślij powiadomienia”. Pozostałe zdarzenia z tej listy, w tym zmiana statusu i aktualizacja danych, wychodzą automatycznie w chwili zdarzenia.

### Import firm

**Kiedy:** po zakończeniu importu firm — zarówno gdy import się uda, jak i gdy zakończy się błędem. W treści jest status importu.

**Odbiorca:** użytkownik, który ten import uruchomił. To wiadomość osobista, niezależna od wydarzeń cateringowych.

### Import oddziałów / departamentów

**Kiedy:** po zakończeniu importu oddziałów, przy sukcesie i przy błędzie.

**Odbiorca:** użytkownik, który ten import uruchomił.

### Import pracowników

**Kiedy:** po zakończeniu importu pracowników, przy sukcesie i przy błędzie.

**Odbiorca:** użytkownik, który ten import uruchomił.

### Przypisanie do obsady wydarzenia

**Kiedy:** w momencie pierwszego dopisania pracownika do obsady tego wydarzenia, ale dopiero po wysłaniu powiadomienia „Utworzenie wydarzenia”. Wcześniej ta wiadomość czeka, żeby ta sama osoba nie dostała jej razem z powiadomieniem o utworzeniu. Zapis zmian u osoby, która już jest w obsadzie (funkcje, godziny, notatka), tej wiadomości nie wysyła.

**Odbiorca:** tylko ten nowo dopisany pracownik. Wiadomość osobista. W treści są jego funkcje na tym wydarzeniu.

Po wysłaniu powiadomienia o utworzeniu wychodzi także przy wydarzeniu w szkicu. Kanały biorą się z panelu tego wydarzenia.

### Usunięcie z obsady wydarzenia

**Kiedy:** w momencie zdjęcia pracownika z obsady, po wysłaniu powiadomienia „Utworzenie wydarzenia”. Zanim ono pójdzie, zdjęcie z obsady tej wiadomości nie wysyła.

**Odbiorca:** tylko zdjęty pracownik. Wiadomość osobista.

### Wyznaczenie do kompletacji

**Kiedy:** przy zapisie listy kompletacji, gdy pole odpowiedzialnego zmienia się na inną osobę, i dopiero po wysłaniu powiadomienia „Utworzenie wydarzenia”. Wcześniej ta wiadomość czeka. Zapis z tym samym odpowiedzialnym tej wiadomości nie wysyła. Wyczyszczenie odpowiedzialnego też jej nie wysyła.

**Odbiorca:** tylko nowo wskazany odpowiedzialny za kompletację. Wiadomość osobista. Sam fakt wyznaczenia nie oznacza, że ta osoba jest w obsadzie wydarzenia.

### Wyznaczenie odpowiedzialnego kuchni

**Kiedy:** przy utworzeniu karty kuchni, jeśli od razu jest wskazany odpowiedzialny, oraz przy zapisie szkicu karty, gdy odpowiedzialny zmienia się na inną osobę. Wychodzi dopiero po wysłaniu powiadomienia „Utworzenie wydarzenia”. Wcześniej czeka. Zapis z tym samym odpowiedzialnym tej wiadomości nie wysyła.

**Odbiorca:** tylko nowo wskazany odpowiedzialny kuchni. Wiadomość osobista.

### Utworzenie wydarzenia

**Kiedy:** nie przy kliknięciu „Utwórz wydarzenie”. Organizator wysyła je ręcznie przełącznikiem „Wyślij powiadomienia” w zakładkach Zespół, Kompletacja albo Powiadomienia, gdy obsada i osoba kompletująca są już zapisane. Idzie raz. Drugie włączenie nic nie wysyła. Nowe wydarzenie jest szkicem, a ta wiadomość i tak może wyjść (warunek statusu jej nie dotyczy). Wydarzenia utworzone przed tą zmianą są oznaczone jako już wysłane.

**Odbiorcy (wiadomość do ról):** koordynator, obsada, kompletacja i kuchnia oraz osoby wskazane dodatkowo przy tej wysyłce. Wskazana osoba zostaje dodatkowym odbiorcą wydarzenia.

### Aktualizacja wydarzenia

**Kiedy:** automatycznie po zapisie edycji wydarzenia, gdy zmieni się co najmniej jedno z pól: data, strefa czasowa, godzina gotowości, start, koniec, miejsce, liczba gości, koordynator. Zmiana tytułu, opisu albo klienta tej wiadomości nie wysyła. Zapis bez zmiany tych pól też jej nie wysyła.

**Odbiorcy:** koordynator, obsada, kompletacja i kuchnia.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie.

### Zmiana statusu wydarzenia

**Kiedy:** automatycznie po każdej udanej zmianie statusu: na zaplanowane, potwierdzone, w trakcie, zakończone albo anulowane.

**Odbiorcy:** koordynator i obsada. Kompletacja i kuchnia tej wiadomości nie dostają, chyba że są na liście dodatkowych odbiorców albo pełnią też rolę koordynatora lub osoby z obsady.

**Status wydarzenia:** warunek „tylko aktywne” tu nie działa. Wejście w zakończone i anulowane też wysyła tę wiadomość do aktualnej obsady i koordynatora.

### Zmiana menu wydarzenia

**Kiedy:** po zapisie menu, gdy lista pozycji naprawdę się zmieni. Zapis identycznego menu tej wiadomości nie wysyła.

**Odbiorcy:** obsada, kompletacja i kuchnia. Sam koordynator dostaje ją wtedy, gdy jest też w obsadzie, jest odpowiedzialny za kompletację albo kuchnię, albo jest dopisany jako dodatkowy odbiorca.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie.

### Zmiana statusu kompletacji

**Kiedy:** przy trzech krokach listy kompletacji:

- zatwierdzenie listy (status: zatwierdzona),
- rozpoczęcie kompletacji (status: w kompletacji),
- potwierdzenie załadunku (status: załadowane).

Osobny krok „lista złożona / gotowa do załadunku” wysyła inne zdarzenie, opisane niżej.

**Odbiorcy:** obsada i kompletacja.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie.

### Potwierdzenie złożenia listy kompletacji

**Kiedy:** gdy odpowiedzialny oznacza listę jako złożoną (status listy przechodzi na gotową do załadunku) i wszystkie pozycje są skompletowane.

**Odbiorcy:** koordynator oraz kierowca i logistyka z obsady. Osoba, która kompletowała listę, z tej wiadomości wypada, także wtedy, gdy jest jednocześnie koordynatorem.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie.

### Wydanie karty kuchni

**Kiedy:** w momencie wydania karty kuchni do produkcji (status karty: wydana). Start produkcji i anulowanie karty osobnej wiadomości nie wysyłają.

**Odbiorcy:** odpowiedzialny kuchni i koordynator.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie.

### Gotowość kuchni

**Kiedy:** gdy karta kuchni zostaje oznaczona jako gotowa.

**Odbiorca:** odpowiedzialny kuchni. Koordynator dostaje ją tylko jako dodatkowy odbiorca albo gdy sam jest odpowiedzialnym kuchni.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie.

### Przekazanie produkcji kuchni

**Kiedy:** gdy produkcja kuchni zostaje przekazana (status karty: przekazana).

**Odbiorca:** odpowiedzialny kuchni, na tych samych zasadach co przy gotowości kuchni.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie.

### Propozycja rewizji kuchni

**Kiedy:** gdy ktoś zgłasza propozycję zmiany wydanej karty kuchni.

**Odbiorcy:** koordynator i odpowiedzialny kuchni.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie.

### Akceptacja rewizji kuchni

**Kiedy:** gdy oczekująca propozycja rewizji zostaje przyjęta.

**Odbiorcy:** koordynator i odpowiedzialny kuchni.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie.

### Odrzucenie rewizji kuchni

**Kiedy:** gdy oczekująca propozycja rewizji zostaje odrzucona.

**Odbiorcy:** koordynator i odpowiedzialny kuchni.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie.

### Przypomnienie o terminie kompletacji

**Kiedy:** raz na listę, z komendy `app:notify-event-deadlines` (w docker-compose co godzinę, okno 24 h). Wychodzi, gdy termin kompletacji jest ustawiony, przypomnienie jeszcze nie poszło, lista nie jest załadowana, a termin mieści się w oknie od godziny wstecz do 24 godzin do przodu.

**Odbiorcy:** kompletacja i koordynator.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie. Szkic, anulowane i zakończone są pomijane jeszcze przed wysyłką.

### Przypomnienie o terminie kuchni

**Kiedy:** raz na kartę kuchni, tą samą komendą co wyżej. Wychodzi, gdy karta ma termin gotowości, przypomnienie jeszcze nie poszło, karta nie jest przekazana ani anulowana, a termin mieści się w tym samym oknie (godzina wstecz do 24 godzin do przodu).

**Odbiorcy:** odpowiedzialny kuchni i koordynator.

**Status wydarzenia:** zaplanowane, potwierdzone albo w trakcie.

## Logowanie nowego pracownika

Po utworzeniu pracownika (formularz lub import) powstaje konto użytkownika:

- **Login:** pierwszy numer telefonu pracownika (znormalizowany).
- **Hasło startowe:** PESEL (także fikcyjny, jeśli tak oznaczono).
- Przy pierwszym logowaniu system wymaga **zmiany hasła** (`must_change_password`).

Zmiana telefonu, e-maila lub PESEL u istniejącego pracownika **nie resetuje** hasła. Szczegóły: [docs/employees-import.md](docs/employees-import.md).

## Uprawnienia katalogu avatarów

Przy podmontowaniu projektu z hosta Apache (`www-data` w kontenerze) musi móc tworzyć katalogi w `public/upload/avatars`. Po nowym wdrożeniu lub przy błędzie `mkdir(): Permission denied` ustaw grupę i dziedziczenie grupy katalogów (bez nadawania zapisu wszystkim użytkownikom):

```bash
docker exec --user root service-company sh -c 'mkdir -p public/upload/avatars && chgrp www-data public/upload public/upload/avatars && chmod 2775 public/upload public/upload/avatars && find public/upload/avatars -mindepth 1 -type d -exec chgrp www-data {} + -exec chmod 2775 {} +'
```

## Poprzednie środowisko chmurowe

Stan sprawdzony: 23.10.2026. Zestawienie dotyczy aktualnie uruchomionego środowiska chmurowego.

`localhost` oznacza maszynę, na której działa środowisko. Aby otworzyć usługę ze swojego komputera, użyj podglądu lub przekierowania odpowiedniego portu w środowisku chmurowym. Sam adres `localhost` na Twoim komputerze nie prowadzi do tej maszyny.

## Aplikacja i panele WWW

| Usługa | Adres w środowisku | Port | Stan / przeznaczenie |
|---|---|---|---|
| Frontend | http://localhost:3000 | 3000 | Uruchomiony, aplikacja Next.js |
| Logowanie pracowników | http://localhost:3000/login | 3000 | Logowanie kodem firmy, e-mailem i hasłem |
| Panel superadministratora SaaS | http://localhost:3000/saas | 3000 | Klienci systemu, tworzenie baz, statusy i historia zmian |
| Wydarzenia | http://localhost:3000/catering/events | 3000 | Obsługiwane wydarzenia |
| Baza miejsc | http://localhost:3000/catering/venues | 3000 | Powtarzalne miejsca i adresy wydarzeń |
| Sprzęt | http://localhost:3000/catering/equipment | 3000 | Katalog sprzętu |
| Produkty i materiały | http://localhost:3000/catering/supplies | 3000 | Katalog materiałów i produktów do przygotowania cateringu |
| Szablony przygotowania | http://localhost:3000/catering/templates | 3000 | Szablony list kompletacyjnych |
| Kuchnia | http://localhost:3000/catering/kitchen | 3000 | Plan przygotowania dań i karty kuchenne |
| Backend API | http://localhost:81/api | 81 | Uruchomiony, Symfony; konkretne endpointy wymagają autoryzacji. Sam `/api` nie jest stroną startową |
| RabbitMQ Management | http://localhost:15672 | 15672 | Uruchomiony, panel kolejek i wiadomości |
| Mailpit | http://localhost:8025 | 8025 | Uruchomiony, podgląd e-maili wysyłanych przez aplikację |
| phpMyAdmin | http://localhost:8081 | 8081 | **Nie jest obecnie uruchomiony.** Taki port przewiduje plik `docker-compose.yaml` backendu |

Konto SaaS lokalnego środowiska: [var/saas-access.txt](var/saas-access.txt). Plik jest lokalny i wyłączony z Git. Nie kopiować haseł do tego dokumentu.

Dotychczasowa firma ma kod `default`. Nowe firmy logują się kodem nadanym w panelu SaaS. Szczegóły: [docs/saas.md](docs/saas.md).

## Usługi techniczne — poza przeglądarką

| Usługa | Adres z maszyny środowiska | Adres z kontenera API | Przeznaczenie |
|---|---|---|---|
| MySQL | `127.0.0.1:3306` | `service-company-db:3306` | Baza centralna i domyślna firma: `service_company`; firmy SaaS: `ec_tenant_<UUID bez myślników>` |
| Redis | `127.0.0.1:6379` | `redis:6379` | Usługa Redis; obecnie brak osobnego panelu WWW |
| RabbitMQ AMQP | `127.0.0.1:5672` | `rabbitmq:5672` | Połączenie aplikacji i workerów z kolejką; panel WWW używa portu 15672 |
| Mailpit SMTP | `127.0.0.1:1025` | `mailpit:1025` | Przechwytywanie e-maili w środowisku deweloperskim; podgląd na porcie 8025 |
| Mercure | `http://localhost:3001/.well-known/mercure` | `http://mercure:3001/.well-known/mercure` | Aktualizacje w czasie rzeczywistym / SSE; nie jest panelem administracyjnym |

Porty MySQL, Redis, AMQP i SMTP wymagają odpowiedniego klienta — nie otwiera się ich jak stron WWW. Mercure bez parametrów subskrypcji może zwrócić HTTP 400; nie oznacza to awarii usługi.

Hasła i parametry połączeń są w lokalnej konfiguracji środowiska (`.env` i konfiguracja Compose), nie w tym pliku.

## Uruchomione kontenery

- `event-cater-api` — backend, port hosta 81 → port kontenera 8000.
- `service-company-db` — MySQL.
- `rabbitmq` — broker AMQP i panel administracyjny.
- `redis` — Redis.
- `mailpit` — testowy serwer poczty.
- `mercure` — hub aktualizacji SSE.

Frontend działa jako proces Next.js na porcie 3000, poza Dockerem.

## Sprawdzenie i ponowne uruchomienie

Lista kontenerów i portów:

```bash
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
```

Backend w tym środowisku korzysta z dodatkowej konfiguracji:

```bash
docker compose -f /workspace/cloud-setup/compose.yaml up -d api
```

Frontend, jeśli nie jest uruchomiony (z katalogu `/workspace/event-cater-frontend`, po instalacji zależności i wykonaniu buildu):

```bash
API_INTERNAL_URL=http://localhost:81 npm start -- --port 3000 --hostname 0.0.0.0
```

Nie uruchamiać drugiej instancji na zajętym porcie. Pliki Compose w repozytoriach opisują również inne warianty uruchomienia; powyższa tabela przedstawia rzeczywisty stan tego środowiska.
