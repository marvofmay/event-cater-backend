# Historia wydarzenia

Historia jest uporządkowana od najnowszej wersji i pokazuje nazwę operacji,
autora oraz czas w strefie Europe/Warsaw. Autorzy są odczytywani zbiorczo
z bazy bieżącego tenanta, bez zapytania dla każdego wpisu.

Nowe zmiany danych wydarzenia, menu i kontaktów zapisują zmienione pola
w `details.changes` jako pary `before` / `after`. Konfiguracja osoby
kompletującej i edycja pozycji listy zapisują także migawki przed i po zmianie.
Interfejs pokazuje te różnice wprost, bez technicznych identyfikatorów.

Starsze wpisy zachowują dotychczasowe migawki dostępne pod „Pokaż zapisane dane”.
Nie są uzupełniane fikcyjnymi wartościami sprzed zmiany. Nazwa autora jest
odczytywana aktualnie; jeśli konto zostało usunięte, widnieje „Użytkownik niedostępny”.
