<?php

return [
    // Fuso orario dei timestamp MES (FTIMESTAMP è ora locale senza offset) e di visualizzazione.
    'timezone_locale' => 'Europe/Rome',

    // Passo di campionamento FDI (TIMERHIST = 900.000 ms).
    'passo_campionamento_secondi' => 900,

    // Valore predefinito di G_max se la regola della macchina non lo specifica.
    'g_max_minuti_default' => 20,

    // Righe massime lette dal MES per macchina in una singola esecuzione.
    'mes_righe_per_lettura' => 3000,

    // Timeout delle query MES (secondi).
    'mes_timeout_query' => 30,

    // Giorni ricalcolati dal job notturno.
    'giorni_ricalcolo_notturno' => 7,
];
