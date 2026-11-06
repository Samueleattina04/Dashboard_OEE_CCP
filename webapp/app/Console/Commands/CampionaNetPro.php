<?php

namespace App\Console\Commands;

use App\Services\CampionatoreNetPro;
use Illuminate\Console\Command;
use Throwable;

class CampionaNetPro extends Command
{
    protected $signature = 'netpro:campiona';

    protected $description = 'Legge (sola lettura) stato di tutte le macchine e segnali in tempo reale di NetPro per la pagina NetPro live';

    public function handle(CampionatoreNetPro $campionatore): int
    {
        try {
            $r = $campionatore->campiona();
        } catch (Throwable $e) {
            $this->error('NetPro live: lettura non riuscita - '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("NetPro live: {$r['macchine']} macchine, {$r['segnali']} segnali ({$r['eliminate']} letture vecchie eliminate)");

        return self::SUCCESS;
    }
}
