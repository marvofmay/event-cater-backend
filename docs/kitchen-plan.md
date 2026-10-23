# Propozycja: Kuchnia, karty produkcyjne i zakupy

Status: pierwszy etap modułu Kuchnia jest zaimplementowany — karty, plan dzienny, rewizje, postęp i PDF. Aktualny kontrakt i ograniczenia: [kitchen.md](kitchen.md). Receptury, wspólne partie i zakupy poniżej pozostają planem rozwoju. Moduł obsługuje catering wyjazdowy i przyjęcia w bazie firmy.

## Podział odpowiedzialności

- **Event** — zamówienie klienta: menu, warianty, liczba gości, harmonogram i miejsce.
- **Kitchen** — zlecenie wykonania potraw: co, ile, dla którego wydarzenia, na kiedy oraz kto odpowiada.
- **EventPreparationList** — rzeczy do zabrania, kompletacja i załadunek. Może później prezentować gotowość pojemników z posiłkami przekazanymi przez kuchnię.
- **Procurement** — planowane zakupy na podstawie jawnych źródeł zapotrzebowania; docelowo uwzględnia również dostępne stany i dostawy.

„Kucharze” to role użytkowników i widok pracy. Nazwa domeny powinna opisywać proces — Kuchnia/Produkcja kuchenna.

## Pierwszy użyteczny zakres Kuchni

Dla każdego wydarzenia koordynator wydaje kartę produkcyjną (`KitchenOrder`). Karta zawiera numer wydarzenia, datę, liczbę gości, rewizję menu, termin przekazania kuchni oraz termin wydania gotowych potraw do pakowania.

Pozycja (`KitchenOrderLine`) zawiera nazwę potrawy, wariant dietetyczny, wymaganą liczbę porcji, wielkość porcji, sposób pakowania, uwagi i osobę odpowiedzialną. Postęp obejmuje przygotowane i przekazane porcje oraz autora/czas zapisu. Dla 100 gości można jawnie wskazać 90 porcji standardowych i 10 wegańskich; system nie zakłada automatycznie 100 porcji każdego wariantu.

Karta A i karta B pozostają oddzielne nawet wtedy, gdy potrawy mają tę samą nazwę. Widok kuchni na dzień grupuje zadania z zachowaniem rozbicia na wydarzenia i warianty. Nie łączy automatycznie dań tylko po nazwie.

Statusy: `draft → released → in_production → ready → handed_over`, z osobnym anulowaniem. Wydruk PDF zawiera numer karty, wersję, termin, pozycje, pola na wpisanie ilości i potwierdzenie przekazania. Nowa rewizja jednoznacznie oznacza poprzedni wydruk jako nieaktualny.

## Kontrola zmian

Wydanie karty tworzy migawkę uzgodnionego menu. Późniejsza zmiana menu wydarzenia generuje propozycję rewizji, zamiast podmieniać zlecenie kucharzom w tle. Rewizja wymaga akceptacji kuchni; trzeba pokazać różnice, np. +20 porcji, usunięty deser albo nowy wariant bez mleka.

Przy anulowaniu zlecenia rozpoczęte ilości pozostają w historii. Różnica między zaplanowanym, przygotowanym i przekazanym nie może zostać wyzerowana samą zmianą planu. Równoczesne zapisy chroni wersjonowanie. Powiadomienia są późniejszą integracją po zatwierdzeniu zmian, a nie skutkiem edycji roboczego szkicu.

## Partie produkcyjne — drugi etap

`ProductionBatch` pozwala przygotować wspólną partię dla kilku wydarzeń. Każdy przydział (`BatchAllocation`) wskazuje kartę i konkretny wariant potrawy. Suma przydzielonych porcji nie może przekraczać dostępnej, zaakceptowanej ilości partii.

Łączenie wymaga zgodności receptury, wariantu dietetycznego, parametrów bezpieczeństwa i terminów. Dania dla osób z alergiami mogą wymagać osobnej produkcji. Potwierdzenia alergenów i procedury kuchni pozostają odpowiedzialnością uprawnionych osób; nazwa potrawy nie stanowi źródła tych informacji.

## Receptury i przyszłe zakupy

Wersjonowana `Recipe` opisuje składniki i ilości dla jawnego uzysku, np. 10 porcji po 250 ml. `RecipeIngredient` wskazuje produkt katalogowy, jednostkę i ilość, a dodatkowo można określić uzasadniony ubytek. Przeliczanie g↔kg i ml↔l jest dokładne; przeliczenie opakowania na masę/objętość wymaga zapisanej wielkości opakowania. Nie należy automatycznie przeliczać litrów na kilogramy bez właściwego przelicznika dla produktu.

Zatwierdzone karty produkcyjne tworzą `IngredientDemand`, a produkty przeznaczone bezpośrednio na wydarzenie tworzą `EventSupplyDemand`. Każde zapotrzebowanie ma unikalne źródło i rewizję. Dzięki temu mleko potrzebne do deseru oraz mleko do ekspresu mogą zostać zsumowane, ale ten sam przydział mleka nie zostanie policzony ponownie jako składnik i pozycja do transportu.

`ShoppingPlan` agreguje zatwierdzone zapotrzebowanie z wybranych wydarzeń/terminów. Pierwsza wersja może pokazywać ilość potrzebną i ręcznie potwierdzoną ilość do zakupu. Dopiero po wdrożeniu magazynu będzie można wiarygodnie odjąć dostępne zapasy, uwzględnić rezerwacje, partie, termin przydatności i dostawy.

Zakupy zaokrąglają się do rzeczywistych opakowań, a nadwyżka wynikająca z zakupu opakowania pozostaje widoczna. Zmiany kart lub receptur wymagają ponownego przeliczenia i zatwierdzenia planu zakupów.

## Proponowane API i wdrożenie

Pierwszy etap:

- `POST /api/events/{uuid}/kitchen-orders` — utworzenie roboczej karty z migawki menu;
- `POST /api/kitchen-orders/{uuid}/release` — wydanie konkretnej rewizji do kuchni;
- `GET /api/kitchen-orders?date=...` — plan pracy kuchni z podziałem na wydarzenia;
- `PUT /api/kitchen-orders/{uuid}/lines/{lineUuid}/progress` — ilości przygotowane/przekazane;
- `POST /api/kitchen-orders/{uuid}/revisions` — jawne uzgodnienie zmiany;
- `GET /api/kitchen-orders/{uuid}/pdf` — wersjonowany wydruk.

Osobne uprawnienia: planowanie, wydanie do produkcji, zapis wykonania, akceptacja zmian i przekazanie do pakowania. Zlecenia i reguły produkcyjne należą do Kitchen; integracja z Event korzysta z portu i jawnych migawek.

Kolejność: karty i wydruk → plan dzienny → receptury → wspólne partie → zapotrzebowanie i zakupy → integracja magazynowa. Każdy etap powinien być użyteczny samodzielnie i nie wymagać od kucharzy wcześniejszego wdrożenia pełnej ewidencji magazynu.
