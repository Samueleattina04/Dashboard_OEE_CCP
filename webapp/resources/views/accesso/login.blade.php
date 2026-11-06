@extends('layout')
@section('titolo', 'Accesso')
@section('contenuto')
<div class="pannello accesso">
    <h1>Dashboard OEE e CCP</h1>
    <p class="muto">Antichi Sapori dell'Etna · accesso riservato</p>
    <form method="post" action="{{ url('/login') }}">
        @csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
        @include('parziali.campo_password', ['nome' => 'password', 'etichetta' => 'Password', 'autocomplete' => 'current-password'])
        <button>Accedi</button>
    </form>
    <p class="piccolo" style="margin-top:1rem"><a href="{{ route('password.dimenticata') }}">Password dimenticata?</a></p>
</div>
@endsection
