<?php

namespace App\Domain;

/**
 * Stati usati dalla dashboard (docs/02_definizioni_kpi.md §2.1).
 */
enum StatoMacchina: string
{
    case Marcia = 'MARCIA';
    case Fermo = 'FERMO';
    case Allarme = 'ALLARME';
    case Avvio = 'AVVIO';
    case Riscaldamento = 'RISCALDAMENTO';
    case Raffreddamento = 'RAFFREDDAMENTO';
    case NonClassificato = 'NON_CLASSIFICATO';
    case SenzaDato = 'SENZA_DATO';

    /** Ordine di impilamento nei grafici e nelle legende (lo stesso ovunque). */
    public static function ordineVisualizzazione(): array
    {
        return [self::Marcia, self::Riscaldamento, self::Raffreddamento, self::Avvio, self::Allarme,
            self::Fermo, self::NonClassificato, self::SenzaDato];
    }

    /** Stati esclusi da numeratore e denominatore dei KPI (dato assente o di significato ignoto). */
    public function esclusoDalCalcolo(): bool
    {
        return $this === self::SenzaDato || $this === self::NonClassificato;
    }

    /** Nome della colonna aggregata (es. secondi_marcia). */
    public function colonnaSecondi(): string
    {
        return 'secondi_'.strtolower($this->value);
    }

    public function etichetta(): string
    {
        return match ($this) {
            self::Marcia => 'In funzione',
            self::Fermo => 'Ferma',
            self::Allarme => 'Allarme',
            self::Avvio => 'Avvio / setup',
            self::Riscaldamento => 'Riscaldamento',
            self::Raffreddamento => 'Raffreddamento',
            self::NonClassificato => 'Non classificato',
            self::SenzaDato => 'Senza dato',
        };
    }
}
