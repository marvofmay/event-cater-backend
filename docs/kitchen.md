# Kuchnia — produkcja na przyjęcia i wydarzenia

Wdrożony zakres backendu: karty produkcyjne dla cateringu wyjazdowego i imprez obsługiwanych w bazie firmy, dzienny plan kuchni, jawne rewizje menu, wykonanie/przekazanie oraz wersjonowany wydruk PDF. Moduł nie dotyczy subskrypcji posiłków pudełkowych.

## Praca zespołu

1. Koordynator tworzy roboczą kartę z menu wydarzenia. Jedno wydarzenie ma jedną kartę; kolejne zmiany są jej rewizjami. Różne wydarzenia i warianty dietetyczne zachowują odrębne pozycje, nawet gdy potrawy mają taką samą nazwę.
2. Ustala `productionDate` (również dzień przed wydarzeniem), `dueAt`, odpowiedzialnego pracownika i instrukcje. `serviceMode=off_site` oznacza wyjazd i przekazanie do transportu; `on_site` oznacza obsługę w bazie firmy. Domyślny termin pochodzi z godziny gotowości cateringu, ale można ustawić wcześniejszy.
3. Wydaje kartę do kuchni: `draft → released → in_production → ready → handed_over`. Wydanie wymaga znanej liczby gości, aktywnego odpowiedzialnego, terminu oraz co najmniej jednej pozycji z dodatnią liczbą porcji. Produkcja nie może przypadać po dacie wydarzenia, a termin wydania potraw po godzinie gotowości cateringu.
4. Kucharze otrzymują osobny PDF dla każdego wydarzenia. Dokument zawiera wydarzenie, miejsce, liczbę gości, numer rewizji, termin w strefie czasowej wydarzenia, potrawy, wielkość porcji, diety, zadeklarowane alergeny, uwagi, instrukcje podania/transportu i puste pola wykonania oraz przekazania. `packing` jest opcjonalną instrukcją, np. „bufet: bemar”, „półmiski na stoły”, „GN z pokrywą do transportu”. System nie wymaga pakowania pojedynczych posiłków.
5. W systemie zapisuje się bezwzględne liczby przygotowanych i przekazanych porcji. Przekazanych nie może być więcej niż przygotowanych. Nadwyżki pozostają widoczne. Gotowość i końcowe przekazanie są osobnymi potwierdzeniami; liczby nie nadają tych statusów automatycznie. Przy obsłudze w bazie przekazanie oznacza przekazanie do obsługi sali/bufetu.

Przykład: karta A — 90 porcji zupy standardowej i 10 wegańskiej na urodziny; karta B — 50 porcji na spotkanie świąteczne. Plan dzienny pokazuje obie karty. Nie zmienia ich w jedną pozycję „150 zup” i nie zakłada, że każdy wariant należy przygotować dla wszystkich gości. W tej wersji ilości produkcji są całkowitymi porcjami, a wielkość porcji jest opisem (np. `250 ml`); automatyczne wyliczanie kilogramów/litrów partii należy do przyszłych receptur.

## Zmiana zamówienia w trakcie gotowania

Karta zawiera migawkę danych produkcyjnych wydarzenia: menu, liczbę gości, datę, miejsce i termin gotowości. Zmiany sprzętu, zdjęć czy kontaktów nie unieważniają karty. `sourceChanged` sygnalizuje różnicę względem bieżącego wydarzenia; `currentSource` udostępnia aktualne dane do uzgodnienia zmian. Menu nie podmienia się kucharzom automatycznie.

Koordynator przesyła pełen proponowany plan przez `POST .../revisions`, zachowując UUID istniejących pozycji. `revisionDiff` pokazuje pozycje dodane, usunięte i zmienione, w tym różnicę liczby porcji. Kuchnia akceptuje lub odrzuca propozycję osobnym uprawnieniem. Jednocześnie może oczekiwać tylko jedna propozycja. Ponowna zmiana wydarzenia powoduje konflikt przy akceptacji — trzeba odrzucić propozycję i uzgodnić nową.

