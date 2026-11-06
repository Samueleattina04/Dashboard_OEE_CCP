<?php

namespace App\Http\Controllers;

use App\Domain\StatoMacchina;
use App\Models\CampioneMes;
use App\Models\IntervalloStato;
use App\Services\LetturaKpi;

/**
 * Stato attuale: ultimo campione HIST importato (ritardo massimo ~15 min). Nessuna lettura diretta dal MES.
 */
class StatoMacchineController extends Controller
{
    public function __invoke(LetturaKpi $kpi)
    {
        $schede = [];
        foreach ($kpi->macchine() as $m) {
            $ultimoCampione = CampioneMes::where('macchina_id', $m->id)->orderByDesc('ts_utc')->first();
            // Ultimo intervallo derivato da un campione reale (non i buchi senza record)
            $ultimo = IntervalloStato::where('macchina_id', $m->id)
                ->whereNotNull('campione_id')
                ->orderByDesc('inizio_utc')->first();

            // Da quando dura lo stato attuale: si risale finché lo stato non cambia
            $dal = $ultimo?->inizio_utc;
            if ($ultimo) {
                $precedenti = IntervalloStato::where('macchina_id', $m->id)
                    ->where('inizio_utc', '<', $ultimo->inizio_utc)
                    ->orderByDesc('inizio_utc')->limit(500)->get(['inizio_utc', 'stato']);
                foreach ($precedenti as $p) {
                    if ($p->stato !== $ultimo->stato) {
                        break;
                    }
                    $dal = $p->inizio_utc;
                }
            }
            $stato = $ultimo ? StatoMacchina::from($ultimo->stato) : StatoMacchina::SenzaDato;

            $schede[] = [
                'macchina' => $m,
                'stato' => $stato,
                'motivo' => $ultimo?->motivo,
                'dal' => $dal,
                'ultimo_campione' => $ultimoCampione,
            ];
        }

        return view('stato', ['schede' => $schede]);
    }
}
