<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Password dimenticate: richieste degli utenti (gestite dal SuperAdmin) e link di reimpostazione monouso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('richieste_reset_password', function (Blueprint $t) {
            $t->id();
            $t->string('email');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('ip', 45)->nullable();
            $t->timestamp('gestita_il')->nullable();
            $t->foreignId('gestita_da')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('link_reset_password', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('token_hash', 64)->unique()->comment('SHA-256 del token: il token in chiaro è solo nel link');
            $t->timestamp('scade_il');
            $t->timestamp('usato_il')->nullable();
            $t->foreignId('creato_da')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_reset_password');
        Schema::dropIfExists('richieste_reset_password');
    }
};
