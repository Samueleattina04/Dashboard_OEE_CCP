<?php

namespace App\Http\Controllers;

use App\Services\LetturaKpi;
use App\Support\Filtri;
use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * Storico e confronti: periodo A contro periodo B (default: periodo precedente di pari durata),
 * per tutte le macchine attive (o una sola). Legge solo gli aggregati giornalieri.
 */
class StoricoController extends Controller
{
    public function __invoke(Request $request, LetturaKpi $kpi)
    {
        [$filtri, $daB, $aB] = self::periodi($request);

        $righe = self::confronto($kpi, $filtri, $daB, $aB);
        $oreMedie = fn (array $r) => $r['ore_marcia_media'] !== null ? round($r['ore_marcia_media'] / 3600, 2) : null;

        return view('storico', [
            'filtri' => $filtri, 'daB' => $daB, 'aB' => $aB,
            'righe' => $righe,
            'datiGrafico' => [
                'macchine' => array_map(fn ($r) => $r['macchina']->codice, $righe),
                'a' => array_map(fn ($r) => $oreMedie($r['a']), $righe),
                'b' => array_map(fn ($r) => $oreMedie($r['b']), $righe),
            ],
            'tutteMacchine' => $kpi->macchine(),
        ]);
    }

    /** @return array{0: Filtri, 1: string, 2: string} */
    public static function periodi(Request $request): array
    {
        $filtri = Filtri::daRichiesta($request);
        $da = new DateTimeImmutable($filtri->da);
        $a = new DateTimeImmutable($filtri->a);
        $giorni = (int) $da->diff($a)->days + 1;

        $bDa = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query('b_da'));
        $bA = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query('b_a'));
        if (! $bDa || ! $bA || $bDa > $bA) {
            // Periodo precedente di pari durata
            $bA = $da->modify('-1 day');
            $bDa = $bA->modify('-'.($giorni - 1).' days');
        }

        return [$filtri, $bDa->format('Y-m-d'), $bA->format('Y-m-d')];
    }

    /** @return array<int, array{macchina: mixed, a: array, b: array}> */
    public static function confronto(LetturaKpi $kpi, Filtri $filtri, string $daB, string $aB): array
    {
        $righe = [];
        foreach ($kpi->macchine($filtri->macchina) as $m) {
            $righe[] = [
                'macchina' => $m,
                'a' => $kpi->riepilogo($kpi->giorni($m, $filtri->da, $filtri->a)),
                'b' => $kpi->riepilogo($kpi->giorni($m, $daB, $aB)),
            ];
        }

        return $righe;
    }
}
