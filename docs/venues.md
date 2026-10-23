# Baza miejsc obsługi

Miejsca to osobny katalog w module Wydarzenia (`events.venues`). Obejmuje powracające adresy wyjazdowe i obiekty w bazie firmy. Zapis zawiera nazwę, pełny adres, instrukcje dojazdu/rozładunku i zaplecza, kontakty, notatki oraz domyślny wariant obsługi (`off_site`/`on_site`).

Można utworzyć miejsce ręcznie albo zapisać dane z istniejącego wydarzenia, także już zakończonego. Przy kolejnym przyjęciu wyszukuje się obiekt po nazwie, mieście lub ulicy i wczytuje dane do formularza. Nie trzeba ponownie wpisywać danych stałych wyjazdów.

Adres i kontakty wydarzenia są niezależnymi migawkami. Poprawka lub archiwizacja miejsca nie zmienia wcześniejszych ani już zaplanowanych wydarzeń. Przy wczytywaniu miejsca do istniejącego wydarzenia użytkownik jawnie decyduje, czy zastąpić również kontakty. Wydarzenie zachowuje pozostałe dane, menu i harmonogram; zmiana miejsca unieważnia zatwierdzenie kompletacji. Kuchnia wykrywa zmianę adresu w źródle i wymaga uzgodnienia aktualnej karty.

Nie ma automatycznego scalania po adresie: dwa obiekty lub sale mogą mieć tę samą ulicę i numer. Nie tworzy się też automatycznie katalogu ze wszystkich historycznych wydarzeń, aby nie utrwalać przypadkowych literówek lub kontaktów bez świadomego wyboru.

| Metoda i ścieżka | Operacja i uprawnienie |
| --- | --- |
| `GET /api/event-venues?q=...&active=true&page=1&limit=20` | Lista; `venues.list`; `active=true,false,all` |
| `GET /api/event-venues/options?q=...` | Do 20 aktywnych podpowiedzi, bez danych kontaktowych; `venues.list` |
| `GET /api/event-venues/{uuid}` | Szczegóły z kontaktami; `venues.view` |
| `POST /api/event-venues` | Nowe miejsce; `venues.create` |
| `PUT /api/event-venues/{uuid}` | Pełny zapis z `version`; `venues.update` |
| `PUT /api/event-venues/{uuid}/active` | `{version,active}`; `venues.delete` / `venues.restore` |
| `POST /api/events/{uuid}/save-venue` | Kopia adresu i kontaktów z wydarzenia; `events.view` + `venues.create` |
| `PUT /api/events/{uuid}/venue` | Atomowe wczytanie miejsca; `events.view` + `events.update` + `venues.view` |

Utworzenie/zapis katalogu:

```json
{
  "name": "Dom kultury — sala główna",
  "serviceMode": "off_site",
  "location": {
    "venue": "Sala główna",
    "street": "Długa 1",
    "city": "Kraków",
    "postalCode": "30-001",
    "instructions": "Rozładunek od podwórza, kuchnia na parterze"
  },
  "contacts": [{"name": "Organizator", "phone": "123456789"}],
  "notes": "Powtarzające się spotkania świąteczne"
}
```

Ulica, miasto i kod są wymagane; kontakty mogą stanowić pustą listę. `name` ma maks. 200 znaków, uwagi 4000, lista kontaktów maks. 50. Pole `version` jest wymagane przy aktualizacji.

Wczytanie miejsca do wydarzenia wymaga `{version, venueUuid, venueVersion, replaceContacts}`. Wersja wydarzenia i wersja miejsca chronią przed równoczesną zmianą. Archiwalnego miejsca nie można zastosować; starsze wydarzenie nadal zawiera jego wcześniejsze dane. Operacja zapisuje w historii identyfikator i wersję źródła oraz adres przed/po zmianie. Nie ma połączenia ORM, które aktualizowałoby historyczne adresy w tle.

Zapis miejsca z wydarzenia przyjmuje `{version, name, notes?, serviceMode?}`. Nie modyfikuje wydarzenia ani jego wersji. Migracja `Version20261023120000` dodaje tabelę `event_venue`; następnie należy uruchomić `app:initialize-system-default-data` i nadać wybranym rolom uprawnienia `venues`.

Frontend: `/catering/venues`, wybór miejsca w nowym wydarzeniu oraz „Miejsce z bazy adresów” w jego szczegółach. Wczytane dane można później zmodyfikować dla konkretnej realizacji. Utworzenie wydarzenia i zapis skopiowanych kontaktów są oddzielnymi operacjami API; jeśli zapis kontaktów się nie powiedzie, formularz blokuje ponowne tworzenie i podaje link do już istniejącego wydarzenia.

Pełny kontrakt: [venues.openapi.json](venues.openapi.json).
