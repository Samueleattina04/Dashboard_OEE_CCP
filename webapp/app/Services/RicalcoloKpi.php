<?php

namespace App\Services;

use App\Domain\CalcoloKpi;
use App\Domain\CalendarioPianificato;
use App\Domain\ContatoreDelta;
use App\Domain\CostruttoreIntervalli;
use App\Domain\Finestre;
use App\Domain\Intervallo;
use App\Domain\StatoMacchina;
use App\Models\AggGiorno;
use App\Models\CampioneMes;
use App\Models\EccezioneCalendario;
use App\Models\IntervalloStato;
use App\Models\Macchina;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Ricalcolo di intervalli di stato e aggregati per giorno locale (docs/02 §2–§4).
 * Legge SOLO il DB applicativo (campioni_mes): non interroga il MES.
 */
class RicalcoloKpi
{
    /** @param string[] $giorni giorni locali "Y-m-d" */
    public function ricalcola(Macchina $macchina, array $giorni): void
    {
        foreach ($giorni as $giorno) {
            DB::transaction(fn () => $this->ricalcolaGiorno($macchina, $giorno));
        }
    }

    public function ricalcolaGiorno(Macchina $macchina, string $giorno): void
    {
        $fuso = config('dashboard.timezone_locale', 'Europe/Rome');
        $tzL = new DateTimeZone($fuso);
        $utc = new DateTimeZone('UTC');
        $inizio = (new DateTimeImmutable($giorno.' 00:00:00', $tzL))->setTimezone($utc);
        $fine = (new DateTimeImmutable($giorno.' 00:00:00', $tzL))->modify('+1 day')->setTimezone($utc);

        $regola = $macchina->regolaValidaIl($inizio);
        IntervalloStato::where('macchina_id', $macchina->id)
            ->where('inizio_utc', '>=', $inizio->format('Y-m-d H:i:s.v'))
            ->where('inizio_utc', '<', $fine->format('Y-m-d H:i:s.v'))
            ->delete();
        DB::table('agg_ora')->where('macchina_id', $macchina->id)
            ->where('ora_inizio_utc', '>=', $inizio->format('Y-m-d H:i:s'))
            ->where('ora_inizio_utc', '<', $fine->format('Y-m-d H:i:s'))
            ->delete();

        if ($regola === null) {
            AggGiorno::updateOrCreate(['macchina_id' => $macchina->id, 'giorno' => $giorno],
                ['motivi' => ['generale' => 'regola_di_stato_non_configurata'], 'calcolato_il' => now()]);

            return;
        }

        // Contesto: 2 ore prima (stato precedente, primo delta dei contatori) e 1 ora dopo (fine dell'ultimo intervallo)
        $campioni = CampioneMes::where('macchina_id', $macchina->id)
            ->where('ts_utc', '>=', $inizio->modify('-2 hours')->format('Y-m-d H:i:s.v'))
            ->where('ts_utc', '<', $fine->modify('+1 hour')->format('Y-m-d H:i:s.v'))
            ->orderBy('ts_utc')->orderBy('frow')
            ->get();
        $dominio = $campioni->map->versoDominio()->all();

        $costruttore = new CostruttoreIntervalli($regola->classificatore(), $regola->gMaxSecondi(),
            (int) config('dashboard.passo_campionamento_secondi', 900));
        $intervalli = $costruttore->costruisci($dominio, $inizio, $fine);

        $this->salvaIntervalli($macchina, $regola->id, $intervalli);
        $this->salvaOre($macchina, $intervalli, $inizio, $fine);

        $riga = ['regola_id' => $regola->id, 'calcolato_il' => now(), 'motivi' => []];
        foreach (StatoMacchina::cases() as $s) {
            $riga[$s->colonnaSecondi()] = 0;
        }
        foreach ($intervalli as $iv) {
            $riga[$iv->stato->colonnaSecondi()] += $iv->secondi();
        }
        $riga['campioni_presenti'] = count(array_filter($dominio, fn ($c) => $c->tsUtc >= $inizio && $c->tsUtc < $fine));
        $riga['avviamenti'] = $this->contaAvviamenti($intervalli);

        // Tempo pianificato
        $finestre = $this->finestrePianificate($macchina, $giorno, $fuso);
        $motivi = [];
        if ($finestre === null) {
            $riga += ['secondi_pianificati' => null, 'secondi_marcia_in_turno' => null,
                'secondi_esclusi_in_turno' => null, 'secondi_marcia_fuori_turno' => null];
            $motivi['disponibilita'] = 'calendario_non_configurato';
        } else {
            $marciaIn = $esclusiIn = 0;
            foreach ($intervalli as $iv) {
                $sov = Finestre::sovrapposizione($iv->inizio->getTimestamp(), $iv->fine->getTimestamp(), $finestre);
                if ($iv->stato === StatoMacchina::Marcia) {
                    $marciaIn += $sov;
                } elseif ($iv->stato->esclusoDalCalcolo()) {
                    $esclusiIn += $sov;
                }
            }
            $riga['secondi_pianificati'] = Finestre::durata($finestre);
            $riga['secondi_marcia_in_turno'] = $marciaIn;
            $riga['secondi_esclusi_in_turno'] = $esclusiIn;
            $riga['secondi_marcia_fuori_turno'] = $riga['secondi_marcia'] - $marciaIn;
            if ($riga['secondi_pianificati'] === 0) {
                $motivi['disponibilita'] = 'nessun_turno_pianificato';
            }
        }

        // Quantità (contatori, es. FX10130)
        $kgIngressoTurno = null;
        $contatori = $regola->parametri['contatori'] ?? null;
        $riga['kg_selezionati'] = $riga['kg_scartati'] = null;
        $riga['reset_contatori'] = 0;
        if ($contatori) {
            $kgIngressoTurno = 0.0;
            foreach (['selezionati' => 'kg_selezionati', 'scartati' => 'kg_scartati'] as $chiave => $colonna) {
                $tot = '0';
                foreach ($contatori[$chiave] ?? [] as $col) {
                    $esito = ContatoreDelta::calcola(array_map(fn ($c) => $c->valore($col), $dominio));
                    foreach ($esito['delta'] as $i => $d) {
                        $ts = $dominio[$i]->tsUtc;
                        if ($d === null || $ts < $inizio || $ts >= $fine) {
                            continue;
                        }
                        $tot = ContatoreDelta::somma($tot, $d);
                        // il delta si riferisce ai 15 minuti precedenti il campione: si usa il punto medio
                        $medio = $ts->getTimestamp() - 450;
                        if ($finestre !== null && Finestre::sovrapposizione($medio, $medio + 1, $finestre) > 0) {
                            $kgIngressoTurno += (float) $d;
                        }
                    }
                    // azzeramenti dei contatori avvenuti nel giorno (segnalati nella vista)
                    foreach ($esito['indici_reset'] as $i) {
                        $ts = $dominio[$i]->tsUtc;
                        if ($ts >= $inizio && $ts < $fine) {
                            $riga['reset_contatori']++;
                        }
                    }
                }
                $riga[$colonna] = $tot;
            }
            if ($finestre === null) {
                $kgIngressoTurno = null;
            }
        } else {
            $motivi['prestazione'] = 'nessun_contatore_di_produzione';
        }
        $riga['kg_ingresso_in_turno'] = $kgIngressoTurno;

        $capacita = $macchina->capacitaValidaIl($giorno);
        $riga['capacita_kg_ora'] = $capacita?->kg_ora;
        if ($contatori && ! $capacita) {
            $motivi['prestazione'] = 'capacita_non_configurata';
        }

        $riga['disponibilita'] = CalcoloKpi::disponibilita($riga['secondi_marcia_in_turno'], $riga['secondi_pianificati'], $riga['secondi_esclusi_in_turno']);
        $riga['prestazione'] = CalcoloKpi::prestazione($kgIngressoTurno, $capacita ? (float) $capacita->kg_ora : null, $riga['secondi_marcia_in_turno']);
        $riga['qualita'] = null;
        $motivi['qualita'] = 'definizione_qualita_non_disponibile';
        $riga['oee'] = CalcoloKpi::oee($riga['disponibilita'], $riga['prestazione'], $riga['qualita']);
        if ($macchina->dati_non_affidabili) {
            $motivi['generale'] = 'dati_non_affidabili: '.$macchina->motivo_non_affidabile;
        }
        $riga['motivi'] = $motivi;

        AggGiorno::updateOrCreate(['macchina_id' => $macchina->id, 'giorno' => $giorno], $riga);
    }

