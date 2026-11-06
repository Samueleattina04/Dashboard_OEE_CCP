<?php

namespace App\Http\Controllers;

use App\Domain\StatoMacchina;
use App\Services\LetturaKpi;
use App\Support\Filtri;
use Illuminate\Http\Request;

class PanoramicaController extends Controller
{
    public function __invoke(Request $request, LetturaKpi $kpi)
    {
        $filtri = Filtri::daRichiesta($request);
        $schede = [];
        foreach ($kpi->macchine($filtri->macchina) as $m) {
            $giorni = $kpi->giorni($m, $filtri->da, $filtri->a);
            $schede[] = [
                'macchina' => $m,
                'riepilogo' => $kpi->riepilogo($giorni),
                'serie' => $this->serie($giorni),
                'stati' => $m->statiRilevanti(),
                'contatori' => $m->haContatori(),
            ];
        }

        return view('panoramica', ['filtri' => $filtri, 'schede' => $schede, 'tutteMacchine' => $kpi->macchine()]);
    }

    /** Serie giornaliere (ore per stato) per il grafico. */
    private function serie($giorni): array
    {
        $serie = ['giorni' => $giorni->map(fn ($g) => $g->giorno->format('d/m'))->all()];
        foreach (StatoMacchina::cases() as $s) {
            $serie[$s->value] = $giorni->map(fn ($g) => round($g->{$s->colonnaSecondi()} / 3600, 2))->all();
        }

        return $serie;
    }
}
