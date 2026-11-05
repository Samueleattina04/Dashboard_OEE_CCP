<?php

namespace Database\Seeders;

use App\Models\Macchina;
use App\Models\RegolaStato;
use Illuminate\Database\Seeder;

/**
 * Macchine e regole di stato decise il 05/11/2026 (docs/02_definizioni_kpi.md §2.2, §12–§14).
 * Idempotente: non sovrascrive regole già presenti (le modifiche si fanno dal pannello Admin, storicizzate).
 */
class MacchineSeeder extends Seeder
{
    public function run(): void
    {
        $fx = Macchina::updateOrCreate(['codice' => 'FX10130'], [
            'descrizione' => 'Selezionatrice Fenix 3UVision',
            'attiva' => true,
            'colonne_import' => array_merge(
                ['FVALUE01', 'FVALUE02', 'FVALUE03', 'FVALUE04'],
                array_map(fn ($i) => sprintf('FNVALUE%02d', $i), range(1, 12))
            ),
        ]);
        if (! $fx->regole()->exists()) {
            RegolaStato::create([
                'macchina_id' => $fx->id,
                'tipo' => 'mappa_valori',
                'valido_dal' => '2000-01-01 00:00:00',
                'note' => 'Regole iniziali (manuale OPC 3UVision + decisioni 05/11/2026)',
                'parametri' => [
                    'colonna' => 'FVALUE01',
                    'g_max_minuti' => 20,
                    'mappa' => [
                        'Selecting' => 'MARCIA', 'Cleaning' => 'MARCIA',
                        'Ready' => 'FERMO', 'Offline' => 'FERMO', 'OfflineOrDisabled' => 'FERMO',
                        'CleaningMachine' => 'FERMO', 'ReadyCheck' => 'FERMO',
                        'ReadyError' => 'ALLARME', 'SelectionError' => 'ALLARME', 'CleaningError' => 'ALLARME',
                        'RecipeError' => 'ALLARME', 'ConnectError' => 'ALLARME', 'ConfigureError' => 'ALLARME',
                        'MachineDataError' => 'ALLARME', 'Emergency' => 'ALLARME',
                        'Configure' => 'AVVIO', 'Connect' => 'AVVIO', 'MachineData' => 'AVVIO', 'Recipe' => 'AVVIO',
                        'Setup' => 'AVVIO', 'FwUpgrade' => 'AVVIO', 'Testing' => 'AVVIO',
                        'End3' => 'NON_CLASSIFICATO',
                    ],
                    // Kg per sezione (non i totali FNVALUE07/08: anomalia A2). Valori indicativi, non fiscali.
                    'contatori' => [
                        'selezionati' => ['FNVALUE04', 'FNVALUE06'],
                        'scartati' => ['FNVALUE03', 'FNVALUE05'],
                    ],
                ],
            ]);
        }

        $forno = Macchina::updateOrCreate(['codice' => 'FORNOKING500'], [
            'descrizione' => 'Forno Brovind King 500',
            'attiva' => true,
            'colonne_import' => ['FVALUE01', 'FVALUE02', 'FVALUE03', 'FVALUE04', 'FVALUE05', 'FVALUE06', 'FVALUE07'],
        ]);
        if (! $forno->regole()->exists()) {
            RegolaStato::create([
                'macchina_id' => $forno->id,
                'tipo' => 'soglie_temperatura',
                'valido_dal' => '2000-01-01 00:00:00',
                'note' => 'Temperatura di tostatura: marcia >= 100 °C, transitori 70-100 °C (decisione 05/11/2026)',
                'parametri' => [
                    'colonna' => 'FVALUE02',
                    'soglia_marcia' => '100',
                    'soglia_transitorio' => '70',
                    'g_max_minuti' => 20,
                ],
            ]);
        }

        Macchina::updateOrCreate(['codice' => 'BROVRAF'], [
            'descrizione' => 'Raffinatrice Brovind linea pasta',
            'attiva' => false,
            'dati_non_affidabili' => true,
            'motivo_non_affidabile' => 'Valori a 0 dal 13/05/2025 (anomalia A3): in sospeso',
            'colonne_import' => array_map(fn ($i) => sprintf('FVALUE%02d', $i), range(1, 11)),
        ]);
    }
}
