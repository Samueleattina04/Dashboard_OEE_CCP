<?php

namespace App\Domain\Classificatori;

use App\Domain\Campione;
use App\Domain\EsitoClassificazione;
use App\Domain\StatoMacchina;

interface ClassificatoreStato
{
    /**
     * @param  Campione|null  $precedente  campione precedente valido (per regole che usano l'andamento)
     * @param  StatoMacchina|null  $statoPrecedente  stato attribuito al campione precedente
     */
    public function classifica(Campione $campione, ?Campione $precedente, ?StatoMacchina $statoPrecedente): EsitoClassificazione;
}
