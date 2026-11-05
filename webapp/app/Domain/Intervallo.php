<?php

namespace App\Domain;

use DateTimeImmutable;

final class Intervallo
{
    public function __construct(
        public readonly DateTimeImmutable $inizio,
        public readonly DateTimeImmutable $fine,
        public readonly StatoMacchina $stato,
        public readonly ?string $motivo = null,
        public readonly ?int $campioneId = null,
    ) {}

    public function secondi(): int
    {
        return max(0, $this->fine->getTimestamp() - $this->inizio->getTimestamp());
    }

    /** Secondi di sovrapposizione con la finestra [da, a). */
    public function secondiIn(DateTimeImmutable $da, DateTimeImmutable $a): int
    {
        $inizio = max($this->inizio->getTimestamp(), $da->getTimestamp());
        $fine = min($this->fine->getTimestamp(), $a->getTimestamp());

        return max(0, $fine - $inizio);
    }
}