Menu wydarzenia nie posiada stabilnych identyfikatorów pozycji. Dlatego system nie dopasowuje automatycznie potraw po nazwie ani po kolejności. Początkowy import tworzy UUID pozycji kuchennych; aktualizacje wymagają jawnego zachowania tych identyfikatorów. Zmiany diet, alergenów, nazwy lub wielkości już produkowanej potrawy wymagają nowej pozycji. Starą pozycję z wykonaniem należy zachować, np. z `requiredPortions=0`. Nie można jej usunąć i przypisać wykonanych porcji innej potrawie.

Akceptacja zachowuje aktualne liczby wykonania, również zapisane po zgłoszeniu propozycji. Zmniejszenie zapotrzebowania nie zeruje gotowych potraw. Rewizja wraca do `in_production`, unieważnia potwierdzenie przekazania i tworzy niezmienny zapis zatwierdzonego planu. Późniejsze zmniejszenie liczb fizycznych wymaga powodu (10–2000 znaków) i trafia do historii. Korekta po końcowym przekazaniu unieważnia jego potwierdzenie.

Nieaktualne menu lub oczekująca propozycja blokują rozpoczęcie z wydanej karty oraz potwierdzenie gotowości/przekazania. Wykonane porcje nadal można ewidencjonować, żeby nie utracić rzeczywistego stanu pracy. Anulowanie/zakończenie wydarzenia blokuje dalszą produkcję, ale nie usuwa karty i historii. Kartę można wtedy jawnie anulować z powodem. Anulowana karta jest tylko do odczytu.

## API

Wszystkie ścieżki wymagają JWT. Specyfikacja: [kitchen.openapi.json](kitchen.openapi.json).

| Metoda i ścieżka | Cel |
| --- | --- |
| `POST /api/events/{eventUuid}/kitchen-orders` | Utworzenie roboczej karty z menu |
| `GET /api/events/{eventUuid}/kitchen-orders` | Karta danego wydarzenia; 404, jeśli nieutworzona |
| `GET /api/kitchen-orders/staff-options?q=...` | Aktywni pracownicy do planowania kuchni; `plan_kitchen_production` |
| `GET /api/kitchen-orders?date=2026-12-30` | Plan kuchni dla dnia produkcji; opcjonalne `status`, `page`, `limit` |
| `GET /api/kitchen-orders/{uuid}` | Karta, wykonanie, bieżące źródło i propozycja zmiany |
| `PUT /api/kitchen-orders/{uuid}` | Pełny plan roboczy i świadome odświeżenie migawki wydarzenia |
| `POST .../{release,start,ready,hand-over,cancel}` | Zmiana etapu; anulowanie wymaga `reason` |
| `PUT .../lines/{lineUuid}/progress` | `preparedPortions` i/lub `handedOverPortions`; przy zmniejszeniu `correctionReason` |
| `POST .../revisions` | Propozycja pełnego planu z powodem i aktualnym `eventVersion` |
| `POST .../revisions/accept` | Akceptacja bieżącej propozycji przez kuchnię |
| `POST .../revisions/reject` | Odrzucenie propozycji z powodem |
| `GET .../revisions/{number}` | Historyczna, niezmienna rewizja z informacją, czy zastąpiona |
| `GET .../history` | Stronicowana historia z autorem, powodem i wersją zapisu |
| `GET .../pdf?revision=1` | Wydruk konkretnej zatwierdzonej rewizji; domyślnie aktualna |

Przykładowe utworzenie:

```json
{
  "eventVersion": 4,
  "serviceMode": "on_site",
  "productionDate": "2026-12-30",
  "dueAt": "2026-12-31T10:00:00+01:00",
  "responsibleUuid": "550e8400-e29b-41d4-a716-446655440000",
  "notes": "Przyjęcie urodzinowe, bufet i półmiski"
}
```

