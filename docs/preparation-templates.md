# Szablony list do zabrania

Szablon przechowuje wielokrotnie używaną listę sprzętu i materiałów. Zastosowanie kopiuje pozycje oraz reguły wyliczeń do wydarzenia. Późniejsza edycja, archiwizacja lub usunięcie szablonu nie zmienia już utworzonych list.

## Ilości i zapas

- „Zawsze tyle samo”: stała ilość niezależna od liczby gości.
- „Według liczby gości”: ilość na osobę lub grupę oraz opcjonalny zapas wyrażony w jednostce pozycji, np. sztukach lub kilogramach.
- Przykład: 1 filiżanka na osobę i 5 sztuk zapasu daje 55 sztuk dla 50 gości, a 105 dla 100 gości. Zapas pozostaje stały.
- Przykładowa liczba gości w edytorze służy wyłącznie podglądowi i nie zapisuje się w szablonie. Wydarzenie używa własnej liczby gości.
- API reguły przyjmuje opcjonalne `reserveQuantity` (liczba całkowita lub tekst dziesiętny do 3 miejsc). Brak oznacza zero. Reguły zachowane w JSON nie wymagają migracji.
- Dawne reguły `reservePercent` nadal działają bez zmiany wyniku. Edytor oznacza je jako dotychczasowy zapas procentowy i pozwala jawnie przejść na dodatkową ilość. Nie można jednocześnie ustawić niezerowego zapasu procentowego i ilościowego.
- Przeliczona ilość jest zaokrąglana w górę do pełnej jednostki, a dla kg/l do 0,001. Dopiero potem dodawany jest stały zapas. Backend i podgląd używają dokładnych obliczeń w tysięcznych jednostki.

## Usuwanie

Archiwizacja pozostaje odwracalna. Osoba z `preparation_templates.delete` może również trwale usunąć aktywny lub archiwalny szablon, po potwierdzeniu ostrzeżenia.

`DELETE /api/preparation-templates/{uuid}` przyjmuje obiekt `{version: number}`. Operacja blokuje rekord i sprawdza wersję: nieaktualna wersja zwraca 409, brak rekordu 404. Zapisane listy wydarzeń, ich reguły przeliczania i historia pozostają bez zmian.
