# Lista przygotowania cateringu

Lista kompletacji jest przypisana do wydarzenia. Obejmuje sprzęt, tekstylia oraz produkty zużywalne: na przykład 100 bulionówek, 100 kompletów kawowych, 5 warników, 20 kartonów mleka i 3 ekspresy. Pokrowce i obrusy są pozycjami katalogu sprzętu, a mleko, kawa i serwetki — katalogu produktów.

[Kontrakt OpenAPI](preparation.openapi.json). [Moduł Kuchnia](kitchen.md). [Dalszy plan receptur i zakupów](kitchen-plan.md).

## Instalacja

```sh
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:initialize-system-default-data --no-interaction
```

Migracja `Version20261023100000` tworzy cztery tabele i uzupełnia listy wszystkich istniejących wydarzeń. Dotychczasowe zapotrzebowanie na sprzęt otrzymuje pozycje kompletacji z zerowym postępem. Wymagana ilość sprzętu nadal pochodzi z `event_equipment_requirement`, a nie z drugiej kopii danych. Migracja nie zmienia dotychczasowych ilości, notatek ani statusów wydarzeń.

## Przebieg pracy

1. Koordynator dodaje sprzęt i produkty, ewentualnie stosuje szablon.
2. Wskazuje odpowiedzialnego pracownika na liście. Opcjonalnie określa termin i notatki. Termin domyślny pochodzi z godziny gotowości cateringu; jawny termin nie może być od niej późniejszy.
3. Zatwierdza plan. Pusta lista i lista bez odpowiedzialnego pracownika nie mogą zostać zatwierdzone.
4. Pracownicy rozpoczynają kompletację i zapisują faktyczne ilości przygotowane.
5. Po przygotowaniu wszystkich pozycji oznaczają listę jako gotową do załadunku.
6. Osoba ładująca potwierdza załadowane ilości.
7. Uprawniona osoba potwierdza cały załadunek. Braki wymagają dodatkowego uprawnienia i uzasadnienia.

Statusy: `draft`, `approved`, `in_preparation`, `ready_for_loading`, `loaded`. Końcowe potwierdzenie jest osobną czynnością: sam zapis ilości nie oznacza automatycznie zgody na wyjazd.

Można potwierdzić kompletne ilości również po ponownym zatwierdzeniu planu, gdy wcześniejsza kompletacja została zachowana. Można też świadomie zatwierdzić wyjazd z brakami, bez udawania kompletności listy.

Jeśli wydarzenie ma niepustą listę przygotowania, przejście wydarzenia do `in_progress` wymaga potwierdzonego załadunku. Dotychczasowy przebieg wydarzeń bez zapotrzebowania pozostaje dostępny. Lista jest historycznie dostępna po zakończeniu/anulowaniu wydarzenia; nie służy do rozliczania zwrotów magazynowych.

## Reguły ilości

API rozróżnia `requiredQuantity`, `preparedQuantity` i `loadedQuantity`. Załadowana ilość nie może przekraczać przygotowanej. Braki i nadwyżki są wyliczane, a nie ręcznie odznaczane.

Wszystkie odpowiedzi używają dokładnych napisów dziesiętnych z trzema miejscami, np. `"20.000"`, `"1.125"`. Żądania przyjmują liczbę całkowitą lub napis dziesiętny; JSON float jest odrzucany, aby uniknąć błędów precyzji. Zakres: 0..1000000.000, a wymagana ilość musi być dodatnia.

Jednostki `piece`, `set`, `kit`, `carton`, `pack`, `bottle` wymagają całkowitych ilości. `kg` i `l` dopuszczają tysięczne części. Karton oznacza konkretne opakowanie danego produktu, np. katalogowy produkt „Mleko 1 l”; system nie zakłada, ile litrów mieści dowolny karton.

Przygotowanie/załadunek ponad zapotrzebowanie jest widoczny jako nadwyżka i nie znika po zmniejszeniu planu. Nie sumujemy w jeden wynik kartonów, stołów i kilogramów — podsumowanie liczy pozycje kompletne i pozycje z brakami.

Zmniejszenie faktycznie przygotowanej lub załadowanej ilości wymaga `correctionReason` o długości 10..2000 znaków. Korekta musi nadal spełniać `loaded <= prepared`. Pozycji z niezerowym postępem nie można usunąć: należy najpierw jawnie skorygować ilości do zera. Bez zatwierdzonego planu dozwolone są korekty zmniejszające, ale nie dalsza kompletacja lub załadunek.

## Zmiany po zatwierdzeniu

Zmiana wymaganych ilości, dodanie/usunięcie pozycji, zmiana odpowiedzialności, instrukcji, lokalizacji pobrania albo ustawień listy cofa plan do `draft`. Zmiana istotnego kontekstu wydarzenia (liczby gości, miejsca, terminu, godzin) lub menu również wymaga ponownego zatwierdzenia. Dotychczasowe ilości fizyczne pozostają zapisane.

