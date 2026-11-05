<?php

namespace App\Domain\Classificatori;

use App\Domain\Campione;
use App\Domain\EsitoClassificazione;
use App\Domain\StatoMacchina;
use App\Domain\ValoreMes;
use InvalidArgumentException;

/**
 * Stato da un campo testuale con mappa valore -> stato (es. FX10130, FVALUE01 = CurrentSmState).
 * Campo vuoto -> SENZA_DATO; valore non presente nella mappa -> SENZA_DATO con motivo "valore_non_mappato".
 */
final class ClassificatoreMappaValori implements ClassificatoreStato
{
    /** @var array<string, StatoMacchina> */
    private array $mappa = [];

    /**
     * @param  array<string, string>  $mappa  es. ['Selecting' => 'MARCIA', 'Ready' => 'FERMO']
     */
    public function __construct(private readonly string $colonna, array $mappa)
    {
        foreach ($mappa as $valore => $stato) {
            $this->mappa[(string) $valore] = StatoMacchina::tryFrom($stato)
                ?? throw new InvalidArgumentException("Stato non valido nella regola: {$stato}");
        }
    }

    public static function daParametri(array $p): self
    {
        return new self($p['colonna'], $p['mappa']);
    }

    public function classifica(Campione $campione, ?Campione $precedente, ?StatoMacchina $statoPrecedente): EsitoClassificazione
    {
        $testo = $campione->valore($this->colonna);
        if (ValoreMes::vuoto($testo)) {
            return new EsitoClassificazione(StatoMacchina::SenzaDato, 'campo_vuoto');
        }
        // Confronto esatto sul valore registrato (solo spazi esterni rimossi)
        $chiave = trim($testo);
        if (! array_key_exists($chiave, $this->mappa)) {
            return new EsitoClassificazione(StatoMacchina::SenzaDato, 'valore_non_mappato');
        }

        return new EsitoClassificazione($this->mappa[$chiave]);
    }
}
