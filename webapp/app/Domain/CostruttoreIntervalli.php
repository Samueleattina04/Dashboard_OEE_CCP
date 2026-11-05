<?php

namespace App\Domain;

use App\Domain\Classificatori\ClassificatoreStato;
use DateTimeImmutable;

/**
 * Trasforma i campioni a 15 minuti in intervalli di stato (docs/02 §2.3).
 *
 * - Il campione al tempo t vale per [t, t_succ).
 * - Se t_succ - t > G_max il campione copre solo [t, t + passo) e il resto è SENZA_DATO (record mancante).
 * - L'ultimo campione copre [t, min(t + passo, fine)).
 * - Il tempo tra "inizio" e il primo campione è SENZA_DATO.
 */
final class CostruttoreIntervalli
{
    public function __construct(
        private readonly ClassificatoreStato $classificatore,
        private readonly int $gMaxSecondi,
        private readonly int $passoSecondi = 900,
    ) {}

    /**
     * @param  Campione[]  $campioni  ordinati per tsUtc; possono includere campioni precedenti a $inizio
     * @return Intervallo[] intervalli contigui e ritagliati su [inizio, fine)
     */
    public function costruisci(array $campioni, DateTimeImmutable $inizio, DateTimeImmutable $fine): array
    {
        $grezzi = [];
        $precedenteValido = null;
        $statoPrecedente = null;
        $n = count($campioni);

        for ($i = 0; $i < $n; $i++) {
            $c = $campioni[$i];
            $esito = $this->classificatore->classifica($c, $precedenteValido, $statoPrecedente);

            $t = $c->tsUtc;
            $succ = $i + 1 < $n ? $campioni[$i + 1]->tsUtc : null;
            $fineCopertura = $t->modify("+{$this->passoSecondi} seconds");

            if ($succ !== null && ($succ->getTimestamp() - $t->getTimestamp()) <= $this->gMaxSecondi) {
                $grezzi[] = new Intervallo($t, $succ, $esito->stato, $esito->motivo, $c->id);
            } else {
                $limite = $succ ?? $fine;
                $fineReale = $fineCopertura < $limite ? $fineCopertura : $limite;
                $grezzi[] = new Intervallo($t, $fineReale, $esito->stato, $esito->motivo, $c->id);
                if ($succ !== null && $fineReale < $succ) {
                    $grezzi[] = new Intervallo($fineReale, $succ, StatoMacchina::SenzaDato, 'record_mancante');
                }
            }

            if ($esito->stato !== StatoMacchina::SenzaDato) {
                $precedenteValido = $c;
                $statoPrecedente = $esito->stato;
            }
        }

        return $this->ritaglia($grezzi, $inizio, $fine);
    }

    /** @param Intervallo[] $grezzi */
    private function ritaglia(array $grezzi, DateTimeImmutable $inizio, DateTimeImmutable $fine): array
    {
        $risultato = [];
        $cursore = $inizio;
        foreach ($grezzi as $iv) {
            if ($iv->fine <= $inizio || $iv->inizio >= $fine) {
                continue;
            }
            $da = $iv->inizio < $inizio ? $inizio : $iv->inizio;
            $a = $iv->fine > $fine ? $fine : $iv->fine;
            if ($da > $cursore) {
                $risultato[] = new Intervallo($cursore, $da, StatoMacchina::SenzaDato, 'record_mancante');
            }
            if ($a > $da) {
                $risultato[] = new Intervallo($da, $a, $iv->stato, $iv->motivo, $iv->campioneId);
            }
            $cursore = $a > $cursore ? $a : $cursore;
        }
        if ($cursore < $fine) {
            // Nessun campione fino alla fine del periodo: senza dato (non "fermo")
            $risultato[] = new Intervallo($cursore, $fine, StatoMacchina::SenzaDato, 'record_mancante');
        }

        return $risultato;
    }
}
