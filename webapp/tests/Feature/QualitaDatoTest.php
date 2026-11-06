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

        $this->actingAs(User::factory()->create(['ruolo' => 'qualita']))
            ->get('/qualita-dato?periodo=personalizzato&da=2026-10-01&a=2026-10-07&macchina=FX10130')
            ->assertOk()
            ->assertSee('04/10/2026')->assertSee('06/10/2026')   // interruzione dal 04 al 06
            ->assertSeeInOrder(['04/10/2026', '06/10/2026', '3']);
    }
}
