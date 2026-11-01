# Menedżer plików

`/module/file_manager` korzysta z trwałego API `/api/file-manager`, a nie danych demonstracyjnych. Jest to **prywatny dysk zalogowanego użytkownika w aktualnie wybranej firmie**. Inny pracownik, administrator firmy i pracownik techniczny nie otrzymują dostępu do cudzych plików. Ten sam użytkownik w innej firmie ma osobny dysk.

## Architektura i uprawnienia

- Moduł `FileManager`: reguły nazw w domenie, serwis przypadków użycia zależny od portów `FileCatalog` i `PrivateFileStorage`, adaptery DBAL i lokalnego magazynu, cienki kontroler HTTP. Nie dodaje zbędnej kolejki ani asynchronicznego zapisu.
- Metadane `managed_file` znajdują się w osobnej bazie tenanta. Każdy odczyt i zapis katalogu jest dodatkowo ograniczony przez `owner_uuid` z uwierzytelnionego konta, nigdy z parametrów klienta. Użytkownik ma własny korzeń, wersję katalogu i licznik zajętości. Nie udostępnia to istniejących importów, awatarów i załączników wydarzeń.
- Dostęp `documents.file_manager` ma uprawnienia `list` (drzewo), `view` (pobranie), `create` (folder/upload/kopia), `update` (nazwa/przeniesienie), `delete` (trwałe usunięcie). Kopiowanie wymaga dodatkowo `view`. Wszystkie operacje sprawdzają aktualne uprawnienia na serwerze.
- Migracja `Version20261101130000` daje istniejącym rolom z dostępem do menedżera tylko `list` i `view`. Operacje zmieniające prywatny dysk należy nadać świadomie w zarządzaniu rolami. Administrator firmy i pracownik techniczny korzystają ze standardowych uprawnień administratora, ale **bez obejścia ograniczenia właściciela plików**.
- Migracja `Version20261101140000` przypisuje wcześniejsze pliki ich autorom. Potomkowie utworzeni przez innego użytkownika przenoszą się do jego prywatnego korzenia, bez przejmowania cudzych folderów; ewentualne kolizje nazw otrzymują sufiks UUID. Zawartość plików nie jest usuwana. Rekordy bez autora pozostają odizolowane i wymagają ręcznego przeglądu operatora. Migracji nie można bezpiecznie cofnąć do wspólnego dysku.
- Wersja całego katalogu i blokada jego korzenia w transakcji chronią przed jednoczesnym nadpisywaniem, przekroczeniem limitów oraz wyścigami przy przenoszeniu/usuwaniu. Nieaktualna wersja zwraca `409`; użytkownik odświeża widok przed ponowieniem.

## API

Żądania wymagają JWT wybranego tenanta. UUID korzenia jest wewnętrzny; w API korzeń oznacza `parentUuid: null` lub brak tego pola.

| Metoda i ścieżka względem `/api/file-manager` | Dane / rezultat |
| --- | --- |
| `GET /` | `{version, items: [{uuid, parentUuid, name, type, size, date}], usedBytes, maxBytes, maxEntries}` |
| `POST /folders` | JSON `{name, parentUuid?, version}` → `201 {uuid, message}` |
| `POST /upload` | multipart: `file`, opcjonalnie `name`, `parentUuid`, wymagane `version` → `201 {uuid, message}` |
| `PATCH /{uuid}/rename` | JSON `{name, version}` |
| `POST /transfer` | JSON `{operation: "copy" lub "move", uuids: [...], parentUuid?, version}` |
| `POST /delete` | JSON `{uuids: [...], version}` — trwale, rekurencyjnie |
| `GET /{uuid}/download` | Autoryzowany plik jako załącznik, bez publicznego URL |

Operacje grupowe przyjmują 1–100 elementów. Zaznaczony rodzic obejmuje swoje podfoldery; równoczesne zaznaczenie potomka nie powiela operacji. Nieistniejący element lub konflikt nazwy przerywa całą transakcję. Kopia zachowuje niezależne metadane i współdzieli niezmienną zawartość; usunięcie jednej kopii nie psuje pozostałych. Nie ma nadpisywania plików o tej samej nazwie ani zmiany rozszerzenia przy zmianie nazwy.

