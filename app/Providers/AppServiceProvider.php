<?php

namespace App\Providers;

use App\Support\Permisos;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // @puede('permiso') ... @endpuede  — muestra el bloque solo si el rol del usuario tiene el permiso.
        Blade::if('puede', function (string $permiso) {
            return Permisos::puede(auth()->user()?->rol, $permiso);
        });
    }
}
