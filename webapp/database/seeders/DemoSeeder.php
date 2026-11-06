<?php

namespace Database\Seeders;

use App\Models\Macchina;
use App\Services\RicalcoloKpi;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DATI SIMULATI per provare l'interfaccia senza MES. Rifiuta di girare fuori dall'ambiente "local".
 * Uso: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(RicalcoloKpi $ricalcolo): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoSeeder: solo in APP_ENV=local.');
        }
        $tz = new DateTimeZone('Europe/Rome');
        $utc = new DateTimeZone('UTC');
        $oggi = new DateTimeImmutable('today', $tz);
        mt_srand(42);

        foreach (['FORNOKING500', 'FX10130'] as $codice) {
            $m = Macchina::where('codice', $codice)->firstOrFail();
            DB::table('campioni_mes')->where('macchina_id', $m->id)->delete();
            $righe = [];
            $kgSel = 1000.0;
            $kgScart = 50.0;
            $temp = 20.0;
            for ($g = $oggi->modify('-30 days'); $g <= $oggi; $g = $g->modify('+1 day')) {
                $lavorativo = (int) $g->format('N') <= 5;
                $inizio = 6 + mt_rand(0, 2);
                $fine = 14 + mt_rand(0, 4);
                for ($q = 0; $q < 96; $q++) {
                    $ts = $g->modify('+'.($q * 15).' minutes +'.mt_rand(40, 50).' seconds');
                    if ($ts > new DateTimeImmutable('now', $tz)) {
                        break;
                    }
                    $ora = $q / 4;
                    $attiva = $lavorativo && $ora >= $inizio && $ora < $fine;
                    if ($codice === 'FORNOKING500') {
                        $obiettivo = $attiva ? 140 : 20;
                        $temp += ($obiettivo - $temp) * 0.35 + mt_rand(-10, 10) / 10;
                        $valori = ['FVALUE01' => number_format($temp * 0.8, 1, ',', ''), 'FVALUE02' => number_format($temp, 1, ',', ''),
                            'FVALUE03' => '21,8', 'FVALUE04' => '17,2', 'FVALUE05' => number_format($temp * 0.95, 1, ',', ''),
                            'FVALUE06' => number_format($temp * 0.7, 1, ',', ''), 'FVALUE07' => '0'];
                    } else {
                        $stato = $attiva ? (mt_rand(0, 30) === 0 ? 'SelectionError' : (mt_rand(0, 10) === 0 ? 'Cleaning' : 'Selecting')) : ($lavorativo ? 'Ready' : 'End3');
                        if ($stato === 'Selecting') {
                            $kgSel += mt_rand(100, 140);
                            $kgScart += mt_rand(3, 9);
                        }
                        if ($q === 0 && mt_rand(0, 6) === 0) {
                            $kgSel = 0;
                            $kgScart = 0;
                        }
                        $valori = ['FVALUE01' => $stato, 'FVALUE02' => '3,97 bar', 'FVALUE03' => 'FULL', 'FVALUE04' => 'FULL',
                            'FNVALUE03' => number_format($kgScart / 2, 5, '.', ''), 'FNVALUE04' => number_format($kgSel / 2, 5, '.', ''),
                            'FNVALUE05' => number_format($kgScart / 2, 5, '.', ''), 'FNVALUE06' => number_format($kgSel / 2, 5, '.', '')];
                    }
                    $righe[] = ['macchina_id' => $m->id, 'fobject' => $codice, 'fchannel' => 'HIST',
                        'ftimestamp_originale' => $ts->format('Y-m-d H:i:s.000'), 'frow' => 0,
                        'ts_utc' => $ts->setTimezone($utc)->format('Y-m-d H:i:s.000'), 'fbatch' => 'DEMO',
                        'valori' => json_encode($valori), 'importato_il' => now()];
                }
            }
            foreach (array_chunk($righe, 500) as $b) {
                DB::table('campioni_mes')->insert($b);
            }
            DB::table('sync_mes')->updateOrInsert(['macchina_id' => $m->id],
                ['esito' => 'ok', 'ultimo_successo' => now(), 'ultima_esecuzione' => now(), 'ultimo_ftimestamp' => end($righe)['ftimestamp_originale']]);
            $giorni = [];
            for ($g = $oggi->modify('-30 days'); $g <= $oggi; $g = $g->modify('+1 day')) {
                $giorni[] = $g->format('Y-m-d');
            }
            $ricalcolo->ricalcola($m, $giorni);
        }
    }
}
