<?php

namespace App\Http\Middleware;

use App\Support\Permisos;
use Closure;
use Illuminate\Http\Request;

/**
 * Enforcement a nivel ruta: si la ruta tiene un permiso asociado
 * (App\Support\Permisos::rutaPermiso) y el rol del usuario no lo tiene,
 * lo redirige a su pantalla de inicio (o 403 si no puede ver nada).
 * Evita que se acceda por URL a secciones ocultas en el sidebar.
 */
class VerificaPermiso
{
    public function handle(Request $request, Closure $next)
    {
        $ruta = $request->route()?->getName();
        $perm = Permisos::rutaPermiso()[$ruta] ?? null;

        if ($perm) {
            $rol = $request->user()?->rol;
            if (! Permisos::puede($rol, $perm)) {
                $inicio = Permisos::inicio($rol);
                if ($inicio !== 'login' && $inicio !== $ruta) {
                    return redirect()->route($inicio);
                }
                abort(403, 'No tenés permiso para acceder a esta sección.');
            }
        }

        return $next($request);
    }
}
