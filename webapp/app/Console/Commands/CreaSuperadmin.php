<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Primo accesso: crea (o promuove) l'utente SuperAdmin. La password è chiesta a video e non compare nei log. */
class CreaSuperadmin extends Command
{
    protected $signature = 'utenti:crea-superadmin {email} {--nome=Administrator}';

    protected $description = 'Crea l\'utente SuperAdmin (password richiesta in modo interattivo)';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $password = $this->secret('Password (min 12 caratteri, maiuscole, minuscole, numeri e simboli)');
        $conferma = $this->secret('Ripeti la password');

        $v = Validator::make(['email' => $email, 'password' => $password, 'password_confirmation' => $conferma], [
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        User::updateOrCreate(['email' => $email], [
            'name' => $this->option('nome'), 'password' => $password, 'ruolo' => 'superadmin', 'attivo' => true,
        ]);
        $this->info("SuperAdmin {$email} pronto.");

        return self::SUCCESS;
    }
}
