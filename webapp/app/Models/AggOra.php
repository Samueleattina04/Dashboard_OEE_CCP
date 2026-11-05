<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AggOra extends Model
{
    protected $table = 'agg_ora';

    public $timestamps = false;

    protected $guarded = ['id'];
}
