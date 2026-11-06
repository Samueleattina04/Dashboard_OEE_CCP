@extends('layout')
@section('titolo', 'Storico e confronti')
@section('contenuto')
@php $F = \App\Support\Formato::class; @endphp
<h1>Storico e confronti</h1>
<p class="muto">Periodo A a confronto con il periodo B (se non indicato: il periodo precedente di pari durata), macchina per macchina.</p>

@include('parziali.filtri', ['macchine' => $tutteMacchine, 'extra' => array_filter(['b_da' => request('b_da'), 'b_a' => request('b_a')])])
<form method="get" class="filtri" aria-label="Periodo di confronto">
    @foreach ($filtri->query() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <label>Periodo B dal<input type="date" name="b_da" value="{{ $daB }}"></label>
    <label>al<input type="date" name="b_a" value="{{ $aB }}"></label>
    <button class="secondario">Confronta</button>
    <span class="muto piccolo">A: {{ $F::data($filtri->da) }}–{{ $F::data($filtri->a) }} · B: {{ $F::data($daB) }}–{{ $F::data($aB) }}</span>
</form>

<div class="riga-flex" style="margin-bottom:12px">
    <a class="bottone secondario" href="{{ route('export.confronto', ['formato' => 'xlsx'] + $filtri->query(['b_da' => $daB, 'b_a' => $aB])) }}">Esporta Excel</a>
    <a class="bottone secondario" href="{{ route('export.confronto', ['formato' => 'csv'] + $filtri->query(['b_da' => $daB, 'b_a' => $aB])) }}">Esporta CSV</a>
</div>

<section class="pannello">
    <h2>Ore in funzione medie al giorno</h2>
    <div class="legenda">
        <span><i class="campione-colore" style="background:var(--serie-1)"></i>Periodo A ({{ $F::data($filtri->da) }}–{{ $F::data($filtri->a) }})</span>
        <span><i class="campione-colore" style="background:var(--serie-2)"></i>Periodo B ({{ $F::data($daB) }}–{{ $F::data($aB) }})</span>
    </div>
    <div class="grafico" id="grafico-confronto" style="height:260px" role="img" aria-label="Ore in funzione medie al giorno, periodo A e B"></div>
    <script type="application/json" id="dati-confronto">{!! json_encode($datiGrafico, JSON_HEX_TAG) !!}</script>
</section>

@php
    $indicatori = [
    ['Ore in funzione', 'secondi_marcia', 'ore'],
    ['Ore in funzione medie/giorno', 'ore_marcia_media', 'ore'],
    ['Utilizzo sul tempo coperto', 'utilizzo_coperto', 'pct'],
    ['Copertura dati', 'copertura', 'pct'],
    ['Disponibilità', 'disponibilita', 'pct'],
    ['Prestazione', 'prestazione', 'pct'],
    ['OEE', 'oee', 'pct'],
    ['Ore riscaldamento', 'secondi_riscaldamento', 'ore'],
    ['Ore raffreddamento', 'secondi_raffreddamento', 'ore'],
    ['Avviamenti', 'avviamenti', 'int'],
    ['Kg selezionati', 'kg_selezionati', 'kg'],
    ['Scarto di selezione', 'scarto_selezione', 'pct'],
];
@endphp
<section class="pannello scorri">
    <h2>Indicatori a confronto</h2>
    <table>
        <thead>
            <tr><th>Indicatore</th>
                @foreach ($righe as $r)<th class="num" colspan="3">{{ $r['macchina']->codice }}</th>@endforeach</tr>
            <tr><th></th>@foreach ($righe as $r)<th class="num">A</th><th class="num">B</th><th class="num">Δ A−B</th>@endforeach</tr>
        </thead>
        <tbody>
        @foreach ($indicatori as [$nome, $chiave, $tipo])
            <tr><td>{{ $nome }}</td>
            @foreach ($righe as $r)
                @php
                    $va = $r['a'][$chiave] ?? null;
                    $vb = $r['b'][$chiave] ?? null;
                    $fmt = fn ($x) => match ($tipo) { 'ore' => $F::ore($x === null ? null : (int) $x), 'pct' => $F::percentuale($x), 'kg' => $F::numero($x, 0), default => $x === null ? 'n.d.' : (string) $x };
                @endphp
                <td class="num">{{ $fmt($va) }}</td><td class="num">{{ $fmt($vb) }}</td>
                <td class="num">
                    @if ($va === null || $vb === null) <span class="muto">—</span>
                    @elseif ($tipo === 'pct') {{ ($va - $vb) >= 0 ? '+' : '' }}{{ number_format(($va - $vb) * 100, 1, ',', '.') }} p.p.
                    @elseif ($tipo === 'ore') {{ ($va - $vb) >= 0 ? '+' : '' }}{{ number_format(($va - $vb) / 3600, 1, ',', '.') }} h
                    @else {{ ($va - $vb) >= 0 ? '+' : '' }}{{ number_format($va - $vb, 0, ',', '.') }}
                    @endif
                </td>
            @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="piccolo muto">Le percentuali di periodo sono pesate (somma delle ore, poi rapporto), non medie dei giorni. Δ in punti percentuali (p.p.) per le percentuali.</p>
</section>
@endsection

@push('script')
<script src="{{ asset('vendor/echarts/echarts.min.js') }}"></script>
<script src="{{ asset('js/confronto.js') }}"></script>
@endpush
