<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvitoDashboard;
use App\Models\User;
use App\Services\ReimpostazionePassword;
use App\Support\Audit;
use App\Support\LogAccessi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/** Solo SuperAdmin: inviti via email, ruoli, disattivazione. */
class UtentiController extends Controller
{
    public function index()
    {
        return view('admin.utenti', [
            'utenti' => User::orderBy('name')->get(),
            'inviti' => DB::table('inviti')->whereNull('usato_il')->where('scade_il', '>', now())->orderByDesc('created_at')->get(),
            'richieste' => DB::table('richieste_reset_password')->whereNull('gestita_il')->orderByDesc('created_at')->get(),
        ]);
    }

    public function invita(Request $request)
    {
        $dati = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'nome' => ['nullable', 'string', 'max:120'],
            'ruolo' => ['required', Rule::in(array_keys(User::RUOLI))],
        ]);

        $token = Str::random(48);
        $scadenza = now()->addDays(7);
        DB::table('inviti')->insert([
            'email' => strtolower($dati['email']), 'nome' => $dati['nome'] ?? null, 'ruolo' => $dati['ruolo'],
            'token_hash' => hash('sha256', $token), 'scade_il' => $scadenza,
            'invitato_da' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $link = route('invito.mostra', $token);

        try {
            Mail::to($dati['email'])->send(new InvitoDashboard($link, User::RUOLI[$dati['ruolo']],
                $scadenza->setTimezone(config('dashboard.timezone_locale'))->format('d/m/Y H:i')));
            $messaggio = "Invito inviato a {$dati['email']}.";
        } catch (Throwable $e) {
            Log::warning('Invio email di invito non riuscito', ['email' => $dati['email'], 'errore' => $e->getMessage()]);
            $messaggio = 'Email non inviata (server di posta non configurato o non raggiungibile). Consegna il link manualmente.';
        }

        // Il link è mostrato una sola volta al SuperAdmin (utile finché la posta non è configurata)
        return back()->with('ok', $messaggio)->with('link_invito', $link);
    }

    public function revocaInvito(int $id)
    {
        DB::table('inviti')->where('id', $id)->whereNull('usato_il')->update(['scade_il' => now(), 'updated_at' => now()]);

        return back()->with('ok', 'Invito revocato.');
    }

    public function aggiorna(Request $request, User $utente)
    {
        $dati = $request->validate([
            'ruolo' => ['required', Rule::in(array_keys(User::RUOLI))],
            'attivo' => ['required', 'boolean'],
        ]);
        if ($utente->id === Auth::id() && ($dati['ruolo'] !== 'superadmin' || ! $dati['attivo'])) {
            return back()->withErrors(['ruolo' => 'Non puoi togliere a te stesso il ruolo SuperAdmin o disattivarti.']);
        }
        $prima = $utente->only(['ruolo', 'attivo']);
        $utente->update($dati);
        Audit::registra('modifica', $utente, $prima);

        return back()->with('ok', "Utente {$utente->email} aggiornato.");
    }

    /** Crea un link di reimpostazione (24 h, monouso), lo invia all'utente e lo mostra al SuperAdmin. */
    public function reimpostaPassword(Request $request, User $utente, ReimpostazionePassword $servizio)
    {
        $esito = $servizio->creaLink($utente);
        LogAccessi::registra($request, 'reset_creato', Auth::id(), $utente->email);

        return back()
            ->with('ok', $esito['email_inviata']
                ? "Link di reimpostazione inviato a {$utente->email}."
                : "Email non inviata a {$utente->email}: consegna il link manualmente.")
            ->with('link_reset', $esito['link']);
    }

    public function ignoraRichiesta(int $id)
    {
        DB::table('richieste_reset_password')->where('id', $id)
            ->update(['gestita_il' => now(), 'gestita_da' => Auth::id(), 'updated_at' => now()]);

        return back()->with('ok', 'Richiesta archiviata.');
    }
}
