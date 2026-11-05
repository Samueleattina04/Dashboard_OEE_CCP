<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EccezioneCalendario extends Model
{
    protected $table = 'eccezioni_calendario';

    protected $fillable = ['calendario_id', 'macchina_id', 'data', 'tipo', 'ora_inizio', 'ora_fine', 'note'];
}
