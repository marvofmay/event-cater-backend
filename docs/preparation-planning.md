# Planowanie listy a kompletowanie

Zakładka kompletacji rozdziela dwa widoki:

- **Planowanie**: organizator ustala rzeczy, ilości, miejsca pobrania i instrukcje. Wybiera jedną osobę dla całej listy. Wiersz ma tylko ołówek otwierający edycję; usuwanie jest dostępne w tym oknie po dodatkowym potwierdzeniu. Nie ma pól postępu ani ostrzeżeń o załadunku podczas planowania.
- **Kompletowanie**: użytkownik z uprawnieniami wykonawczymi zapisuje rzeczywisty postęp. Dla listy w szkicu formularze postępu są ukryte do zatwierdzenia planu. Uprawnienia do przygotowania i załadunku pozostają niezależne; przełącznik widoku sam ich nie nadaje.

Osoba z prawem planowania domyślnie widzi plan, także gdy ma równocześnie prawa wykonawcze (np. administrator techniczny). Osoba mająca wyłącznie prawa wykonawcze domyślnie widzi kompletowanie. Wybrany widok nie zmienia się przy zapisie ilości.

## Odpowiedzialność

Osobę przypisuje się przez `PUT /api/events/{uuid}/preparation`. Endpoint konfiguracji pozycji odrzuca niepuste `responsibleUuid`. Dawne przypisania poszczególnych pozycji nie są już używane do walidacji odpowiedzialności; historyczne dane nie są usuwane migracją. Nowe pozycje i edycje metadanych nie zapisują osobnych wykonawców.

## Miejsce pobrania

Nowa pozycja sprzętu kopiuje domyślnie miejsce przechowywania z katalogu. Własny wpis organizatora lub szablonu ma pierwszeństwo. Zapisana lokalizacja jest migawką, więc zmiana biblioteki nie nadpisuje ustalonego miejsca. Starsze pozycje z pustym miejscem dostają podpowiedź z aktualnego katalogu, pobieraną zbiorczo dla całej listy.

Pole „Skąd pobrać” korzysta z autocomplete biblioteki miejsc sprzętu i dopuszcza własny opis. Przy dodawaniu sprzętu do szablonu również podpowiadana jest jego lokalizacja.

## Atomowa edycja

`PUT /api/events/{uuid}/preparation/lines/{lineUuid}` pozwala zapisać metadane i opcjonalne `requiredQuantity` w jednej transakcji, ze sprawdzeniem wersji i przynależności pozycji do wydarzenia. Pominięte miejsce i instrukcje pozostają niezmienione. Zmiana ilości sprzętu wymaga dodatkowo `events.plan_event_equipment`; pozostała konfiguracja wymaga `events.plan_event_preparation`. Ręczna zmiana ilości wyłącza jej dalsze automatyczne przeliczanie z szablonu, tak jak dotychczasowa edycja planu.
