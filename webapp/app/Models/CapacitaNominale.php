<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapacitaNominale extends Model
{
    protected $table = 'capacita_nominali';

    protected $fillable = ['macchina_id', 'kg_ora', 'valido_dal', 'valido_al'];
}
