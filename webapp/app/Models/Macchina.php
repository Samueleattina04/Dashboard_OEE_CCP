<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Macchina extends Model
{
    protected $table = 'macchine';

    protected $fillable = ['codice', 'descrizione', 'attiva', 'dati_non_affidabili', 'motivo_non_affidabile', 'colonne_import', 'calendario_id'];

    protected $casts = ['attiva' => 'boolean', 'dati_non_affidabili' => 'boolean', 'colonne_import' => 'array'];

    public function regole(): HasMany
    {
        return $this->hasMany(RegolaStato::class);
    }

    public function capacita(): HasMany
    {
        return $this->hasMany(CapacitaNominale::class);
    }

    public function calendario(): BelongsTo
    {
        return $this->belongsTo(CalendarioTurni::class, 'calendario_id');
    }

    public function sync(): HasOne
    {
        return $this->hasOne(SyncMes::class);
    }

    /** Regola di stato valida in un istante (UTC). */
    public function regolaValidaIl(\DateTimeInterface $istante): ?RegolaStato
    {
        return $this->regole()
            ->where('valido_dal', '<=', $istante)
            ->where(fn ($q) => $q->whereNull('valido_al')->orWhere('valido_al', '>', $istante))
            ->orderByDesc('valido_dal')
            ->first();
    }

    public function capacitaValidaIl(string $giorno): ?CapacitaNominale
    {
        return $this->capacita()
            ->where('valido_dal', '<=', $giorno)
            ->where(fn ($q) => $q->whereNull('valido_al')->orWhere('valido_al', '>=', $giorno))
            ->orderByDesc('valido_dal')
            ->first();
    }
}
