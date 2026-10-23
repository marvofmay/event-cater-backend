<?php

declare(strict_types=1);

namespace App\Module\Kitchen\Infrastructure\PDF;

use Dompdf\Dompdf;
use Dompdf\Options;

final class KitchenPdfRenderer
{
    public function html(array $revision): string
    {
        $source = $revision['snapshot']['source'];
        $plan = $revision['snapshot']['plan'];
        $e = static fn (mixed $v): string => htmlspecialchars((string)($v ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $warnings = [];
        if ($revision['superseded']) {
            $warnings[] = 'NIEAKTUALNA REWIZJA — aktualna: '.$revision['currentRevision'];
        }
        if ($revision['orderStatus'] === 'cancelled' || $revision['eventStatus'] === 'cancelled') {
            $warnings[] = 'ANULOWANO — nie realizować';
        }
        if ($revision['eventStatus'] === 'completed') {
            $warnings[] = 'WYDARZENIE ZAKOŃCZONE — wydruk archiwalny';
        }
        if ($revision['sourceChanged']) {
            $warnings[] = 'ZMIENIONO WYDARZENIE — uzgodnij aktualną kartę z koordynatorem';
        }
        if ($revision['pendingRevision']) {
            $warnings[] = 'OCZEKUJE PROPOZYCJA ZMIANY — wymaga akceptacji kuchni';
        }
        $html = '<!doctype html><html lang="pl"><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}h1{font-size:18px}.warning{border:2px solid #a00;padding:8px;font-weight:bold}table{border-collapse:collapse;width:100%}th,td{border:1px solid #777;padding:6px;vertical-align:top}thead{display:table-header-group}tr{page-break-inside:avoid}.meta{margin:10px 0}.note{white-space:pre-wrap}footer{font-size:9px;margin-top:15px}</style></head><body>';
        $html .= '<h1>Karta produkcyjna kuchni</h1><p>'.$e($revision['orderNumber']).' · rewizja '.$e($revision['number']).'</p>';
        foreach ($warnings as $warning) {
            $html .= '<p class="warning">'.$e($warning).'</p>';
        }
        $due = $plan['dueAt'] === null ? '—' : (new \DateTimeImmutable($plan['dueAt']))->setTimezone(new \DateTimeZone($source['timezone']))->format('Y-m-d H:i P');
        $mode = ($plan['serviceMode'] ?? 'off_site') === 'on_site' ? 'W bazie firmy — przekazanie do obsługi na miejscu' : 'Wyjazd — przekazanie do transportu';
        $html .= '<p><b>Obsługa:</b> '.$e($mode).'</p>';
        $location = array_filter($source['location'] ?? [], static fn ($v) => is_string($v) && $v !== '');
        $html .= '<p><b>Miejsce wydarzenia:</b> '.$e(implode(', ', $location)).'</p>';
        $html .= '<div class="meta"><b>Wydarzenie:</b> '.$e($source['title']).' ('.$e($source['number']).')<br><b>Data wydarzenia:</b> '.$e($source['date']).' · <b>Goście:</b> '.$e($source['guestCount']).'<br><b>Dzień produkcji:</b> '.$e($plan['productionDate']).' · <b>Gotowe na:</b> '.$e($due).' ('.$e($source['timezone']).')<br><b>Odpowiedzialny:</b> '.$e($plan['responsibleName']).'</div>';
        $html .= '<p class="note">'.$e($plan['notes']).'</p><table><thead><tr><th>Potrawa / wymagania</th><th>Porcje / wielkość</th><th>Podanie / transport / odpowiedzialny</th><th>Wykonano</th><th>Przekazano</th></tr></thead><tbody>';
        foreach ($plan['lines'] as $line) {
            $html .= '<tr><td><b>'.$e($line['name']).'</b><br>Diety: '.$e(implode(', ', $line['diets']) ?: 'Nie podano').'<br>Alergeny: '.$e(implode(', ', $line['allergens']) ?: 'Nie podano — do weryfikacji').'<p class="note">'.$e($line['notes']).'</p></td><td>'.$e($line['requiredPortions']).'<br>'.$e($line['portionSize']).'</td><td>'.$e($line['packing']).'<br>'.$e($line['responsibleName'] ?? $plan['responsibleName']).'</td><td>________</td><td>________</td></tr>';
        }
        return $html.'</tbody></table><p>Przygotował(a): ____________________ &nbsp; Data/godzina: ____________________</p><p>Przekazał(a): _____________________ &nbsp; Odebrał(a): ________________________</p><footer>Wydruk: '.$e((new \DateTimeImmutable('now', new \DateTimeZone($source['timezone'])))->format(DATE_ATOM)).'. Przed pracą sprawdź numer aktualnej rewizji w systemie. Pola wykonania służą do ręcznego uzupełnienia.</footer></body></html>';
    }
    public function render(array $revision): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->html($revision), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        return $pdf->output();
    }
}
