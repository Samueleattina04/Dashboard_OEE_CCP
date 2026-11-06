<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'ruolo', 'attivo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Ruoli e permessi (prompt "Ruoli" + SuperAdmin). */
    public const RUOLI = [
        'superadmin' => 'SuperAdmin',
        'admin' => 'Admin',
        'direzione' => 'Direzione',
        'qualita' => 'Qualità',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'attivo' => 'boolean',
        ];
    }

    public function haRuolo(string ...$ruoli): bool
    {
        return in_array($this->ruolo, $ruoli, true);
    }

    /** Configurazione (turni, capacità, soglie, regole, target). */
    public function puoConfigurare(): bool
    {
        return $this->haRuolo('superadmin', 'admin');
    }

    /** Gestione utenti e inviti. */
    public function puoGestireUtenti(): bool
    {
        return $this->haRuolo('superadmin');
    }

    public function etichettaRuolo(): string
    {
        return self::RUOLI[$this->ruolo] ?? $this->ruolo;
    }
}
