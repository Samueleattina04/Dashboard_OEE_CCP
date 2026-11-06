<?php

namespace App\Support;

use App\Domain\ValoreMes;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/** Formati italiani (date gg/mm/aaaa, virgola decimale) e fuso Europe/Rome per la visualizzazione. */
final class Formato
{
    public static function data(string|DateTimeInterface|null $d): string
    {
        if ($d === null || $d === '') {
            return '';
        }

        return (is_string($d) ? new DateTimeImmutable($d) : $d)->format('d/m/Y');
    }

    /** Converte un istante UTC (stringa DB o oggetto) in ora locale formattata. */
    public static function oraLocale(string|DateTimeInterface|null $utc, string $formato = 'd/m/Y H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '';
        }
        $dt = is_string($utc) ? new DateTimeImmutable($utc, new DateTimeZone('UTC')) : DateTimeImmutable::createFromInterface($utc);

        return $dt->setTimezone(new DateTimeZone(config('dashboard.timezone_locale', 'Europe/Rome')))->format($formato);
    }

    /** Ore con una cifra decimale: 30.960 s → "8,6". */
    public static function ore(?int $secondi, int $decimali = 1): string
    {
        return $secondi === null ? 'n.d.' : number_format($secondi / 3600, $decimali, ',', '.');
    }

    /** Durata hh:mm. */
    public static function durata(?int $secondi): string
    {
        if ($secondi === null) {
            return 'n.d.';
        }
        $m = intdiv($secondi, 60);

        return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
    }

    public static function percentuale(float|string|null $x, int $decimali = 1): string
    {
        return $x === null ? 'n.d.' : number_format((float) $x * 100, $decimali, ',', '.').' %';
    }

    public static function numero(float|string|null $x, int $decimali = 1): string
    {
        return $x === null ? 'n.d.' : number_format((float) $x, $decimali, ',', '.');
    }

    /**
     * Valore NetPro in formato italiano per la sola visualizzazione: "21.80000" → "21,8", "11457" → "11.457".
     * I valori non numerici restano come sono. Il valore originale va mostrato altrove (es. title).
     */
    public static function valoreMes(?string $v): string
    {
        $d = ValoreMes::decimale($v);
        if ($d === null) {
            return $v ?? '—';
        }
        $decimali = str_contains($d, '.') ? strlen(rtrim(substr($d, strpos($d, '.') + 1), '0')) : 0;

        return number_format((float) $d, $decimali, ',', '.');
    }

    /** Spiegazioni dei motivi di "n.d." */
    public static function motivo(?string $codice): string
    {
        return match ($codice) {
            null => '',
            'calendario_non_configurato' => 'Calendario dei turni non configurato (Admin)',
            'nessun_turno_pianificato' => 'Nessun turno pianificato nel periodo',
            'capacita_non_configurata' => 'Capacità nominale non configurata (Admin)',
            'nessun_contatore_di_produzione' => 'La macchina non registra quantità prodotte',
            'definizione_qualita_non_disponibile' => 'Definizione di Qualità non ancora stabilita',
            'regola_di_stato_non_configurata' => 'Regola di stato non configurata',
            'valori_tutti_zero' => 'Valori tutti a zero: il PLC non comunica',
            default => $codice,
        };
    }
}
