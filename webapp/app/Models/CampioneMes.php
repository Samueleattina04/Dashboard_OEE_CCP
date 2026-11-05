<?php

namespace App\Models;

use App\Domain\Campione;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;

class CampioneMes extends Model
{
    protected $table = 'campioni_mes';

    public $timestamps = false;

    protected $fillable = ['macchina_id', 'fobject', 'fchannel', 'ftimestamp_originale', 'frow', 'ts_utc', 'fbatch', 'valori'];

    protected $casts = ['valori' => 'array'];

    public function versoDominio(): Campione
    {
        return new Campione(
            new DateTimeImmutable($this->getRawOriginal('ts_utc'), new DateTimeZone('UTC')),
            $this->valori ?? [],
            $this->id,
            $this->ftimestamp_originale,
        );
    }
}
