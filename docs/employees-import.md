# Pracownicy — formularz i import XLSX

- Wymagane: imię, PESEL, e-mail, co najmniej jeden niepusty telefon i dane zatrudnienia.
- Nazwisko oraz poszczególne pola adresu są opcjonalne. Podane nazwisko ma 3–50 znaków.
- PESEL zawsze ma 11 cyfr i pozostaje identyfikatorem pracownika przy imporcie. Ponowne użycie tego samego numeru aktualizuje istniejącego pracownika.
- Formularz pozwala zaznaczyć „Fikcyjny PESEL”. Taki numer nie wymaga poprawnej sumy kontrolnej i jest oznaczony w podglądzie. Nie należy używać numeru innej osoby.
- Import zachowuje dotychczasową kolejność kolumn A–S. Opcjonalna kolumna T (`isPeselFake`) przyjmuje `1` dla fikcyjnego numeru i `0` dla prawdziwego. Brak wartości zachowuje oznaczenie istniejącego pracownika; dla nowego oznacza numer prawdziwy.
- PESEL i telefon należy zapisywać w Excelu jako tekst, aby nie utracić początkowych zer.
- API POST/PUT `/api/employees` przyjmuje `isPeselFake: boolean`, opcjonalne `lastName` i opcjonalny obiekt `address`. Pominięte pola adresowe pozostają puste.
