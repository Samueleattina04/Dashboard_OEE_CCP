<?php

namespace App\Console\Commands;

use App\Models\Macchina;
use App\Services\RicalcoloKpi as Servizio;
use DateTimeImmutable;
use Illuminate\Console\Command;

class RicalcolaKpi extends Command
{
    protected $signature = 'kpi:ricalcola {--macchina=} {--da= : Y-m-d (default: oggi meno N giorni)} {--a= : Y-m-d incluso (default oggi)}';

    protected $description = 'Ricalcola intervalli e aggregati dal DB applicativo (non interroga il MES)';

    public function handle(Servizio $servizio): int
    {
        $giorniDefault = (int) config('dashboard.giorni_ricalcolo_notturno', 7);
        $da = new DateTimeImmutable($this->option('da') ?: "-{$giorniDefault} days");
        $a = new DateTimeImmutable($this->option('a') ?: 'today');
        $giorni = [];
        for ($g = $da->setTime(0, 0); $g <= $a; $g = $g->modify('+1 day')) {
            $giorni[] = $g->format('Y-m-d');
        }
        $macchine = Macchina::where('attiva', true)
            ->when($this->option('macchina'), fn ($q, $c) => $q->where('codice', $c))->get();
        foreach ($macchine as $m) {
            $servizio->ricalcola($m, $giorni);
            $this->info("{$m->codice}: ".count($giorni).' giorni ricalcolati');
        }

        return self::SUCCESS;
    }
}
