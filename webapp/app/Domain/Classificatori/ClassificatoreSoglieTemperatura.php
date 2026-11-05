<?php

namespace App\Domain\Classificatori;

use App\Domain\Campione;
use App\Domain\EsitoClassificazione;
use App\Domain\StatoMacchina;
use App\Domain\ValoreMes;

/**
 * Forno: stato dedotto dalla temperatura di tostatura (docs/02 §2.2, decisione 05/11/2026).
 *   T >= soglia_marcia                      -> MARCIA
 *   soglia_transitorio <= T < soglia_marcia -> RISCALDAMENTO se in salita, RAFFREDDAMENTO se in discesa
 *   T < soglia_transitorio                  -> FERMO
 *   vuoto / non numerico                    -> SENZA_DATO
 * Con temperatura invariata (o primo campione) nel transitorio: si mantiene il transitorio precedente;
 * altrimenti RISCALDAMENTO se il campione precedente era sotto la fascia, RAFFREDDAMENTO se sopra.
 */
final class ClassificatoreSoglieTemperatura implements ClassificatoreStato
{
    public function __construct(
        private readonly string $colonna,
        private readonly string $sogliaMarcia,
        private readonly string $sogliaTransitorio,
    ) {}

    public static function daParametri(array $p): self
    {
        return new self($p['colonna'], (string) $p['soglia_marcia'], (string) $p['soglia_transitorio']);
    }

    public function classifica(Campione $campione, ?Campione $precedente, ?StatoMacchina $statoPrecedente): EsitoClassificazione
    {
        $testo = $campione->valore($this->colonna);
        if (ValoreMes::vuoto($testo)) {
            return new EsitoClassificazione(StatoMacchina::SenzaDato, 'campo_vuoto');
        }
        $t = ValoreMes::decimale($testo);
        if ($t === null) {
            return new EsitoClassificazione(StatoMacchina::SenzaDato, 'valore_non_numerico');
        }

        if (ValoreMes::confronta($t, $this->sogliaMarcia) >= 0) {
            return new EsitoClassificazione(StatoMacchina::Marcia);
        }
        if (ValoreMes::confronta($t, $this->sogliaTransitorio) < 0) {
            return new EsitoClassificazione(StatoMacchina::Fermo);
        }

        // Fascia transitoria: si guarda l'andamento rispetto al campione precedente
        $tPrec = $precedente ? ValoreMes::decimale($precedente->valore($this->colonna)) : null;
        if ($tPrec !== null) {
            $cmp = ValoreMes::confronta($t, $tPrec);
            if ($cmp > 0) {
                return new EsitoClassificazione(StatoMacchina::Riscaldamento);
            }
            if ($cmp < 0) {
                return new EsitoClassificazione(StatoMacchina::Raffreddamento);
            }
        }
        if ($statoPrecedente === StatoMacchina::Riscaldamento || $statoPrecedente === StatoMacchina::Raffreddamento) {
            return new EsitoClassificazione($statoPrecedente);
        }
        if ($statoPrecedente === StatoMacchina::Marcia) {
            return new EsitoClassificazione(StatoMacchina::Raffreddamento);
        }

        return new EsitoClassificazione(StatoMacchina::Riscaldamento);
    }
}
