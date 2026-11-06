@extends('layout')
@section('titolo', 'Utenti')
@section('contenuto')
@php($F = \App\Support\Formato::class)
<h1>Utenti</h1>
@if ($richieste->isNotEmpty())
    <section class="pannello" style="border-color:var(--avviso-bordo)">
        <h2>Richieste di reimpostazione password ({{ $richieste->count() }})</h2>
        <table>
            <thead><tr><th>Email</th><th>Richiesta il</th><th></th></tr></thead>
            <tbody>
            @foreach ($richieste as $r)
                @php($u = $utenti->firstWhere('email', $r->email))
                <tr><td>{{ $r->email }}</td><td>{{ $F::oraLocale($r->created_at) }}</td>
                    <td class="riga-flex">
                        @if ($u)<form method="post" action="{{ route('admin.utenti.reset', $u) }}">@csrf<button>Invia link di reimpostazione</button></form>@endif
                        <form method="post" action="{{ route('admin.richieste.archivia', $r->id) }}">@csrf<button class="secondario">Archivia</button></form>
                    </td></tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endif
@if (session('link_reset'))
    <div class="banda avviso">Link di reimpostazione password (mostrato solo ora, valido 24 ore, utilizzabile una volta):<br><code style="word-break:break-all">{{ session('link_reset') }}</code></div>
@endif
@if (session('link_invito'))
    <div class="banda avviso">Link di invito (mostrato solo ora, valido 7 giorni):<br><code style="word-break:break-all">{{ session('link_invito') }}</code></div>
@endif
<section class="pannello">
    <h2>Invita un utente</h2>
    <form method="post" action="{{ route('admin.utenti.invita') }}" class="filtri">
        @csrf
        <label>Email<input type="email" name="email" required></label>
        <label>Nome<input name="nome" maxlength="120"></label>
        <label>Ruolo
            <select name="ruolo">@foreach (\App\Models\User::RUOLI as $k => $v)<option value="{{ $k }}" @selected($k === 'direzione')>{{ $v }}</option>@endforeach</select>
        </label>
        <button>Invia invito</button>
    </form>
    <p class="piccolo muto">L'utente riceve un link per scegliere la password e accede con la sua email.
        Qualità: viste OEE (e CCP) + export · Direzione: tutte le viste + export · Admin: + configurazione · SuperAdmin: + utenti.</p>
</section>

<section class="pannello scorri">
    <h2>Utenti registrati</h2>
    <table>
        <thead><tr><th>Nome</th><th>Email</th><th>Ruolo</th><th>Attivo</th><th></th></tr></thead>
        <tbody>
        @foreach ($utenti as $u)
            <tr>
                <td>{{ $u->name }}</td><td>{{ $u->email }}</td>
                <td><select name="ruolo" form="u-{{ $u->id }}">@foreach (\App\Models\User::RUOLI as $k => $v)<option value="{{ $k }}" @selected($u->ruolo === $k)>{{ $v }}</option>@endforeach</select></td>
                <td><select name="attivo" form="u-{{ $u->id }}"><option value="1" @selected($u->attivo)>Sì</option><option value="0" @selected(! $u->attivo)>No</option></select></td>
                <td class="riga-flex">
                    <form method="post" id="u-{{ $u->id }}" action="{{ route('admin.utenti.aggiorna', $u) }}">@csrf<button class="secondario">Salva</button></form>
                    <form method="post" action="{{ route('admin.utenti.reset', $u) }}" onsubmit="return confirm('Creare un link di reimpostazione password per {{ $u->email }}?')">@csrf<button class="secondario">Reimposta password</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>

<section class="pannello scorri">
    <h2>Inviti in attesa</h2>
    <table>
        <thead><tr><th>Email</th><th>Ruolo</th><th>Scade il</th><th></th></tr></thead>
        <tbody>
        @forelse ($inviti as $i)
            <tr><td>{{ $i->email }}</td><td>{{ \App\Models\User::RUOLI[$i->ruolo] ?? $i->ruolo }}</td><td>{{ $F::oraLocale($i->scade_il) }}</td>
                <td><form method="post" action="{{ route('admin.inviti.revoca', $i->id) }}">@csrf @method('DELETE')<button class="pericolo">Revoca</button></form></td></tr>
        @empty
            <tr><td colspan="4" class="muto">Nessun invito in attesa.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
@endsection
