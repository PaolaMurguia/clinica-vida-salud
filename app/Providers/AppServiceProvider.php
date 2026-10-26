<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // RNF05: al menos 8 caracteres con mayúsculas, minúsculas, números y un símbolo
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        // Paginación con estilos de Bootstrap 5
        Paginator::useBootstrapFive();

        // Producción (Render): todas las URL generadas usan https (RNF15)
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
