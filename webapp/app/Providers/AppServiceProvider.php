<?php

namespace App\Providers;

use App\Mes\RepositoryMes;
use App\Mes\SorgenteCampioni;
use App\Services\LetturaKpi;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SorgenteCampioni::class, RepositoryMes::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Indicatore "dati aggiornati alle hh:mm" / "MES non risponde" su tutte le pagine autenticate
        View::composer('layout', function ($view) {
            if (auth()->check()) {
                $view->with('statoSync', app(LetturaKpi::class)->statoSincronizzazione());
            }
        });
    }
}
