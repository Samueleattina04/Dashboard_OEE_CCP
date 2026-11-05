<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copia fedele dei campioni MES, intervalli di stato e aggregati.
 * I valori sono conservati nel TESTO ORIGINALE: la conversione avviene in fase di calcolo,
 * senza arrotondamenti (vincolo di integrità del dato).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campioni_mes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('macchina_id')->constrained('macchine')->cascadeOnDelete();
            // Chiave di origine in FPROCESSDATA (tracciabilità)
            $t->string('fobject', 40);
            $t->string('fchannel', 40);
            $t->string('ftimestamp_originale', 23)->comment('Ora locale Europe/Rome come registrata dal MES');
            $t->unsignedInteger('frow');
            $t->dateTime('ts_utc', 3);
            $t->string('fbatch', 80)->nullable();
            $t->json('valori')->comment('{"FVALUE01":"Selecting",...} testo originale; null = campo vuoto');
            $t->timestamp('importato_il')->useCurrent();
            $t->unique(['macchina_id', 'fchannel', 'ftimestamp_originale', 'frow'], 'campioni_mes_chiave_origine');
            $t->index(['macchina_id', 'ts_utc']);
        });

        Schema::create('intervalli_stato', function (Blueprint $t) {
            $t->id();
            $t->foreignId('macchina_id')->constrained('macchine')->cascadeOnDelete();
            $t->dateTime('inizio_utc', 3);
            $t->dateTime('fine_utc', 3);
            $t->string('stato', 20);
            $t->string('motivo', 60)->nullable()->comment('es. campo_vuoto, record_mancante, valore_non_mappato');
            $t->foreignId('campione_id')->nullable()->constrained('campioni_mes')->nullOnDelete();
            $t->foreignId('regola_id')->nullable()->constrained('regole_stato')->nullOnDelete();
            $t->index(['macchina_id', 'inizio_utc']);
        });

        $colonneStato = function (Blueprint $t) {
            foreach (['marcia', 'fermo', 'allarme', 'avvio', 'riscaldamento', 'raffreddamento',
                'non_classificato', 'senza_dato'] as $stato) {
                $t->unsignedInteger('secondi_'.$stato)->default(0);
            }
        };

        Schema::create('agg_ora', function (Blueprint $t) use ($colonneStato) {
            $t->id();
            $t->foreignId('macchina_id')->constrained('macchine')->cascadeOnDelete();
            $t->dateTime('ora_inizio_utc');
            $colonneStato($t);
            $t->unique(['macchina_id', 'ora_inizio_utc']);
        });

        Schema::create('agg_giorno', function (Blueprint $t) use ($colonneStato) {
            $t->id();
            $t->foreignId('macchina_id')->constrained('macchine')->cascadeOnDelete();
            $t->date('giorno')->comment('Giorno locale Europe/Rome');
            $colonneStato($t);
            $t->unsignedInteger('campioni_presenti')->default(0);
            $t->unsignedInteger('avviamenti')->default(0)->comment('Passaggi FERMO -> RISCALDAMENTO/MARCIA');
            // Tempo pianificato (NULL = calendario non configurato)
            $t->unsignedInteger('secondi_pianificati')->nullable();
            $t->unsignedInteger('secondi_marcia_in_turno')->nullable();
            $t->unsignedInteger('secondi_esclusi_in_turno')->nullable()->comment('senza dato + non classificato nel turno');
            $t->unsignedInteger('secondi_marcia_fuori_turno')->nullable();
            // Quantità (FX10130, kg indicativi)
            $t->decimal('kg_selezionati', 15, 5)->nullable();
            $t->decimal('kg_scartati', 15, 5)->nullable();
            $t->decimal('kg_ingresso_in_turno', 15, 5)->nullable();
            $t->unsignedInteger('reset_contatori')->default(0);
            $t->decimal('capacita_kg_ora', 12, 3)->nullable();
            // KPI (NULL = n.d., motivo in "motivi")
            $t->decimal('disponibilita', 9, 6)->nullable();
            $t->decimal('prestazione', 9, 6)->nullable();
            $t->decimal('qualita', 9, 6)->nullable();
            $t->decimal('oee', 9, 6)->nullable();
            $t->json('motivi')->nullable();
            $t->foreignId('regola_id')->nullable()->constrained('regole_stato')->nullOnDelete();
            $t->timestamp('calcolato_il')->useCurrent();
            $t->unique(['macchina_id', 'giorno']);
        });

        Schema::create('sync_mes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('macchina_id')->unique()->constrained('macchine')->cascadeOnDelete();
            $t->string('ultimo_ftimestamp', 23)->nullable();
            $t->timestamp('ultima_esecuzione')->nullable();
            $t->timestamp('ultimo_successo')->nullable();
            $t->string('esito', 10)->nullable()->comment('ok | errore');
            $t->text('messaggio')->nullable();
            $t->unsignedInteger('righe_importate')->default(0);
        });
    }

    public function down(): void
    {
        foreach (['sync_mes', 'agg_giorno', 'agg_ora', 'intervalli_stato', 'campioni_mes'] as $tabella) {
            Schema::dropIfExists($tabella);
        }
    }
};
