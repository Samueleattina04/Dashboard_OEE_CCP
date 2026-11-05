<?php

namespace App\Domain;

use DateTimeImmutable;

/**
 * Un record HIST di FPROCESSDATA, con i valori nel testo originale.
 */
final class Campione
{
    /**
     * @param  array<string, string|null>  $valori  es. ['FVALUE01' => 'Selecting', 'FNVALUE03' => '12.50000']
     */
    public function __construct(
        public readonly DateTimeImmutable $tsUtc,
        public readonly array $valori,
        public readonly ?int $id = null,
        public readonly ?string $ftimestampOriginale = null,
    ) {}

    public function valore(string $colonna): ?string
    {
        return $this->valori[strtoupper($colonna)] ?? null;
    }
}
