<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntervalloStato extends Model
{
    protected $table = 'intervalli_stato';

    public $timestamps = false;

    protected $fillable = ['macchina_id', 'inizio_utc', 'fine_utc', 'stato', 'motivo', 'campione_id', 'regola_id'];
}