    /** @param Intervallo[] $intervalli */
    private function salvaIntervalli(Macchina $macchina, int $regolaId, array $intervalli): void
    {
        $righe = array_map(fn (Intervallo $iv) => [
            'macchina_id' => $macchina->id,
            'inizio_utc' => $iv->inizio->format('Y-m-d H:i:s.v'),
            'fine_utc' => $iv->fine->format('Y-m-d H:i:s.v'),
            'stato' => $iv->stato->value,
            'motivo' => $iv->motivo,
            'campione_id' => $iv->campioneId,
            'regola_id' => $regolaId,
        ], $intervalli);
        foreach (array_chunk($righe, 500) as $blocco) {
            IntervalloStato::insert($blocco);
        }
    }

    /** @param Intervallo[] $intervalli */
    private function salvaOre(Macchina $macchina, array $intervalli, DateTimeImmutable $inizio, DateTimeImmutable $fine): void
    {
        $righe = [];
        for ($ora = $inizio; $ora < $fine; $ora = $ora->modify('+1 hour')) {
            $succ = $ora->modify('+1 hour');
            $riga = ['macchina_id' => $macchina->id, 'ora_inizio_utc' => $ora->format('Y-m-d H:i:s')];
            foreach (StatoMacchina::cases() as $s) {
                $riga[$s->colonnaSecondi()] = 0;
            }
            foreach ($intervalli as $iv) {
                $riga[$iv->stato->colonnaSecondi()] += $iv->secondiIn($ora, $succ);
            }
            $righe[] = $riga;
        }
        DB::table('agg_ora')->insert($righe);
    }

