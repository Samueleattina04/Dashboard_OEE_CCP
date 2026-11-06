<?php

namespace Tests\Feature;

use App\Mes\SorgenteCampioni;
use App\Models\AggGiorno;
use App\Models\CampioneMes;
use App\Models\Macchina;
use App\Models\SyncMes;
use App\Models\User;
use Database\Seeders\MacchineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportEConfrontoTest extends TestCase
{
    use RefreshDatabase;

    private function utente(): User
    {
        return User::factory()->create(['ruolo' => 'qualita']);
    }

    private function aggregato(string $codice, string $giorno, int $marcia, int $fermo): void
    {
        AggGiorno::create([
            'macchina_id' => Macchina::where('codice', $codice)->value('id'), 'giorno' => $giorno,
            'secondi_marcia' => $marcia, 'secondi_fermo' => $fermo, 'campioni_presenti' => 96,
            'secondi_pianificati' => 8 * 3600, 'secondi_marcia_in_turno' => min($marcia, 8 * 3600), 'secondi_esclusi_in_turno' => 0,
            'disponibilita' => min($marcia, 8 * 3600) / (8 * 3600),
            'motivi' => ['qualita' => 'definizione_qualita_non_disponibile'],
        ]);
    }

    /** CSV: BOM UTF-8, separatore ";", decimali con virgola, date gg/mm/aaaa, riga di intestazione con utente e periodo. */
    public function test_formati_italiani_del_csv(): void
    {
        $this->seed(MacchineSeeder::class);
        $this->aggregato('FORNOKING500', '2026-10-15', 6 * 3600 + 1800, 17 * 3600 + 1800); // 6,5 h e 17,5 h
        $u = $this->utente();

        $csv = $this->actingAs($u)->get('/export/giornaliero.csv?periodo=personalizzato&da=2026-10-15&a=2026-10-15&macchina=FORNOKING500')
            ->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $righe = explode("\n", trim(substr($csv, 3)));
        $this->assertStringContainsString($u->email, $righe[0]);
        $this->assertStringContainsString('dal 15/10/2026 al 15/10/2026', $righe[0]);
        $this->assertStringStartsWith('Macchina;Giorno;', $righe[1]);
        $dati = str_getcsv($righe[2], ';');
        $this->assertSame('FORNOKING500', $dati[0]);
        $this->assertSame('15/10/2026', $dati[1]);
        $this->assertSame('6,50', $dati[2]);
        $this->assertSame('17,50', $dati[3]);
        $this->assertSame('81,25', $dati[16]); // Disponibilità 6,5/8 = 81,25 %
    }

    public function test_excel_valido(): void
    {
        $this->seed(MacchineSeeder::class);
        $this->aggregato('FX10130', '2026-10-15', 4 * 3600, 20 * 3600);
        $risposta = $this->actingAs($this->utente())->get('/export/giornaliero.xlsx?periodo=personalizzato&da=2026-10-15&a=2026-10-15');
        $risposta->assertOk();
        $this->assertStringStartsWith('PK', $risposta->streamedContent()); // archivio xlsx
    }

    /** Confronto: periodo B di default = periodo precedente di pari durata; differenze corrette. */
    public function test_storico_e_confronti(): void
    {
        $this->seed(MacchineSeeder::class);
        $this->aggregato('FORNOKING500', '2026-10-10', 8 * 3600, 16 * 3600); // periodo A
        $this->aggregato('FORNOKING500', '2026-10-09', 6 * 3600, 18 * 3600); // periodo B
        $u = $this->utente();

        $this->actingAs($u)->get('/storico?periodo=personalizzato&da=2026-10-10&a=2026-10-10&macchina=FORNOKING500')
            ->assertOk()->assertSee('09/10/2026')->assertSee('+2,0 h');

        $csv = $this->actingAs($u)->get('/export/confronto.csv?periodo=personalizzato&da=2026-10-10&a=2026-10-10&macchina=FORNOKING500')
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('FORNOKING500;"Ore in funzione";8,00;6,00;2,00', $csv);
        $this->assertSame(1, \DB::table('log_export')->where('vista', 'confronto')->count());
    }

    /**
     * Domenica 25/10/2026 (fine ora legale): il giorno locale dura 25 ore.
     * Campioni ogni 15 minuti su tutto il giorno (100), forno sempre a 140 °C:
     * a mano → 25 h in funzione, 0 senza dato, 100 campioni.
     */
    public function test_giornata_del_cambio_ora_completa(): void
    {
        $this->seed(MacchineSeeder::class);
        $righe = [];
        $visti = [];
        $t = new \DateTimeImmutable('2026-10-24 22:00:00', new \DateTimeZone('UTC')); // 00:00 locale (CEST)
        for ($i = 0; $i < 100; $i++) {
            $locale = $t->setTimezone(new \DateTimeZone('Europe/Rome'))->format('Y-m-d H:i:s');
            // Seconda occorrenza dell'ora ripetuta: stesso FTIMESTAMP, FROW diverso (come impone la PK del MES)
            $frow = isset($visti[$locale]) ? 1 : 0;
            $visti[$locale] = true;
            $righe[] = ['FCHANNEL' => 'HIST', 'FTIMESTAMP' => $locale.'.000', 'FROW' => $frow, 'FBATCH' => '0', 'FVALUE02' => '140,0'];
            $t = $t->modify('+15 minutes');
        }
        // Il primo campione del giorno dopo chiude l'ultimo intervallo
        $righe[] = ['FCHANNEL' => 'HIST', 'FTIMESTAMP' => '2026-10-26 00:00:00.000', 'FROW' => 0, 'FBATCH' => '0', 'FVALUE02' => '140,0'];
        $this->app->instance(SorgenteCampioni::class, new SorgenteFinta(['FORNOKING500' => $righe]));

        $this->artisan('mes:importa', ['--macchina' => 'FORNOKING500'])->assertExitCode(0);

        $agg = AggGiorno::whereDate('giorno', '2026-10-25')->first();
        $this->assertSame(25 * 3600, $agg->secondi_marcia);
        $this->assertSame(0, $agg->secondi_senza_dato);
        $this->assertSame(100, $agg->campioni_presenti);
    }

    /**
     * Importazione incrementale a cavallo del cambio d'ora: la seconda 02:15 arriva DOPO che il punto di
     * ripresa è già a 02:45 (prima occorrenza). Deve essere importata comunque.
     */
    public function test_importazione_incrementale_ora_ripetuta(): void
    {
        $this->seed(MacchineSeeder::class);
        $riga = fn ($ts, $frow) => ['FCHANNEL' => 'HIST', 'FTIMESTAMP' => $ts, 'FROW' => $frow, 'FBATCH' => '0', 'FVALUE02' => '140,0'];
        $finta = new SorgenteFinta(['FORNOKING500' => [
            $riga('2026-10-25 02:15:00.000', 0), $riga('2026-10-25 02:45:00.000', 0),
        ]]);
        $this->app->instance(SorgenteCampioni::class, $finta);
        $this->artisan('mes:importa', ['--macchina' => 'FORNOKING500'])->assertExitCode(0);

        // FDI scrive la seconda occorrenza delle 02:15 (stesso FTIMESTAMP, FROW 1)
        $finta->righe['FORNOKING500'][] = $riga('2026-10-25 02:15:00.000', 1);
        usort($finta->righe['FORNOKING500'], fn ($a, $b) => [$a['FTIMESTAMP'], $a['FROW']] <=> [$b['FTIMESTAMP'], $b['FROW']]);
        $this->artisan('mes:importa', ['--macchina' => 'FORNOKING500'])->assertExitCode(0);

        $this->assertSame(3, CampioneMes::count());
        $this->assertSame('2026-10-25 02:45:00.000', SyncMes::first()->ultimo_ftimestamp);
    }
}
