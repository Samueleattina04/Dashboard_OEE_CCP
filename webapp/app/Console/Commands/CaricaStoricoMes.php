<?php

namespace App\Console\Commands;

use App\Models\Macchina;
use App\Services\ImportatoreCampioni;
use App\Services\RicalcoloKpi;
use DateTimeImmutable;
use Illuminate\Console\Command;

/**
 * Caricamento storico iniziale (Q2), a blocchi mensili. Da eseguire di notte.
 */
class CaricaStoricoMes extends Command
{
    protected $signature = 'mes:storico {macchina : codice FOBJECT} {--da= : data iniziale Y-m-d} {--a= : data finale esclusa Y-m-d (default oggi)}';

    protected $description = 'Carica lo storico HIST di una macchina a blocchi mensili e ricalcola gli aggregati';

    public function handle(ImportatoreCampioni $importatore, RicalcoloKpi $ricalcolo): int
    {
        $macchina = Macchina::where('codice', $this->argument('macchina'))->firstOrFail();
        $da = new DateTimeImmutable($this->option('da') ?: '-12 months');
        $a = new DateTimeImmutable($this->option('a') ?: 'today');

        for ($mese = $da->modify('first day of this month'); $mese < $a; $mese = $mese->modify('+1 month')) {
            $inizio = max($mese, $da)->format('Y-m-d 00:00:00');
            $fineBlocco = min($mese->modify('+1 month'), $a)->format('Y-m-d 00:00:00');
            // "da" escluso: si parte da un istante prima dell'inizio del blocco
            $r = $importatore->importa($macchina, $fineBlocco, (new DateTimeImmutable($inizio))->modify('-1 second')->format('Y-m-d H:i:s.v'));
            $ricalcolo->ricalcola($macchina, $r['giorni']);
            $this->info("{$macchina->codice} {$mese->format('Y-m')}: {$r['righe']} campioni");
        }

        return self::SUCCESS;
    }
}
