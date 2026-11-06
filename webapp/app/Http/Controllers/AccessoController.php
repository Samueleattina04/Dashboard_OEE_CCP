<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LogAccessi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AccessoController extends Controller
{
    public function mostraLogin()
    {
        return view('accesso.login');
    }

    public function login(Request $request)
    {
        $credenziali = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credenziali + ['attivo' => true])) {
            $request->session()->regenerate();
            LogAccessi::registra($request, 'login', Auth::id(), $credenziali['email']);

            return redirect()->intended(route('panoramica'));
        }

        LogAccessi::registra($request, 'login_fallito', null, $credenziali['email']);

        return back()->withErrors(['email' => 'Credenziali non valide.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        LogAccessi::registra($request, 'logout', Auth::id(), Auth::user()?->email);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function mostraInvito(string $token)
    {
        $invito = $this->invitoValido($token);
        abort_if(! $invito, 404, 'Invito non valido o scaduto.');

        return view('accesso.invito', ['invito' => $invito, 'token' => $token]);
    }

    public function accettaInvito(Request $request, string $token)
    {
        $invito = $this->invitoValido($token);
        abort_if(! $invito, 404, 'Invito non valido o scaduto.');

        $dati = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $utente = DB::transaction(function () use ($invito, $dati) {
            $utente = User::updateOrCreate(['email' => $invito->email], [
                'name' => $dati['name'],
                'password' => $dati['password'],
                'ruolo' => $invito->ruolo,
                'attivo' => true,
            ]);
            DB::table('inviti')->where('id', $invito->id)->update(['usato_il' => now(), 'updated_at' => now()]);

            return $utente;
        });

        LogAccessi::registra($request, 'invito_accettato', $utente->id, $utente->email);
        Auth::login($utente);
        $request->session()->regenerate();

        return redirect()->route('panoramica');
    }

    private function invitoValido(string $token): ?object
    {
        return DB::table('inviti')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('usato_il')
            ->where('scade_il', '>', now())
            ->first();
    }
}
