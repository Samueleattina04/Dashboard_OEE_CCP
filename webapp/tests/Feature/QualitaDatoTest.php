<?php

namespace Tests\Feature;

use App\Models\AggGiorno;
use App\Models\Macchina;
use App\Models\User;
use Database\Seeders\MacchineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualitaDatoTest extends TestCase
{
    use RefreshDatabase;

    /** 3 giorni pieni, 2 giorni senza dato, 1 giorno coperto al 40 %, 1 pieno → interruzione di 3 giorni. */
    public function test_interruzioni_di_copertura(): void
    {
        $this->seed(MacchineSeeder::class);
        $fx = Macchina::where('codice', 'FX10130')->first();
        $giorni = ['2026-10-01' => 0, '2026-10-02' => 0, '2026-10-03' => 0, '2026-10-04' => 86400, '2026-10-05' => 86400,
            '2026-10-06' => (int) (86400 * 0.6), '2026-10-07' => 0];
        foreach ($giorni as $g => $senzaDato) {
            AggGiorno::create(['macchina_id' => $fx->id, 'giorno' => $g, 'secondi_marcia' => 86400 - $senzaDato,
                'secondi_senza_dato' => $senzaDato, 'campioni_presenti' => $senzaDato === 86400 ? 0 : 96]);
        }

        // Primo campione importato il 01/10: i giorni precedenti sono "non importati", non interruzioni
        \DB::table('campioni_mes')->insert(['macchina_id' => $fx->id, 'fobject' => 'FX10130', 'fchannel' => 'HIST',
            'ftimestamp_originale' => '2026-10-01 08:00:00.000', 'frow' => 0, 'ts_utc' => '2026-10-01 06:00:00.000',
            'valori' => '{}', 'importato_il' => now()]);

        $html = $this->actingAs(User::factory()->create(['ruolo' => 'qualita']))
            ->get('/qualita-dato?periodo=personalizzato&da=2026-09-29&a=2026-10-07&macchina=FX10130')
            ->assertOk()->getContent();

        // Tabella delle interruzioni: una sola riga, dal 04/10 al 06/10, 3 giorni (29–30/09 esclusi: non importati)
        $tabella = substr($html, strpos($html, '<h3>Interruzioni'));
        $this->assertSame(1, substr_count($tabella, '<tr><td>'));
        $this->assertMatchesRegularExpression('#<tr><td>04/10/2026</td><td>06/10/2026</td><td class="num">3</td>#', $tabella);
        $this->assertStringContainsString('29/09/2026: periodo non importato', $html);
    }
}
