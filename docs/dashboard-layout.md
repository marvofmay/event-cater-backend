# Kolejność kafelków pulpitu

W `/me/settings`, w domyślnej zakładce „Pulpit”, użytkownik może przeciągać dostępne kafelki, zmieniać ich kolejność strzałkami (także dotykowo i z klawiatury) oraz przywrócić kolejność domyślną. Zmiany wymagają kliknięcia „Zapisz układ pulpitu”.

Układ jest zapisany w istniejącej tabeli `application_preference`, pod zasobem `dashboard` i UUID zalogowanego użytkownika, w bazie bieżącego tenanta. Nie ma wspólnego układu nadrzędnego ani zapisu pod wskazanym przez klienta właścicielem. Kolejność obowiązuje po ponownym logowaniu i na innym urządzeniu.

- `GET /api/preferences/dashboard` zwraca `data: {order: string[], version: number}`.
- `PUT /api/preferences/dashboard` przyjmuje wyłącznie `order` i `version`. Pusta lista przywraca domyślną kolejność. Nieznane lub powtórzone moduły są odrzucane (422), konflikt wersji zwraca 409.
- `GET /api/preferences` udostępnia również `data.dashboardOrder`, więc pulpit nie wymaga kolejnego żądania.

Kafelki są nadal filtrowane według modułów, dostępów i czynności użytkownika. Zapis kolejności nie nadaje uprawnień. Nowo dostępne kafelki bez zapisanej pozycji trafiają na koniec. Ukryte kafelki nie są pokazywane w edytorze, ale ich zapisane identyfikatory są zachowywane przy zmianie kolejności pozostałych.
