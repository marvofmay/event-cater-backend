# Struktura firmy

Widok `/module/company` pokazuje firmy i spółki zależne, oddziały i pododdziały oraz pracowników z nazwą stanowiska. W każdym oddziale najpierw pokazywani są jego bezpośredni pracownicy, a pod nimi pododdziały; obie grupy są sortowane alfabetycznie. Zarządzanie rekordami nadal jest dostępne na dotychczasowych stronach `/manage`.

`GET /api/company-structure` zwraca tylko bezpośrednie dzieci wybranej gałęzi:

- `parentType`: `root` (domyślnie), `company` lub `department`.
- `parentUuid`: wymagany UUID dla firmy lub oddziału.
- `page`: od 1; `pageSize`: od 1 do 50, domyślnie 30.
- `phrase`: do 100 znaków; wyszukiwanie w nazwie i opisie pomocniczym bezpośrednich dzieci (dla pracownika: imię, nazwisko i stanowisko).

Odpowiedź `data` zawiera `items`, `total`, `page`, `hasMore`. Każdy element ma `type`, `uuid`, `name`, `subtitle`, `active`. Endpoint nie zwraca kontaktów, PESEL, adresów ani kont pracowników. Całe drzewo, wyszukiwanie i ścieżki wymagają osobnego dostępu `company_structure` z uprawnieniem `view`, bez dostępu do katalogów zarządzania. Tenant jest wybierany przez istniejący kontekst autoryzacji, nie przez parametry endpointu.

Ikona oka pobiera dopiero po kliknięciu `GET /api/company-structure/employees/{uuid}`. Odpowiedź zawiera wyłącznie UUID, imię, nazwisko oraz aktywne telefony i e-maile pracownika. Obowiązuje to samo uprawnienie do struktury, a usunięty lub niedostępny w danym tenancie pracownik zwraca 404.

W edytorze uprawnień „Struktura firmy” jest niezależna od „Zarządzania”. Dostępy do katalogów (firmy, oddziały, pracownicy itd.) wymagają nadrzędnego dostępu `manage`; samo `manage` nie nadaje żadnych czynności. Migracja zachowuje wcześniejsze dostępy ról i przyznaje podgląd struktury rolom, które wcześniej miały `companies.list`.

Kafelek firmy na pulpicie prowadzi wyłącznie do `/module/company` i wymaga podglądu struktury. Nie zastępuje go link do katalogu zarządzania. Migracja naprawcza `Version20261031100000` uwzględnia starsze bazy z domyślnym `deleted_at = CURRENT_TIMESTAMP`, które ukrywały nowy dostęp i przypisania ról.

## Wyszukiwanie globalne

Pole „Szukaj w całej strukturze” przeszukuje również nierozwinięte i jeszcze niepobrane gałęzie. `GET /api/company-structure?mode=search&phrase=Kowalska` zwraca pasujące firmy, oddziały i pracowników, z zachowaniem uprawnień oraz stronicowania. Fraza ma od 2 do 100 znaków. Oprócz nazw i stanowisk wyszukiwanie obejmuje aktywne, nieusunięte kontakty telefoniczne i e-mailowe firm, oddziałów oraz pracowników. Numery telefonów można wpisać bez spacji, myślników i nawiasów; dopasowanie obejmuje także fragment numeru. Kontakty są sprawdzane przez `EXISTS`, bez powielania wyników i bez zwracania danych kontaktowych w odpowiedzi. Wyniki zawierają dodatkowo `companyName` i `departmentName`, żeby odróżnić osoby o tym samym imieniu. Zapytanie uruchamia się po 300 ms przerwy w pisaniu i można je anulować; wyniki są świeże przez 30 sekund.

Kliknięcie wyniku pobiera jego ścieżkę przez `mode=path&nodeType=employee&nodeUuid=UUID`. API zwraca wyłącznie przodków i wybrany element, używając ograniczonych zapytań rekurencyjnych, zamiast pobierać całe drzewo. Interfejs rozwija przodków, przewija do wyniku i podświetla go. Wynik oraz przodkowie są widoczni również wtedy, gdy nie należą do pierwszych 30 elementów danej gałęzi. W interfejsie jest tylko wyszukiwarka globalna, bez dodatkowych pól w poszczególnych gałęziach.

Gałęzie są pobierane po rozwinięciu, stronami po 30 elementów. Zapytania SQL zwracają płaskie projekcje, bez rekurencyjnego ładowania encji i zapytań ORM N+1. Usunięte rekordy są pomijane; dzieci usuniętego rodzica są pokazywane na najbliższym dostępnym poziomie swojego rodzaju. Nieaktywne rekordy pozostają widoczne z oznaczeniem.

Frontend przechowuje dane gałęzi w pamięci React Query przez maksymalnie 10 minut, uznając je za świeże przez 2 minuty. Klucz uwzględnia bieżącą sesję i uprawnienia; ponowne rozwinięcie świeżej gałęzi nie pobiera jej ponownie. Zwinięte gałęzie nie renderują potomków. Wyszukiwanie ma opóźnienie 300 ms i anuluje nieaktualne żądania. „Odśwież strukturę” odświeża otwarte gałęzie i oznacza pozostałe jako nieaktualne. Odpowiedzi API mają `private, no-store`.

Po udanej edycji pracownika otwarte drzewko odświeża gałęzie i wyniki wyszukiwania, również w innych kartach przeglądarki. Sygnał między kartami zawiera jedynie losowy identyfikator zmiany, bez danych pracownika. Powrót do okna także odświeża dane. Stara ścieżka ujawnionego wyniku jest wtedy usuwana, aby nie pokazywać przeniesionego pracownika dodatkowo w poprzednim dziale. Nieudany zapis nie wysyła sygnału zmiany.

## Ustawienia osobiste

Zwykłe konta korzystają z `/me/settings`: zakładki ustawień osobistych obejmują firmy, oddziały, pracowników, stanowiska, role, formy zatrudnienia, branże i powiadomienia. Bezpośrednie wejście na `/settings/*` przekierowuje tam z wybraną zakładką. Menu ustawień nadrzędnych pozostaje widoczne dla administratora technicznego SaaS. W `/settings/*` edytuje on wyłącznie zakres globalny bieżącej firmy, bez przełącznika ustawień osobistych; te pozostają dostępne w `/me/settings`. Ustawienia osobiste można zmieniać bez nadawania dostępu do katalogów; nie nadają one uprawnień do rekordów ani zmiany domyślnych ustawień firmy.
