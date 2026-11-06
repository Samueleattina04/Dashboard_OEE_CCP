<?php

namespace App\Providers;

use App\Mes\RepositoryMes;
use App\Mes\SorgenteCampioni;
use App\Mes\SorgenteNetProLive;
use App\Services\LetturaKpi;
use Illuminate\Support\Facades\URL;
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
        $this->app->bind(SorgenteNetProLive::class, RepositoryMes::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // I link generati (inviti, reimpostazione password) usano sempre APP_URL, anche se l'app è aperta da un altro indirizzo
        $url = (string) config('app.url');
        if ($url !== '' && ! app()->runningUnitTests()) {
            URL::forceRootUrl($url);
            if (str_starts_with($url, 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Indicatore "dati aggiornati alle hh:mm" / "MES non risponde" su tutte le pagine autenticate
        View::composer('layout', function ($view) {
            if (auth()->check()) {
                $view->with('statoSync', app(LetturaKpi::class)->statoSincronizzazione());
            }
        });
    }
}
