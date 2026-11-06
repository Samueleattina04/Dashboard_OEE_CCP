<?php

namespace Tests\Unit;

use App\Domain\CalcoloKpi;
use App\Domain\CalendarioPianificato;
use App\Domain\Campione;
use App\Domain\Classificatori\ClassificatoreMappaValori;
use App\Domain\Classificatori\ClassificatoreSogliaColonne;
use App\Domain\Classificatori\ClassificatoreSoglieTemperatura;
use App\Domain\ContatoreDelta;
use App\Domain\ConvertitoreTimestamp;
use App\Domain\CostruttoreIntervalli;
use App\Domain\Finestre;
use App\Domain\StatoMacchina;
use App\Domain\ValoreMes;
use App\Mes\GuardiaSolaLettura;
use App\Support\Formato;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Casi di test con calcolo a mano: docs/02_definizioni_kpi.md §9.
 */
class CalcoliTest extends TestCase
{
    private function utc(string $s): DateTimeImmutable
    {
        return new DateTimeImmutable($s, new DateTimeZone('UTC'));
    }

    private function campione(string $ts, array $valori): Campione
    {
        return new Campione($this->utc($ts), $valori);
    }

    /** @return array<string,int> secondi per stato */
    private function secondiPerStato(array $intervalli): array
    {
        $tot = [];
        foreach ($intervalli as $iv) {
            $tot[$iv->stato->value] = ($tot[$iv->stato->value] ?? 0) + $iv->secondi();
        }

        return $tot;
    }

    private function fx(): ClassificatoreMappaValori
    {
        return new ClassificatoreMappaValori('FVALUE01', [
            'Selecting' => 'MARCIA', 'Cleaning' => 'MARCIA', 'Ready' => 'FERMO',
            'SelectionError' => 'ALLARME', 'End3' => 'NON_CLASSIFICATO',
        ]);
    }

    /**
     * T1 — 08:00 Selecting, 08:15 Selecting, 08:30 Ready, (08:45 assente), 09:10 Selecting, 09:25 Selecting; fine 09:40.
     * A mano: marcia 15+15+15+15 = 60 min; fermo 08:30–08:45 = 15 min; senza dato 08:45–09:10 = 25 min.
     * Copertura 75/100; utilizzo su tempo coperto 60/75 = 80%.
     */
    public function test_t1_intervalli_da_campioni_con_record_mancante(): void
    {
        $c = [
            $this->campione('2026-11-05 08:00:00', ['FVALUE01' => 'Selecting']),
            $this->campione('2026-11-05 08:15:00', ['FVALUE01' => 'Selecting']),
            $this->campione('2026-11-05 08:30:00', ['FVALUE01' => 'Ready']),
            $this->campione('2026-11-05 09:10:00', ['FVALUE01' => 'Selecting']),
            $this->campione('2026-11-05 09:25:00', ['FVALUE01' => 'Selecting']),
        ];
        $iv = (new CostruttoreIntervalli($this->fx(), 20 * 60))
            ->costruisci($c, $this->utc('2026-11-05 08:00:00'), $this->utc('2026-11-05 09:40:00'));
        $s = $this->secondiPerStato($iv);

        $this->assertSame(60 * 60, $s['MARCIA']);
        $this->assertSame(15 * 60, $s['FERMO']);
        $this->assertSame(25 * 60, $s['SENZA_DATO']);
        $coperti = 100 * 60 - $s['SENZA_DATO'];
        $this->assertEqualsWithDelta(0.80, $s['MARCIA'] / $coperti, 1e-9);
    }

