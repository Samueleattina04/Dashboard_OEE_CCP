<?php

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * FTIMESTAMP è ora locale Europe/Rome senza offset. Conversione in UTC con gestione dell'ora ripetuta
 * di fine ottobre: i campioni sono letti in ordine (FTIMESTAMP, FROW); se l'UTC calcolato non è successivo
 * al precedente si assume la seconda occorrenza dell'ora (ora solare, +1 h).
 * Lo stato (ultimo UTC) va mantenuto per macchina.
 */
final class ConvertitoreTimestamp
{
    private ?DateTimeImmutable $ultimoUtc = null;

    private DateTimeZone $locale;

    private DateTimeZone $utc;

    public function __construct(string $fuso = 'Europe/Rome', ?DateTimeImmutable $ultimoUtc = null)
    {
        $this->locale = new DateTimeZone($fuso);
        $this->utc = new DateTimeZone('UTC');
        $this->ultimoUtc = $ultimoUtc;
    }

    /** @param string $locale es. "2026-10-25 02:30:00.000" */
    public function versoUtc(string $locale): DateTimeImmutable
    {
        $formato = str_contains($locale, '.') ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
        $dt = DateTimeImmutable::createFromFormat($formato, $locale, $this->locale);
        if ($dt === false) {
            throw new \InvalidArgumentException("Timestamp MES non valido: {$locale}");
        }
        // Candidati UTC che corrispondono alla stessa ora locale (due nell'ora ripetuta di fine ottobre)
        $base = $dt->setTimezone($this->utc);
        $chiave = $dt->format('Y-m-d H:i:s.u');
        $candidati = [];
        foreach (['-1 hour', '+0 hour', '+1 hour'] as $spostamento) {
            $c = $base->modify($spostamento);
            if ($c->setTimezone($this->locale)->format('Y-m-d H:i:s.u') === $chiave) {
                $candidati[] = $c;
            }
        }
        sort($candidati);
        // Si sceglie la prima occorrenza successiva all'ultimo campione; altrimenti la prima in assoluto
        $utc = $candidati[0];
        if ($this->ultimoUtc !== null) {
            foreach ($candidati as $c) {
                if ($c > $this->ultimoUtc) {
                    $utc = $c;
                    break;
                }
            }
        }
        $this->ultimoUtc = $utc;

        return $utc;
    }
}
