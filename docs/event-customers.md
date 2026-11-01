# Kontrahenci wydarzenia

Pole „Klient / organizator” wybiera zapisany rekord z bazy bieżącego tenanta.
Wyszukiwanie jest opóźnione o 250 ms i wykonywane tylko przy otwartej liście.
API zwraca maksymalnie 20 wyników, szukając po nazwie, NIP, REGON, KRS,
telefonie i e-mailu. Przycisk „+” otwiera formularz i nie zapisuje wydarzenia.
Po utworzeniu rekord jest automatycznie wybrany w formularzu wydarzenia.

Tylko nazwa jest wymagana (maksymalnie 200 znaków). Opcjonalne pola to
NIP, REGON, KRS, miasto, ulica i numer, kod pocztowy, kraj, telefon i e-mail.
Nowy kontrahent ma domyślnie kraj „Polska”; można go zmienić lub wyczyścić.
Adres jest zapisywany w osobnych polach `city`, `street`, `postalCode`, `country`.
Pole `address` pozostaje dostępne dla starszych integracji i do prezentacji
pełnego adresu. Migracja `Version20261101090000` zachowuje dotychczasowy tekst
adresu bez zgadywania, które fragmenty są miastem lub ulicą.
Podane identyfikatory muszą mieć
odpowiednią liczbę cyfr: NIP 10, REGON 9 lub 14, KRS 10. Spacje i myślniki
w identyfikatorach są usuwane. Telefon i e-mail są walidowane, jeśli je podano.

`GET /api/event-customers?q=...` wymaga dostępu do podglądu, tworzenia lub
edycji wydarzeń. `POST /api/event-customers` wymaga tworzenia lub edycji wydarzeń.
Baza jest oddzielna dla każdej firmy; identyfikator kontrahenta z innej firmy
nie może zostać przypisany do wydarzenia.

Wydarzenie zapisuje `customerUuid` oraz migawkę `customerName`. Przy podaniu
UUID nazwę ustala serwer z bazy, ignorując przesłaną nazwę. Dotychczasowe
integracje mogą nadal przesyłać samo `customerName` bez UUID.

Migracja `Version20261031120000` przenosi dotychczasowe niepuste nazwy klientów
z wydarzeń do katalogu (bez powielania tych samych nazw) i przypisuje rekordy
wydarzeniom, zachowując ich zapisane nazwy. Nie pobiera danych z zewnętrznych
rejestrów ani nie zmienia adresu miejsca realizacji wydarzenia.
