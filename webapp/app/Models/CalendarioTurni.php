<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarioTurni extends Model
{
    protected $table = 'calendari_turni';

    protected $fillable = ['nome', 'note'];

    public function turni(): HasMany
    {
        return $this->hasMany(Turno::class, 'calendario_id');
    }

    public function eccezioni(): HasMany
    {
        return $this->hasMany(EccezioneCalendario::class, 'calendario_id');
    }
}
