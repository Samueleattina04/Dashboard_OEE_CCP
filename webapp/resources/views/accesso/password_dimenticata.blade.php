@extends('layout')
@section('titolo', 'Password dimenticata')
@section('contenuto')
<div class="pannello accesso">
    <h1>Password dimenticata</h1>
    <p class="muto">Inserisci la tua email: l'amministratore riceverà la richiesta e ti manderà un link per scegliere una nuova password.</p>
    <form method="post" action="{{ route('password.dimenticata') }}">
        @csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
        <button>Invia richiesta</button>
    </form>
    <p class="piccolo" style="margin-top:1rem"><a href="{{ route('login') }}">Torna all'accesso</a></p>
</div>
@endsection
