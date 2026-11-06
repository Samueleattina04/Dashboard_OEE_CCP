@extends('layout')
@section('titolo', 'NetPro live')
@push('testa')<meta http-equiv="refresh" content="120">@endpush
@section('contenuto')
@php
    $F = \App\Support\Formato::class;
    $oraMes = function (?string $ts, string $formato = 'd/m/Y H:i:s') {
        if (! $ts) return '—';
        $t = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.v', $ts) ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ts);
        return $t ? $t->format($formato) : $ts;
    };
    $eta = fn (?int $m) => $m === null ? 'mai letto' : ($m < 60 ? "{$m} min fa" : ($m < 2880 ? intdiv($m, 60).' h fa' : intdiv($m, 1440).' giorni fa'));
@endphp
<h1>NetPro live</h1>
<p class="muto">
    Tutte le macchine e tutti i segnali in tempo reale di NetPro (tabelle <code>MACCHINE</code> e <code>FIOLINES</code>), letti dalla dashboard ogni 2 minuti
    e conservati per {{ $minuti }} minuti. NetPro conserva solo l'ultimo valore: lo storico di questi segnali esiste solo qui, per la finestra indicata.
    Ultima lettura: <strong>{{ $ultimaLettura ? $F::oraLocale($ultimaLettura, 'H:i:s') : 'nessuna' }}</strong>. La pagina si aggiorna ogni 2 minuti.
</p>
<p>
    @if ($tutte)
        <a href="{{ route('netpro_live') }}">Mostra solo le macchine con segnali</a>
    @else
        <a href="{{ route('netpro_live', ['tutte' => 1]) }}">Mostra anche le {{ count($nascoste) }} macchine senza segnali</a>
    @endif
</p>

@forelse ($schede as $s)
<section class="pannello">
    <div class="riga-flex" style="justify-content:space-between">
        <h2 style="margin:0">
            @if ($s['in_dashboard'])<a href="{{ route('macchina', $s['codice']) }}">{{ $s['codice'] }}</a>@else{{ $s['codice'] }}@endif
            · {{ $s['descrizione'] }}
        </h2>
        @if (empty($s['segnali']))
            <span class="etichetta-stato muto">nessun segnale</span>
        @elseif ($s['attiva'])
            <span class="etichetta-stato ok">● segnali attivi</span>
        @else
            <span class="etichetta-stato fermo">● segnali fermi</span>
        @endif
    </div>
    <p class="piccolo muto" style="margin:.35rem 0 .75rem">
        Centro {{ $s['centro'] ?: '—' }} ·
        stato fisico <strong>{{ $s['stato']['STATO_FISICO'] ?? '—' }}</strong> dal {{ $oraMes($s['stato']['ORA_ST_FISICO'] ?? null, 'd/m/Y H:i') }} ·
        stato logico <strong>{{ $s['stato']['STATO_LOGICO'] ?? '—' }}</strong> dal {{ $oraMes($s['stato']['ORA_ST_LOGICO'] ?? null, 'd/m/Y H:i') }}
        @if (! empty($s['stato']['FCODE01'])) · stato macchina <strong>{{ $s['stato']['FCODE01'] }}</strong>@endif
        @if (! empty($s['stato']['TASK'])) · task {{ $s['stato']['TASK'] }}@endif
        @if (($s['stato']['TOTALE_CONTATORE'] ?? '') !== '') · contatore {{ $s['stato']['VALORE_CONTATORE'] }} (totale {{ $s['stato']['TOTALE_CONTATORE'] }})@endif
    </p>

    @if (! empty($s['segnali']))
    <div class="scorri">
    <table>
        <thead><tr><th>Segnale</th><th>Descrizione</th><th class="num">Valore attuale</th><th class="num">Δ {{ $minuti }} min</th><th>Ultimi valori</th><th>Letto dal PLC</th></tr></thead>
        <tbody>
        @foreach ($s['segnali'] as $g)
            <tr @class(['muto' => $g['fermo']])>
                <td><code>{{ $g['nome'] }}</code></td>
                <td>{{ $g['descrizione'] }}</td>
                <td class="num" title="Valore registrato: {{ $g['valore'] }}"><strong>{{ $F::valoreMes($g['valore']) }}</strong></td>
                <td class="num">{{ ($g['delta'] === null || abs($g['delta']) < 0.000001) ? '' : (($g['delta'] > 0 ? '+' : '').$F::valoreMes((string) round($g['delta'], 5))) }}</td>
                <td class="piccolo">
                    @foreach (array_slice($g['storia'], -5) as $h)<span class="valore-storico" title="letto alle {{ $F::oraLocale($h['ora'], 'H:i:s') }}">{{ $F::oraLocale($h['ora'], 'H:i') }} → {{ $F::valoreMes($h['valore']) }}</span>@endforeach
                </td>
                <td class="piccolo">
                    {{ $oraMes($g['ts_mes']) }}
                    @if ($g['fermo'])<br><span class="avviso-testo">fermo da {{ $eta($g['eta_minuti']) }}</span>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</section>
@empty
    <div class="pannello">Nessuna lettura ancora. Le letture partono entro 2 minuti dall'aggiornamento (oppure: <code>php artisan netpro:campiona</code>).</div>
@endforelse
@endsection
