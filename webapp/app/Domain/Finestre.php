<?php

namespace App\Domain;

/**
 * Operazioni su insiemi di finestre temporali [inizio, fine) espresse in timestamp Unix (secondi).
 */
final class Finestre
{
    /** @param array<int, array{0:int,1:int}> $f */
    public static function normalizza(array $f): array
    {
        $f = array_values(array_filter($f, fn ($x) => $x[1] > $x[0]));
        usort($f, fn ($a, $b) => $a[0] <=> $b[0]);
        $out = [];
        foreach ($f as $x) {
            if ($out && $x[0] <= $out[count($out) - 1][1]) {
                $out[count($out) - 1][1] = max($out[count($out) - 1][1], $x[1]);
            } else {
                $out[] = $x;
            }
        }

        return $out;
    }

    /** Sottrae le finestre $meno da $da. */
    public static function sottrai(array $da, array $meno): array
    {
        $risultato = self::normalizza($da);
        foreach (self::normalizza($meno) as [$ms, $me]) {
            $nuove = [];
            foreach ($risultato as [$s, $e]) {
                if ($me <= $s || $ms >= $e) {
                    $nuove[] = [$s, $e];

                    continue;
                }
                if ($ms > $s) {
                    $nuove[] = [$s, $ms];
                }
                if ($me < $e) {
                    $nuove[] = [$me, $e];
                }
            }
            $risultato = $nuove;
        }

        return $risultato;
    }

    public static function durata(array $f): int
    {
        return array_sum(array_map(fn ($x) => $x[1] - $x[0], self::normalizza($f)));
    }

    /** Secondi di sovrapposizione di un intervallo con un insieme di finestre. */
    public static function sovrapposizione(int $s, int $e, array $f): int
    {
        $tot = 0;
        foreach ($f as [$fs, $fe]) {
            $tot += max(0, min($e, $fe) - max($s, $fs));
        }

        return $tot;
    }
}