Zmiana liczby gości przelicza pozycje z regułą `per_guest`. Pozycje stałe i ręczne pozostają bez zmian. Usunięcie liczby gości jest odrzucane, gdy lista nadal zawiera reguły zależne od tej liczby.

Korekta fizycznych ilości po potwierdzeniu załadunku cofa jego potwierdzenie. Gdy nadal wszystko jest przygotowane, lista wraca do `ready_for_loading`; w przeciwnym razie do `in_preparation`. Akceptacja wcześniejszych braków nie jest przenoszona na zmieniony plan ani zmienione ilości.

## Odpowiedzialność i historia

Każda pozycja może zawierać odpowiedzialnego pracownika, miejsce pobrania oraz instrukcje. Przy braku odpowiedzialności na pozycji obowiązuje odpowiedzialny za całą listę. Zatwierdzanie planu i załadunku sprawdza aktywność wskazanych pracowników. Są to funkcje magazynowe/organizacyjne — nie wymagają automatycznie przypisania tej osoby do obsługi wydarzenia.

`preparedBy`, `preparedAt`, `loadedBy`, `loadedAt` wskazują użytkownika i czas ostatniej zmiany danej ilości. Historia wydarzenia przechowuje stan przed/po zmianie, przyczynę korekty, zatwierdzenia oraz dokładny wykaz zaakceptowanych braków. Nazwy produktów i osób są migawkami, więc późniejsze zmiany katalogu nie przepisują wcześniejszych list.

Każda mutacja listy używa aktualnej `version` wydarzenia. Nie ma osobnego, nieskoordynowanego licznika do równoległych zapisów sprzętu i kompletacji. Nieaktualna wersja daje 409. Transakcja blokuje wydarzenie, listę oraz powiązane pozycje; odczyt katalogu jest stabilizowany blokadą współdzieloną. Historię zapisuje się w tej samej transakcji.

## Katalog i szablony

`/api/consumables` udostępnia katalog produktów/materiałów. Obowiązuje unikalny kod, nazwa, kategoria, jednostka, opis i archiwizacja. Jednostki nie można zmienić, gdy produkt jest powiązany z wydarzeniem.

Kategorie produktów to biblioteka: `GET/POST /api/consumable-categories`, `PUT/DELETE /api/consumable-categories/{uuid}`. Korzysta z uprawnień `consumables.list/create/update/delete`. Zmiana i usunięcie wymagają bieżącej `version`. Produkt przyjmuje `categoryUuid`; odczyt zwraca identyfikator oraz aktualną nazwę w `category`. Dotychczasowe tekstowe `category` pozostaje obsługiwane dla starszych klientów. Listę produktów można filtrować po `categoryUuid`.

Migracja `Version20261024090000` przenosi istniejące nazwy kategorii do biblioteki i zachowuje przypisania produktów. Komenda `php bin/console app:initialize-consumable-categories --no-interaction` uzupełnia brakujące grupy: Napoje i dodatki, Produkty spożywcze, Naczynia i sztućce jednorazowe, Opakowania, Higiena i sprzątanie, Materiały pomocnicze oraz Inne. Jest też częścią inicjalizacji danych domyślnych i można ją uruchamiać wielokrotnie bez duplikowania kategorii. Frontend udostępnia bibliotekę pod `/catering/supplies/categories`, a w formularzu produktu wybór z listy.

Kategoria używana przez produkt, także archiwalny, wymaga wskazania `replacementCategoryUuid` przy usuwaniu. Przeniesienie wszystkich pozycji i usunięcie kategorii odbywają się atomowo oraz wymagają dodatkowo `consumables.update`. Wersje przeniesionych produktów rosną. Nazwy produktów i jednostki zapisane na wydarzeniach pozostają zachowane.

`GET /api/preparation-options?kind=all&q=kawa` daje wspólne autocomplete sprzętu i produktów (do 20 wyników). Dostępne filtry: `all`, `equipment`, `consumable`. Odpowiedź zawiera `kind` i `itemUuid`; klient wybiera właściwy endpoint zapotrzebowania. Oddzielne katalogi mają własne uprawnienia.

Szablon zawiera do 100 unikalnych pozycji. Reguła pozycji:

```json
{"mode":"fixed","quantity":"20"}
```

albo:

```json
{"mode":"per_guest","quantity":"1","perGuests":50,"reservePercent":0}
```

Drugi przykład oznacza jedną sztukę na 50 gości: dla 120 osób potrzeba trzech sztuk. Zapas 10% dla jednego kompletu na osobę: `quantity=1`, `perGuests=1`, `reservePercent=10`. Dla kawy można podać `quantity="0.020"` kg na jednego gościa.