    /** Campo vuoto nel record (caso normale FDI): SENZA_DATO, non FERMO. Valore sconosciuto: SENZA_DATO segnalato. */
    public function test_campo_vuoto_e_valore_non_mappato_sono_senza_dato(): void
    {
        $iv = (new CostruttoreIntervalli($this->fx(), 1200))->costruisci([
            $this->campione('2026-11-05 10:00:00', ['FVALUE01' => '']),
            $this->campione('2026-11-05 10:15:00', ['FVALUE01' => 'StatoNuovo']),
            $this->campione('2026-11-05 10:30:00', ['FVALUE01' => 'End3']),
        ], $this->utc('2026-11-05 10:00:00'), $this->utc('2026-11-05 10:45:00'));

        $this->assertSame([StatoMacchina::SenzaDato, StatoMacchina::SenzaDato, StatoMacchina::NonClassificato],
            array_map(fn ($i) => $i->stato, $iv));
        $this->assertSame(['campo_vuoto', 'valore_non_mappato', null], array_map(fn ($i) => $i->motivo, $iv));
        $this->assertTrue(StatoMacchina::NonClassificato->esclusoDalCalcolo());
    }

    /**
     * Forno, soglie 70/100 °C. Sequenza: 25 → 75 (sale) → 95 (sale) → 140 → 120 → 90 (scende) → 90 (uguale) → 60.
     * A mano: FERMO, RISCALDAMENTO, RISCALDAMENTO, MARCIA, MARCIA, RAFFREDDAMENTO, RAFFREDDAMENTO, FERMO.
     */
    public function test_forno_transitori_di_riscaldamento_e_raffreddamento(): void
    {
        $cl = new ClassificatoreSoglieTemperatura('FVALUE02', '100', '70');
        $valori = ['25', '75', '95,5', '140', '120,0', '90', '90', '60'];
        $campioni = [];
        foreach ($valori as $i => $v) {
            $campioni[] = $this->campione(sprintf('2026-11-05 06:%02d:00', $i * 5), ['FVALUE02' => $v]);
        }
        $iv = (new CostruttoreIntervalli($cl, 1200, 300))
            ->costruisci($campioni, $this->utc('2026-11-05 06:00:00'), $this->utc('2026-11-05 06:40:00'));

        $this->assertSame(['FERMO', 'RISCALDAMENTO', 'RISCALDAMENTO', 'MARCIA', 'MARCIA', 'RAFFREDDAMENTO', 'RAFFREDDAMENTO', 'FERMO'],
            array_map(fn ($i) => $i->stato->value, $iv));
    }

    /** Valore pari alla soglia: 100,0 °C è MARCIA, 99,99 no; 70,0 è transitorio, 69,99 è FERMO. */
    public function test_forno_valori_pari_alla_soglia(): void
    {
        $cl = new ClassificatoreSoglieTemperatura('FVALUE02', '100', '70');
        $st = fn ($v) => $cl->classifica($this->campione('2026-11-05 06:00:00', ['FVALUE02' => $v]), null, null)->stato;
        $this->assertSame(StatoMacchina::Marcia, $st('100,0'));
        $this->assertSame(StatoMacchina::Riscaldamento, $st('99,99'));
        $this->assertSame(StatoMacchina::Riscaldamento, $st('70'));
        $this->assertSame(StatoMacchina::Fermo, $st('69,99'));
        $this->assertSame(StatoMacchina::SenzaDato, $st(''));
    }

    /**
     * T2 — turno 07–16 con pausa di 1 h → pianificato 480 min; marcia 360; 600 kg/h; 3.000 kg; Q = 0,98.
     * A mano: D = 360/480 = 0,75; P = 3000/(600×6) = 0,8333…; OEE = 0,75 × 0,8333 × 0,98 = 0,6125.
     */
    public function test_t2_oee_di_un_turno(): void
    {
        $d = CalcoloKpi::disponibilita(360 * 60, 480 * 60, 0);
        $p = CalcoloKpi::prestazione(3000.0, 600.0, 360 * 60);
        $this->assertEqualsWithDelta(0.75, $d, 1e-9);
        $this->assertEqualsWithDelta(3000 / 3600, $p, 1e-9);
        $this->assertEqualsWithDelta(0.6125, CalcoloKpi::oee($d, $p, 0.98), 1e-9);
    }