## Bezpieczeństwo i limity

- Prywatne dane: `storage/file-manager-<środowisko>/<uuid-tenanta>/<losowy-uuid>`, poza `public/`, katalogi `0700`, pliki `0600`. Środowisko testowe ma oddzielny magazyn. Proces WWW musi mieć prawo tworzenia katalogów w `storage/`.
- Ścieżki klienta nie trafiają do systemu plików. Nazwy są normalizowane do NFC, sprawdzane pod kątem separatorów, znaków sterujących, długości i kolizji bez rozróżniania wielkości liter.
- Maksymalnie 20 MiB na plik, 2 GiB logicznej pojemności i 5000 plików/folderów na użytkownika w danej firmie. Kopie liczą się do limitu pojemności. Maksymalnie 32 poziomy i 4096 bajtów ścieżki. Ograniczony katalog jest pobierany jednym zapytaniem, bez N+1; wyszukiwanie i nawigacja odbywają się lokalnie, bez przesyłania treści plików.
- Dozwolone PDF, DOC, DOCX, XLS, XLSX, PNG, JPG/JPEG, WebP, TXT, CSV. Rozszerzenie jest porównywane z wykrytym MIME, obraz dodatkowo z nagłówkiem i limitem 40 megapikseli. DOCX/XLSX sprawdzają strukturę ZIP, limity rozpakowania i liczbę wpisów, odrzucają szyfrowanie, makra VBA, osadzone obiekty i ActiveX.
- Pliki są pobierane z `Content-Disposition: attachment`, `nosniff`, restrykcyjnym CSP i `private, no-store`. Aplikacja nie uruchamia ani nie renderuje dokumentów. Te kontrole **nie zastępują antywirusa**; starsze DOC/XLS mogą zawierać aktywną zawartość. Przed udostępnieniem uploadu niezaufanym zewnętrznym użytkownikom warto dodać kwarantannę i skaner przez port magazynu.
- Nowy blob jest usuwany przy nieudanym zapisie metadanych. Przy kasowaniu blob znika po zatwierdzeniu transakcji i tylko wtedy, gdy żadna kopia go nie używa — również kopia innego właściciela pozostała po migracji dawnego wspólnego dysku. Błędy sprzątania są logowane. Nagłe przerwanie procesu pomiędzy operacjami dysku i SQL może zostawić osierocony blob; nie jest on dostępny przez API. Monitoring i okresowy audyt zajętości dysku pozostają obowiązkiem operatora.

## Wdrożenie

Wykonać kopię baz i prywatnego magazynu, następnie migrację centralnej bazy i `app:saas:migrate-tenants --execute`. Nowi tenanci dostają schemat przez istniejący provisioner Doctrine; korzeń katalogu powstaje automatycznie. Nie cofać migracji bez osobnej kopii plików/metadanych: `down` usuwa tabelę, nie odtwarza dokumentów.

Dockerfile kopiuje istniejący `config/php/event-media.ini` (100 MiB upload / 110 MiB POST dla pozostałych modułów); Compose dodatkowo montuje ten plik, aby starszy obraz nie ograniczał wszystkich uploadów do domyślnych 2 MiB PHP. Limit menedżera pozostaje 20 MiB. Po zmianie konfiguracji odtworzyć kontener PHP/Apache. Backup i odtwarzanie muszą obejmować **jednocześnie** bazę firmy oraz jej prywatne pliki.

## Testy

- `php bin/phpunit tests/functional/module/system/FileManagerApiTest.php`
- `php bin/phpunit tests/unit/module/fileManager/FileManagerTest.php`
- `php bin/phpunit tests/functional/module/saas/SaasApiTest.php --filter testManagedFilesUseSeparateTenantCatalogsAndPrivateStorage`
- `php bin/phpunit tests/functional/module/company/presentation/api/controller/role/RoleAuthorizationTest.php --filter testFileManagerReadAccessDoesNotGrantFileMutation`
- Frontend: `npx playwright test --config=playwright.catering.config.ts tests/catering/file-manager.spec.ts` i `npx tsc --noEmit --incremental false`.
