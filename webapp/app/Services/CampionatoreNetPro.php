<?php

namespace App\Services;

use App\Mes\RepositoryMes;
use App\Mes\SorgenteNetProLive;
use Illuminate\Support\Facades\DB;

/**
 * Legge (sola lettura) lo stato corrente di tutte le macchine e di tutti i segnali NetPro e lo salva nel DB della
 * dashboard, eliminando le letture più vecchie della finestra configurata.
 */
class CampionatoreNetPro
{
    public function __construct(private readonly SorgenteNetProLive $sorgente) {}

    /** @return array{macchine:int, segnali:int, eliminate:int} */
    public function campiona(): array
    {
        $adesso = now()->utc()->format('Y-m-d H:i:s.v');
        $macchine = $this->sorgente->statoMacchine();
        $segnali = $this->sorgente->segnaliCorrenti();
        $righe = [];

        foreach ($macchine as $m) {
            $stato = [];
            foreach (RepositoryMes::COLONNE_STATO_MACCHINE as $c) {
                $stato[$c] = isset($m[$c]) ? trim((string) $m[$c]) : null;
            }
            DB::table('netpro_macchine')->updateOrInsert(['codice' => (string) $m['MACCHINA']], [
                'descrizione' => $m['DESCRIZIONE'] ?? null, 'centro' => $m['CENTRO'] ?? null,
                'stato' => json_encode($stato, JSON_UNESCAPED_UNICODE), 'letto_il' => $adesso,
            ]);
            foreach (['STATO_FISICO' => 'ORA_ST_FISICO', 'STATO_LOGICO' => 'ORA_ST_LOGICO', 'FCODE01' => null,
                'VALORE_CONTATORE' => 'ORA_CONTATORE', 'TOTALE_CONTATORE' => 'ORA_CONTATORE'] as $c => $ora) {
                $righe[] = ['letto_il' => $adesso, 'oggetto' => (string) $m['MACCHINA'], 'tipo' => 'stato', 'chiave' => $c,
                    'descrizione' => null, 'valore' => $stato[$c], 'ts_mes' => $ora ? $stato[$ora] : null];
            }
        }
        foreach ($segnali as $s) {
            $righe[] = ['letto_il' => $adesso, 'oggetto' => (string) $s['FOBJECT'], 'tipo' => 'segnale',
                'chiave' => (string) $s['FIOLINE'], 'descrizione' => $s['FDESCR'] ?? null,
                'valore' => isset($s['FVALUE']) ? (string) $s['FVALUE'] : null,
                'ts_mes' => isset($s['FREADDATE']) ? (string) $s['FREADDATE'] : null];
        }
        foreach (array_chunk($righe, 500) as $blocco) {
            DB::table('netpro_letture')->insert($blocco);
        }

        $limite = now()->utc()->subMinutes((int) config('dashboard.netpro_live_minuti', 10))->format('Y-m-d H:i:s.v');
        $eliminate = DB::table('netpro_letture')->where('letto_il', '<', $limite)->delete();

        return ['macchine' => count($macchine), 'segnali' => count($segnali), 'eliminate' => $eliminate];
    }
}
