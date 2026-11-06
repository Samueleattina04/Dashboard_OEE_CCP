<?php

use App\Http\Controllers\AccessoController;
use App\Http\Controllers\Admin\ConfigurazioneController;
use App\Http\Controllers\Admin\UtentiController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\MacchinaController;
use App\Http\Controllers\NetProLiveController;
use App\Http\Controllers\PanoramicaController;
use App\Http\Controllers\QualitaDatoController;
use App\Http\Controllers\StatoMacchineController;
use App\Http\Controllers\StoricoController;
use Illuminate\Support\Facades\Route;

// Accesso (pubblico)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AccessoController::class, 'mostraLogin'])->name('login');
    Route::post('/login', [AccessoController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/invito/{token}', [AccessoController::class, 'mostraInvito'])->name('invito.mostra');
    Route::post('/invito/{token}', [AccessoController::class, 'accettaInvito'])->middleware('throttle:10,1')->name('invito.accetta');
    Route::get('/password-dimenticata', [AccessoController::class, 'mostraPasswordDimenticata'])->name('password.dimenticata');
    Route::post('/password-dimenticata', [AccessoController::class, 'richiediReset'])->middleware('throttle:3,10');
    Route::get('/reimposta/{token}', [AccessoController::class, 'mostraReset'])->name('reset.mostra');
    Route::post('/reimposta/{token}', [AccessoController::class, 'applicaReset'])->middleware('throttle:10,1')->name('reset.applica');
});
Route::post('/logout', [AccessoController::class, 'logout'])->middleware('auth')->name('logout');

// Viste OEE: tutti i ruoli (Qualità in lettura + export; Direzione tutte le viste + export)
Route::middleware(['auth', 'ruolo'])->group(function () {
    Route::get('/', PanoramicaController::class)->name('panoramica');
    Route::get('/stato', StatoMacchineController::class)->name('stato');
    Route::get('/macchine/{codice}', MacchinaController::class)->name('macchina');
    Route::get('/storico', StoricoController::class)->name('storico');
    Route::get('/qualita-dato', QualitaDatoController::class)->name('qualita_dato');
    Route::get('/netpro-live', NetProLiveController::class)->name('netpro_live');
    Route::get('/export/giornaliero.{formato}', [ExportController::class, 'giornaliero'])->name('export.giornaliero');
    Route::get('/export/confronto.{formato}', [ExportController::class, 'confronto'])->name('export.confronto');
});

// Configurazione: Admin e SuperAdmin
Route::middleware(['auth', 'ruolo:superadmin,admin'])->prefix('admin')->name('admin.')->group(function () {
    $c = ConfigurazioneController::class;
    Route::get('/configurazione', [$c, 'index'])->name('configurazione');
    Route::get('/storico-modifiche', [$c, 'audit'])->name('audit');
    Route::post('/macchine/{macchina}/regola', [$c, 'aggiornaRegola'])->name('regola');
    Route::post('/macchine/{macchina}/attiva', [$c, 'impostaAttiva'])->name('macchina.attiva');
    Route::post('/macchine/{macchina}/capacita', [$c, 'aggiungiCapacita'])->name('capacita');
    Route::post('/macchine/{macchina}/target', [$c, 'aggiungiTarget'])->name('target');
    Route::post('/macchine/{macchina}/calendario', [$c, 'assegnaCalendario'])->name('assegna_calendario');
    Route::post('/calendari', [$c, 'creaCalendario'])->name('calendario.crea');
    Route::post('/calendari/{calendario}/turni', [$c, 'aggiungiTurno'])->name('turno.aggiungi');
    Route::delete('/turni/{turno}', [$c, 'eliminaTurno'])->name('turno.elimina');
    Route::post('/calendari/{calendario}/eccezioni', [$c, 'aggiungiEccezione'])->name('eccezione.aggiungi');
    Route::delete('/eccezioni/{eccezione}', [$c, 'eliminaEccezione'])->name('eccezione.elimina');
    Route::post('/importa-ora', [$c, 'importaOra'])->name('importa_ora');
    Route::post('/ricalcola', [$c, 'ricalcola'])->name('ricalcola');
});

// Utenti: solo SuperAdmin
Route::middleware(['auth', 'ruolo:superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/utenti', [UtentiController::class, 'index'])->name('utenti');
    Route::post('/utenti/invita', [UtentiController::class, 'invita'])->name('utenti.invita');
    Route::post('/utenti/{utente}', [UtentiController::class, 'aggiorna'])->name('utenti.aggiorna');
    Route::delete('/inviti/{id}', [UtentiController::class, 'revocaInvito'])->name('inviti.revoca');
    Route::post('/utenti/{utente}/reimposta-password', [UtentiController::class, 'reimpostaPassword'])->name('utenti.reset');
    Route::post('/richieste-reset/{id}/archivia', [UtentiController::class, 'ignoraRichiesta'])->name('richieste.archivia');
});
