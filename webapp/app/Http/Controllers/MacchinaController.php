<?php

namespace App\Http\Controllers;

use App\Domain\StatoMacchina;
use App\Models\CampioneMes;
use App\Models\IntervalloStato;
use App\Models\Macchina;
use App\Services\LetturaKpi;
use App\Support\Filtri;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;

class MacchinaController extends Controller
{
    public function __invoke(Request $request, string $codice, LetturaKpi $kpi)
    {
        $macchina = Macchina::where('codice', $codice)->firstOrFail();
        $filtri = Filtri::daRichiesta($request);
        $tz = new DateTimeZone(config('dashboard.timezone_locale', 'Europe/Rome'));

        $giornoSel = $request->query('giorno');
        if (! $giornoSel || ! DateTimeImmutable::createFromFormat('!Y-m-d', $giornoSel)) {
            $giornoSel = $filtri->a;
        }
        $inizio = (new DateTimeImmutable($giornoSel.' 00:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
        $fine = (new DateTimeImmutable($giornoSel.' 00:00:00', $tz))->modify('+1 day')->setTimezone(new DateTimeZone('UTC'));

        $intervalli = IntervalloStato::where('macchina_id', $macchina->id)
            ->where('inizio_utc', '>=', $inizio->format('Y-m-d H:i:s.v'))
            ->where('inizio_utc', '<', $fine->format('Y-m-d H:i:s.v'))
            ->orderBy('inizio_utc')->get();
        $durataGiorno = $fine->getTimestamp() - $inizio->getTimestamp();
        // Intervalli consecutivi con lo stesso stato e motivo sono uniti in un unico blocco
        $blocchi = [];
        foreach ($intervalli as $iv) {
            $ultimo = $blocchi ? count($blocchi) - 1 : null;
            if ($ultimo !== null && $blocchi[$ultimo]['stato'] === $iv->stato && $blocchi[$ultimo]['motivo'] === $iv->motivo
                && $blocchi[$ultimo]['fine'] === $iv->inizio_utc) {
                $blocchi[$ultimo]['fine'] = $iv->fine_utc;
            } else {
                $blocchi[] = ['stato' => $iv->stato, 'motivo' => $iv->motivo, 'inizio' => $iv->inizio_utc, 'fine' => $iv->fine_utc];
            }
        }
        $timeline = collect($blocchi)->map(function ($b) use ($inizio, $durataGiorno) {
            $s = (new DateTimeImmutable($b['inizio'], new DateTimeZone('UTC')))->getTimestamp();
            $e = (new DateTimeImmutable($b['fine'], new DateTimeZone('UTC')))->getTimestamp();

            return $b + [
                'stato_enum' => StatoMacchina::from($b['stato']),
                'sinistra' => 100 * ($s - $inizio->getTimestamp()) / $durataGiorno,
                'larghezza' => 100 * ($e - $s) / $durataGiorno,
            ];
        });

        $campioni = CampioneMes::where('macchina_id', $macchina->id)
            ->where('ts_utc', '>=', $inizio->format('Y-m-d H:i:s.v'))
            ->where('ts_utc', '<', $fine->format('Y-m-d H:i:s.v'))
            ->orderBy('ts_utc')->get();

        $giorni = $kpi->giorni($macchina, $filtri->da, $filtri->a);

        return view('macchina', [
            'macchina' => $macchina,
            'filtri' => $filtri,
            'giorno' => $giornoSel,
            'timeline' => $timeline,
            'campioni' => $campioni,
            'giorni' => $giorni,
            'riepilogo' => $kpi->riepilogo($giorni),
            'regola' => $macchina->regolaValidaIl($inizio),
            'stati' => $macchina->statiRilevanti(),
            'contatori' => $macchina->haContatori(),
        ]);
    }
}
