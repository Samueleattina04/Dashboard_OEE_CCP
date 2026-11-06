@extends('layout')
@section('titolo', 'Nuova password')
@section('contenuto')
<div class="pannello accesso">
    <h1>Scegli una nuova password</h1>
    @if ($email)<p class="muto">{{ $email }}</p>@endif
    <form method="post" action="{{ route('reset.applica', $token) }}">
        @csrf
        @include('parziali.campo_password', ['nome' => 'password', 'etichetta' => 'Nuova password', 'autocomplete' => 'new-password', 'minlength' => 12])
        @include('parziali.campo_password', ['nome' => 'password_confirmation', 'etichetta' => 'Ripeti la password', 'autocomplete' => 'new-password'])
        <p class="piccolo muto">Almeno 12 caratteri, con maiuscole, minuscole, numeri e simboli.</p>
        <button>Salva password</button>
    </form>
</div>
@endsection
