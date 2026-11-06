@extends('layout')
@section('titolo', $macchina->codice)
@section('contenuto')
@php($F = \App\Support\Formato::class)
<h1>{{ $macchina->codice }} · {{ $macchina->descrizione }}</h1>
@include('parziali.filtri', ['extra' => ['giorno' => $giorno]])
@include('parziali.kpi', ['r' => $riepilogo])

<section class="pannello">
    <form method="get" class="riga-flex" style="justify-content:space-between">
        <h2 style="margin:0">Timeline del {{ $F::data($giorno) }}</h2>
        <span>
            @foreach ($filtri->query() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <input type="date" name="giorno" value="{{ $giorno }}" onchange="this.form.submit()" aria-label="Giorno">
        </span>
    </form>
    @if ($regola)
        <p class="piccolo muto">Regola: {{ $regola->tipo === 'soglie_temperatura'
            ? 'temperatura '.$regola->parametri['colonna'].' ≥ '.$regola->parametri['soglia_marcia'].' °C in funzione, '.$regola->parametri['soglia_transitorio'].'–'.$regola->parametri['soglia_marcia'].' °C transitorio'
            : 'stato '.$regola->parametri['colonna'].' (CurrentSmState)' }} · valida dal {{ $F::oraLocale($regola->valido_dal, 'd/m/Y') }}</p>
    @endif
    @include('parziali.legenda')
    <div class="timeline" role="img" aria-label="Timeline degli stati">
        @foreach ($timeline as $t)
            <div class="st-{{ $t['stato'] }}" style="left:{{ $t['sinistra'] }}%;width:{{ $t['larghezza'] }}%"
                 title="{{ $t['stato_enum']->etichetta() }}: {{ $F::oraLocale($t['inizio'], 'H:i') }}–{{ $F::oraLocale($t['fine'], 'H:i') }}{{ $t['motivo'] ? ' ('.$t['motivo'].')' : '' }}"></div>
        @endforeach
    </div>
    <div class="scala-ore">@for ($h = 0; $h <= 24; $h += 3)<span style="left:{{ $h / 24 * 100 }}%">{{ sprintf('%02d', $h) }}</span>@endfor</div>
    @if ($timeline->isEmpty())<p class="muto">Nessun dato per questo giorno.</p>@endif

    <details style="margin-top:1rem">
        <summary>Campioni del giorno ({{ $campioni->count() }}) — valori come registrati nel MES</summary>
        <div class="scorri">
        <table>
            <thead><tr><th>Ora (Roma)</th><th>FTIMESTAMP originale</th><th>FBATCH</th>
                @foreach ($macchina->colonne_import as $col)<th>{{ $col }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($campioni as $c)
                <tr><td>{{ $F::oraLocale($c->getRawOriginal('ts_utc'), 'H:i:s') }}</td><td class="muto">{{ $c->ftimestamp_originale }}</td><td>{{ $c->fbatch }}</td>
                    @foreach ($macchina->colonne_import as $col)<td>{{ $c->valori[$col] ?? '' }}</td>@endforeach</tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <p class="piccolo muto">Fonte: NETPRO.dbo.FPROCESSDATA, FOBJECT = {{ $macchina->codice }}, FCHANNEL = HIST. I valori non sono arrotondati.</p>
    </details>
</section>

<section class="pannello">
    <div class="riga-flex" style="justify-content:space-between">
        <h2 style="margin:0">Giorno per giorno</h2>
        <span>
            <a class="bottone secondario" href="{{ route('export.giornaliero', ['formato' => 'xlsx'] + $filtri->query(['macchina' => $macchina->codice])) }}">Excel</a>
            <a class="bottone secondario" href="{{ route('export.giornaliero', ['formato' => 'csv'] + $filtri->query(['macchina' => $macchina->codice])) }}">CSV</a>
        </span>
    </div>
    <div class="scorri">
    <table>
        <thead><tr><th>Giorno</th><th class="num">In funzione h</th><th class="num">Ferma h</th><th class="num">Allarme h</th>
            <th class="num">Riscald. h</th><th class="num">Raffredd. h</th><th class="num">Non class. h</th><th class="num">Senza dato h</th>
            <th class="num">Pianificate h</th><th class="num">Disp.</th><th class="num">Prest.</th><th class="num">Kg sel.</th><th class="num">Kg scart.</th></tr></thead>
        <tbody>
        @foreach ($giorni->sortByDesc('giorno') as $g)
            <tr>
                <td><a href="{{ request()->fullUrlWithQuery(['giorno' => $g->giorno->format('Y-m-d')]) }}">{{ $g->giorno->format('d/m/Y') }}</a></td>
                <td class="num">{{ $F::ore($g->secondi_marcia) }}</td><td class="num">{{ $F::ore($g->secondi_fermo) }}</td>
                <td class="num">{{ $F::ore($g->secondi_allarme) }}</td><td class="num">{{ $F::ore($g->secondi_riscaldamento) }}</td>
                <td class="num">{{ $F::ore($g->secondi_raffreddamento) }}</td><td class="num">{{ $F::ore($g->secondi_non_classificato) }}</td>
                <td class="num">{{ $F::ore($g->secondi_senza_dato) }}</td><td class="num">{{ $F::ore($g->secondi_pianificati) }}</td>
                <td class="num">{{ $F::percentuale($g->disponibilita) }}</td><td class="num">{{ $F::percentuale($g->prestazione) }}</td>
                <td class="num">{{ $F::numero($g->kg_selezionati, 0) }}</td><td class="num">{{ $F::numero($g->kg_scartati, 0) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</section>
@endsection
