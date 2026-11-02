<?php

declare(strict_types=1);

namespace App\Module\Event\Infrastructure\PDF;

use Dompdf\Dompdf;
use Dompdf\Options;

final class PreparationPdfRenderer
{
    private const STATUS = [
        'draft' => 'Szkic',
        'approved' => 'Zatwierdzona',
        'in_preparation' => 'Kompletowanie',
        'ready_for_loading' => 'Gotowe do załadunku',
        'loaded' => 'Załadowane',
    ];

    private const UNITS = [
        'piece' => 'szt.',
        'set' => 'zestaw',
        'kit' => 'komplet',
        'carton' => 'karton',
        'pack' => 'opak.',
        'bottle' => 'butelka',
        'kg' => 'kg',
        'l' => 'l',
    ];

    public function html(array $event, array $preparation): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $qty = static function (mixed $value): string {
            $text = rtrim(rtrim(sprintf('%.3F', (float) $value), '0'), '.');

            return $text === '' ? '0' : $text;
        };
        $timezone = is_string($event['timezone'] ?? null) && $event['timezone'] !== '' ? $event['timezone'] : 'Europe/Warsaw';
        $status = self::STATUS[$preparation['status'] ?? ''] ?? (string) ($preparation['status'] ?? '—');
        $deadline = null;
        if (!empty($preparation['deadlineAt'])) {
            $deadline = (new \DateTimeImmutable((string) $preparation['deadlineAt']))
                ->setTimezone(new \DateTimeZone($timezone))
                ->format('Y-m-d H:i');
        }
        $html = '<!doctype html><html lang="pl"><head><meta charset="UTF-8"><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:10px}'
            .'h1{font-size:18px;margin:0 0 8px}'
            .'.meta{margin:8px 0 12px;line-height:1.45}'
            .'.note{white-space:pre-wrap;margin:8px 0}'
            .'table{border-collapse:collapse;width:100%}'
            .'th,td{border:1px solid #777;padding:5px;vertical-align:top}'
            .'thead{display:table-header-group}'
            .'tr{page-break-inside:avoid}'
            .'.check{width:42px;text-align:center;font-size:14px}'
            .'.muted{color:#444;font-size:9px}'
            .'footer{font-size:9px;margin-top:14px}'
            .'</style></head><body>';
        $html .= '<h1>Lista kompletacji</h1>';
        $html .= '<div class="meta">'
            .'<b>Wydarzenie:</b> '.$e($event['title']).' ('.$e($event['number']).')<br>'
            .'<b>Data:</b> '.$e($event['date']).' · <b>Goście:</b> '.$e($event['guestCount'] ?? '—').'<br>'
            .'<b>Status listy:</b> '.$e($status)
            .' · <b>Rewizja planu:</b> '.$e($preparation['planRevision'] ?? '—')
            .' · <b>Termin:</b> '.$e($deadline ?? '—').'<br>'
            .'<b>Odpowiedzialny:</b> '.$e($preparation['responsibleName'] ?? 'Nie wyznaczono')
            .'</div>';
        if (!empty($preparation['notes'])) {
            $html .= '<p class="note"><b>Notatki:</b> '.$e($preparation['notes']).'</p>';
        }
        $html .= '<table><thead><tr>'
            .'<th>Lp.</th><th>Pozycja</th><th>Ilość</th><th>Skąd / instrukcja</th>'
            .'<th class="check">Skomplet.</th><th class="check">Załad.</th>'
            .'</tr></thead><tbody>';
        $index = 1;
        foreach ($preparation['lines'] ?? [] as $line) {
            if (!is_array($line)) {
                continue;
            }
            $kind = ($line['kind'] ?? '') === 'equipment' ? 'Sprzęt' : 'Produkt';
            $unit = self::UNITS[$line['unit'] ?? ''] ?? (string) ($line['unit'] ?? '');
            $details = [];
            if (!empty($line['sourceLocation'])) {
                $details[] = 'Skąd: '.$line['sourceLocation'];
            }
            if (!empty($line['responsibleName'])) {
                $details[] = 'Odp.: '.$line['responsibleName'];
            }
            if (!empty($line['instructions'])) {
                $details[] = (string) $line['instructions'];
            }
            if (!empty($line['planningNotes'])) {
                $details[] = (string) $line['planningNotes'];
            }
            $html .= '<tr>'
                .'<td>'.$index++.'</td>'
                .'<td><b>'.$e($line['name'] ?? '').'</b><br><span class="muted">'.$e($kind)
                .(isset($line['code']) && $line['code'] !== '' && $line['code'] !== null ? ' · '.$e($line['code']) : '')
                .'</span></td>'
                .'<td>'.$e($qty($line['requiredQuantity'] ?? 0)).' '.$e($unit).'</td>'
                .'<td class="note">'.$e(implode("\n", $details) ?: '—').'</td>'
                .'<td class="check">☐</td><td class="check">☐</td>'
                .'</tr>';
        }
        $html .= '</tbody></table>'
            .'<p>Skompletował(a): ____________________ &nbsp; Data/godzina: ____________________</p>'
            .'<p>Załadował(a): ______________________ &nbsp; Data/godzina: ____________________</p>'
            .'<footer>Wydruk: '.$e((new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('Y-m-d H:i P'))
            .'. Pola checkbox służą do ręcznego odhaczania. Aktualny postęp zapisuj także w systemie.</footer>'
            .'</body></html>';

        return $html;
    }

    public function render(array $event, array $preparation): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->html($event, $preparation), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
