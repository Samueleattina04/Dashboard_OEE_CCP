{{-- Riquadri KPI di periodo. Parametri: $r (riepilogo), $macchina --}}
@php($F = \App\Support\Formato::class)
<div class="kpi">
    <div>
        <div class="etichetta">Ore in funzione <span class="info" title="Somma delle durate in stato «In funzione», ricavate dai campioni FDI ogni 15 minuti (risoluzione ±15 min).">i</span></div>
        <div class="valore">{{ $F::ore($r['secondi_marcia']) }} h</div>
        <div class="nota">media {{ $F::ore($r['ore_marcia_media'] !== null ? (int) $r['ore_marcia_media'] : null) }} h/giorno</div>
    </div>
    <div>
        <div class="etichetta">Utilizzo sul tempo coperto <span class="info" title="Ore in funzione / (ore totali − ore senza dato − ore non classificate). Da leggere insieme alla copertura dati.">i</span></div>
        <div class="valore">{{ $F::percentuale($r['utilizzo_coperto']) }}</div>
        <div class="nota">su calendario 24 h: {{ $F::percentuale($r['utilizzo_calendario']) }}</div>
    </div>
    <div>
        <div class="etichetta">Copertura dati <span class="info" title="(Ore totali − ore senza dato) / ore totali. Senza dato = campo vuoto nel record FDI o record mancante: non è contato come fermo.">i</span></div>
        <div class="valore">{{ $F::percentuale($r['copertura']) }}</div>
        <div class="nota">{{ number_format($r['campioni'], 0, ',', '.') }} campioni</div>
    </div>
    <div>
        <div class="etichetta">Disponibilità <span class="info" title="Ore in funzione nel turno / (ore pianificate − ore senza dato nel turno). Ore pianificate = turni − pause − eccezioni (configurazione Admin). Aggregata pesando sul tempo pianificato.">i</span></div>
        <div class="valore">{{ $F::percentuale($r['disponibilita']) }}</div>
        <div class="nota">{{ $F::motivo($r['motivi']['disponibilita'] ?? null) }}</div>
    </div>
    @if ($contatori ?? true)
    <div>
        <div class="etichetta">Prestazione <span class="info" title="Kg in ingresso nel turno / (capacità nominale kg/h × ore in funzione nel turno). Kg indicativi, non fiscali (manuale 3UVision).">i</span></div>
        <div class="valore">{{ $F::percentuale($r['prestazione']) }}</div>
        <div class="nota">{{ $F::motivo($r['motivi']['prestazione'] ?? null) }}</div>
    </div>
    @endif
    <div>
        <div class="etichetta">OEE <span class="info" title="Disponibilità × Prestazione × Qualità. Se un fattore non è disponibile l'OEE è n.d. (mai sostituito con 100%).">i</span></div>
        <div class="valore">{{ $F::percentuale($r['oee']) }}</div>
        <div class="nota">{{ $F::motivo($r['motivi']['qualita'] ?? null) }}</div>
    </div>
    @if (isset($stati) ? in_array(\App\Domain\StatoMacchina::Riscaldamento, $stati, true) : $r['secondi_riscaldamento'] + $r['secondi_raffreddamento'] > 0)
    <div>
        <div class="etichetta">Riscaldamento / raffreddamento <span class="info" title="Forno: temperatura di tostatura nella fascia transitoria (Admin: 70–100 °C), in salita o in discesa. Non conta come «In funzione».">i</span></div>
        <div class="valore">{{ $F::ore($r['secondi_riscaldamento']) }} / {{ $F::ore($r['secondi_raffreddamento']) }} h</div>
        <div class="nota">{{ $r['avviamenti'] }} avviamenti</div>
    </div>
    @endif
    @if ($r['kg_selezionati'] !== null)
    <div>
        <div class="etichetta">Kg selezionati / scartati <span class="info" title="Somma degli incrementi dei contatori di sezione 1 e 2 (FNVALUE04+06 / FNVALUE03+05). Valori indicativi, non fiscali. Gli azzeramenti dei contatori sono gestiti e segnalati.">i</span></div>
        <div class="valore">{{ $F::numero($r['kg_selezionati'], 0) }} / {{ $F::numero($r['kg_scartati'], 0) }}</div>
        <div class="nota">scarto di selezione {{ $F::percentuale($r['scarto_selezione']) }}@if ($r['reset_contatori']) · {{ $r['reset_contatori'] }} azzeramenti @endif</div>
    </div>
    @endif
</div>
