@extends('layout')
@section('titolo', 'Stato macchine')
@push('testa')<meta http-equiv="refresh" content="60">@endpush
@section('contenuto')
<h1>Stato macchine</h1>
<p class="muto">Stato secondo l'ultimo campione FDI importato (ritardo massimo circa 15 minuti). La pagina si aggiorna ogni 60 secondi leggendo solo il database della dashboard.</p>
@php($F = \App\Support\Formato::class)
<div class="griglia">
@foreach ($schede as $s)
    @php($c = $s['ultimo_campione'])
    @php($vecchio = $c && now()->diffInMinutes(\Illuminate\Support\Carbon::parse($c->getRawOriginal('ts_utc'), 'UTC'), true) > 35)
    <section class="pannello stato-scheda" style="border-left-color: var(--s-{{ strtolower(str_replace('_', '-', $s['stato']->value)) }})">
        <h2><a href="{{ route('macchina', $s['macchina']->codice) }}">{{ $s['macchina']->codice }}</a></h2>
        <div class="muto piccolo">{{ $s['macchina']->descrizione }}</div>
        <div class="stato-grande" style="margin:.5rem 0"><i class="campione-colore st-{{ $s['stato']->value }}" style="width:18px;height:18px"></i>{{ $s['stato']->etichetta() }}</div>
        @if ($s['dal'])<div>da {{ $F::oraLocale($s['dal']) }}</div>@endif
        @if ($s['motivo'] === 'campo_vuoto')<div class="piccolo muto">Il record FDI arriva ma il campo è vuoto (macchina non comunica?)</div>@endif
        @if ($s['motivo'] === 'valori_tutti_zero')<div class="piccolo muto">Valori tutti a zero (temperature comprese): il PLC non comunica</div>@endif
        @if ($s['motivo'] === 'valore_non_mappato')<div class="piccolo muto">Valore non previsto dalle regole: da classificare (Configurazione)</div>@endif
        @if ($c)
            <div class="piccolo muto" style="margin-top:.5rem">Ultimo campione: {{ $F::oraLocale($c->getRawOriginal('ts_utc')) }}</div>
            @if ($vecchio)<div class="banda avviso piccolo" style="margin-top:.5rem">Nessun campione da oltre 35 minuti</div>@endif
        @else
            <div class="piccolo muto">Nessun campione importato.</div>
        @endif
    </section>
@endforeach
</div>
@include('parziali.legenda')
@endsection