    /** Avviamento = passaggio da FERMO a RISCALDAMENTO o MARCIA (eventuali buchi senza dato in mezzo sono ignorati). */
    private function contaAvviamenti(array $intervalli): int
    {
        $n = 0;
        $ultimoNoto = null;
        foreach ($intervalli as $iv) {
            if ($iv->stato->esclusoDalCalcolo()) {
                continue;
            }
            if ($ultimoNoto === StatoMacchina::Fermo
                && in_array($iv->stato, [StatoMacchina::Riscaldamento, StatoMacchina::Marcia], true)) {
                $n++;
            }
            $ultimoNoto = $iv->stato;
        }

        return $n;
    }

    /** @return array<int, array{0:int,1:int}>|null null se la macchina non ha un calendario */
    private function finestrePianificate(Macchina $macchina, string $giorno, string $fuso): ?array
    {
        $calendario = $macchina->calendario;
        if ($calendario === null) {
            return null;
        }
        $turni = $calendario->turni()->get(['tipo', 'giorno_settimana', 'ora_inizio', 'ora_fine'])->toArray();
        $eccezioni = EccezioneCalendario::where('calendario_id', $calendario->id)
            ->whereDate('data', $giorno)
            ->where(fn ($q) => $q->whereNull('macchina_id')->orWhere('macchina_id', $macchina->id))
            ->get(['ora_inizio', 'ora_fine'])->toArray();

        return CalendarioPianificato::finestre($giorno, $turni, $eccezioni, $fuso);
    }
}
