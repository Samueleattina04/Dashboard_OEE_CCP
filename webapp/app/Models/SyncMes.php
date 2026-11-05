<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncMes extends Model
{
    protected $table = 'sync_mes';

    public $timestamps = false;

    protected $fillable = ['macchina_id', 'ultimo_ftimestamp', 'ultima_esecuzione', 'ultimo_successo', 'esito', 'messaggio', 'righe_importate'];

    protected $casts = ['ultima_esecuzione' => 'datetime', 'ultimo_successo' => 'datetime'];
}
