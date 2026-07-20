<?php

namespace App\Livewire\Usuarios;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Local;
use App\Models\Rol;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Usuarios — E.Comercial')]
class Index extends Component
{
    use WithFileUploads, AutorizaPermisos;

    public string $buscar = '';
    public string $rol = 'todos';
    public string $estado = 'todos';
    public ?string $mensaje = null;

    // ===== Modal alta/edición =====
    public bool $modal = false;
    public ?int $editando = null;       // id del usuario en edición (null = alta)
    public string $fNombre = '';
    public string $fEmail = '';
    public string $fTelefono = '';
    public string $fRol = 'vendedor';
    public string $fLocal = 'Local A';  // nombre del local | 'Todos'
    public string $fPassword = '';
    public $fAvatar = null;

    public function mount(): void
    {
        $this->autorizar('ver_usuarios'); // defensa en profundidad (además del middleware de ruta)
    }

    private function localId(string $nombre): ?int
    {
        return $nombre === 'Todos' ? null : Local::where('nombre', $nombre)->value('id');
    }

    public function nuevoUsuario(): void
    {
        $this->autorizar('gestionar_usuarios');
        $this->reset(['editando', 'fNombre', 'fEmail', 'fTelefono', 'fPassword', 'fAvatar']);
        $this->fRol = 'vendedor';
        $this->fLocal = Local::orderBy('id')->value('nombre') ?? 'Local A';
        $this->resetValidation();
        $this->modal = true;
    }

    public function editarUsuario(int $id): void
    {
        $this->autorizar('gestionar_usuarios');
        $u = User::find($id);
        if (! $u) {
            return;
        }
        $this->editando = $u->id;
        $this->fNombre = $u->name;
        $this->fEmail = $u->email;
        $this->fTelefono = $u->telefono ?? '';
        $this->fRol = $u->rol;
        $this->fLocal = $u->local?->nombre ?? 'Todos';
        $this->fPassword = '';
        $this->fAvatar = null;
        $this->resetValidation();
        $this->modal = true;
    }

    public function guardarUsuario(): void
    {
        $this->autorizar('gestionar_usuarios');
        $this->validate([
            'fNombre' => 'required|min:2',
            'fEmail' => 'required|email',
            'fRol' => ['required', Rule::in(array_keys(Permisos::rolesNombres()))],
            'fPassword' => $this->editando ? 'nullable|min:6' : 'required|min:6',
            'fAvatar' => 'nullable|image|max:2048',
        ], attributes: [
            'fNombre' => 'nombre', 'fEmail' => 'email', 'fRol' => 'rol', 'fPassword' => 'contraseña', 'fAvatar' => 'avatar',
        ]);

        $existe = User::where('email', $this->fEmail)
            ->when($this->editando, fn ($q) => $q->where('id', '!=', $this->editando))
            ->exists();
        if ($existe) {
            $this->addError('fEmail', 'Ya existe un usuario con ese email.');
            return;
        }

        $attrs = [
            'name' => $this->fNombre,
            'email' => $this->fEmail,
            'telefono' => $this->fTelefono ?: null,
            'rol' => $this->fRol,
            'local_id' => $this->localId($this->fLocal),
        ];
        if ($this->fPassword) {
            $attrs['password'] = Hash::make($this->fPassword);
        }
        if ($this->fAvatar) {
            $attrs['avatar'] = $this->fAvatar->store('avatars', 'public');
        }

        if ($this->editando) {
            User::where('id', $this->editando)->update($attrs);
            $this->mensaje = "Usuario {$this->fNombre} actualizado.";
        } else {
            User::create($attrs + ['activo' => true]);
            $this->mensaje = "Usuario {$this->fNombre} creado.";
        }

        $this->modal = false;
        $this->editando = null;
    }

    public function cerrarModal(): void
    {
        $this->modal = false;
        $this->resetValidation();
    }

