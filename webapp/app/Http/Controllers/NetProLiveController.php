<?php

namespace App\Http\Controllers;

use App\Domain\ValoreMes;
use App\Models\Macchina;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * NetPro live: tutte le macchine e tutti i segnali in tempo reale di NetPro, dalle letture ogni 2 minuti
 * salvate nel DB della dashboard (la pagina non interroga il MES).
 */
class NetProLiveController extends Controller
{
    public function __invoke(Request $request)
    {
        $tz = new DateTimeZone(config('dashboard.timezone_locale', 'Europe/Rome'));
        $adesso = new DateTimeImmutable('now', $tz);
        $sogliaFermo = (int) config('dashboard.netpro_segnale_fermo_minuti', 30);
        $tutte = $request->boolean('tutte');

        $letture = DB::table('netpro_letture')->orderBy('letto_il')->get()->groupBy('oggetto');
        $ultimaLettura = DB::table('netpro_letture')->max('letto_il');
        $inDashboard = Macchina::pluck('codice')->all();

        $schede = [];
        foreach (DB::table('netpro_macchine')->orderBy('codice')->get() as $m) {
            $stato = json_decode($m->stato, true) ?? [];
            $perChiave = ($letture[$m->codice] ?? collect())->groupBy('chiave');

            $segnali = [];
            foreach ($perChiave as $chiave => $serie) {
                $prima = $serie->first();
                if ($prima->tipo !== 'segnale') {
                    continue;
                }
                $ultima = $serie->last();
                $eta = $this->minutiDa($ultima->ts_mes, $tz, $adesso);
                $numeri = $serie->map(fn ($r) => ValoreMes::decimale($r->valore))->filter(fn ($v) => $v !== null)->values();
                $segnali[] = [
                    'chiave' => $chiave,
                    'nome' => str_contains($chiave, '.') ? substr($chiave, strpos($chiave, '.') + 1) : $chiave,
                    'descrizione' => $ultima->descrizione,
                    'valore' => $ultima->valore,
                    'ts_mes' => $ultima->ts_mes,
                    'eta_minuti' => $eta,
                    'fermo' => $eta === null || $eta > $sogliaFermo,
                    // Valori letti nella finestra (solo quando cambiano)
                    'storia' => $serie->reduce(function ($acc, $r) {
                        if (! $acc || end($acc)['valore'] !== $r->valore) {
                            $acc[] = ['ora' => $r->letto_il, 'valore' => $r->valore];
                        }

                        return $acc;
                    }, []),
                    'delta' => $numeri->count() >= 2 ? (float) $numeri->last() - (float) $numeri->first() : null,
                ];
            }

            $attiva = collect($segnali)->contains(fn ($s) => ! $s['fermo']);
            $schede[] = [
                'codice' => $m->codice,
                'descrizione' => $m->descrizione,
                'centro' => $m->centro,
                'stato' => $stato,
                'segnali' => $segnali,
                'attiva' => $attiva,
                'in_dashboard' => in_array($m->codice, $inDashboard, true),
            ];
        }

        // Prima le macchine con segnali attivi, poi quelle con segnali fermi, poi quelle senza segnali
        usort($schede, fn ($a, $b) => [! $a['attiva'], empty($a['segnali']), $a['codice']] <=> [! $b['attiva'], empty($b['segnali']), $b['codice']]);
        $visibili = $tutte ? $schede : array_filter($schede, fn ($s) => ! empty($s['segnali']));

        return view('netpro_live', [
            'schede' => $visibili,
            'nascoste' => array_values(array_filter($schede, fn ($s) => empty($s['segnali']))),
            'tutte' => $tutte,
            'ultimaLettura' => $ultimaLettura,
            'minuti' => (int) config('dashboard.netpro_live_minuti', 10),
            'sogliaFermo' => $sogliaFermo,
        ]);
    }

    /** Minuti trascorsi da un'ora locale MES ("Y-m-d H:i:s.v"); null se assente. */
    private function minutiDa(?string $ts, DateTimeZone $tz, DateTimeImmutable $adesso): ?int
    {
        if (! $ts) {
            return null;
        }
        $t = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.v', $ts, $tz) ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ts, $tz);

        return $t ? (int) floor(($adesso->getTimestamp() - $t->getTimestamp()) / 60) : null;
    }
}
