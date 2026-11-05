<?php

namespace App\Domain;

/**
 * Differenze di un contatore crescente tra campioni consecutivi (es. kg selezionati FX10130).
 * Differenza negativa = azzeramento (DO_RESET_PRODUCT): si assume delta = valore nuovo e si segnala.
 * Campi vuoti o non numerici sono saltati (il delta riparte dal successivo valore valido).
 */
final class ContatoreDelta
{
    /**
     * @param  array<int, string|null>  $valori  valori testuali in ordine temporale
     * @return array{totale: string, reset: int, delta: array<int, string|null>, indici_reset: int[]}
     *                                                                                                delta[i] = incremento attribuito al campione i (null per il primo valido)
     */
    public static function calcola(array $valori): array
    {
        $totale = '0';
        $reset = 0;
        $indiciReset = [];
        $delta = [];
        $precedente = null;

        foreach ($valori as $i => $testo) {
            $v = ValoreMes::decimale($testo);
            if ($v === null) {
                $delta[$i] = null;

                continue;
            }
            if ($precedente === null) {
                $delta[$i] = null;
            } else {
                $d = self::sottrai($v, $precedente);
                if (ValoreMes::confronta($d, '0') < 0) {
                    $d = $v;
                    $reset++;
                    $indiciReset[] = $i;
                }
                $delta[$i] = $d;
                $totale = self::somma($totale, $d);
            }
            $precedente = $v;
        }

        return ['totale' => $totale, 'reset' => $reset, 'delta' => $delta, 'indici_reset' => $indiciReset];
    }

    public static function somma(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, 5) : self::fmt((float) $a + (float) $b);
    }

    public static function sottrai(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, 5) : self::fmt((float) $a - (float) $b);
    }

    private static function fmt(float $x): string
    {
        return number_format($x, 5, '.', '');
    }
}
