<?php

namespace App\Http\Controllers;

use App\Services\LetturaKpi;
use App\Support\Filtri;
use App\Support\Formato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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

    /** Export della tabella di confronto periodo A / periodo B. */
    public function confronto(Request $request, string $formato, LetturaKpi $kpi)
    {
        abort_unless(in_array($formato, ['csv', 'xlsx'], true), 404);
        [$filtri, $daB, $aB] = StoricoController::periodi($request);
        $righeConfronto = StoricoController::confronto($kpi, $filtri, $daB, $aB);

        $voci = [
            ['Ore in funzione', 'secondi_marcia', 'ore'], ['Ore in funzione medie/giorno', 'ore_marcia_media', 'ore'],
            ['Utilizzo sul tempo coperto %', 'utilizzo_coperto', 'pct'], ['Copertura dati %', 'copertura', 'pct'],
            ['Disponibilità %', 'disponibilita', 'pct'], ['Prestazione %', 'prestazione', 'pct'], ['OEE %', 'oee', 'pct'],
            ['Ore riscaldamento', 'secondi_riscaldamento', 'ore'], ['Ore raffreddamento', 'secondi_raffreddamento', 'ore'],
            ['Avviamenti', 'avviamenti', 'num'], ['Kg selezionati', 'kg_selezionati', 'num'], ['Scarto di selezione %', 'scarto_selezione', 'pct'],
        ];
        $conv = fn ($x, $tipo) => $x === null ? null : match ($tipo) {
            'ore' => round($x / 3600, 2), 'pct' => round($x * 100, 2), default => round((float) $x, 2),
        };
        $intestazione = ['Macchina', 'Indicatore', 'Periodo A', 'Periodo B', 'Differenza A-B'];
        $righe = [];
        foreach ($righeConfronto as $r) {
            foreach ($voci as [$nome, $chiave, $tipo]) {
                $a = $conv($r['a'][$chiave] ?? null, $tipo);
                $b = $conv($r['b'][$chiave] ?? null, $tipo);
                $righe[] = [$r['macchina']->codice, $nome, $a, $b, ($a === null || $b === null) ? null : round($a - $b, 2)];
            }
        }

        DB::table('log_export')->insert([
            'user_id' => Auth::id(), 'vista' => 'confronto', 'formato' => $formato,
            'filtri' => json_encode($filtri->query() + ['da' => $filtri->da, 'a' => $filtri->a, 'b_da' => $daB, 'b_a' => $aB]),
            'righe' => count($righe), 'creato_il' => now(),
        ]);

        $info = ['Estrazione del '.now()->setTimezone(config('dashboard.timezone_locale'))->format('d/m/Y H:i')
            .' — utente '.Auth::user()->email.' — periodo A '.Formato::data($filtri->da).'–'.Formato::data($filtri->a)
            .' — periodo B '.Formato::data($daB).'–'.Formato::data($aB)];
        $nome = 'confronto_'.$filtri->da.'_'.$filtri->a.'_vs_'.$daB.'_'.$aB;

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
        $ultima = Coordinate::stringFromColumnIndex(count($intestazione));
        $foglio->getStyle("A2:{$ultima}2")->getFont()->setBold(true);
        $foglio->getStyle('C3:'.$ultima.(count($righe) + 2))->getNumberFormat()->setFormatCode('#,##0.00');
        for ($i = 1; $i <= count($intestazione); $i++) {
            $foglio->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, $nome.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
