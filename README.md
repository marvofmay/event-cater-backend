# Adresy i usługi środowiska Event Cater

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