Obliczenia używają liczb całkowitych w tysięcznych jednostki. Wynik zaokrąglany jest raz w górę: do pełnej sztuki/opakowania lub 0.001 kg/l. System nie tworzy receptur ani przeliczników produktów z nazw potraw.

Stosowanie szablonu wymaga konkretnej `templateVersion`, dodaje/aktualizuje jego pozycje i zachowuje pozostałe pozycje listy. Zastępuje zaplanowane ilości i reguły pasujących pozycji, ale nie kasuje przygotowanych/załadowanych ilości. Operacja jest atomowa: nieudane przeliczenie lub nieaktywny produkt cofa całość. Brak liczby gości przy regule `per_guest` daje 422.

Lista zachowuje kopię reguły oraz identyfikator i wersję szablonu. Edycja szablonu nie aktualizuje wydarzeń w tle. Ręczna zmiana wymaganej ilości usuwa automatyczną regułę tej pozycji, zachowując informację o pochodzeniu. Zmiana jednostki katalogowej wymaga najpierw świadomej aktualizacji szablonu; nie interpretujemy starej liczby jako nowej jednostki.

## Główne endpointy

- `GET/PUT /api/events/{uuid}/preparation` — lista i ustawienia odpowiedzialności/terminu.
- `PUT/DELETE .../preparation/consumables/{itemUuid}` — zapotrzebowanie na produkt (`requiredQuantity`).
- Dotychczasowe `PUT/DELETE /api/events/{uuid}/equipment/{equipmentUuid}` — zapotrzebowanie na sprzęt, automatycznie odzwierciedlane w kompletacji.
- `PUT .../preparation/lines/{lineUuid}` — odpowiedzialność, `sourceLocation`, `instructions`.
- `PUT .../preparation/lines/{lineUuid}/progress` — `preparedQuantity`, `loadedQuantity`, opcjonalnie `correctionReason`.
- `POST .../preparation/approve`, `/start`, `/ready`, `/confirm-loaded`.
- `POST .../preparation/apply-template` — `templateUuid`, `templateVersion`.
- `GET/POST /api/preparation-templates`, `GET/PUT /api/preparation-templates/{uuid}`, `PUT .../{uuid}/active`.

Każdy zapis dotyczący wydarzenia przyjmuje aktualną `version`; zmiany ustawień są pełnym zastąpieniem tych ustawień. Postęp jest zapisem absolutnych ilości, nie przyrostem: ponowienie żądania nie dodaje kolejnych 20 sztuk. Wszelkie błędy walidacji/reguł dają 422, brak pozycji w danym wydarzeniu 404, a konflikt wersji 409.

## Uprawnienia

Odczyt listy wymaga `view` dla `events.events`. Każda edycja wymaga również tego dostępu.

| Czynność | Dodatkowe uprawnienie wydarzeń |
|---|---|
| Plan, odpowiedzialność, produkty, zastosowanie szablonu, zatwierdzenie planu | `plan_event_preparation` |
| Zapotrzebowanie na sprzęt / szablon obejmujący sprzęt | `plan_event_equipment` |
| Przygotowanie, rozpoczęcie pracy, gotowość do załadunku | `prepare_event_catering` |
| Zapis ilości załadowanej | `load_event_catering` |
| Potwierdzenie załadunku | `dispatch_event_catering` |
| Wyjazd z brakami | dodatkowo `accept_preparation_shortages` |

Braki przy potwierdzeniu wymagają `acceptShortagesReason` o długości 10..2000 znaków. Samo uprawnienie do załadunku nie pozwala zaakceptować braków. Akceptacja zapisuje użytkownika, czas, uzasadnienie i migawkę braków; lista nadal pokazuje `hasShortages=true`.

Katalog produktów: dostęp `supplies.consumables` i standardowe CRUD/list/view/restore. Szablony: `events.preparation_templates` i standardowe uprawnienia. Zastosowanie szablonu wymaga jego podglądu, a edycja jego pozycji — dostępu do odpowiednich katalogów. Sprawdzana jest dokładnie wersja szablonu, której zawartość posłużyła do kontroli uprawnień.

## Zakres

Kompletacja i potwierdzenie załadunku nie rezerwują magazynu, nie odejmują stanów i nie rozliczają zwrotów. Moduł Kuchnia i karty PDF są wdrożone osobno; receptury, partie produkcyjne i lista zakupów pozostają planem rozwoju. Frontend zawiera zakładkę kompletacji wydarzenia i katalog szablonów. Nie ma jeszcze osobnego wydruku listy kompletacji. Wybór odpowiedzialnych za kompletację jest dostępny przez `GET /api/events/{uuid}/preparation/staff-options?q=...` z uprawnieniem `plan_event_preparation`, bez wymagania uprawnienia do obsady wydarzenia.
