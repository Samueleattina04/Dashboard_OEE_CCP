<?php

namespace App\Services;

use App\Domain\CalcoloKpi;
use App\Domain\StatoMacchina;
use App\Models\AggGiorno;
use App\Models\Macchina;
use App\Models\SyncMes;
use Illuminate\Support\Collection;

/**
 * Letture per le viste: solo DB applicativo (aggregati giornalieri), mai il MES.
 */
class LetturaKpi
{
    /** @return Collection<int, Macchina> */
    public function macchine(?string $codice = null): Collection
    {
        return Macchina::where('attiva', true)
            ->when($codice, fn ($q) => $q->where('codice', $codice))
            ->orderBy('codice')->get();
    }

    /** Righe giornaliere ordinate per giorno. */
    public function giorni(Macchina $m, string $da, string $a): Collection
    {
        return AggGiorno::where('macchina_id', $m->id)
            ->whereBetween('giorno', [$da, $a])
            ->orderBy('giorno')->get();
    }

    /**
     * Totali di periodo con KPI pesati (somma delle grandezze, poi rapporto).
     *
     * @return array<string, mixed>
     */
    public function riepilogo(Collection $giorni): array
    {
        $r = ['giorni' => $giorni->count(), 'campioni' => (int) $giorni->sum('campioni_presenti'),
            'avviamenti' => (int) $giorni->sum('avviamenti'), 'reset_contatori' => (int) $giorni->sum('reset_contatori')];
        foreach (StatoMacchina::cases() as $s) {
            $r[$s->colonnaSecondi()] = (int) $giorni->sum($s->colonnaSecondi());
        }
        $totale = array_sum(array_map(fn ($s) => $r[$s->colonnaSecondi()], StatoMacchina::cases()));
        $esclusi = $r['secondi_senza_dato'] + $r['secondi_non_classificato'];
        $r['secondi_totali'] = $totale;
        $r['copertura'] = $totale > 0 ? ($totale - $r['secondi_senza_dato']) / $totale : null;
        $r['utilizzo_coperto'] = ($totale - $esclusi) > 0 ? $r['secondi_marcia'] / ($totale - $esclusi) : null;
        $r['utilizzo_calendario'] = $totale > 0 ? $r['secondi_marcia'] / $totale : null;
        $r['ore_marcia_media'] = $giorni->count() ? $r['secondi_marcia'] / $giorni->count() : null;
        $r['kg_selezionati'] = $giorni->whereNotNull('kg_selezionati')->count() ? (float) $giorni->sum('kg_selezionati') : null;
        $r['kg_scartati'] = $giorni->whereNotNull('kg_scartati')->count() ? (float) $giorni->sum('kg_scartati') : null;
        $r['scarto_selezione'] = ($r['kg_selezionati'] !== null && ($r['kg_selezionati'] + $r['kg_scartati']) > 0)
            ? $r['kg_scartati'] / ($r['kg_selezionati'] + $r['kg_scartati']) : null;

        $periodi = $giorni->map(fn ($g) => [
            'marcia_in_turno' => $g->secondi_marcia_in_turno,
            'pianificati' => $g->secondi_pianificati,
            'esclusi_in_turno' => $g->secondi_esclusi_in_turno,
            'kg_prodotti' => $g->kg_ingresso_in_turno !== null ? (float) $g->kg_ingresso_in_turno : null,
            'kg_teorici' => ($g->capacita_kg_ora !== null && $g->secondi_marcia_in_turno !== null)
                ? (float) $g->capacita_kg_ora * $g->secondi_marcia_in_turno / 3600 : null,
            'kg_conformi' => null, 'kg_totali' => null,
        ])->all();
        $r += CalcoloKpi::aggrega($periodi);

        // Motivo del primo n.d. per ciascun fattore
        $r['motivi'] = [];
        foreach (['disponibilita', 'prestazione', 'qualita'] as $k) {
            if ($r[$k] === null) {
                $r['motivi'][$k] = $giorni->map(fn ($g) => $g->motivi[$k] ?? null)->filter()->first();
            }
        }

        return $r;
    }

    /** Ultimo aggiornamento riuscito e stato della connessione al MES. */
    public function statoSincronizzazione(): array
    {
        $sync = SyncMes::all();
        $ultimo = $sync->max('ultimo_successo');
        $errore = $sync->firstWhere('esito', 'errore');
        $ritardo = $ultimo ? now()->diffInMinutes($ultimo, true) : null;

        return [
            'ultimo_successo' => $ultimo,
            'errore' => $errore?->messaggio,
            'mes_non_risponde' => $errore !== null || $ultimo === null || $ritardo > 35,
        ];
    }
}
