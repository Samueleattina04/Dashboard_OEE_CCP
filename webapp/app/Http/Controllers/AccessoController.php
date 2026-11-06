<?php

namespace App\Http\Controllers;

use App\Mail\AvvisoRichiestaReset;
use App\Models\User;
use App\Services\ReimpostazionePassword;
use App\Support\LogAccessi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Throwable;

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

    // ---- Password dimenticata -------------------------------------------------

    public function mostraPasswordDimenticata()
    {
        return view('accesso.password_dimenticata');
    }

    /**
     * Registra la richiesta e avvisa i SuperAdmin (in app e, se possibile, via email).
     * La risposta è sempre la stessa, che l'email esista o no (nessuna enumerazione degli utenti).
     */
    public function richiediReset(Request $request)
    {
        $email = strtolower($request->validate(['email' => ['required', 'email', 'max:255']])['email']);
        $utente = User::where('email', $email)->where('attivo', true)->first();
        LogAccessi::registra($request, 'richiesta_reset', $utente?->id, $email);

        $giaPresente = DB::table('richieste_reset_password')->where('email', $email)->whereNull('gestita_il')
            ->where('created_at', '>', now()->subHour())->exists();
        if ($utente && ! $giaPresente) {
            DB::table('richieste_reset_password')->insert([
                'email' => $email, 'user_id' => $utente->id, 'ip' => $request->ip(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (User::where('ruolo', 'superadmin')->where('attivo', true)->pluck('email') as $sa) {
                try {
                    Mail::to($sa)->send(new AvvisoRichiestaReset($email, route('admin.utenti')));
                } catch (Throwable $e) {
                    Log::warning('Avviso di richiesta reset non inviato', ['a' => $sa, 'errore' => $e->getMessage()]);
                }
            }
        }

        return back()->with('ok', 'Richiesta inviata. L\'amministratore ti manderà un link per reimpostare la password.');
    }

    public function mostraReset(string $token, ReimpostazionePassword $servizio)
    {
        $link = $servizio->linkValido($token);
        abort_if(! $link, 404, 'Link non valido o scaduto.');

        return view('accesso.reset', ['token' => $token, 'email' => User::find($link->user_id)?->email]);
    }

    public function applicaReset(Request $request, string $token, ReimpostazionePassword $servizio)
    {
        $link = $servizio->linkValido($token);
        abort_if(! $link, 404, 'Link non valido o scaduto.');
        $dati = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $utente = $servizio->applica($link, $dati['password']);
        LogAccessi::registra($request, 'password_reimpostata', $utente->id, $utente->email);

        return redirect()->route('login')->with('ok', 'Password aggiornata. Ora puoi accedere.');
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
