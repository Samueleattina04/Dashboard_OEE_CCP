<?php

namespace Tests\Feature;

use App\Mail\AvvisoRichiestaReset;
use App\Mail\LinkResetPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_dimenticata_avvisa_il_superadmin_senza_rivelare_se_l_email_esiste(): void
    {
        Mail::fake();
        $sa = User::factory()->create(['ruolo' => 'superadmin']);
        $u = User::factory()->create(['ruolo' => 'qualita']);

        // Email esistente e inesistente: stessa risposta
        $this->post('/password-dimenticata', ['email' => $u->email])->assertSessionHas('ok');
        $this->post('/password-dimenticata', ['email' => 'nessuno@ase.local'])->assertSessionHas('ok');

        $this->assertSame(1, DB::table('richieste_reset_password')->count());
        Mail::assertSent(AvvisoRichiestaReset::class, fn ($m) => $m->hasTo($sa->email) && $m->email === $u->email);

        // La richiesta compare nella pagina Utenti del SuperAdmin
        $this->actingAs($sa)->get('/admin/utenti')->assertSee($u->email)->assertSee('Richieste di reimpostazione password');
    }

    public function test_reimposta_password_dal_superadmin_link_monouso(): void
    {
        Mail::fake();
        $sa = User::factory()->create(['ruolo' => 'superadmin']);
        $u = User::factory()->create(['ruolo' => 'direzione']);

        $this->actingAs($sa)->post("/admin/utenti/{$u->id}/reimposta-password")->assertSessionHas('link_reset');
        $link = session('link_reset');
        Mail::assertSent(LinkResetPassword::class, fn ($m) => $m->hasTo($u->email) && $m->link === $link);
        $this->post('/logout');

        $percorso = parse_url($link, PHP_URL_PATH);
        $this->get($percorso)->assertOk()->assertSee($u->email);
        $this->post($percorso, ['password' => 'Nuova-Password-1', 'password_confirmation' => 'Nuova-Password-1'])
            ->assertRedirect('/login');
        $this->assertTrue(Hash::check('Nuova-Password-1', $u->fresh()->password));

        $this->get($percorso)->assertNotFound(); // monouso
        $this->post('/login', ['email' => $u->email, 'password' => 'Nuova-Password-1'])->assertRedirect(route('panoramica'));
    }

    public function test_solo_il_superadmin_puo_reimpostare(): void
    {
        $admin = User::factory()->create(['ruolo' => 'admin']);
        $u = User::factory()->create();
        $this->actingAs($admin)->post("/admin/utenti/{$u->id}/reimposta-password")->assertForbidden();
    }
}
