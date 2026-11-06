<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;

/**
 * Filtri globali riflessi nell'URL (periodo, macchina, giorno): una vista si condivide con un link.
 * Le date sono giorni locali Europe/Rome; "a" è incluso.
 */
final class Filtri
{
    public const PRESET = [
        'oggi' => 'Oggi', 'ieri' => 'Ieri', 'settimana' => 'Ultimi 7 giorni', 'mese' => 'Ultimi 30 giorni',
        '90giorni' => 'Ultimi 90 giorni', 'anno' => 'Ultimi 12 mesi', 'personalizzato' => 'Personalizzato',
    ];

    public function __construct(
        public readonly string $periodo,
        public readonly string $da,
        public readonly string $a,
        public readonly ?string $macchina,
    ) {}

    public static function daRichiesta(Request $request, string $periodoDefault = 'mese'): self
    {
        $tz = new DateTimeZone(config('dashboard.timezone_locale', 'Europe/Rome'));
        $oggi = new DateTimeImmutable('today', $tz);
        $periodo = array_key_exists($request->query('periodo'), self::PRESET) ? $request->query('periodo') : $periodoDefault;

        [$da, $a] = match ($periodo) {
            'oggi' => [$oggi, $oggi],
            'ieri' => [$oggi->modify('-1 day'), $oggi->modify('-1 day')],
            'settimana' => [$oggi->modify('-6 days'), $oggi],
            'mese' => [$oggi->modify('-29 days'), $oggi],
            '90giorni' => [$oggi->modify('-89 days'), $oggi],
            'anno' => [$oggi->modify('-1 year +1 day'), $oggi],
            'personalizzato' => [self::data($request->query('da'), $oggi->modify('-29 days')), self::data($request->query('a'), $oggi)],
        };
        if ($da > $a) {
            [$da, $a] = [$a, $da];
        }
        $macchina = $request->query('macchina') ?: null;

        return new self($periodo, $da->format('Y-m-d'), $a->format('Y-m-d'), $macchina);
    }

    private static function data(?string $valore, DateTimeImmutable $default): DateTimeImmutable
    {
        $d = $valore ? DateTimeImmutable::createFromFormat('!Y-m-d', $valore, $default->getTimezone()) : false;

        return $d ?: $default;
    }

    /** Parametri da riportare nei link e negli export. */
    public function query(array $extra = []): array
    {
        $q = ['periodo' => $this->periodo, 'macchina' => $this->macchina];
        if ($this->periodo === 'personalizzato') {
            $q += ['da' => $this->da, 'a' => $this->a];
        }

        return array_filter($q + $extra, fn ($v) => $v !== null && $v !== '');
    }

    public function descrizione(): string
    {
        return 'dal '.Formato::data($this->da).' al '.Formato::data($this->a)
            .($this->macchina ? ' — macchina '.$this->macchina : '');
    }
}
