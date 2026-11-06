<?php

namespace Tests\Feature;

use App\Mes\SorgenteCampioni;
use App\Models\AggGiorno;
use App\Models\CalendarioTurni;
use App\Models\CampioneMes;
use App\Models\CapacitaNominale;
use App\Models\Macchina;
use App\Models\SyncMes;
use App\Models\Turno;
use Database\Seeders\MacchineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MES simulato: restituisce righe come farebbe RepositoryMes, rispettando "dopo" e "limite". */
class SorgenteFinta implements SorgenteCampioni
{
    public int $chiamate = 0;

    public function __construct(public array $righe) {}

    public function campioniDopo(string $macchina, array $colonne, ?string $dopo, ?string $finoA, int $limite): array
    {
        $this->chiamate++;
        $r = array_values(array_filter($this->righe[$macchina] ?? [], fn ($x) => ($dopo === null || $x['FTIMESTAMP'] > $dopo)
            && ($finoA === null || $x['FTIMESTAMP'] < $finoA)));

        return array_slice($r, 0, $limite);
    }
}

class ImportazioneRicalcoloTest extends TestCase
{
    use RefreshDatabase;

    private function rigaFx(string $locale, string $stato, ?string $selS1 = null): array
    {
        return ['FCHANNEL' => 'HIST', 'FTIMESTAMP' => $locale.'.000', 'FROW' => 0, 'FBATCH' => 'RICETTA',
            'FVALUE01' => $stato, 'FVALUE02' => '3,97 bar', 'FNVALUE04' => $selS1, 'FNVALUE06' => '0.00000',
            'FNVALUE03' => '0.00000', 'FNVALUE05' => '0.00000'];
    }

    public function test_importa_ricalcola_e_calcola_disponibilita_e_prestazione(): void
    {
        $this->seed(MacchineSeeder::class);
        $fx = Macchina::where('codice', 'FX10130')->first();

        // Calendario: giovedì 07:00–16:00, pausa 12:00–13:00 → 8 h pianificate
        $cal = CalendarioTurni::create(['nome' => 'Giornaliero']);
        Turno::create(['calendario_id' => $cal->id, 'tipo' => 'turno', 'nome' => 'Mattino', 'giorno_settimana' => 4, 'ora_inizio' => '07:00', 'ora_fine' => '16:00']);
        Turno::create(['calendario_id' => $cal->id, 'tipo' => 'pausa', 'nome' => 'Pranzo', 'giorno_settimana' => 4, 'ora_inizio' => '12:00', 'ora_fine' => '13:00']);
        $fx->update(['calendario_id' => $cal->id]);
        CapacitaNominale::create(['macchina_id' => $fx->id, 'kg_ora' => 600, 'valido_dal' => '2026-01-01']);

        // 05/11/2026 (ora locale): Selecting dalle 08:00 alle 10:00 (8 campioni), poi Ready fino alle 11:00.
        // Kg selezionati S1: +100 kg ogni campione in marcia.
        $righe = [];
        $kg = 1000;
        for ($i = 0; $i < 12; $i++) {
            $ora = sprintf('2026-11-05 %02d:%02d:00', 8 + intdiv($i * 15, 60), ($i * 15) % 60);
            $marcia = $i < 8;
            if ($marcia && $i > 0) {
                $kg += 100;
            }
            $righe[] = $this->rigaFx($ora, $marcia ? 'Selecting' : 'Ready', number_format($kg, 5, '.', ''));
        }
        $finta = new SorgenteFinta(['FX10130' => $righe]);
        $this->app->instance(SorgenteCampioni::class, $finta);

        $this->artisan('mes:importa', ['--macchina' => 'FX10130'])->assertExitCode(0);

        $this->assertSame(12, CampioneMes::count());
        $campione = CampioneMes::orderBy('ts_utc')->first();
        // Tracciabilità: chiave di origine e testo originale conservati; 08:00 Roma = 07:00 UTC
        $this->assertSame('2026-11-05 08:00:00.000', $campione->ftimestamp_originale);
        $this->assertStringStartsWith('2026-11-05 07:00:00', $campione->getRawOriginal('ts_utc'));
        $this->assertSame('3,97 bar', $campione->valori['FVALUE02']);
        $this->assertSame('2026-11-05 10:45:00.000', SyncMes::first()->ultimo_ftimestamp);

        $agg = AggGiorno::where('macchina_id', $fx->id)->whereDate('giorno', '2026-11-05')->first();
        // A mano: marcia 8 × 15 min = 2 h; fermo 4 × 15 min = 1 h; resto del giorno senza dato (21 h)
        $this->assertSame(2 * 3600, $agg->secondi_marcia);
        $this->assertSame(3600, $agg->secondi_fermo);
        $this->assertSame(21 * 3600, $agg->secondi_senza_dato);
        $this->assertSame(12, $agg->campioni_presenti);
        // Pianificato 8 h; senza dato nel turno: 07–08 e 11–12 e 13–16 = 5 h → netto 3 h; marcia 2 h → D = 2/3
        $this->assertSame(8 * 3600, $agg->secondi_pianificati);
        $this->assertSame(5 * 3600, $agg->secondi_esclusi_in_turno);
        $this->assertEqualsWithDelta(2 / 3, (float) $agg->disponibilita, 1e-6);
        // Kg: 7 incrementi da 100 = 700 kg in 2 h di marcia a 600 kg/h → P = 700/1200 = 0,5833
        $this->assertEqualsWithDelta(700.0, (float) $agg->kg_selezionati, 1e-6);
        $this->assertEqualsWithDelta(700 / 1200, (float) $agg->prestazione, 1e-6);
        // Qualità non definita → OEE n.d. con motivo
        $this->assertNull($agg->oee);
        $this->assertSame('definizione_qualita_non_disponibile', $agg->motivi['qualita']);

        // Seconda esecuzione: nessun nuovo dato, nessun duplicato
        $this->artisan('mes:importa', ['--macchina' => 'FX10130'])->assertExitCode(0);
        $this->assertSame(12, CampioneMes::count());
    }

