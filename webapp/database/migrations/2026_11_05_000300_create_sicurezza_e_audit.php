<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruoli, inviti, audit delle modifiche, log di accessi ed export (contesto NIS2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('ruolo', 20)->default('qualita')->after('email')
                ->comment('superadmin | admin | direzione | qualita');
            $t->boolean('attivo')->default(true)->after('ruolo');
        });

        Schema::create('inviti', function (Blueprint $t) {
            $t->id();
            $t->string('email');
            $t->string('nome', 120)->nullable();
            $t->string('ruolo', 20);
            $t->string('token_hash', 64)->unique()->comment('SHA-256 del token: il token in chiaro è solo nel link email');
            $t->timestamp('scade_il');
            $t->timestamp('usato_il')->nullable();
            $t->foreignId('invitato_da')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('audit_modifiche', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('tabella', 60);
            $t->unsignedBigInteger('record_id')->nullable();
            $t->string('azione', 20)->comment('creazione | modifica | eliminazione');
            $t->json('valore_precedente')->nullable();
            $t->json('valore_nuovo')->nullable();
            $t->timestamp('creato_il')->useCurrent();
            $t->index(['tabella', 'record_id']);
        });

        Schema::create('log_accessi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('email', 255)->nullable();
            $t->string('evento', 30)->comment('login | logout | login_fallito | invito_accettato');
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('creato_il')->useCurrent();
        });

        Schema::create('log_export', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('vista', 60);
            $t->string('formato', 10);
            $t->json('filtri')->nullable();
            $t->unsignedInteger('righe')->default(0);
            $t->timestamp('creato_il')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['log_export', 'log_accessi', 'audit_modifiche', 'inviti'] as $tabella) {
            Schema::dropIfExists($tabella);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['ruolo', 'attivo']);
        });
    }
};
