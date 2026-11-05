<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Nessun utente predefinito: il SuperAdmin si crea con "php artisan utenti:crea-superadmin".
     */
    public function run(): void
    {
        $this->call(MacchineSeeder::class);
    }
}
