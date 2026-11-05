<?php

namespace App\Domain;

/**
 * Conversione dei valori testuali di FPROCESSDATA (es. "42,2") SENZA arrotondamenti.
 * Il risultato è una stringa decimale normalizzata ("42.2"): il testo originale resta conservato altrove.
 */
final class ValoreMes
{
    /** true se il campo è assente o vuoto (FDI scrive comunque il record ogni 15 minuti). */
    public static function vuoto(?string $testo): bool
    {
        return $testo === null || trim($testo) === '';
    }

    /**
     * Restituisce la stringa decimale normalizzata, oppure null se il testo non è un numero puro
     * (es. "3,97 bar" non è numerico: contiene l'unità di misura).
     */
    public static function decimale(?string $testo): ?string
    {
        if (self::vuoto($testo)) {
            return null;
        }
        $t = trim($testo);
        if (! preg_match('/^[+-]?\d+([.,]\d+)?$/', $t)) {
            return null;
        }

        return ltrim(str_replace(',', '.', $t), '+');
    }

    /** Confronto numerico a - b senza perdita di precisione per i valori del MES (max 15 cifre, 5 decimali). */
    public static function confronta(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, 5);
        }

        return round((float) $a, 5) <=> round((float) $b, 5);
    }
}
