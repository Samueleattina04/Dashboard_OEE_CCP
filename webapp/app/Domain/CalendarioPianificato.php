<?php

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Finestre di tempo pianificato di un giorno locale (docs/02 §4.1):
 * turni del giorno della settimana − pause − eccezioni (festivi, fermi programmati, assenza ordini).
 * I turni che superano la mezzanotte appartengono al giorno in cui iniziano.
 */
final class CalendarioPianificato
{
    /**
     * @param  array<int, array{tipo:string, giorno_settimana:int, ora_inizio:string, ora_fine:string}>  $turni
     * @param  array<int, array{ora_inizio:?string, ora_fine:?string}>  $eccezioniDelGiorno
     * @return array<int, array{0:int,1:int}> finestre in timestamp Unix
     */
    public static function finestre(string $giorno, array $turni, array $eccezioniDelGiorno, string $fuso = 'Europe/Rome'): array
    {
        $tz = new DateTimeZone($fuso);
        $base = new DateTimeImmutable($giorno.' 00:00:00', $tz);
        $isoGiorno = (int) $base->format('N');

        $turniGiorno = [];
        $pause = [];
        foreach ($turni as $t) {
            if ((int) $t['giorno_settimana'] !== $isoGiorno) {
                continue;
            }
            $f = self::finestra($base, $t['ora_inizio'], $t['ora_fine'], $tz);
            if ($t['tipo'] === 'pausa') {
                $pause[] = $f;
            } else {
                $turniGiorno[] = $f;
            }
        }

        $escluse = [];
        foreach ($eccezioniDelGiorno as $e) {
            if ($e['ora_inizio'] === null || $e['ora_fine'] === null) {
                $fineGiorno = $base->modify('+1 day');
                // Tutto il giorno, compresi i turni che proseguono dopo la mezzanotte
                $escluse[] = [$base->getTimestamp(), $fineGiorno->modify('+1 day')->getTimestamp()];
            } else {
                $escluse[] = self::finestra($base, $e['ora_inizio'], $e['ora_fine'], $tz);
            }
        }

        return Finestre::sottrai(Finestre::sottrai($turniGiorno, $pause), $escluse);
    }

    private static function finestra(DateTimeImmutable $base, string $da, string $a, DateTimeZone $tz): array
    {
        $giorno = $base->format('Y-m-d');
        $s = new DateTimeImmutable("{$giorno} {$da}", $tz);
        $e = new DateTimeImmutable("{$giorno} {$a}", $tz);
        if ($e <= $s) {
            $e = $e->modify('+1 day');
        }

        return [$s->getTimestamp(), $e->getTimestamp()];
    }
}
