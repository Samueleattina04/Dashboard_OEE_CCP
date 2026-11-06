<?php

namespace App\Services;

use App\Mail\LinkResetPassword;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Link di reimpostazione monouso (24 ore), creato dal SuperAdmin.
 */
class ReimpostazionePassword
{
    /** @return array{link:string, email_inviata:bool} */
    public function creaLink(User $utente): array
    {
        // Un solo link valido per volta
        DB::table('link_reset_password')->where('user_id', $utente->id)->whereNull('usato_il')
            ->update(['scade_il' => now(), 'updated_at' => now()]);

        $token = Str::random(48);
        $scadenza = now()->addHours(24);
        DB::table('link_reset_password')->insert([
            'user_id' => $utente->id, 'token_hash' => hash('sha256', $token), 'scade_il' => $scadenza,
            'creato_da' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('richieste_reset_password')->where('email', $utente->email)->whereNull('gestita_il')
            ->update(['gestita_il' => now(), 'gestita_da' => Auth::id(), 'updated_at' => now()]);

        $link = route('reset.mostra', $token);
        try {
            Mail::to($utente->email)->send(new LinkResetPassword($link,
                $scadenza->setTimezone(config('dashboard.timezone_locale'))->format('d/m/Y H:i')));
            $inviata = true;
        } catch (Throwable $e) {
            Log::warning('Invio email di reimpostazione non riuscito', ['email' => $utente->email, 'errore' => $e->getMessage()]);
            $inviata = false;
        }

        return ['link' => $link, 'email_inviata' => $inviata];
    }

    public function linkValido(string $token): ?object
    {
        return DB::table('link_reset_password')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('usato_il')
            ->where('scade_il', '>', now())
            ->first();
    }

    /** Imposta la nuova password, consuma il link e chiude le sessioni aperte dell'utente. */
    public function applica(object $link, string $password): User
    {
        return DB::transaction(function () use ($link, $password) {
            $utente = User::findOrFail($link->user_id);
            $utente->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            DB::table('link_reset_password')->where('id', $link->id)->update(['usato_il' => now(), 'updated_at' => now()]);
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $utente->id)->delete();
            }

            return $utente;
        });
    }
}
