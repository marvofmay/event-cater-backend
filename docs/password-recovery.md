# Odzyskiwanie hasła pracownika

Na `/login` użytkownik wybiera firmę, następnie „Zapomniałeś hasła?”. Podaje login (telefon) lub przypisany do konta e-mail. Wiadomość jest wysyłana wyłącznie na adres zapisany w koncie, nigdy na dowolny adres dostarczony osobno w żądaniu.

Dotyczy kont pracowników danej firmy, zarówno z hasłem własnym, jak i startowym PESEL. Udany reset ustawia `must_change_password = false`, więc nie wymaga później ponownej zmiany hasła startowego. Nie loguje automatycznie: należy zalogować się telefonem i nowym hasłem. Starsze JWT są unieważniane przez zwiększenie wersji poświadczeń. Konta techniczne SaaS pozostają zarządzane centralnie i nie można ich resetować z kontekstu pojedynczego tenanta.

## API i zabezpieczenia

- `POST /api/auth/password-reset/request`, nagłówek `X-Tenant`, JSON `{ "login": "123456789" }`. Odpowiedź `202` ma zawsze tę samą treść dla kont istniejących, nieistniejących, nieaktywnych lub bez e-maila. Wyszukanie konta i wysyłka odbywają się w osobnej kolejce `password_reset`, aby odpowiedź HTTP nie ujawniała istnienia konta czasem wysyłania wiadomości.
- Limit: 20 żądań na IP na 15 minut i 3 na identyfikator konta w firmie na 15 minut; przekroczenie limitu konta nadal zwraca tę samą odpowiedź `202`. Worker dodatkowo nie wysyła kolejnego linku przez 60 sekund. Potwierdzenia mają osobny limit 20 prób na IP na 15 minut. Limity na IP zwracają `429`.
- Token: 32 losowe bajty, jednorazowy, ważny 15 minut. W tabeli `password_reset_request` zapisuje się jedynie SHA-256 połączony z UUID tenanta, wraz z wersją poświadczeń i adresem odbiorcy. Zmiana telefonu, e-maila lub hasła unieważnia wcześniejsze linki; potwierdzenie sprawdza też aktywność i usunięcie pracownika/konta.
- Link wskazuje `/reset-password#token=…&tenant=…`. Fragment nie jest przesyłany do serwera w URL; frontend usuwa go z bieżącej historii i przechowuje token tylko w pamięci widoku. Strona ma `Referrer-Policy: no-referrer` i `Cache-Control: no-store`. Odświeżenie strony wymaga ponownego otwarcia pełnego linku z wiadomości.
- `POST /api/auth/password-reset/confirm`, `X-Tenant`, JSON z polami `token`, `newPassword`, `confirmation`. Obowiązuje wspólna polityka 8–64 znaków i reguły widoczne w checkboxach, zgodność haseł oraz zakaz powtórzenia dotychczasowego hasła. Słabe hasło nie zużywa linku.
- Zapis hasła i usunięcie wszystkich linków konta odbywają się w transakcji z blokadą wiersza użytkownika. Powtórzone, wygasłe i nieprawidłowe linki zwracają ten sam komunikat `422`. Jednoczesne potwierdzenia nie mogą wykorzystać tokenu dwukrotnie.
- Surowy token nie trafia do kolejki Messengera ani archiwum `email`. Wiadomość tworzy i wysyła bezpośrednio worker. Profilowanie endpointów z sekretami jest wyłączone. Po zmianie osobna wiadomość powiadamia właściciela konta, bez ujawniania hasła.
- Te e-maile bezpieczeństwa nie zależą od osobistych ani firmowych przełączników kanałów powiadomień.

## Wdrożenie

1. Wykonaj kopię baz i uruchom migracje centralnej bazy oraz tenantów.
2. Ustaw `PASSWORD_RESET_URL` na stały, zaufany adres frontendu, np. `https://app.example.com/reset-password`. Adresy poza lokalnym `localhost` / `127.0.0.1` muszą używać HTTPS. Linki nie korzystają z nagłówka `Host` ani adresu przekazanego przez klienta. Lokalna wartość domyślna to `http://localhost:3000/reset-password`.
3. Uruchom `docker compose up -d worker-password-reset`. Worker musi mieć właściwy `MAILER_DSN`; konfiguracja lokalna kieruje wiadomości do Mailpit, panel na `http://localhost:8025`. Nie dostarcza to wiadomości do rzeczywistych skrzynek internetowych. Produkcja wymaga własnej konfiguracji SMTP oraz `PASSWORD_RESET_SENDER` z adresem nadawcy dopuszczonym przez dostawcę poczty (domyślnie `noreply@hrapp.com`).
4. Uzupełnij prawdziwe e-maile istniejących pracowników przez edycję lub XLSX. Migracja zachowuje istniejące konta i dopuszcza ich dotychczasowy brak e-maila w bazie; nie wymyśla adresów ani nie blokuje logowania. Nowe zapisy formularza i importu wymagają już e-maila.

Wysyłka jest dostępna dla żądań nie starszych niż 5 minut; po dłuższej awarii kolejki należy poprosić o nowy link. Przechowywany jest maksymalnie jeden aktualny link dla konta. Wygasłe rekordy można usuwać w utrzymaniu bazy przez `DELETE FROM password_reset_request WHERE expires_at <= UTC_TIMESTAMP()`.

Zasady bezpieczeństwa: [OWASP Forgot Password Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html).
