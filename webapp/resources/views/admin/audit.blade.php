@extends('layout')
@section('titolo', 'Storico modifiche')
@section('contenuto')
@php($F = \App\Support\Formato::class)
<h1>Storico modifiche</h1>
<p class="muto">Ogni modifica di configurazione: chi, quando, valore precedente e nuovo.</p>
<section class="pannello scorri">
<table>
    <thead><tr><th>Quando</th><th>Utente</th><th>Tabella</th><th>Record</th><th>Azione</th><th>Prima</th><th>Dopo</th></tr></thead>
    <tbody>
    @forelse ($voci as $v)
        <tr><td>{{ $F::oraLocale($v->getRawOriginal('creato_il')) }}</td><td>{{ $utenti[$v->user_id] ?? 'sistema' }}</td><td>{{ $v->tabella }}</td>
            <td>{{ $v->record_id }}</td><td>{{ $v->azione }}</td>
            <td><code class="piccolo">{{ $v->valore_precedente ? json_encode($v->valore_precedente, JSON_UNESCAPED_UNICODE) : '' }}</code></td>
            <td><code class="piccolo">{{ $v->valore_nuovo ? json_encode($v->valore_nuovo, JSON_UNESCAPED_UNICODE) : '' }}</code></td></tr>
    @empty
        <tr><td colspan="7" class="muto">Nessuna modifica registrata.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $voci->links() }}
</section>
@endsection