    public function test_forno_senza_calendario_ha_disponibilita_non_disponibile(): void
    {
        $this->seed(MacchineSeeder::class);
        $righe = [];
        $orari = ['07:00', '07:15', '07:30', '07:45', '08:00', '08:15'];
        foreach (['25,0', '80,0', '120,5', '140,0', '90,0', '50,0'] as $i => $t) {
            $righe[] = ['FCHANNEL' => 'HIST', 'FTIMESTAMP' => "2026-11-05 {$orari[$i]}:00.000",
                'FROW' => 0, 'FBATCH' => '0', 'FVALUE02' => $t];
        }
        $this->app->instance(SorgenteCampioni::class, new SorgenteFinta(['FORNOKING500' => $righe]));

        $this->artisan('mes:importa', ['--macchina' => 'FORNOKING500'])->assertExitCode(0);

        $agg = AggGiorno::whereDate('giorno', '2026-11-05')->first();
        // A mano: 25 fermo, 80 riscaldamento, 120,5 marcia, 140 marcia, 90 raffreddamento, 50 fermo (15 min ciascuno)
        $this->assertSame(30 * 60, $agg->secondi_marcia);
        $this->assertSame(15 * 60, $agg->secondi_riscaldamento);
        $this->assertSame(15 * 60, $agg->secondi_raffreddamento);
        $this->assertSame(30 * 60, $agg->secondi_fermo);
        $this->assertSame(1, $agg->avviamenti);
        $this->assertNull($agg->disponibilita);
        $this->assertSame('calendario_non_configurato', $agg->motivi['disponibilita']);
    }

    public function test_riconciliazione_senza_scostamenti(): void
    {
        $this->seed(MacchineSeeder::class);
        $righe = [];
        foreach (['06:00' => '25,0', '06:15' => '80,0', '06:30' => '130,0', '06:45' => '140,0', '07:00' => '60,0'] as $ora => $t) {
            $righe[] = ['FCHANNEL' => 'HIST', 'FTIMESTAMP' => "2026-11-04 {$ora}:00.000", 'FROW' => 0, 'FBATCH' => '0', 'FVALUE02' => $t];
        }
        $this->app->instance(SorgenteCampioni::class, new SorgenteFinta(['FORNOKING500' => $righe]));
        $this->artisan('mes:importa', ['--macchina' => 'FORNOKING500'])->assertExitCode(0);

        $this->artisan('kpi:riconcilia', ['macchina' => 'FORNOKING500', 'giorno' => '2026-11-04', '--dettaglio' => true])
            ->expectsOutputToContain('Campioni MES nel giorno: 5 · nella dashboard: 5')
            ->expectsOutputToContain('Nessuno scostamento.')
            ->assertExitCode(0);
    }
}
