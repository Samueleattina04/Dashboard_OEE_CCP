@extends('layout')
@section('titolo', 'Nuova password')
@section('contenuto')
<div class="pannello accesso">
    <h1>Scegli una nuova password</h1>
    @if ($email)<p class="muto">{{ $email }}</p>@endif
    <form method="post" action="{{ route('reset.applica', $token) }}">
        @csrf
        <label>Nuova password<input type="password" name="password" required autocomplete="new-password" minlength="12"></label>
        <label>Ripeti la password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        <p class="piccolo muto">Almeno 12 caratteri, con maiuscole, minuscole, numeri e simboli.</p>
        <button>Salva password</button>
    </form>
</div>
@endsection
