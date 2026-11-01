# Pracownicy — formularz i import XLSX

- Wymagane: imię, PESEL, e-mail, co najmniej jeden niepusty telefon oraz firma, oddział, stanowisko i rola.
- E-mail jest wymagany przy dodawaniu i edycji w modalu oraz w kolumnie D importu XLSX, ponieważ służy odzyskiwaniu dostępu. Musi być poprawny, mieć maksymalnie 100 znaków i nie należeć do innego konta w firmie. API odrzuca brak, `null`, pusty lub nieprawidłowy e-mail. Import wykrywa również powtarzające się adresy między różnymi pracownikami w pliku. Forma zatrudnienia (kolumna M) pozostaje opcjonalna; podana musi istnieć.
- Nowy pracownik otrzymuje konto. Loginem jest pierwszy telefon, a hasłem startowym jego PESEL (również fikcyjny, jeśli tak go oznaczono). Telefon jest obowiązkowy w modalu i kolumnie E XLSX oraz unikalny jako login w obrębie tenanta. Nie podawaj wspólnego telefonu kilku pracowników.
- „Zatrudnienie od” jest opcjonalne w formularzu i w kolumnie J importu XLSX. Pusta data zapisuje się jako `null`, bez uzupełniania dzisiejszą datą. API przyjmuje również pominięte pole `employmentFrom` lub pusty tekst. Podana data musi mieć format `YYYY-MM-DD`; jeśli podano obie daty zatrudnienia, data końcowa musi być późniejsza od początkowej.
- Nazwisko oraz poszczególne pola adresu są opcjonalne. Podane nazwisko ma 3–50 znaków.
- PESEL zawsze ma 11 cyfr i pozostaje identyfikatorem pracownika przy imporcie. Ponowne użycie tego samego numeru aktualizuje istniejącego pracownika.
- Formularz pozwala zaznaczyć „Fikcyjny PESEL”. Taki numer nie wymaga poprawnej sumy kontrolnej i jest oznaczony w podglądzie. Nie należy używać numeru innej osoby.
- Import zachowuje dotychczasową kolejność kolumn A–S. Opcjonalna kolumna T (`isPeselFake`) przyjmuje `1` dla fikcyjnego numeru i `0` dla prawdziwego. Brak wartości zachowuje oznaczenie istniejącego pracownika; dla nowego oznacza numer prawdziwy.
- PESEL i telefon należy zapisywać w Excelu jako tekst, aby nie utracić początkowych zer.
- Bezpośredni przełożony jest opcjonalny. Formularz pozwala wybrać pracownika z dowolnego działu tej samej firmy; nie pozwala wybrać siebie ani podwładnego. Zmiana firmy czyści wybór przełożonego.
- W XLSX kolumna O zawiera PESEL przełożonego. Przełożony musi istnieć w bazie lub w tym samym pliku, również w późniejszym wierszu; import przetwarza przełożonych przed podwładnymi. Puste pole usuwa przypisanie. Nieprawidłowy PESEL, brak przełożonego, inna firma lub cykl w hierarchii powodują błąd walidacji.
- API POST/PUT `/api/employees` przyjmuje `isPeselFake: boolean`, opcjonalne `lastName` i opcjonalny obiekt `address`. Pominięte pola adresowe pozostają puste.

## Logowanie i hasła

- Numer logowania jest normalizowany: spacje, myślniki i nawiasy są usuwane, `+` i `00` są równoważne, a polski numer 9-cyfrowy otrzymuje prefiks `48`. Numery zagraniczne należy podawać z kodem kraju. To samo konto można znaleźć przez `123456789`, `+48 123 456 789` i `0048123456789`.
- Przy pierwszym logowaniu nie można korzystać z aplikacji przed zmianą hasła. Modal pokazuje reguły i ich spełnienie na bieżąco; API również wymusza zmianę, zwracając `423 PASSWORD_CHANGE_REQUIRED` dla pozostałych operacji. Dostępne pozostają zmiana hasła i wylogowanie.
- `POST /api/auth/change-password` przyjmuje `currentPassword`, `newPassword`, `confirmation`. Wymagane: 8–64 znaków, maksymalnie 72 bajty, duża i mała litera, cyfra, znak specjalny, brak znaków sterujących, zgodne potwierdzenie i inne hasło niż dotychczasowe. Limit bajtów chroni przed obcięciem przez bcrypt. Hasła są hashowane, nie zapisywane wprost ani wysyłane w e-mailu. Profilowanie logowania i zmiany hasła jest wyłączone. Oba endpointy mają limity prób.
- Zmiana hasła zwraca nowy JWT i unieważnia wszystkie starsze tokeny konta przez wersję poświadczeń. Samo odświeżenie strony lub inna karta nie omija obowiązkowej zmiany.
- Panel startowy i menu pokazują tylko przyznane moduły, dostępy i uprawnienia; nazwa roli sama nie nadaje uprawnień. Konta nieaktywnych pracowników nie mogą się logować.
- Aktualizacja pierwszego telefonu lub e-maila w formularzu pracownika lub XLSX unieważnia poprzednie sesje, ale nie resetuje hasła. Pierwszy telefon pozostaje loginem. Zmiana PESEL w danych pracownika także nie resetuje istniejącego hasła. Konto techniczne SaaS zachowuje dotychczasowe logowanie i centralne zarządzanie hasłem.
- Migracja nie resetuje haseł istniejących kont. Przy aktualizacji pracownika bez konta konto jest tworzone według nowych zasad.
- Istniejących aktywnych pracowników bez kont można uzupełnić przez `php bin/console app:employees:provision-accounts --tenant=mapa-smakow` (podgląd), następnie z `--execute` po wykonaniu kopii bazy. Komenda wymaga jawnego tenanta, sprawdza telefony, PESEL i e-maile przed zapisem, działa transakcyjnie i nie zmienia istniejących kont ani ich haseł. Ponowne wykonanie nie tworzy duplikatów. Pomija również pracowników z usuniętym kontem, zamiast przywracać je bez zgody.

PESEL jest daną osobową i przewidywalnym hasłem startowym — należy przekazać je bezpiecznym kanałem i zmienić niezwłocznie. Docelowo bezpieczniejszym sposobem aktywacji byłby jednorazowy, losowy kod lub link.
