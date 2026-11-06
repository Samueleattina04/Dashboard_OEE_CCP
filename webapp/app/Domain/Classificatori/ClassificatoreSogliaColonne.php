<?php

namespace App\Domain\Classificatori;

use App\Domain\Campione;
use App\Domain\EsitoClassificazione;
use App\Domain\StatoMacchina;
use App\Domain\ValoreMes;

/**
 * Stato da una o più colonne numeriche (es. BROVRAF: velocità motore raffinatrice 1 e 2).
 *   almeno una colonna > soglia             -> MARCIA
 *   tutte <= soglia                         -> FERMO
 *   colonne vuote o non numeriche           -> SENZA_DATO
 *   tutte le "colonne_validita" esattamente 0 -> SENZA_DATO (motivo "valori_tutti_zero"):
 *     es. temperature prodotto a 0,0 °C = PLC che non comunica, non macchina ferma.
 */
final class ClassificatoreSogliaColonne implements ClassificatoreStato
{
    /**
     * @param  string[]  $colonne
     * @param  string[]  $colonneValidita
     */
    public function __construct(
        private readonly array $colonne,
        private readonly string $soglia,
        private readonly array $colonneValidita = [],
    ) {}

    public static function daParametri(array $p): self
    {
        return new self($p['colonne'], (string) ($p['soglia'] ?? '0'), $p['colonne_validita'] ?? []);
    }

    public function classifica(Campione $campione, ?Campione $precedente, ?StatoMacchina $statoPrecedente): EsitoClassificazione
    {
        if ($this->colonneValidita) {
            $tuttiZero = true;
            foreach ($this->colonneValidita as $c) {
                $v = ValoreMes::decimale($campione->valore($c));
                if ($v === null || ValoreMes::confronta($v, '0') !== 0) {
                    $tuttiZero = false;
                    break;
                }
            }
            if ($tuttiZero) {
                return new EsitoClassificazione(StatoMacchina::SenzaDato, 'valori_tutti_zero');
            }
        }

        $valide = 0;
        foreach ($this->colonne as $c) {
            $v = ValoreMes::decimale($campione->valore($c));
            if ($v === null) {
                continue;
            }
            $valide++;
            if (ValoreMes::confronta($v, $this->soglia) > 0) {
                return new EsitoClassificazione(StatoMacchina::Marcia);
            }
        }

        return $valide > 0
            ? new EsitoClassificazione(StatoMacchina::Fermo)
            : new EsitoClassificazione(StatoMacchina::SenzaDato, 'campo_vuoto');
    }
}
