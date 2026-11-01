# Wiadomości wewnętrzne

`/messages` to osobna skrzynka korespondencji między użytkownikami tej samej
firmy, niezależna od automatycznych powiadomień w `/notifications`.
Wiadomość ma tytuł (1–200 znaków), treść (1–10 000 znaków) i od 1 do 100
odbiorców. Treść jest zwykłym tekstem, nie wykonywanym HTML-em. Nie ma
załączników, edycji wysłanej treści ani wysyłki przez SMS / e-mail.

## Architektura

Moduł `Messaging` używa CQRS: komendy `SendMessage` / `MarkMessageRead`,
typowane zapytania i projekcje DBAL. Agregat `DirectMessage` posiada
`MessageReceipt` dla każdego odbiorcy. Repozytorium i katalog odbiorców mają
interfejsy, a adaptery Doctrine / DBAL korzystają z bieżącego połączenia tenanta.
Komendy są wykonywane w transakcjach istniejącej szyny `command.bus`.

Wysyłka jest atomowa: niedostępny odbiorca odrzuca całą wiadomość. Wybór
odbiorców obejmuje aktywne konta pracowników z uprawnieniami `messages.list`
i `messages.view` albo administratorów firmy. Przechowywane są migawki nazw
nadawcy i odbiorców. Wskazanie UUID z innej firmy jest odrzucane.

`requestUuid` jest kluczem idempotencji wysyłki. Powtórzenie identycznej komendy
nie tworzy drugiej wiadomości, a ponowne użycie klucza z inną treścią lub innym
nadawcą daje 409. Interfejs zachowuje klucz podczas ponawiania nieudanej próby.

## API i uprawnienia

- `GET /api/messages?folder=inbox|sent&page=1&limit=20&unread=false&q=...`
  — `messages.list`, wyłącznie własne odebrane / wysłane.
- `GET /api/messages/unread-count` — `messages.list`, licznik dla odbiorcy.
- `GET /api/messages/recipients?q=...` — `messages.create`, do 20 wyników.
- `POST /api/messages` — `messages.create`; `title`, `content`, `recipientUuids`,
  opcjonalne `requestUuid` (UUID).
- `GET /api/messages/{uuid}` — `messages.view`; tylko nadawca lub odbiorca.
- `PATCH /api/messages/{uuid}/read` — `messages.view`; tylko odbiorca,
  wielokrotne wywołanie zachowuje czas pierwszego odczytu.

Nawet administrator nie może otworzyć cudzej korespondencji, jeśli nie jest
jej nadawcą ani odbiorcą. Nadawca widzi listę odbiorców i ich czasy odczytu;
odbiorca nie otrzymuje listy pozostałych adresatów. Sam `GET` nie oznacza
wiadomości jako przeczytanej. Interfejs robi to dopiero po otwarciu podglądu.

## Odpowiedzi i usuwanie

Lista ma formę tabeli z sortowaniem tytułu, nadawcy i daty, wyborem rozmiaru
strony oraz checkboxami bieżącej strony. Akcje to podgląd, odpowiedź i usunięcie.
Zaznaczone wiadomości można usunąć zbiorczo po potwierdzeniu.

Odpowiedź wymaga `messages.create` i wskazuje `replyToUuid` w `POST /api/messages`.
Serwer sprawdza, czy autor jest odbiorcą nieusuniętego oryginału i czy jedynym
adresatem odpowiedzi jest nadawca oryginału. Nie ma „odpowiedz wszystkim” ani
ujawniania innych odbiorców. Interfejs uzupełnia adresata oraz tytuł `Re: ...`.
Powiązanie z oryginałem jest częścią kontroli idempotencji wysyłki.

`DELETE /api/messages/{uuid}?folder=inbox|sent` oraz
`POST /api/messages/delete` z `{folder, uuids}` wymagają `messages.delete`.
Usuwanie działa tylko dla właściciela wskazanej skrzynki i jest idempotentne.
Żądanie zbiorcze (1–100 identyfikatorów) jest atomowe: obcy identyfikator odrzuca
całą operację. Blokady pobierane są w stałej kolejności UUID. Kopia odbiorcy
oraz pozycja nadawcy w wysłanych mają niezależne znaczniki ukrycia; nikt nie
może w ten sposób cofnąć wysyłki innym osobom. Ukryte odebrane nie zwiększają
licznika i nie można ich odczytać ani użyć jako podstawy nowej odpowiedzi.
Nie ma kosza ani przywracania w interfejsie; treść nie jest fizycznie kasowana.

Migracja `Version20261101120000` zachowuje istniejącą korespondencję, dodaje
powiązanie odpowiedzi, znaczniki ukrycia, indeksy uwzględniające usunięcie oraz
uprawnienie usuwania własnych wiadomości rolom z dotychczasowym dostępem do
skrzynki. Nie nadaje prawa wysyłania. Załączniki nie są jeszcze obsługiwane.

Migracja `Version20261101110000` tworzy tabele i indeksy skrzynki oraz nadaje
`list` i `view` rolom, które już miały dostęp `system.messages`. Nie nadaje
automatycznie `create`: wysyłkę można przyznać w konfiguracji uprawnień roli.
Administratorzy firmy zachowują dostęp zgodnie z istniejącymi regułami systemu.

## Licznik i kanały

Koperta w nawigacji pokazuje liczbę nieprzeczytanych wiadomości, niezależnie
od dzwonka automatycznych powiadomień. Licznik aktualizuje się po odczycie,
wysyłce, powrocie do karty oraz co 15 sekund w widocznej karcie. Zmiany są
sygnalizowane także innym kartom. Stare żądania i stan licznika są odrzucane
przy zmianie sesji / tenanta. Skrzynka jest stronicowana, a wyszukiwanie
tytułu / treści oraz odbiorców opóźnione o 300 ms.

Kanały automatycznych powiadomień nadal podlegają regule:
`kanał firmy AND (wybór użytkownika lub dziedziczenie)`. Próba włączenia
wyłączonego firmowo kanału przez API preferencji daje 422; w interfejsie
odpowiedni switch jest zablokowany. Ręczne wiadomości mają własną skrzynkę
i nie są usuwane ani blokowane przez wyłączenie kanału powiadomień systemowych.