    public function resetearPassword(int $id): void
    {
        $this->autorizar('reset_password');
        $u = User::find($id);
        if (! $u) {
            return;
        }
        $temp = Str::password(10, symbols: false);
        $u->update(['password' => Hash::make($temp)]);
        $this->mensaje = "Contraseña temporal para {$u->email}: «{$temp}» (el usuario debería cambiarla al ingresar).";
    }

    public function toggleActivo(int $id): void
    {
        $this->autorizar('gestionar_usuarios');
        $u = User::find($id);
        if (! $u) {
            return;
        }
        $u->update(['activo' => ! $u->activo]);
        $this->mensaje = "Usuario {$u->name} " . ($u->activo ? 'activado (desbloqueado).' : 'desactivado (bloqueado).');
    }

    /**
     * Eliminar un usuario (renuncia/despido). Si tiene historial (ventas, etc.) NO se puede
     * borrar sin romper la integridad → cae automáticamente a BAJA (activo=false). Las zonas
     * donde era cobrador quedan sin cobrador (FK nullOnDelete) — reasignalas en Configuración → Zonas.
     */
    public function eliminarUsuario(int $id): void
    {
        $this->autorizar('gestionar_usuarios');
        $u = User::find($id);
        if (! $u) {
            return;
        }
        if ($u->id === auth()->id()) {
            $this->mensaje = 'No podés eliminar tu propio usuario.';
            return;
        }
        if ($u->rol === 'super_admin' && User::where('rol', 'super_admin')->count() <= 1) {
            $this->mensaje = 'No se puede eliminar el único super administrador.';
            return;
        }

        $nombre = $u->name;
        $zonas = $u->zonasComoCobrador()->count();
        try {
            $u->delete();
            $this->mensaje = "Usuario {$nombre} eliminado."
                . ($zonas ? " {$zonas} zona(s) quedaron sin cobrador — reasignalas en Configuración → Zonas." : '');
        } catch (\Illuminate\Database\QueryException $e) {
            // Tiene historial referenciado (ventas/cobros): no se borra, se da de baja.
            $u->update(['activo' => false]);
            $this->mensaje = "«{$nombre}» tiene historial en el sistema, así que se dio de BAJA (bloqueado) en lugar de eliminarlo.";
        }
    }

    public function limpiar(): void
    {
        $this->reset(['buscar', 'rol', 'estado', 'mensaje']);
        $this->rol = 'todos';
        $this->estado = 'todos';
    }

    public function render()
    {
        Carbon::setLocale('es');

        $variante = Rol::pluck('variante', 'clave')->toArray();

        $filas = User::with('local:id,nombre')
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->buscar}%")
                ->orWhere('email', 'like', "%{$this->buscar}%")))
            ->when($this->rol !== 'todos', fn ($q) => $q->where('rol', $this->rol))
            ->when($this->estado !== 'todos', fn ($q) => $q->where('activo', $this->estado === 'activo'))
            ->orderBy('name')
            ->get()
            ->map(function (User $u) use ($variante) {
                $ini = collect(explode(' ', $u->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: 'NN';

                return [
                    'id' => $u->id,
                    'email' => $u->email,
                    'nom' => $u->name,
                    'ini' => $ini,
                    'vv' => $variante[$u->rol] ?? 'gray',
                    'rol' => $u->rol,
                    'local' => $u->rol === 'super_admin' ? 'Todos' : ($u->local?->nombre ?? '—'),
                    'tel' => $u->telefono ?? '',
                    'activo' => (bool) $u->activo,
                    'acceso' => $u->ultimo_acceso?->diffForHumans() ?? 'Nunca',
                ];
            });

        return view('livewire.usuarios.index', [
            'filas' => $filas,
            'roles' => Permisos::rolesNombres(),
            'locales' => Local::where('activo', true)->orderBy('id')->get(['id', 'nombre']),
            'stats' => [
                'total' => User::count(),
                'activos' => User::where('activo', true)->count(),
                'admins' => User::whereIn('rol', ['super_admin', 'admin_local'])->count(),
                'vendedores' => User::where('rol', 'vendedor')->count(),
            ],
        ]);
    }
}
