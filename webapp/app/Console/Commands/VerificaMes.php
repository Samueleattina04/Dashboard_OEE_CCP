<?php

namespace App\Console\Commands;

use App\Mes\RepositoryMes;
use App\Models\Macchina;
use Illuminate\Console\Command;
use Throwable;

class VerificaMes extends Command
{
    protected $signature = 'mes:verifica';

    protected $description = 'Verifica la connessione al MES, i permessi (sola lettura) e l\'ultimo campione per macchina';

    public function handle(RepositoryMes $mes): int
    {
        try {
            $r = $mes->verifica(Macchina::where('attiva', true)->pluck('codice')->all());
        } catch (Throwable $e) {
            $this->error('Connessione al MES non riuscita: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('Connessione OK. Permessi/ruoli dell\'utente: '.implode(', ', $r['permessi']));
        $scrittura = array_intersect($r['permessi'], ['INSERT', 'UPDATE', 'DELETE', 'ALTER', 'EXECUTE', 'CONTROL', 'db_datawriter', 'db_owner']);
        if ($scrittura) {
            $this->warn('ATTENZIONE: l\'utente ha permessi di scrittura ('.implode(', ', $scrittura).'). Usare un utente in sola lettura.');
        }
        foreach ($r['ultimi'] as $codice => $ts) {
            $this->line("  {$codice}: ultimo campione HIST ".($ts ?? 'nessuno'));
        }

        return self::SUCCESS;
    }
}
