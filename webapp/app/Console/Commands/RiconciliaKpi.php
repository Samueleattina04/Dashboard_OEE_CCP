<?php

namespace App\Console\Commands;

use App\Domain\Campione;
use App\Domain\ConvertitoreTimestamp;
use App\Domain\CostruttoreIntervalli;
use App\Domain\StatoMacchina;
use App\Mes\SorgenteCampioni;
use App\Models\AggGiorno;
use App\Models\Macchina;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

/**
 * Riconciliazione (Fase 4): rilegge FPROCESSDATA direttamente dal MES (sola lettura) per una macchina e un giorno,
 * ricalcola le ore per stato IN MEMORIA, senza usare né scrivere il DB della dashboard, e le confronta con l'aggregato
 * salvato. Con --dettaglio stampa ogni campione con il valore originale e lo stato attribuito.
 */
class RiconciliaKpi extends Command
{
    protected $signature = 'kpi:riconcilia {macchina : codice FOBJECT} {giorno : AAAA-MM-GG} {--dettaglio : elenca ogni campione}';

    protected $description = 'Confronta le ore per stato della dashboard con un ricalcolo indipendente dai dati MES';

    public function handle(SorgenteCampioni $mes): int
    {
        $m = Macchina::where('codice', $this->argument('macchina'))->firstOrFail();
        $giorno = $this->argument('giorno');
        $tzL = new DateTimeZone(config('dashboard.timezone_locale', 'Europe/Rome'));
        $utc = new DateTimeZone('UTC');
        $inizioL = new DateTimeImmutable($giorno.' 00:00:00', $tzL);
        $inizio = $inizioL->setTimezone($utc);
        $fine = $inizioL->modify('+1 day')->setTimezone($utc);
        $adesso = new DateTimeImmutable('now', $utc);
        $fineDati = $fine < $adesso ? $fine : $adesso;

        $regola = $m->regolaValidaIl($inizio);
        if (! $regola) {
            $this->error('Nessuna regola di stato valida per quel giorno.');

            return self::FAILURE;
        }

        // Lettura dal MES: da 2 ore prima a 1 ora dopo il giorno (ora locale, come FTIMESTAMP)
        $righe = $mes->campioniDopo($m->codice, $m->colonne_import,
            $inizioL->modify('-2 hours')->format('Y-m-d H:i:s.v'),
            $inizioL->modify('+1 day +1 hour')->format('Y-m-d H:i:s.v'), 20000);
        $conv = new ConvertitoreTimestamp($tzL->getName());
        $campioni = [];
        foreach ($righe as $r) {
            $valori = [];
            foreach ($m->colonne_import as $c) {
                $valori[$c] = isset($r[$c]) ? (string) $r[$c] : null;
            }
            $campioni[] = new Campione($conv->versoUtc((string) $r['FTIMESTAMP']), $valori, null, (string) $r['FTIMESTAMP']);
        }

        $intervalli = (new CostruttoreIntervalli($regola->classificatore(), $regola->gMaxSecondi(),
            (int) config('dashboard.passo_campionamento_secondi', 900)))->costruisci($campioni, $inizio, $fineDati);
        $mesOre = [];
        foreach ($intervalli as $iv) {
            $mesOre[$iv->stato->value] = ($mesOre[$iv->stato->value] ?? 0) + $iv->secondi();
        }
        $nelGiorno = array_filter($campioni, fn ($c) => $c->tsUtc >= $inizio && $c->tsUtc < $fine);

        $agg = AggGiorno::where('macchina_id', $m->id)->whereDate('giorno', $giorno)->first();
        $this->info("Riconciliazione {$m->codice} — {$giorno} (regola: {$regola->tipo}, valida dal {$regola->valido_dal->format('d/m/Y')})");
        $this->line('Campioni MES nel giorno: '.count($nelGiorno).($agg ? " · nella dashboard: {$agg->campioni_presenti}" : ' · nessun aggregato nella dashboard'));

        $tabella = [];
        $scostamentoMax = 0;
        foreach ($m->statiRilevanti() as $s) {
            $daMes = $mesOre[$s->value] ?? 0;
            $daDash = $agg ? (int) $agg->{$s->colonnaSecondi()} : null;
            $diff = $daDash === null ? null : $daDash - $daMes;
            $scostamentoMax = max($scostamentoMax, abs((int) $diff));
            $tabella[] = [$s->etichetta(), self::ore($daMes), $daDash === null ? '—' : self::ore($daDash),
                $diff === null ? '—' : sprintf('%+d min', intdiv($diff, 60))];
        }
        $this->table(['Stato', 'Ricalcolo da MES (h)', 'Dashboard (h)', 'Differenza'], $tabella);
        $this->line($scostamentoMax === 0 ? '<info>Nessuno scostamento.</info>'
            : '<comment>Scostamento massimo: '.intdiv($scostamentoMax, 60).' min</comment> (normale se nel frattempo FDI ha scritto nuovi record: rieseguire kpi:ricalcola per quel giorno).');

        if ($this->option('dettaglio')) {
            $prec = null;
            $statoPrec = null;
            $cl = $regola->classificatore();
            $righeDettaglio = [];
            foreach ($campioni as $c) {
                if ($c->tsUtc < $inizio || $c->tsUtc >= $fine) {
                    // aggiorna comunque il contesto per i transitori
                } else {
                    $e = $cl->classifica($c, $prec, $statoPrec);
                    $valori = array_filter($c->valori, fn ($v) => $v !== null && $v !== '');
                    $righeDettaglio[] = [$c->tsUtc->setTimezone($tzL)->format('H:i:s'), $c->ftimestampOriginale,
                        $e->stato->etichetta().($e->motivo ? " ({$e->motivo})" : ''),
                        mb_strimwidth(implode(' · ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($valori), $valori)), 0, 90, '…')];
                }
                $e2 = $cl->classifica($c, $prec, $statoPrec);
                if ($e2->stato !== StatoMacchina::SenzaDato) {
                    $prec = $c;
                    $statoPrec = $e2->stato;
                }
            }
            $this->table(['Ora (Roma)', 'FTIMESTAMP originale', 'Stato attribuito', 'Valori registrati'], $righeDettaglio);
        }

        return self::SUCCESS;
    }

    private static function ore(int $secondi): string
    {
        return number_format($secondi / 3600, 2, ',', '.');
    }
}
