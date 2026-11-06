<?php

namespace App\Http\Controllers;

use App\Domain\StatoMacchina;
use App\Models\CampioneMes;
use App\Models\SyncMes;
use App\Services\LetturaKpi;
use App\Support\Filtri;
use App\Support\Formato;
use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * Qualità del dato: copertura giorno per giorno e interruzioni del flusso FDI, per macchina.
 * Evidenza da condividere con il fornitore (es. periodi senza record di FX10130).
 */
class QualitaDatoController extends Controller
{
    /** Soglia sotto cui un giorno conta come "interrotto". */
    private const SOGLIA_INTERRUZIONE = 0.5;

    public function __invoke(Request $request, LetturaKpi $kpi)
    {
        $filtri = Filtri::daRichiesta($request, '90giorni');
        $schede = [];
        foreach ($kpi->macchine($filtri->macchina) as $m) {
            $perGiorno = $kpi->giorni($m, $filtri->da, $filtri->a)->keyBy(fn ($g) => $g->giorno->format('Y-m-d'));
            $primo = CampioneMes::where('macchina_id', $m->id)->min('ts_utc');
            $primoGiorno = $primo ? Formato::oraLocale($primo, 'Y-m-d') : null;
            $giorni = [];
            for ($g = new DateTimeImmutable($filtri->da); $g <= new DateTimeImmutable($filtri->a); $g = $g->modify('+1 day')) {
                $chiave = $g->format('Y-m-d');
                $agg = $perGiorno->get($chiave);
                $totale = 0;
                if ($agg) {
                    foreach (StatoMacchina::cases() as $s) {
                        $totale += $agg->{$s->colonnaSecondi()};
                    }
                }
                $giorni[] = [
                    'giorno' => $chiave,
                    // Prima del primo campione importato: periodo non importato, non un'interruzione del MES
                    'non_importato' => $primoGiorno === null || $chiave < $primoGiorno,
                    'copertura' => ($agg && $totale > 0) ? ($totale - $agg->secondi_senza_dato) / $totale : null,
                    'campioni' => $agg?->campioni_presenti ?? 0,
                    'senza_dato' => $agg?->secondi_senza_dato,
                    'totale' => $totale,
                ];
            }

            $schede[] = [
                'macchina' => $m,
                'giorni' => $giorni,
                'settimane' => $this->settimane($giorni),
                'interruzioni' => $this->interruzioni($giorni),
                'sync' => SyncMes::where('macchina_id', $m->id)->first(),
                'primo' => $primo,
                'ultimo' => CampioneMes::where('macchina_id', $m->id)->max('ts_utc'),
            ];
        }

        return view('qualita_dato', ['filtri' => $filtri, 'schede' => $schede, 'tutteMacchine' => $kpi->macchine()]);
    }

    /**
     * Raggruppa i giorni in settimane da lunedì a domenica (celle vuote prima del primo e dopo l'ultimo giorno),
     * con l'etichetta del mese sulla prima settimana di ogni mese.
     *
     * @return array<int, array{mese: ?string, giorni: array<int, ?array>}>
     */
    private function settimane(array $giorni): array
    {
        if (! $giorni) {
            return [];
        }
        $mesi = ['', 'gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
        $vuoti = (int) (new DateTimeImmutable($giorni[0]['giorno']))->format('N') - 1;
        $celle = array_merge(array_fill(0, $vuoti, null), $giorni);
        $settimane = [];
        $meseVisto = null;
        foreach (array_chunk($celle, 7) as $blocco) {
            $blocco = array_pad($blocco, 7, null);
            $etichetta = null;
            foreach ($blocco as $g) {
                if ($g) {
                    $mese = substr($g['giorno'], 0, 7);
                    if ($mese !== $meseVisto) {
                        $meseVisto = $mese;
                        $etichetta = $mesi[(int) substr($mese, 5, 2)].(substr($mese, 5, 2) === '01' || ! $settimane ? ' '.substr($mese, 2, 2) : '');
                    }
                    break;
                }
            }
            $settimane[] = ['mese' => $etichetta, 'giorni' => $blocco];
        }

        return $settimane;
    }

    /** Periodi consecutivi di giorni con copertura < 50 % (o senza alcun dato). */
    private function interruzioni(array $giorni): array
    {
        $periodi = [];
        $corrente = null;
        foreach ($giorni as $g) {
            if ($g['non_importato']) {
                continue;
            }
            $interrotto = $g['copertura'] === null || $g['copertura'] < self::SOGLIA_INTERRUZIONE;
            if ($interrotto) {
                $corrente ??= ['da' => $g['giorno'], 'a' => $g['giorno'], 'giorni' => 0, 'ore_senza_dato' => 0];
                $corrente['a'] = $g['giorno'];
                $corrente['giorni']++;
                $corrente['ore_senza_dato'] += ($g['senza_dato'] ?? 86400) / 3600;
            } elseif ($corrente) {
                $periodi[] = $corrente;
                $corrente = null;
            }
        }
        if ($corrente) {
            $periodi[] = $corrente;
        }

        return array_reverse($periodi);
    }
}
