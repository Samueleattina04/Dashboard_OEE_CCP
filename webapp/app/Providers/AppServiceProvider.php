<?php

namespace App\Providers;

use App\Mes\RepositoryMes;
use App\Mes\SorgenteCampioni;
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
        //
    }
}
