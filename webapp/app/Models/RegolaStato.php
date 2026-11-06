<?php

namespace App\Models;

use App\Domain\Classificatori\ClassificatoreStato;
use App\Domain\Classificatori\FabbricaClassificatori;
use App\Domain\StatoMacchina;
use Illuminate\Database\Eloquent\Model;

class RegolaStato extends Model
{
    protected $table = 'regole_stato';

    protected $fillable = ['macchina_id', 'tipo', 'parametri', 'valido_dal', 'valido_al', 'note', 'creato_da'];

    protected $casts = ['parametri' => 'array', 'valido_dal' => 'datetime', 'valido_al' => 'datetime'];

    public function classificatore(): ClassificatoreStato
    {
        return FabbricaClassificatori::crea($this->tipo, $this->parametri);
    }

    /**
     * Stati che questa regola può produrre (più SENZA_DATO, sempre possibile), nell'ordine di visualizzazione.
     * Serve a mostrare per ogni macchina solo le colonne che la riguardano.
     *
     * @return StatoMacchina[]
     */
    public function statiPossibili(): array
    {
        $possibili = match ($this->tipo) {
            'soglie_temperatura' => [StatoMacchina::Marcia, StatoMacchina::Riscaldamento, StatoMacchina::Raffreddamento, StatoMacchina::Fermo],
            'soglia_colonne' => [StatoMacchina::Marcia, StatoMacchina::Fermo],
            'mappa_valori' => array_map(fn ($v) => StatoMacchina::from($v), array_values($this->parametri['mappa'] ?? [])),
            default => StatoMacchina::cases(),
        };
        $possibili[] = StatoMacchina::SenzaDato;

        return array_values(array_filter(StatoMacchina::ordineVisualizzazione(), fn ($s) => in_array($s, $possibili, true)));
    }

    /** La macchina registra quantità (contatori kg)? */
    public function haContatori(): bool
    {
        return ! empty($this->parametri['contatori']);
    }

    public function gMaxSecondi(): int
    {
        return 60 * (int) ($this->parametri['g_max_minuti'] ?? config('dashboard.g_max_minuti_default', 20));
    }
}
