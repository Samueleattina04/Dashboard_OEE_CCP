<?php

namespace App\Mes;

interface SorgenteNetProLive
{
    /** @return array<int, array<string, mixed>> righe di FIOLINES (valore corrente dei segnali) */
    public function segnaliCorrenti(): array;

    /** @return array<int, array<string, mixed>> righe di MACCHINE (stato e contatori correnti) */
    public function statoMacchine(): array;
}
