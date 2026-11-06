@extends('layout')
@section('titolo', 'Qualità del dato')
@section('contenuto')
@php
    $F = \App\Support\Formato::class;
    // Scala sequenziale (un solo tono, chiaro → scuro) per la copertura
    $classe = function ($c) {
        if ($c === null || $c <= 0) return 'cop-0';
        if ($c < 0.25) return 'cop-1';
        if ($c < 0.5) return 'cop-2';
        if ($c < 0.75) return 'cop-3';
        if ($c < 0.999) return 'cop-4';
        return 'cop-5';
    };
@endphp
<h1>Qualità del dato</h1>
<p class="muto">Copertura dei dati FDI giorno per giorno: quota del giorno con un campione valido. Un giorno a 0 % significa che il MES non ha registrato valori utilizzabili (record mancanti o campi vuoti), non che la macchina era ferma.</p>
@include('parziali.filtri', ['macchine' => $tutteMacchine])

<div class="legenda" aria-label="Legenda copertura">
    <span><i class="campione-colore cop-0"></i>nessun dato</span>
    <span><i class="campione-colore cop-1"></i>&lt; 25 %</span>
    <span><i class="campione-colore cop-2"></i>25–50 %</span>
    <span><i class="campione-colore cop-3"></i>50–75 %</span>
    <span><i class="campione-colore cop-4"></i>75–99 %</span>
    <span><i class="campione-colore cop-5"></i>100 %</span>
</div>

@foreach ($schede as $s)
<section class="pannello">
    <h2><a href="{{ route('macchina', $s['macchina']->codice) }}">{{ $s['macchina']->codice }}</a> · {{ $s['macchina']->descrizione }}</h2>
    <p class="piccolo muto">
        Primo campione importato: {{ $s['primo'] ? $F::oraLocale($s['primo']) : '—' }} ·
        ultimo: <strong>{{ $s['ultimo'] ? $F::oraLocale($s['ultimo']) : '—' }}</strong> ·
        ultima importazione: {{ $s['sync']?->ultima_esecuzione ? $F::oraLocale($s['sync']->ultima_esecuzione) : '—' }} ({{ $s['sync']?->esito ?? '—' }})
    </p>
    <div class="calendario-copertura" role="img" aria-label="Copertura giornaliera {{ $s['macchina']->codice }}">
        @foreach ($s['giorni'] as $g)
            <div class="{{ $classe($g['copertura']) }}" title="{{ $F::data($g['giorno']) }}: copertura {{ $F::percentuale($g['copertura']) }}, {{ $g['campioni'] }} campioni{{ $g['senza_dato'] !== null ? ', '.$F::ore($g['senza_dato']).' h senza dato' : '' }}"></div>
        @endforeach
    </div>
    <div class="scala-giorni piccolo muto"><span>{{ $F::data($filtri->da) }}</span><span>{{ $F::data($filtri->a) }}</span></div>

    <h3>Interruzioni (giorni consecutivi con copertura sotto il 50 %)</h3>
    @if (empty($s['interruzioni']))
        <p class="muto">Nessuna interruzione nel periodo.</p>
    @else
        <div class="scorri">
        <table>
            <thead><tr><th>Dal</th><th>Al</th><th class="num">Giorni</th><th class="num">Ore senza dato</th></tr></thead>
            <tbody>
            @foreach ($s['interruzioni'] as $i)
                <tr><td>{{ $F::data($i['da']) }}</td><td>{{ $F::data($i['a']) }}</td><td class="num">{{ $i['giorni'] }}</td><td class="num">{{ $F::numero($i['ore_senza_dato'], 1) }}</td></tr>
            @endforeach
            </tbody>
        </table>
        </div>
    @endif
</section>
@endforeach
@endsection
