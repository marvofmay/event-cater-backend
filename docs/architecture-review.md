# Przegląd architektury backendu

## Stan obecny

Kod jest podzielony na moduły biznesowe oraz warstwy `Application`, `Domain`,
`Infrastructure` i `Presentation`. Komendy i zapytania mają osobne handlery, a
warstwa domenowa definiuje interfejsy repozytoriów. Jest to dobry fundament pod
CQRS i architekturę heksagonalną.

Nie jest to jednak jeszcze ścisły CQRS: modele zapisu i odczytu często korzystają
z tych samych encji Doctrine, a część serwisów importu w `Domain` zależy od
Symfony Messenger, tłumaczeń i PhpSpreadsheet. Te zależności techniczne powinny
docelowo znajdować się w adapterach `Application`/`Infrastructure`.

## Najważniejsze problemy i zalecenia

### Dwa kontrakty wykonania importu

Importy nie mają jednego kontraktu HTTP i nie powinny być traktowane jednakowo:

| Tryb | Typy importu | Odpowiedź HTTP | Wynik końcowy |
| --- | --- | --- | --- |
| synchroniczny | role, stanowiska, branże, typy umów | `201` albo `422` z błędami wierszy | klient dostaje wynik natychmiast |
| asynchroniczny (RabbitMQ) | firmy, działy, pracownicy | `202` z `importUUID` | status, logi/raport i powiadomienie po zakończeniu workera |

Konfiguracja routingu Messengera jest źródłem prawdy o trybie wykonania; sama
obecność `command.bus` nie oznacza wykonania synchronicznego. Dla importów
asynchronicznych identyfikator zwracany w odpowiedzi musi być używany do śledzenia
statusu i powiązania wpisu historii. Walidacyjne zakończenie `FAILED` już generuje
zdarzenie powiadomienia. Dodatkowy listener błędu workera obsługuje również
nieoczekiwany wyjątek po wyczerpaniu retry: ustawia `FAILED`, zapisuje log błędu i
wysyła istniejące zdarzenie notyfikacyjne do właściciela importu.

Encje `Import`, `ImportLog` oraz `ImportReport` tworzą podstawę historii, ale w
aplikacji nadal brakuje kompletnego query/API listy i szczegółów importu. Należy
dodać odczyt ograniczony do właściciela (lub administratora), zawierający status,
czasy, liczniki raportu i błędy, oraz endpoint pobrania raportu. Nie należy
zwracać szczegółów błędów asynchronicznych z pierwotnego żądania HTTP, bo w chwili
odpowiedzi `202` worker jeszcze ich nie zna.

### Import XLSX i wydajność

Dotychczas każde wywołanie `import()` ponownie otwierało i parsowało plik.
Importery wywołują tę metodę podczas ładowania referencji, walidacji, przygotowania
i zwracania wyniku; niektóre robią to dodatkowo dla każdego walidowanego wiersza.
Powodowało to wielokrotny odczyt pliku, a w najgorszym przypadku koszt zbliżony do
O(n²). `XLSXIterator` przechowuje teraz jeden arkusz i jedną zmaterializowaną
tablicę wierszy na plik, czyta tylko dane oraz zwalnia arkusz po zmianie pliku.

Dalszy krok dla bardzo dużych plików to port zwracający `iterable`/generator i
adapter oparty na filtrze odczytu PhpSpreadsheet albo czytniku strumieniowym.
Walidację i zapis należy wtedy wykonywać partiami (np. 250–1000 rekordów), z
`flush()` i `clear()` pomiędzy partiami. Kolekcjonowanie całego pliku i wszystkich
błędów w pamięci nadal wyznacza górny limit rozmiaru importu.

### CQRS i architektura heksagonalna

1. Przenieść orkiestrację importu z `Domain/Service/*Import*FromXLSX` do handlerów
   aplikacyjnych. Domena nie powinna importować klas Symfony ani PhpSpreadsheet.
2. Wprowadzić port `TabularFileReader` zwracający wiersze oraz adapter XLSX w
   `Infrastructure`. Dzięki temu testy domenowe nie wymagają prawdziwego pliku.
3. Ujednolicić rezultat komend: komenda zapisu nie powinna zwracać modelu
   odczytowego. Status importu i raport powinny być pobierane osobnym query.
4. Granicę transakcji ustawić w handlerze komendy lub dedykowanym middleware, nie
   w repozytoriach. Zdarzenia publikować po udanym zatwierdzeniu transakcji.

### Clean Code, SOLID i DRY

1. Siedem importerów realizuje ten sam szablon: odczyt, preload, walidacja,
   przygotowanie, zapis, logowanie i zmiana statusu. Wydzielić aplikacyjny
   `ImportWorkflow`, a różnice przekazywać przez małe porty strategii. Ograniczy
   to duplikację bez tworzenia kolejnej rozbudowanej klasy bazowej.
2. Nie udostępniać publicznych, mutowalnych pól loaderów referencji. Zwracać
   niemutowalny obiekt kontekstu z metody `load()`.
3. Zastąpić ogólne wyjątki nazwanymi wyjątkami aplikacyjnymi (`FileNotFound`,
   `EmptyImport`) i mapować je na HTTP wyłącznie w warstwie prezentacji.
4. Kontrolery w obrębie tego samego trybu powinny współdzielić walidację uploadu
   i budowę odpowiedzi. Nie należy jednak ukrywać różnicy pomiędzy synchronicznym
   `import` a asynchronicznym `enqueue` za identycznym kontraktem odpowiedzi.
5. Nazwy interfejsów repozytoriów powinny opisywać port biznesowy (`RoleStore`,
   `RoleLookup`), bez szczegółu `InDB`. Implementacja Doctrine jest adapterem.

## Strategia testów backendowych

* **Jednostkowe:** wartości domenowe, walidatory wierszy, preparery, przejścia
  statusów importu i strategie create/update.
* **Kontraktowe adaptera XLSX:** pusty plik, komórki puste, daty, liczby z zerem
  wiodącym, formuły, zmiana pliku oraz powtarzalna walidacja. Pierwsze testy
  regresyjne iteratora zostały dodane wraz z optymalizacją.
* **Integracyjne:** repozytoria Doctrine i import partii na testowej bazie, w tym
  rollback, unikalność i brak zapytań N+1.
* **Funkcjonalne:** upload, autoryzacja, błędny MIME/rozmiar, odpowiedź 202 dla
  kolejki oraz końcowy raport błędów.
* **Wydajnościowe:** pliki 1k/10k/100k wierszy; rejestrować czas, szczyt pamięci,
  liczbę zapytań SQL oraz liczbę otwarć pliku i ustalić budżety regresji.

## Sugerowana kolejność prac

1. Dodać metryki i test bazowy importu, następnie batchowanie zapisu.
2. Wydzielić port czytnika i przenieść workflow do `Application`.
3. Usunąć duplikację importerów przez kompozycję strategii.
4. Rozdzielić modele read/write tam, gdzie pomiary pokażą realną korzyść.
5. Włączyć w CI PHPUnit, PHPStan i PHP-CS-Fixer oraz blokować regresje.
