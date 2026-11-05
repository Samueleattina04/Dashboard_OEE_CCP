<?php

namespace App\Domain;

/**
 * Formule OEE (docs/02 §4). Un fattore non calcolabile è NULL (n.d.), mai 0 o 1 per difetto.
 * Le aggregazioni sommano prima le grandezze e poi calcolano il rapporto (pesatura sul tempo pianificato).
 */
final class CalcoloKpi
{
    /** D = marcia nel turno / (pianificato - esclusi nel turno) */
    public static function disponibilita(?int $marciaInTurno, ?int $pianificati, ?int $esclusiInTurno): ?float
    {
        if ($marciaInTurno === null || $pianificati === null) {
            return null;
        }
        $netto = $pianificati - (int) $esclusiInTurno;

        return $netto > 0 ? $marciaInTurno / $netto : null;
    }

    /** P = kg prodotti / (capacità kg/h × ore di marcia) */
    public static function prestazione(?float $kgProdotti, ?float $capacitaKgOra, ?int $marciaSecondi): ?float
    {
        if ($kgProdotti === null || $capacitaKgOra === null || $capacitaKgOra <= 0 || ! $marciaSecondi) {
            return null;
        }

        return $kgProdotti / ($capacitaKgOra * $marciaSecondi / 3600);
    }

    /** Q = conformi / totale */
    public static function qualita(?float $conformi, ?float $totale): ?float
    {
        if ($conformi === null || $totale === null || $totale <= 0) {
            return null;
        }

        return $conformi / $totale;
    }

    public static function oee(?float $d, ?float $p, ?float $q): ?float
    {
        return ($d === null || $p === null || $q === null) ? null : $d * $p * $q;
    }

    /**
     * Aggregazione pesata di più periodi.
     *
     * @param  array<int, array{marcia_in_turno:?int, pianificati:?int, esclusi_in_turno:?int, kg_prodotti:?float, kg_teorici:?float, kg_conformi:?float, kg_totali:?float}>  $periodi
     *                                                                                                                                                                                  kg_teorici = capacità × ore di marcia del periodo
     * @return array{disponibilita:?float, prestazione:?float, qualita:?float, oee:?float}
     */
    public static function aggrega(array $periodi): array
    {
        $marcia = $netto = 0;
        $kgProd = $kgTeo = $kgConf = $kgTot = 0.0;
        $dOk = $pOk = $qOk = count($periodi) > 0;

        foreach ($periodi as $p) {
            if ($p['marcia_in_turno'] === null || $p['pianificati'] === null) {
                $dOk = false;
            } else {
                $marcia += $p['marcia_in_turno'];
                $netto += $p['pianificati'] - (int) ($p['esclusi_in_turno'] ?? 0);
            }
            if ($p['kg_prodotti'] === null || $p['kg_teorici'] === null) {
                $pOk = false;
            } else {
                $kgProd += $p['kg_prodotti'];
                $kgTeo += $p['kg_teorici'];
            }
            if ($p['kg_conformi'] === null || $p['kg_totali'] === null) {
                $qOk = false;
            } else {
                $kgConf += $p['kg_conformi'];
                $kgTot += $p['kg_totali'];
            }
        }

        $d = $dOk && $netto > 0 ? $marcia / $netto : null;
        $pr = $pOk && $kgTeo > 0 ? $kgProd / $kgTeo : null;
        $q = $qOk && $kgTot > 0 ? $kgConf / $kgTot : null;

        return ['disponibilita' => $d, 'prestazione' => $pr, 'qualita' => $q, 'oee' => self::oee($d, $pr, $q)];
    }
}
