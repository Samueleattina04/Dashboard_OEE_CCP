<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "NetPro live": fotografia periodica (ogni 2 minuti) di tutte le macchine e di tutti i segnali in tempo reale
 * di NetPro (MACCHINE, FIOLINES), conservata per una finestra breve (config dashboard.netpro_live_minuti).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('netpro_macchine', function (Blueprint $t) {
            $t->id();
            $t->string('codice', 40)->unique()->comment('MACCHINE.MACCHINA');
            $t->string('descrizione', 120)->nullable();
            $t->string('centro', 40)->nullable();
            $t->json('stato')->comment('Ultima lettura delle colonne di stato e contatori (testo originale)');
            $t->dateTime('letto_il', 3);
        });

        Schema::create('netpro_letture', function (Blueprint $t) {
            $t->id();
            $t->dateTime('letto_il', 3)->comment('Quando la dashboard ha letto (UTC)');
            $t->string('oggetto', 40)->comment('Macchina (FOBJECT o MACCHINA)');
            $t->string('tipo', 10)->comment('segnale | stato');
            $t->string('chiave', 60)->comment('FIOLINE (es. HEUFT.VEL) o colonna di MACCHINE');
            $t->string('descrizione', 120)->nullable();
            $t->string('valore', 200)->nullable()->comment('Valore come registrato da NetPro');
            $t->string('ts_mes', 23)->nullable()->comment('Ora della lettura dal PLC (FREADDATE) o del cambio stato, ora locale');
            $t->index(['oggetto', 'chiave', 'letto_il']);
            $t->index('letto_il');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('netpro_letture');
        Schema::dropIfExists('netpro_macchine');
    }
};
