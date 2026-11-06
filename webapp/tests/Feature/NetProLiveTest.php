<?php

namespace Tests\Feature;

use App\Mes\GuardiaSolaLettura;
use App\Mes\RepositoryMes;
use App\Mes\SorgenteNetProLive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NetProFinto implements SorgenteNetProLive
{
    public float $contenitori = 11000;

    public function segnaliCorrenti(): array
    {
        $ora = now()->setTimezone('Europe/Rome');

        return [
            ['FIOLINE' => 'HEUFT.CTOT', 'FOBJTYPE' => 'MAC', 'FOBJECT' => 'HEUFT', 'FDESCR' => 'Contenitori processati totali',
                'FTYPE' => 'CNT', 'FSTATUS' => 'ON', 'FPOSITION' => 'CTOT', 'FVALUE' => number_format($this->contenitori, 5, '.', ''),
                'FREADDATE' => $ora->format('Y-m-d H:i:s.v')],
            ['FIOLINE' => 'SEYDELMANN.TEMP', 'FOBJTYPE' => 'MAC', 'FOBJECT' => 'SEYDELMANN', 'FDESCR' => 'Temperature',
                'FTYPE' => 'CNT', 'FSTATUS' => 'ON', 'FPOSITION' => 'TEMP', 'FVALUE' => '23.97136', 'FREADDATE' => '2024-05-20 16:18:10.000'],
        ];
    }

    public function statoMacchine(): array
    {
        $base = ['STATO_LINEE' => null, 'TIPO_RILEVAZIONE' => 'C,R,S,1', 'FCODE02' => null, 'CONTATORE_SCARTI' => null,
            'TOTALE_CONTATORE_SCARTI' => null, 'FTOTQTY' => null, 'FCOUNTSTATUS' => 'ON', 'PRODUZIONE' => '0',
            'ORA_LETTURA' => '2026-11-06 13:02:00.000', 'ORA_CONTATORE' => '2023-03-21 18:57:07.000', 'ORA_ST_LOGICO' => '2023-03-21 19:03:06.000'];

        return [
            ['MACCHINA' => 'HEUFT', 'DESCRIZIONE' => 'Confezionatrice Heuft', 'CENTRO' => 'INVASETIC_01', 'STATO_FISICO' => 'ON',
                'ORA_ST_FISICO' => '2026-06-19 11:06:20.000', 'STATO_LOGICO' => 'IDLE', 'TASK' => '00005110', 'FCODE01' => null,
                'VALORE_CONTATORE' => '11172', 'TOTALE_CONTATORE' => '0'] + $base,
            ['MACCHINA' => 'SEYDELMANN', 'DESCRIZIONE' => 'Cutter Seydelmann', 'CENTRO' => 'MISCRAFF_01', 'STATO_FISICO' => 'OFF',
                'ORA_ST_FISICO' => '2024-05-20 09:02:23.000', 'STATO_LOGICO' => 'IDLE', 'TASK' => null, 'FCODE01' => null,
                'VALORE_CONTATORE' => '1', 'TOTALE_CONTATORE' => '2'] + $base,
            ['MACCHINA' => 'SBG1', 'DESCRIZIONE' => 'INVASAMENTO SBG1', 'CENTRO' => 'INVASETIC_01', 'STATO_FISICO' => 'ON',
                'ORA_ST_FISICO' => null, 'STATO_LOGICO' => 'IDLE', 'TASK' => null, 'FCODE01' => null,
                'VALORE_CONTATORE' => null, 'TOTALE_CONTATORE' => null] + $base,
        ];
    }
}

class NetProLiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_campionamento_finestra_e_pagina(): void
    {
        $finto = new NetProFinto;
        $this->app->instance(SorgenteNetProLive::class, $finto);

        $this->travelTo(now()->subMinutes(14));
        $this->artisan('netpro:campiona')->assertExitCode(0);   // lettura vecchia: verrà eliminata
        $this->travelBack();
        $this->travelTo(now()->subMinutes(4));
        $this->artisan('netpro:campiona')->assertExitCode(0);
        $this->travelBack();
        $finto->contenitori = 11120;
        $this->artisan('netpro:campiona')->expectsOutputToContain('3 macchine, 2 segnali')->assertExitCode(0);

        // Finestra di 10 minuti: restano 2 letture per chiave
        $this->assertSame(2, DB::table('netpro_letture')->where('chiave', 'HEUFT.CTOT')->count());
        $this->assertSame(3, DB::table('netpro_macchine')->count());

        $html = $this->actingAs(User::factory()->create(['ruolo' => 'direzione']))->get('/netpro-live')->assertOk()->getContent();
        $this->assertStringContainsString('Contenitori processati totali', $html);
        $this->assertStringContainsString('+120', $html);
        $this->assertStringContainsString('11.120', $html);               // formato italiano              // Δ contenitori nella finestra
        $this->assertStringContainsString('segnali attivi', $html);        // HEUFT
        $this->assertStringContainsString('segnali fermi', $html);         // SEYDELMANN (fermo dal 2024)
        $this->assertStringNotContainsString('INVASAMENTO SBG1', $html);   // senza segnali: nascosta di default
        $this->get('/netpro-live?tutte=1')->assertSee('INVASAMENTO SBG1');
    }

    /** Le query reali di NetPro live sono SELECT su FIOLINES e MACCHINE e superano la guardia di sola lettura. */
    public function test_query_reali_sola_lettura(): void
    {
        $repo = new class extends RepositoryMes
        {
            public array $sql = [];

            protected function select(string $sql, array $parametri): array
            {
                GuardiaSolaLettura::verifica($sql);
                $this->sql[] = $sql;

                return [];
            }
        };
        $repo->segnaliCorrenti();
        $repo->statoMacchine();
        $this->assertStringContainsString('FROM dbo.FIOLINES', $repo->sql[0]);
        $this->assertStringContainsString('FROM dbo.MACCHINE', $repo->sql[1]);
        $this->assertStringNotContainsString('RESPONSABILE', $repo->sql[1]);
    }
}
