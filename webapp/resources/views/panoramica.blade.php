@extends('layout')
@section('titolo', 'Panoramica')
@section('contenuto')
<h1>Panoramica stabilimento</h1>
<p class="muto">Quanto lavorano le macchine secondo i dati oggettivi FDI (FPROCESSDATA, un campione ogni 15 minuti).</p>
@include('parziali.filtri', ['macchine' => $tutteMacchine])

<div class="riga-flex" style="margin-bottom:12px">
    <a class="bottone secondario" href="{{ route('export.giornaliero', ['formato' => 'xlsx'] + $filtri->query()) }}">Esporta Excel</a>
    <a class="bottone secondario" href="{{ route('export.giornaliero', ['formato' => 'csv'] + $filtri->query()) }}">Esporta CSV</a>
</div>

@forelse ($schede as $i => $s)
    @php($m = $s['macchina'])
    <section class="pannello">
        <h2><a href="{{ route('macchina', ['codice' => $m->codice] + $filtri->query(['macchina' => null])) }}">{{ $m->codice }}</a> · {{ $m->descrizione }}</h2>
        @if ($m->dati_non_affidabili)<div class="banda avviso">Dati non affidabili: {{ $m->motivo_non_affidabile }}</div>@endif
        @if ($s['riepilogo']['giorni'] === 0)
            <p class="muto">Nessun dato nel periodo. Se l'app è appena installata, esegui l'importazione dello storico.</p>
        @else
            @include('parziali.kpi', ['r' => $s['riepilogo']])
            <h3>Ore per stato, giorno per giorno</h3>
            @include('parziali.legenda')
            <div class="grafico" id="grafico-{{ $i }}" role="img" aria-label="Ore per stato per giorno, {{ $m->codice }}"></div>
            <script type="application/json" id="dati-{{ $i }}">@json($s['serie'])</script>
        @endif
    </section>
@empty
    <div class="pannello">Nessuna macchina attiva.</div>
@endforelse
@endsection

@push('script')
<script src="{{ asset('vendor/echarts/echarts.min.js') }}"></script>
<script src="{{ asset('js/grafici.js') }}"></script>
@endpush
