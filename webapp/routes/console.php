<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Pianificazione (su Windows: Utilità di pianificazione esegue "php artisan schedule:run" ogni minuto).
 * Il MES viene letto UNA volta ogni 15 minuti per macchina (decisione 05/11/2026, docs/02 §12).
 */
Schedule::command('mes:importa')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30);

// Ricalcolo notturno degli ultimi giorni dal solo DB applicativo (righe in ritardo, regole modificate)
Schedule::command('kpi:ricalcola')
    ->dailyAt('02:10')
    ->timezone('Europe/Rome')
    ->withoutOverlapping(120);