    /** Un fattore mancante rende l'OEE n.d. (mai 100% per difetto). */
    public function test_fattore_mancante_oee_non_disponibile(): void
    {
        $this->assertNull(CalcoloKpi::oee(0.8, 0.9, null));
        $this->assertNull(CalcoloKpi::disponibilita(100, null, 0));
        $this->assertNull(CalcoloKpi::prestazione(100.0, null, 3600));
    }

    /**
     * T3 — G1: pianificato 480, marcia 360 (75%); G2: pianificato 240, marcia 216 (90%).
     * A mano, pesata: 576/720 = 80,0%. La media semplice (82,5%) è sbagliata.
     */
    public function test_t3_aggregazione_pesata_non_media_semplice(): void
    {
        $base = ['esclusi_in_turno' => 0, 'kg_prodotti' => null, 'kg_teorici' => null, 'kg_conformi' => null, 'kg_totali' => null];
        $r = CalcoloKpi::aggrega([
            ['marcia_in_turno' => 360 * 60, 'pianificati' => 480 * 60] + $base,
            ['marcia_in_turno' => 216 * 60, 'pianificati' => 240 * 60] + $base,
        ]);
        $this->assertEqualsWithDelta(0.80, $r['disponibilita'], 1e-9);
        $this->assertNotEqualsWithDelta(0.825, $r['disponibilita'], 1e-6);
        $this->assertNull($r['oee']);
    }

    /** T4 — contatore 100, 150, 20, 60 → delta 50, reset (20), 40 → totale 110, 1 reset. */
    public function test_t4_reset_contatore(): void
    {
        $r = ContatoreDelta::calcola(['100', '150', '20', '60']);
        $this->assertEqualsWithDelta(110.0, (float) $r['totale'], 1e-9);
        $this->assertSame(1, $r['reset']);
        $this->assertSame([2], $r['indici_reset']);
    }

    /** T5 — conversione senza arrotondamento; "3,97 bar" non è numerico. */
    public function test_t5_conversione_valori_testuali(): void
    {
        $this->assertSame('42.2', ValoreMes::decimale('42,2'));
        $this->assertSame('139.85', ValoreMes::decimale('139,85'));
        $this->assertSame('3406.78520', ValoreMes::decimale('3406.78520'));
        $this->assertNull(ValoreMes::decimale('3,97 bar'));
        $this->assertNull(ValoreMes::decimale(''));
        $this->assertTrue(ValoreMes::vuoto('  '));
    }

    /** Ora ripetuta del 25/10/2026: 02:30 locale compare due volte (CEST poi CET). */
    public function test_cambio_ora_legale_ora_ripetuta(): void
    {
        $conv = new ConvertitoreTimestamp('Europe/Rome');
        $a = $conv->versoUtc('2026-10-25 02:15:00.000');
        $b = $conv->versoUtc('2026-10-25 02:30:00.000');
        $c = $conv->versoUtc('2026-10-25 02:15:00.000'); // seconda occorrenza (ora solare)
        $this->assertSame('2026-10-25 00:15:00', $a->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 00:30:00', $b->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 01:15:00', $c->format('Y-m-d H:i:s'));
    }

    /** Turno 07–16 con pausa 12–13 → 8 h pianificate; festivo → 0. */
    public function test_calendario_pianificato(): void
    {
        $turni = [
            ['tipo' => 'turno', 'giorno_settimana' => 4, 'ora_inizio' => '07:00', 'ora_fine' => '16:00'],
            ['tipo' => 'pausa', 'giorno_settimana' => 4, 'ora_inizio' => '12:00', 'ora_fine' => '13:00'],
        ];
        // 05/11/2026 è giovedì (ISO 4)
        $this->assertSame(8 * 3600, Finestre::durata(CalendarioPianificato::finestre('2026-11-05', $turni, [])));
        $this->assertSame(0, Finestre::durata(CalendarioPianificato::finestre('2026-11-05', $turni, [['ora_inizio' => null, 'ora_fine' => null]])));
        $this->assertSame(0, Finestre::durata(CalendarioPianificato::finestre('2026-11-06', $turni, [])));
    }

