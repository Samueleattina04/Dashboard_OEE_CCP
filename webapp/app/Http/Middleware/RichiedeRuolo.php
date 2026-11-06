<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('ruolo:superadmin,admin'). Gli utenti disattivati vengono disconnessi.
 */
class RichiedeRuolo
{
    public function handle(Request $request, Closure $next, string ...$ruoli): Response
    {
        $utente = $request->user();
        if (! $utente || ! $utente->attivo) {
            Auth::logout();

            return redirect()->route('login');
        }
        if ($ruoli && ! $utente->haRuolo(...$ruoli)) {
            abort(403, 'Non hai i permessi per questa pagina.');
        }

        return $next($request);
    }
}
