<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $table = 'turni';

    protected $fillable = ['calendario_id', 'tipo', 'nome', 'giorno_settimana', 'ora_inizio', 'ora_fine'];
}
