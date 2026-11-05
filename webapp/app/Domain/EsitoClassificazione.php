<?php

namespace App\Domain;

final class EsitoClassificazione
{
    public function __construct(
        public readonly StatoMacchina $stato,
        public readonly ?string $motivo = null,
    ) {}
}