    /** BROVRAF: in funzione se un motore > 0; temperature tutte a 0 = PLC che non comunica (senza dato). */
    public function test_brovraf_soglia_colonne_e_valori_tutti_zero(): void
    {
        $cl = new ClassificatoreSogliaColonne(['FVALUE07', 'FVALUE08'], '0', ['FVALUE01', 'FVALUE02']);
        $st = fn (array $v) => $cl->classifica($this->campione('2024-06-01 10:00:00', $v), null, null);

        $this->assertSame(StatoMacchina::Marcia, $st(['FVALUE01' => '38,2', 'FVALUE02' => '41', 'FVALUE07' => '0', 'FVALUE08' => '75'])->stato);
        $this->assertSame(StatoMacchina::Fermo, $st(['FVALUE01' => '25,1', 'FVALUE02' => '24', 'FVALUE07' => '0', 'FVALUE08' => '0'])->stato);
        $e = $st(['FVALUE01' => '0', 'FVALUE02' => '0,00000', 'FVALUE07' => '0', 'FVALUE08' => '0']);
        $this->assertSame(StatoMacchina::SenzaDato, $e->stato);
        $this->assertSame('valori_tutti_zero', $e->motivo);
        $this->assertSame(StatoMacchina::SenzaDato, $st(['FVALUE01' => '30', 'FVALUE02' => '30', 'FVALUE07' => '', 'FVALUE08' => null])->stato);
    }

    /** Campioni al minuto (BROVRAF 2024): 60 campioni in un'ora, 45 in marcia → 45 min. */
    public function test_campioni_al_minuto(): void
    {
        $cl = new ClassificatoreSogliaColonne(['FVALUE07'], '0');
        $c = [];
        for ($i = 0; $i < 60; $i++) {
            $c[] = $this->campione(sprintf('2024-06-01 10:%02d:00', $i), ['FVALUE07' => $i < 45 ? '80' : '0']);
        }
        $iv = (new CostruttoreIntervalli($cl, 1200))->costruisci($c, $this->utc('2024-06-01 10:00:00'), $this->utc('2024-06-01 11:00:00'));
        $s = $this->secondiPerStato($iv);
        $this->assertSame(45 * 60, $s['MARCIA']);
        $this->assertSame(15 * 60, $s['FERMO']);
    }

    public function test_formato_valori_netpro(): void
    {
        $this->assertSame('21,8', Formato::valoreMes('21.80000'));
        $this->assertSame('11.457', Formato::valoreMes('11457'));
        $this->assertSame('3,9707', Formato::valoreMes('3,97070'));
        $this->assertSame('Selecting', Formato::valoreMes('Selecting'));
    }

    public function test_guardia_sola_lettura(): void
    {
        GuardiaSolaLettura::verifica('SELECT TOP (10) FVALUE01 FROM dbo.FPROCESSDATA WHERE FOBJECT = ?');
        foreach ([
            'UPDATE dbo.MACCHINE SET FCODE01 = 1',
            'SELECT 1; DELETE FROM dbo.FPROCESSDATA',
            'SELECT * INTO copia FROM dbo.FPROCESSDATA',
            'EXEC sp_who',
            '/* commento */ DROP TABLE x',
        ] as $sql) {
            try {
                GuardiaSolaLettura::verifica($sql);
                $this->fail("Accettata: {$sql}");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertTrue(GuardiaSolaLettura::colonnaValida('FNVALUE12'));
        $this->assertFalse(GuardiaSolaLettura::colonnaValida('FVALUE01; DROP'));
    }
}