Każda zmiana istniejącej karty wymaga jej `version`. Utworzenie, zapis szkicu i propozycja rewizji wymagają również `eventVersion`; wersje wydarzenia i karty są niezależne. Konflikt zwraca 409, reguła biznesowa 422, brak zasobu 404. UUID pozycji jest sprawdzany w obrębie karty. Liczby porcji to liczby całkowite JSON 0–1 000 000, bez wartości zmiennoprzecinkowych i tekstów.

Plan dzienny jest stronicowany (domyślnie 20, maks. 100 kart), uporządkowany po numerze karty i nie łączy potraw z różnych wydarzeń. Filtr daty jest wymagany i odnosi się do `productionDate`, nie do daty wydarzenia. PDF ma nagłówki `private, no-store`; szablon koduje dane użytkownika i nie pobiera zasobów zewnętrznych. Wydruk historyczny, anulowany lub wymagający uzgodnienia zmian ma wyraźne oznaczenie. Istniejący papier nie zmienia się po rewizji — numer aktualnej karty trzeba sprawdzić w systemie i wymienić nieaktualny wydruk.

## Uprawnienia i technika

Moduł: `kitchen`, dostęp: `kitchen.orders`. Każda operacja wymaga `view`, lista dodatkowo `list`, PDF dodatkowo `pdf`.

- `plan_kitchen_production`: tworzenie i planowanie kart/propozycji; utworzenie wymaga też `view` do wydarzeń.
- `release_kitchen_production`: wydanie karty do kuchni.
- `prepare_kitchen_meals`: rozpoczęcie, przygotowane porcje, potwierdzenie gotowości.
- `hand_over_kitchen_meals`: przekazane porcje oraz potwierdzenie przekazania.
- `accept_kitchen_revision`: akceptacja/odrzucenie zmian przez kuchnię.
- `cancel_kitchen_order`: anulowanie karty.

Zapis obu liczb jednocześnie wymaga obu uprawnień. Kucharz może korzystać z karty przez dostęp do Kuchni bez dostępu do całej kartoteki wydarzeń i kontaktów klienta. Uprawnienia nie są automatycznie nadawane istniejącym rolom.

DDD/CQRS: agregat `KitchenOrder` posiada plan, pozycje i wykonanie. Reguły i wartości planu są w domenie. Komendy używają istniejącego `command.bus` i transakcji Doctrine, odczyty `query.bus`. Integracja z wydarzeniami i pracownikami odbywa się przez porty. Karta przechowuje migawki zamiast relacji ORM do agregatów innych modułów. Transakcje blokują najpierw wydarzenie, potem kartę, a wersjonowanie chroni równoległe zapisy. Każdy zapis ma historię z rosnącą wersją; wydane rewizje mają osobne, niezmienne migawki. Porównywanie źródła jest odporne na zmianę kolejności kluczy JSON przez MySQL.

Migracja `Version20261023110000` dodaje trzy tabele: `kitchen_order`, `kitchen_revision`, `kitchen_history`. Nie tworzy ani nie wydaje kart automatycznie dla starych wydarzeń. Po migracji uruchomić `app:initialize-system-default-data`, aby dopisać moduł, dostęp i uprawnienia. Migracja w dół usuwa dane Kuchni; pozostałe moduły zachowuje.

## Kolejne etapy

Wdrożono backend, PDF oraz ekrany w `/catering/kitchen` i w zakładce Kuchnia wydarzenia. Receptury, zakupy, magazyn i wspólne partie produkcyjne pozostają kolejnymi etapami. Gotowość kuchni jest odczytywana z jej karty; nie zmienia automatycznie ilości sprzętu, załadunku ani statusu wydarzenia. Nie wysyła powiadomień.

Przyszłe receptury będą wersjonowane i obliczą składniki dla jawnego uzysku. Klucze `orderUuid + lineUuid + revision` pozwolą tworzyć identyfikowalne zapotrzebowanie do zakupów. Mleko wykorzystane do deseru i mleko do ekspresów trzeba ująć jako odrębne zapotrzebowania, a następnie zsumować, unikając powielenia tego samego źródła. Plan rozwoju: [kitchen-plan.md](kitchen-plan.md).
