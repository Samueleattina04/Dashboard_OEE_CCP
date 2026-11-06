<?php

namespace App\Http\Controllers;

use App\Services\LetturaKpi;
use App\Support\Filtri;
use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export della tabella giornaliera: CSV (separatore ";", decimali con virgola, date gg/mm/aaaa) o Excel.
 * Ogni export è registrato (chi, cosa, quando, filtri).
 */
class ExportController extends Controller
{
    public function giornaliero(Request $request, string $formato, LetturaKpi $kpi)
    {
        abort_unless(in_array($formato, ['csv', 'xlsx'], true), 404);
        $filtri = Filtri::daRichiesta($request);

        $intestazione = ['Macchina', 'Giorno', 'Ore in funzione', 'Ore ferma', 'Ore allarme', 'Ore avvio/setup',
            'Ore riscaldamento', 'Ore raffreddamento', 'Ore non classificate', 'Ore senza dato', 'Campioni',
            'Avviamenti', 'Ore pianificate', 'Ore in funzione nel turno', 'Kg selezionati', 'Kg scartati',
            'Disponibilità %', 'Prestazione %', 'Qualità %', 'OEE %', 'Note'];
        $righe = [];
        foreach ($kpi->macchine($filtri->macchina) as $m) {
            foreach ($kpi->giorni($m, $filtri->da, $filtri->a) as $g) {
                $ore = fn (?int $s) => $s === null ? null : round($s / 3600, 2);
                $pct = fn ($x) => $x === null ? null : round((float) $x * 100, 2);
                $righe[] = [
                    $m->codice, $g->giorno->format('d/m/Y'),
                    $ore($g->secondi_marcia), $ore($g->secondi_fermo), $ore($g->secondi_allarme), $ore($g->secondi_avvio),
                    $ore($g->secondi_riscaldamento), $ore($g->secondi_raffreddamento), $ore($g->secondi_non_classificato),
                    $ore($g->secondi_senza_dato), $g->campioni_presenti, $g->avviamenti,
                    $ore($g->secondi_pianificati), $ore($g->secondi_marcia_in_turno),
                    $g->kg_selezionati !== null ? (float) $g->kg_selezionati : null,
                    $g->kg_scartati !== null ? (float) $g->kg_scartati : null,
                    $pct($g->disponibilita), $pct($g->prestazione), $pct($g->qualita), $pct($g->oee),
                    implode('; ', array_map([Formato::class, 'motivo'], array_values($g->motivi ?? []))),
                ];
            }
        }

        DB::table('log_export')->insert([
            'user_id' => Auth::id(), 'vista' => 'giornaliero', 'formato' => $formato,
            'filtri' => json_encode($filtri->query() + ['da' => $filtri->da, 'a' => $filtri->a]),
            'righe' => count($righe), 'creato_il' => now(),
        ]);

        $nome = 'ore_funzionamento_'.$filtri->da.'_'.$filtri->a;
        $info = ['Estrazione del '.now()->setTimezone(config('dashboard.timezone_locale'))->format('d/m/Y H:i')
            .' — utente '.Auth::user()->email.' — periodo '.$filtri->descrizione()];

        return $formato === 'csv'
            ? $this->csv($nome, $info, $intestazione, $righe)
            : $this->xlsx($nome, $info, $intestazione, $righe);
    }

    private function csv(string $nome, array $info, array $intestazione, array $righe)
    {
        return response()->streamDownload(function () use ($info, $intestazione, $righe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel apre correttamente gli accenti
            fputcsv($out, $info, ';');
            fputcsv($out, $intestazione, ';');
            foreach ($righe as $r) {
                fputcsv($out, array_map(fn ($v) => is_float($v) ? number_format($v, 2, ',', '') : $v, $r), ';');
            }
            fclose($out);
        }, $nome.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function xlsx(string $nome, array $info, array $intestazione, array $righe)
    {
        $libro = new Spreadsheet;
        $foglio = $libro->getActiveSheet()->setTitle('Giornaliero');
        $foglio->fromArray([$info, $intestazione], null, 'A1');
        $foglio->fromArray($righe, null, 'A3', true);
        $foglio->getStyle('A2:U2')->getFont()->setBold(true);
        $foglio->getStyle('C3:T'.(count($righe) + 2))->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'U') as $col) {
            $foglio->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $nome.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
