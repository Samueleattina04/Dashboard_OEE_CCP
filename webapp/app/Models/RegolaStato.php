<?php

namespace App\Models;

use App\Domain\Classificatori\ClassificatoreStato;
use App\Domain\Classificatori\FabbricaClassificatori;
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

    public function gMaxSecondi(): int
    {
        return 60 * (int) ($this->parametri['g_max_minuti'] ?? config('dashboard.g_max_minuti_default', 20));
    }
}
