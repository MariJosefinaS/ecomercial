<?php

namespace App\Livewire\Concerns;

use App\Support\Permisos;

/** Helpers de permisos para componentes Livewire (chequeo en servidor). */
trait AutorizaPermisos
{
    public function puede(string $permiso): bool
    {
        return Permisos::puede(auth()->user()?->rol, $permiso);
    }

    /** Aborta con 403 si el usuario no tiene el permiso (seguridad real, no solo ocultar el botón). */
    public function autorizar(string $permiso): void
    {
        abort_unless($this->puede($permiso), 403, 'No tenés permiso para esta acción.');
    }
}
