<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditModifica extends Model
{
    protected $table = 'audit_modifiche';

    public $timestamps = false;

    protected $fillable = ['user_id', 'tabella', 'record_id', 'azione', 'valore_precedente', 'valore_nuovo'];

    protected $casts = ['valore_precedente' => 'array', 'valore_nuovo' => 'array'];
}
