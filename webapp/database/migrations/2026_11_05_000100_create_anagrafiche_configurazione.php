<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anagrafiche e configurazioni gestite dalla dashboard (dati che il MES non ha).
 * Ogni configurazione ha una validità (valido_dal / valido_al): i ricalcoli sono ripetibili.
 * Riferimento: docs/02_definizioni_kpi.md §8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendari_turni', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 80)->unique();
            $t->string('note', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('macchine', function (Blueprint $t) {
            $t->id();
            $t->string('codice', 40)->unique()->comment('FPROCESSDATA.FOBJECT');
            $t->string('descrizione', 120);
            $t->boolean('attiva')->default(true);
            $t->boolean('dati_non_affidabili')->default(false);
            $t->string('motivo_non_affidabile', 255)->nullable();
            $t->json('colonne_import')->comment('Colonne FPROCESSDATA da leggere (es. ["FVALUE01","FNVALUE03"])');
            $t->foreignId('calendario_id')->nullable()->constrained('calendari_turni')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('regole_stato', function (Blueprint $t) {
            $t->id();
            $t->foreignId('macchina_id')->constrained('macchine')->cascadeOnDelete();
            $t->string('tipo', 40)->comment('mappa_valori | soglie_temperatura');
            $t->json('parametri');
            $t->dateTime('valido_dal');
            $t->dateTime('valido_al')->nullable();
            $t->string('note', 255)->nullable();
            $t->foreignId('creato_da')->nullable();
            $t->timestamps();
            $t->index(['macchina_id', 'valido_dal']);
        });

        Schema::create('capacita_nominali', function (Blueprint $t) {
            $t->id();
            $t->foreignId('macchina_id')->constrained('macchine')->cascadeOnDelete();
            $t->decimal('kg_ora', 12, 3);
            $t->date('valido_dal');
            $t->date('valido_al')->nullable();
            $t->timestamps();
        });

        Schema::create('turni', function (Blueprint $t) {
            $t->id();
            $t->foreignId('calendario_id')->constrained('calendari_turni')->cascadeOnDelete();
            $t->string('tipo', 10)->default('turno')->comment('turno | pausa');
            $t->string('nome', 60);
            $t->unsignedTinyInteger('giorno_settimana')->comment('1 = lunedì … 7 = domenica (ISO)');
            $t->time('ora_inizio');
            $t->time('ora_fine')->comment('Se minore di ora_inizio il turno termina il giorno dopo');
            $t->timestamps();
        });

        Schema::create('eccezioni_calendario', function (Blueprint $t) {
            $t->id();
            $t->foreignId('calendario_id')->constrained('calendari_turni')->cascadeOnDelete();
            $t->foreignId('macchina_id')->nullable()->constrained('macchine')->cascadeOnDelete();
            $t->date('data');
            $t->string('tipo', 30)->comment('festivo | fermo_programmato | assenza_ordini');
            $t->time('ora_inizio')->nullable()->comment('NULL = tutto il giorno');
            $t->time('ora_fine')->nullable();
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->index(['calendario_id', 'data']);
        });

        Schema::create('target_oee', function (Blueprint $t) {
            $t->id();
            $t->foreignId('macchina_id')->constrained('macchine')->cascadeOnDelete();
            $t->decimal('oee', 5, 4)->nullable();
            $t->decimal('disponibilita', 5, 4)->nullable();
            $t->decimal('prestazione', 5, 4)->nullable();
            $t->decimal('qualita', 5, 4)->nullable();
            $t->date('valido_dal');
            $t->date('valido_al')->nullable();
            $t->timestamps();
        });

        Schema::create('big_losses', function (Blueprint $t) {
            $t->id();
            $t->string('codice', 30)->unique();
            $t->string('descrizione', 120);
        });

        Schema::create('causali_fermo', function (Blueprint $t) {
            $t->id();
            $t->string('codice', 30)->unique();
            $t->string('descrizione', 120);
            $t->foreignId('big_loss_id')->nullable()->constrained('big_losses')->nullOnDelete();
            $t->boolean('pianificata')->default(false)->comment('Se vera, il fermo è escluso dal tempo pianificato');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['causali_fermo', 'big_losses', 'target_oee', 'eccezioni_calendario', 'turni',
            'capacita_nominali', 'regole_stato', 'macchine', 'calendari_turni'] as $tabella) {
            Schema::dropIfExists($tabella);
        }
    }
};
