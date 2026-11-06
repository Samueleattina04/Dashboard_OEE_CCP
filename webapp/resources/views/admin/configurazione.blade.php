@extends('layout')
@section('titolo', 'Configurazione')
@section('contenuto')
@php($F = \App\Support\Formato::class)
@php($giorniSett = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'])
<h1>Configurazione</h1>
<p class="muto">Dati che il MES non contiene, gestiti qui e storicizzati. Le modifiche ricalcolano automaticamente i giorni interessati.</p>

<section class="pannello">
    <h2>Importazione dal MES</h2>
    <table>
        <thead><tr><th>Macchina</th><th>Ultimo FTIMESTAMP importato</th><th>Ultimo esito</th><th>Ultima esecuzione</th><th>Attiva</th></tr></thead>
        <tbody>
        @foreach ($macchine as $m)
            @php($s = $sync[$m->id] ?? null)
            <tr><td>{{ $m->codice }} @unless ($m->attiva)<span class="muto">(non attiva)</span>@endunless</td>
                <td>{{ $s?->ultimo_ftimestamp ?? '—' }}</td>
                <td>{{ $s?->esito ?? '—' }} @if ($s?->messaggio)<span class="piccolo muto">{{ \Illuminate\Support\Str::limit($s->messaggio, 120) }}</span>@endif</td>
                <td>{{ $s?->ultima_esecuzione ? $F::oraLocale($s->ultima_esecuzione) : '—' }}</td>
                <td><form method="post" action="{{ route('admin.macchina.attiva', $m) }}" onsubmit="return confirm('{{ $m->attiva ? 'Disattivare' : 'Attivare' }} {{ $m->codice }}?')">@csrf
                    <input type="hidden" name="attiva" value="{{ $m->attiva ? 0 : 1 }}">
                    <button class="secondario">{{ $m->attiva ? 'Disattiva' : 'Attiva' }}</button></form></td></tr>
        @endforeach
        </tbody>
    </table>
    <form method="post" action="{{ route('admin.importa_ora') }}" style="margin-top:.75rem">@csrf<button class="secondario">Importa ora</button>
        <span class="piccolo muto">Il job automatico gira ogni 15 minuti; per lo storico iniziale usare il comando <code>php artisan mes:storico</code>.</span></form>
</section>

@foreach ($macchine->where('attiva', true) as $m)
<section class="pannello">
    <h2>{{ $m->codice }} · {{ $m->descrizione }}</h2>
    @php($r = $m->regola_attuale)
    @if ($r)
        <h3>Regola di stato <span class="muto piccolo">(versione valida dal {{ $F::oraLocale($r->valido_dal, 'd/m/Y') }})</span></h3>
        <form method="post" action="{{ route('admin.regola', $m) }}">
            @csrf
            @if ($r->tipo === 'soglie_temperatura')
                <div class="filtri">
                    <label>In funzione da (°C)<input type="number" step="0.1" name="soglia_marcia" value="{{ $r->parametri['soglia_marcia'] }}" required></label>
                    <label>Transitorio da (°C)<input type="number" step="0.1" name="soglia_transitorio" value="{{ $r->parametri['soglia_transitorio'] }}" required></label>
                    <span class="piccolo muto">Colonna {{ $r->parametri['colonna'] }} (temperatura di tostatura). Sotto il transitorio: ferma.</span>
                </div>
            @elseif ($r->tipo === 'soglia_colonne')
                <div class="filtri">
                    <label>In funzione se maggiore di<input type="number" step="0.01" min="0" name="soglia" value="{{ $r->parametri['soglia'] }}" required></label>
                    <span class="piccolo muto">Colonne {{ implode(', ', $r->parametri['colonne']) }} (velocità motore). Se {{ implode(', ', $r->parametri['colonne_validita'] ?? []) }} valgono tutte esattamente 0: senza dato (PLC non comunica).</span>
                </div>
            @else
                <p class="piccolo muto">Valori di {{ $r->parametri['colonna'] }} (CurrentSmState) e stato attribuito:</p>
                <div class="griglia" style="grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:6px">
                    @foreach ($r->parametri['mappa'] as $valore => $stato)
                        <label class="riga-flex" style="gap:.4rem"><span style="min-width:110px">{{ $valore }}</span>
                            <select name="mappa[{{ $valore }}]">@foreach ($stati as $st)<option value="{{ $st->value }}" @selected($st->value === $stato)>{{ $st->etichetta() }}</option>@endforeach</select></label>
                    @endforeach
                </div>
                <div class="filtri" style="margin-top:.5rem">
                    <label>Nuovo valore<input name="nuovo_valore" maxlength="60" placeholder="es. End3"></label>
                    <label>Stato<select name="nuovo_stato"><option value=""></option>@foreach ($stati as $st)<option value="{{ $st->value }}">{{ $st->etichetta() }}</option>@endforeach</select></label>
                </div>
            @endif
            <div class="filtri">
                <label>Buco massimo tra campioni (min)<input type="number" name="g_max_minuti" min="15" max="120" value="{{ $r->parametri['g_max_minuti'] ?? 20 }}"></label>
                <label>Valida dal<input type="date" name="valido_dal" value="{{ now()->format('Y-m-d') }}" required></label>
                <label>Nota<input name="note" maxlength="255"></label>
                <button>Salva nuova versione</button>
            </div>
        </form>
    @endif

    <div class="griglia">
        <form method="post" action="{{ route('admin.capacita', $m) }}">
            @csrf
            <h3>Capacità nominale</h3>
            <p class="piccolo">Attuale: <strong>{{ $m->capacita_attuale ? $F::numero($m->capacita_attuale->kg_ora, 1).' kg/h' : 'non configurata' }}</strong></p>
            <div class="filtri">
                <label>kg/h<input type="number" step="0.001" min="0" name="kg_ora" required></label>
                <label>Valida dal<input type="date" name="valido_dal" value="{{ now()->format('Y-m-d') }}" required></label>
                <button>Registra</button>
            </div>
        </form>
        <form method="post" action="{{ route('admin.assegna_calendario', $m) }}">
            @csrf
            <h3>Calendario turni</h3>
            <div class="filtri">
                <select name="calendario_id" aria-label="Calendario">
                    <option value="">Nessuno (Disponibilità n.d.)</option>
                    @foreach ($calendari as $c)<option value="{{ $c->id }}" @selected($m->calendario_id === $c->id)>{{ $c->nome }}</option>@endforeach
                </select>
                <button>Assegna</button>
            </div>
        </form>
        <form method="post" action="{{ route('admin.target', $m) }}">
            @csrf
            <h3>Target</h3>
            <p class="piccolo">Attuale: OEE {{ $m->target_attuale?->oee !== null ? $F::percentuale($m->target_attuale->oee) : '—' }} · Disponibilità {{ $m->target_attuale?->disponibilita !== null ? $F::percentuale($m->target_attuale->disponibilita) : '—' }}</p>
            <div class="filtri">
                <label>OEE %<input type="number" step="0.1" min="0" max="100" name="oee"></label>
                <label>Disponibilità %<input type="number" step="0.1" min="0" max="100" name="disponibilita"></label>
                <label>Dal<input type="date" name="valido_dal" value="{{ now()->format('Y-m-d') }}" required></label>
                <button>Registra</button>
            </div>
        </form>
    </div>
</section>
@endforeach

<section class="pannello">
    <h2>Calendari dei turni</h2>
    <form method="post" action="{{ route('admin.calendario.crea') }}" class="filtri">@csrf
        <label>Nuovo calendario<input name="nome" required maxlength="80" placeholder="es. Turno unico 7–16"></label><button>Crea</button></form>

    @foreach ($calendari as $c)
        <h3>{{ $c->nome }}</h3>
        <div class="scorri">
        <table>
            <thead><tr><th>Giorno</th><th>Tipo</th><th>Nome</th><th>Dalle</th><th>Alle</th><th></th></tr></thead>
            <tbody>
            @forelse ($c->turni as $t)
                <tr><td>{{ $giorniSett[$t->giorno_settimana] }}</td><td>{{ $t->tipo }}</td><td>{{ $t->nome }}</td>
                    <td>{{ substr($t->ora_inizio, 0, 5) }}</td><td>{{ substr($t->ora_fine, 0, 5) }}</td>
                    <td><form method="post" action="{{ route('admin.turno.elimina', $t) }}">@csrf @method('DELETE')<button class="pericolo">Elimina</button></form></td></tr>
            @empty
                <tr><td colspan="6" class="muto">Nessun turno.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <form method="post" action="{{ route('admin.turno.aggiungi', $c) }}" class="filtri" style="margin-top:.5rem">
            @csrf
            <label>Tipo<select name="tipo"><option value="turno">Turno</option><option value="pausa">Pausa</option></select></label>
            <label>Nome<input name="nome" required maxlength="60" placeholder="es. Mattino"></label>
            <label>Dalle<input type="time" name="ora_inizio" required></label>
            <label>Alle<input type="time" name="ora_fine" required></label>
            <fieldset class="riga-flex" style="border:0;padding:0;margin:0;gap:.4rem">
                @foreach ($giorniSett as $n => $g)<label style="flex-direction:row;gap:2px"><input type="checkbox" name="giorni[]" value="{{ $n }}" @checked($n <= 5)>{{ $g }}</label>@endforeach
            </fieldset>
            <button>Aggiungi</button>
        </form>

        <h3 class="piccolo">Eccezioni (festivi, fermi programmati, assenza ordini)</h3>
        <form method="post" action="{{ route('admin.eccezione.aggiungi', $c) }}" class="filtri">
            @csrf
            <label>Data<input type="date" name="data" required></label>
            <label>Tipo<select name="tipo"><option value="festivo">Festivo</option><option value="fermo_programmato">Fermo programmato</option><option value="assenza_ordini">Assenza ordini</option></select></label>
            <label>Macchina<select name="macchina_id"><option value="">Tutte</option>@foreach ($macchine as $m)<option value="{{ $m->id }}">{{ $m->codice }}</option>@endforeach</select></label>
            <label>Dalle (vuoto = tutto il giorno)<input type="time" name="ora_inizio"></label>
            <label>Alle<input type="time" name="ora_fine"></label>
            <label>Nota<input name="note" maxlength="255"></label>
            <button>Aggiungi</button>
        </form>
        @if ($c->eccezioni->isNotEmpty())
        <table>
            <tbody>
            @foreach ($c->eccezioni as $e)
                <tr><td>{{ $F::data($e->data) }}</td><td>{{ $e->tipo }}</td><td>{{ $e->macchina_id ? $macchine->firstWhere('id', $e->macchina_id)?->codice : 'Tutte' }}</td>
                    <td>{{ $e->ora_inizio ? substr($e->ora_inizio, 0, 5).'–'.substr($e->ora_fine, 0, 5) : 'tutto il giorno' }}</td><td>{{ $e->note }}</td>
                    <td><form method="post" action="{{ route('admin.eccezione.elimina', $e) }}">@csrf @method('DELETE')<button class="pericolo">Elimina</button></form></td></tr>
            @endforeach
            </tbody>
        </table>
        @endif
    @endforeach
    <p class="piccolo muto">I turni non hanno data di validità: una modifica ricalcola gli ultimi 90 giorni delle macchine che usano il calendario.</p>
</section>
@endsection
