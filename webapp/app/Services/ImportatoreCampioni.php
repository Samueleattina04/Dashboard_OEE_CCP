<?php

namespace App\Services;

use App\Domain\ConvertitoreTimestamp;
use App\Mes\SorgenteCampioni;
use App\Models\CampioneMes;
use App\Models\Macchina;
use App\Models\SyncMes;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Importazione incrementale dei campioni HIST da FPROCESSDATA (query Q1, docs/02 §7).
 * Idempotente: la chiave di origine è univoca in campioni_mes.
 */
class ImportatoreCampioni
{
    public function __construct(private readonly SorgenteCampioni $sorgente) {}

    /**
     * @param  string|null  $finoA  limite superiore escluso (ora locale "Y-m-d H:i:s"), per i caricamenti storici a blocchi
     * @param  string|null  $da  se indicato, ignora il punto di ripresa e parte da qui (ora locale)
     * @return array{righe:int, giorni:string[]} giorni locali toccati
     */
    public function importa(Macchina $macchina, ?string $finoA = null, ?string $da = null): array
    {
        $sync = SyncMes::firstOrCreate(['macchina_id' => $macchina->id]);
        $sync->ultima_esecuzione = now();
        $limite = (int) config('dashboard.mes_righe_per_lettura', 3000);
        $fuso = config('dashboard.timezone_locale', 'Europe/Rome');

        $ultimoUtc = CampioneMes::where('macchina_id', $macchina->id)->max('ts_utc');
        $convertitore = new ConvertitoreTimestamp($fuso, ($da === null && $ultimoUtc) ? new DateTimeImmutable($ultimoUtc, new DateTimeZone('UTC')) : null);

        $cursore = $da ?? $sync->ultimo_ftimestamp;
        $totale = 0;
        $minUtc = $maxUtc = null;

        try {
            do {
                $righe = $this->sorgente->campioniDopo($macchina->codice, $macchina->colonne_import, $cursore, $finoA, $limite);
                $inserimenti = [];
                foreach ($righe as $r) {
                    $originale = (string) $r['FTIMESTAMP'];
                    $utc = $convertitore->versoUtc($originale);
                    $valori = [];
                    foreach ($macchina->colonne_import as $col) {
                        $v = $r[$col] ?? null;
                        $valori[$col] = $v === null ? null : (string) $v;
                    }
                    $inserimenti[] = [
                        'macchina_id' => $macchina->id,
                        'fobject' => $macchina->codice,
                        'fchannel' => (string) ($r['FCHANNEL'] ?? 'HIST'),
                        'ftimestamp_originale' => $originale,
                        'frow' => (int) $r['FROW'],
                        'ts_utc' => $utc->format('Y-m-d H:i:s.v'),
                        'fbatch' => isset($r['FBATCH']) ? (string) $r['FBATCH'] : null,
                        'valori' => json_encode($valori, JSON_UNESCAPED_UNICODE),
                    ];
                    $minUtc = $minUtc === null || $utc < $minUtc ? $utc : $minUtc;
                    $maxUtc = $maxUtc === null || $utc > $maxUtc ? $utc : $maxUtc;
                    $cursore = $originale;
                }
                foreach (array_chunk($inserimenti, 500) as $blocco) {
                    DB::table('campioni_mes')->insertOrIgnore($blocco);
                }
                $totale += count($righe);
            } while (count($righe) === $limite);

            if ($cursore !== null && ($sync->ultimo_ftimestamp === null || $cursore > $sync->ultimo_ftimestamp)) {
                $sync->ultimo_ftimestamp = $cursore;
            }
            $sync->esito = 'ok';
            $sync->messaggio = null;
            $sync->ultimo_successo = now();
            $sync->righe_importate = $totale;
            $sync->save();
        } catch (Throwable $e) {
            $sync->esito = 'errore';
            $sync->messaggio = mb_substr($e->getMessage(), 0, 1000);
            $sync->save();
            throw $e;
        }

        return ['righe' => $totale, 'giorni' => $this->giorniToccati($minUtc, $maxUtc, $fuso)];
    }

    /** Giorni locali da ricalcolare: dal giorno del primo campione (incluso il precedente, il cui ultimo intervallo cambia) all'ultimo. */
    private function giorniToccati(?DateTimeImmutable $min, ?DateTimeImmutable $max, string $fuso): array
    {
        if ($min === null) {
            return [];
        }
        $tz = new DateTimeZone($fuso);
        $giorno = $min->setTimezone($tz)->modify('-1 day')->setTime(0, 0);
        $ultimo = $max->setTimezone($tz)->setTime(0, 0);
        $giorni = [];
        while ($giorno <= $ultimo) {
            $giorni[] = $giorno->format('Y-m-d');
            $giorno = $giorno->modify('+1 day');
        }

        return $giorni;
    }
}
