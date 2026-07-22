<?php

namespace App\Providers;

use App\Support\Permisos;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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

        // Registrar el ÚLTIMO INGRESO en cualquier autenticación (form login, "recordarme", etc.).
        Event::listen(Login::class, function (Login $event) {
            $u = $event->user;
            if ($u && method_exists($u, 'forceFill')) {
                $u->forceFill(['ultimo_acceso' => now()])->saveQuietly();
            }
        });
    }
}
