<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AggGiorno extends Model
{
    protected $table = 'agg_giorno';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['motivi' => 'array', 'giorno' => 'date:Y-m-d'];
}
