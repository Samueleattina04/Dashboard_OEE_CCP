<?php

namespace Tests\Feature;

use App\Models\Macchina;
use App\Models\User;
use Database\Seeders\MacchineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermessiTest extends TestCase
{
    use RefreshDatabase;

    private function utente(string $ruolo, bool $attivo = true): User
    {
        return User::factory()->create(['ruolo' => $ruolo, 'attivo' => $attivo]);
    }

    public function test_ospite_reindirizzato_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/admin/configurazione')->assertRedirect('/login');
    }

    /** Matrice ruoli → pagine (prompt "Ruoli"). */
    public function test_matrice_permessi_per_ruolo(): void
    {
        $this->seed(MacchineSeeder::class);
        $attese = [
            'qualita' => ['/' => 200, '/stato' => 200, '/export/giornaliero.csv' => 200, '/admin/configurazione' => 403, '/admin/utenti' => 403],
            'direzione' => ['/' => 200, '/stato' => 200, '/export/giornaliero.csv' => 200, '/admin/configurazione' => 403, '/admin/utenti' => 403],
            'admin' => ['/' => 200, '/admin/configurazione' => 200, '/admin/storico-modifiche' => 200, '/admin/utenti' => 403],
            'superadmin' => ['/' => 200, '/admin/configurazione' => 200, '/admin/utenti' => 200],
        ];
        foreach ($attese as $ruolo => $pagine) {
            $u = $this->utente($ruolo);
            foreach ($pagine as $url => $codice) {
                $this->actingAs($u)->get($url)->assertStatus($codice);
            }
        }
    }

    public function test_utente_disattivato_non_accede(): void
    {
        $u = $this->utente('direzione', false);
        $this->actingAs($u)->get('/')->assertRedirect('/login');
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_export_registrato_nel_log(): void
    {
        $this->seed(MacchineSeeder::class);
        $this->actingAs($this->utente('qualita'))->get('/export/giornaliero.csv?periodo=settimana')->assertOk();
        $this->assertSame(1, DB::table('log_export')->count());
    }

    public function test_invito_e_attivazione(): void
    {
        $sa = $this->utente('superadmin');
        $this->actingAs($sa)->post('/admin/utenti/invita', ['email' => 'nuovo@ase.local', 'ruolo' => 'qualita'])
            ->assertSessionHas('link_invito');
        $link = session('link_invito');
        $this->post('/logout');

        $percorso = parse_url($link, PHP_URL_PATH);
        $this->get($percorso)->assertOk();
        $this->post($percorso, ['name' => 'Nuovo', 'password' => 'debole', 'password_confirmation' => 'debole'])
            ->assertSessionHasErrors('password');
        $this->post($percorso, ['name' => 'Nuovo', 'password' => 'Password-Sicura-1', 'password_confirmation' => 'Password-Sicura-1'])
            ->assertRedirect(route('panoramica'));

        $this->assertSame('qualita', User::where('email', 'nuovo@ase.local')->value('ruolo'));
        $this->post('/logout');
        $this->get($percorso)->assertNotFound(); // monouso
    }

    public function test_modifica_regola_storicizzata(): void
    {
        $this->seed(MacchineSeeder::class);
        $forno = Macchina::where('codice', 'FORNOKING500')->first();
        $this->actingAs($this->utente('admin'))->post("/admin/macchine/{$forno->id}/regola", [
            'soglia_marcia' => 110, 'soglia_transitorio' => 70, 'g_max_minuti' => 20, 'valido_dal' => now()->format('Y-m-d'),
        ])->assertSessionHas('ok');

        $this->assertSame(2, $forno->regole()->count());
        $this->assertSame(1, $forno->regole()->whereNull('valido_al')->count());
        $this->assertSame('110', $forno->regole()->whereNull('valido_al')->first()->parametri['soglia_marcia']);
        $this->assertSame(2, DB::table('audit_modifiche')->where('tabella', 'regole_stato')->count());
    }
}
