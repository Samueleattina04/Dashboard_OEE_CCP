<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titolo', 'Dashboard') — OEE e CCP ASE</title>
    <script>
        /* Tema scelto dall'utente (chiaro/scuro/automatico), applicato prima del disegno della pagina */
        (function () { try { var t = localStorage.getItem('tema'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {} })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('testa')
</head>
<body>
@auth
<header class="testata">
    <a class="marchio" href="{{ route('panoramica') }}">ASE · Dashboard OEE</a>
    <nav>
        <a href="{{ route('panoramica') }}" @class(['attivo' => request()->routeIs('panoramica')])>Panoramica</a>
        <a href="{{ route('stato') }}" @class(['attivo' => request()->routeIs('stato')])>Stato macchine</a>
        <a href="{{ route('storico') }}" @class(['attivo' => request()->routeIs('storico')])>Storico e confronti</a>
        @if (auth()->user()->puoConfigurare())
            <a href="{{ route('admin.configurazione') }}" @class(['attivo' => request()->routeIs('admin.configurazione')])>Configurazione</a>
            <a href="{{ route('admin.audit') }}" @class(['attivo' => request()->routeIs('admin.audit')])>Storico modifiche</a>
        @endif
        @if (auth()->user()->puoGestireUtenti())
            <a href="{{ route('admin.utenti') }}" @class(['attivo' => request()->routeIs('admin.utenti')])>Utenti</a>
        @endif
    </nav>
    <div class="utente">
        @include('parziali.tema')
        <span>{{ auth()->user()->name }} · {{ auth()->user()->etichettaRuolo() }}</span>
        <form method="post" action="{{ route('logout') }}" class="in-linea">@csrf<button class="secondario">Esci</button></form>
    </div>
</header>
@endauth
@guest
<div class="tema-ospite">@include('parziali.tema')</div>
@endguest
<main class="contenuto">
    @auth
        @isset($statoSync)
            @if ($statoSync['mes_non_risponde'])
                <div class="banda errore" role="alert">
                    ⚠ <strong>Il MES non risponde o i dati non sono aggiornati.</strong>
                    @if ($statoSync['ultimo_successo']) Ultimo aggiornamento riuscito: {{ \App\Support\Formato::oraLocale($statoSync['ultimo_successo']) }}. @else Nessuna importazione eseguita finora. @endif
                    @if ($statoSync['errore'])<br><span class="piccolo">{{ \Illuminate\Support\Str::limit($statoSync['errore'], 200) }}</span>@endif
                </div>
            @else
                <div class="banda ok">Dati aggiornati alle <strong>{{ \App\Support\Formato::oraLocale($statoSync['ultimo_successo'], 'H:i') }}</strong> del {{ \App\Support\Formato::oraLocale($statoSync['ultimo_successo'], 'd/m/Y') }} · campionamento FDI ogni 15 minuti</div>
            @endif
        @endisset
    @endauth
    @if (session('ok'))<div class="banda ok">{{ session('ok') }}</div>@endif
    @if ($errors->any())
        <div class="banda errore"><ul class="errori" style="margin:0;padding-left:1.2rem">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @yield('contenuto')
</main>
<script src="{{ asset('js/tema.js') }}"></script>
@stack('script')
</body>
</html>
