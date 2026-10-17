<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CryptoService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the crypto service
        $this->app->singleton(CryptoService::class, function ($app) {
            return new CryptoService();
        });

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}