@extends('layout')
@section('titolo', 'Attiva accesso')
@section('contenuto')
<div class="pannello accesso">
    <h1>Attiva il tuo accesso</h1>
    <p class="muto">{{ $invito->email }} · ruolo {{ \App\Models\User::RUOLI[$invito->ruolo] ?? $invito->ruolo }}</p>
    <form method="post" action="{{ route('invito.accetta', $token) }}">
        @csrf
        <label>Nome e cognome<input name="name" value="{{ old('name', $invito->nome) }}" required maxlength="120"></label>
        @include('parziali.campo_password', ['nome' => 'password', 'etichetta' => 'Password', 'autocomplete' => 'new-password', 'minlength' => 12])
        @include('parziali.campo_password', ['nome' => 'password_confirmation', 'etichetta' => 'Ripeti la password', 'autocomplete' => 'new-password'])
        <p class="piccolo muto">Almeno 12 caratteri, con maiuscole, minuscole, numeri e simboli.</p>
        <button>Attiva</button>
    </form>
</div>
@endsection
