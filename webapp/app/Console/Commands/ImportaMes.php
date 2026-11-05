<?php

namespace App\Console\Commands;

use App\Models\Macchina;
use App\Services\ImportatoreCampioni;
use App\Services\RicalcoloKpi;
use Illuminate\Console\Command;
use Throwable;

class ImportaMes extends Command
{
    protected $signature = 'mes:importa {--macchina= : codice FOBJECT (default: tutte le macchine attive)}';

    protected $description = 'Importa i nuovi campioni HIST da FPROCESSDATA (sola lettura) e ricalcola i giorni toccati';

    public function handle(ImportatoreCampioni $importatore, RicalcoloKpi $ricalcolo): int
    {
        $esito = self::SUCCESS;
        foreach ($this->macchine() as $macchina) {
            try {
                $r = $importatore->importa($macchina);
                $ricalcolo->ricalcola($macchina, $r['giorni']);
                $this->info("{$macchina->codice}: {$r['righe']} campioni, ".count($r['giorni']).' giorni ricalcolati');
            } catch (Throwable $e) {
                $this->error("{$macchina->codice}: errore - {$e->getMessage()}");
                $esito = self::FAILURE;
            }
        }

        return $esito;
    }

    private function macchine()
    {
        return Macchina::where('attiva', true)
            ->when($this->option('macchina'), fn ($q, $c) => $q->where('codice', $c))
            ->get();
    }
}
