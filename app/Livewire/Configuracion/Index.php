<?php

namespace App\Livewire\Configuracion;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Categoria;
use App\Models\ConceptoPrecio;
use App\Models\Local;
use App\Models\PermisoRol;
use App\Models\PlanCredito;
use App\Models\Rol;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Configuración — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;

    #[Url(as: 'sub')]
    public string $sub = 'roles';   // roles | permisos | parametros | sucursales | conceptos | creditos | categorias

    public ?string $mensaje = null;

    /** Sucursales / locales (DB): [id, nombre, direccion, telefono, activo]. */
    public array $sucursales = [];
    public string $nuevaSucursalNombre = '';
    public string $nuevaSucursalDir = '';

    /** Roles (clave => nombre). Los "sistema" no se pueden borrar. */
    public array $roles = [];
    public array $rolesSistema = [];
    public array $usuariosPorRol = [];
    /** Color del badge por rol (clave => variante: gray|brand|blue|green|red|purple|amber). */
    public array $colorRol = [];

    /** Paleta de colores disponible para los roles custom. */
    public const PALETA = ['gray', 'brand', 'blue', 'green', 'red', 'purple', 'amber'];

    /** Matriz de permisos: matriz[rol][permiso] = bool. */
    public array $matriz = [];

    public string $nuevoRol = '';
    public int $stockMinimo = 5;

    /** Conceptos de precio (Flete, Remarcar, …) configurables, con su ámbito (costo|venta). */
    public array $conceptos = [];
    public string $nuevoConceptoNombre = '';
    public string $nuevoConceptoPct = '';
    public string $nuevoConceptoAmbito = 'costo';

    /** Planes de crédito (productos de crédito) configurables. */
    public array $planesCredito = [];
    public string $nuevoPlanNombre = '';
    public string $nuevoPlanModalidad = 'diario';
    public string $nuevoPlanAnticipo = '0';
    public string $nuevoPlanTasa = '0';
    public string $nuevoPlanPlazo = '0';
    public string $nuevoPlanIncobrable = '0';

    /** Categorías de productos (DB): [id, nombre, icono, productos]. */
    public array $categorias = [];
    public string $nuevaCategoriaNombre = '';
    public string $nuevaCategoriaIcono = '';

    /** Zonas de cobranza (DB): [id, nombre, local_id, cobrador_id, activo, cuotas_abiertas]. */
    public array $zonas = [];
    /** Usuarios elegibles como cobrador (activos): id => "Nombre (rol)". */
    public array $cobradores = [];
    public string $nuevaZonaNombre = '';
    public ?int $nuevaZonaLocal = null;
    public ?int $nuevaZonaCobrador = null;

    /** Comisión de cobradores (solo super_admin). % general + override por cobrador. */
    public string $comisionGeneral = '';
    /** [ [id, name, rol, comision_pct(''|número)], ... ] solo cobradores (con zona). */
    public array $comisionesCobradores = [];

    /** Definición de permisos agrupados (fuente única: App\Support\Permisos). */
    public function grupos(): array
    {
        return \App\Support\Permisos::grupos();
    }

    public function mount(): void
    {
        $this->autorizar('ver_config'); // defensa en profundidad (además del middleware de ruta)
        $this->cargarRoles();

        $this->cargarConceptos();
        $this->cargarPlanesCredito();
        $this->cargarSucursales();
        $this->cargarCategorias();
        $this->cargarZonas();
        $this->cargarComisiones();
    }

    private function esSuper(): bool
    {
        return auth()->user()?->esRol('super_admin') ?? false;
    }

    // ===== Comisión de cobradores (solo super_admin) =====
    private function cargarComisiones(): void
    {
        $this->comisionGeneral = (string) \App\Support\Comisiones::general();

        // Solo cobradores = usuarios con al menos una zona asignada.
        $this->comisionesCobradores = User::whereHas('zonasComoCobrador')->orderBy('name')->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'rol' => Permisos::rolesNombres()[$u->rol] ?? $u->rol,
                'comision_pct' => $u->comision_pct !== null ? (string) (float) $u->comision_pct : '',
            ])->toArray();
    }

    public function guardarComisiones(): void
    {
        abort_unless($this->esSuper(), 403, 'Solo el super administrador puede configurar comisiones.');

        $this->validate([
            'comisionGeneral' => 'nullable|numeric|min:0|max:100',
            'comisionesCobradores.*.comision_pct' => 'nullable|numeric|min:0|max:100',
        ], attributes: ['comisionGeneral' => '% general']);

        \App\Support\Comisiones::general(); // no-op para claridad
        \App\Models\Parametro::set(\App\Support\Comisiones::CLAVE_GENERAL, (float) ($this->comisionGeneral ?: 0));

        foreach ($this->comisionesCobradores as $c) {
            $pct = trim((string) ($c['comision_pct'] ?? ''));
            User::where('id', $c['id'])->update([
                'comision_pct' => $pct === '' ? null : (float) $pct,   // vacío = usa el general
            ]);
        }

        $this->cargarComisiones();
        $this->mensaje = 'Comisiones guardadas. Los cobradores sin % propio usan el general.';
    }

    // ===== Zonas de cobranza (cobrador por zona) =====
    private function cargarZonas(): void
    {
        $this->cobradores = User::where('activo', true)->orderBy('name')->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->name . ' (' . (Permisos::rolesNombres()[$u->rol] ?? $u->rol) . ')'])
            ->toArray();

        $this->zonas = \App\Models\Zona::with('local')
            ->withCount(['cuotas as cuotas_abiertas' => fn ($q) => $q->where('estado', 'pendiente')])
            ->orderBy('nombre')->get()
            ->map(fn (\App\Models\Zona $z) => [
                'id' => $z->id,
                'nombre' => $z->nombre,
                'local_id' => $z->local_id,
                'cobrador_id' => $z->cobrador_id,
                'activo' => (bool) $z->activo,
                'cuotas_abiertas' => $z->cuotas_abiertas,
            ])->toArray();

        if ($this->nuevaZonaLocal === null) {
            $this->nuevaZonaLocal = Local::where('activo', true)->orderBy('id')->value('id');
        }
    }

    public function agregarZona(): void
    {
        $this->autorizar('gestionar_zonas');
        $nombre = trim($this->nuevaZonaNombre);
        if ($nombre === '') {
            return;
        }
        if (\App\Models\Zona::where('nombre', $nombre)->exists()) {
            $this->mensaje = "Ya existe una zona «{$nombre}».";
            return;
        }
        \App\Models\Zona::create([
            'nombre' => $nombre,
            'local_id' => $this->nuevaZonaLocal,
            'cobrador_id' => $this->nuevaZonaCobrador,
            'activo' => true,
        ]);
        $this->reset(['nuevaZonaNombre', 'nuevaZonaCobrador']);
        $this->cargarZonas();
        $this->mensaje = "Zona «{$nombre}» creada.";
    }

    /**
     * Guarda nombre/sucursal/cobrador/activo de cada zona. Reasignar el cobrador
     * (cobrador_id) mueve automáticamente las cuotas abiertas de la zona al nuevo
     * cobrador — la cuota resuelve su cobrador por `zona_id` (requisito: renuncia).
     */
    public function guardarZonas(): void
    {
        $this->autorizar('gestionar_zonas');
        foreach ($this->zonas as $z) {
            $nombre = trim($z['nombre']);
            if ($nombre === '') {
                continue;
            }
            \App\Models\Zona::where('id', $z['id'])->update([
                'nombre' => $nombre,
                'local_id' => $z['local_id'] ?: null,
                'cobrador_id' => $z['cobrador_id'] ?: null,
                'activo' => (bool) ($z['activo'] ?? true),
            ]);
        }
        $this->cargarZonas();
        $this->mensaje = 'Zonas guardadas. (Reasignar el cobrador mueve sus cuotas abiertas.)';
    }

    public function eliminarZona(int $id): void
    {
        $this->autorizar('gestionar_zonas');
        $z = \App\Models\Zona::find($id);
        if (! $z) {
            return;
        }
        // FK nullOnDelete: ventas/cuotas/clientes de esta zona quedan sin zona (no se rompen).
        $z->delete();
        $this->cargarZonas();
        $this->mensaje = "Zona «{$z->nombre}» eliminada.";
    }

    /** Roles + matriz + conteo real de usuarios por rol (todo desde DB). */
    private function cargarRoles(): void
    {
        $this->roles = Permisos::rolesNombres();
        $this->rolesSistema = Permisos::rolesSistema();
        $this->usuariosPorRol = User::selectRaw('rol, count(*) as c')->groupBy('rol')->pluck('c', 'rol')->toArray();
        $this->colorRol = Rol::pluck('variante', 'clave')->toArray();
        $this->matriz = Permisos::matriz();
    }

    // ===== Categorías de productos =====
    private function cargarCategorias(): void
    {
        $this->categorias = Categoria::withCount('productos')->orderBy('nombre')->get()
            ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'icono' => $c->icono ?? '', 'productos' => $c->productos_count])
            ->toArray();
    }

    public function agregarCategoria(): void
    {
        $this->autorizar('gestionar_stock');
        $nombre = trim($this->nuevaCategoriaNombre);
        if ($nombre === '') {
            return;
        }
        Categoria::create(['nombre' => $nombre, 'icono' => trim($this->nuevaCategoriaIcono) ?: 'category']);
        $this->nuevaCategoriaNombre = '';
        $this->nuevaCategoriaIcono = '';
        $this->cargarCategorias();
        $this->mensaje = "Categoría «{$nombre}» agregada.";
    }

    public function guardarCategorias(): void
    {
        $this->autorizar('gestionar_stock');
        foreach ($this->categorias as $c) {
            $nombre = trim($c['nombre']);
            if ($nombre === '') {
                continue;
            }
            Categoria::where('id', $c['id'])->update(['nombre' => $nombre, 'icono' => trim($c['icono']) ?: 'category']);
        }
        $this->cargarCategorias();
        $this->mensaje = 'Categorías guardadas.';
    }

    public function eliminarCategoria(int $id): void
    {
        $this->autorizar('gestionar_stock');
        $cat = Categoria::withCount('productos')->find($id);
        if (! $cat) {
            return;
        }
        $cat->delete(); // los productos quedan sin categoría (FK nullOnDelete)
        $this->cargarCategorias();
        $this->mensaje = $cat->productos_count
            ? "Categoría «{$cat->nombre}» eliminada. {$cat->productos_count} producto(s) quedaron sin categoría."
            : "Categoría «{$cat->nombre}» eliminada.";
    }

    // ===== Sucursales / locales =====
    private function cargarSucursales(): void
    {
        $this->sucursales = Local::orderBy('id')->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'nombre' => $l->nombre,
                'direccion' => $l->direccion ?? '',
                'telefono' => $l->telefono ?? '',
                'activo' => (bool) $l->activo,
            ])
            ->toArray();
    }

    public function agregarSucursal(): void
    {
        $this->autorizar('gestionar_locales');
        $nombre = trim($this->nuevaSucursalNombre);
        if ($nombre === '') {
            return;
        }
        Local::create(['nombre' => $nombre, 'direccion' => trim($this->nuevaSucursalDir) ?: null, 'activo' => true]);
        $this->nuevaSucursalNombre = '';
        $this->nuevaSucursalDir = '';
        $this->cargarSucursales();
        $this->mensaje = "Sucursal «{$nombre}» agregada.";
    }

    public function guardarSucursales(): void
    {
        $this->autorizar('gestionar_locales');
        foreach ($this->sucursales as $s) {
            $nombre = trim($s['nombre']);
            if ($nombre === '') {
                continue;
            }
            Local::where('id', $s['id'])->update([
                'nombre' => $nombre,
                'direccion' => trim($s['direccion']) ?: null,
                'telefono' => trim($s['telefono']) ?: null,
                'activo' => (bool) $s['activo'],
            ]);
        }
        $this->cargarSucursales();
        $this->mensaje = 'Sucursales guardadas.';
    }

    public function toggleSucursal(int $id): void
    {
        $this->autorizar('gestionar_locales');
        $l = Local::find($id);
        if ($l) {
            $l->update(['activo' => ! $l->activo]);
            $this->cargarSucursales();
            $this->mensaje = "Sucursal «{$l->nombre}» " . ($l->activo ? 'activada' : 'desactivada') . '.';
        }
    }

    private function cargarConceptos(): void
    {
        // Conceptos activos (costo + venta). El ámbito define sobre qué recarga cada uno.
        $this->conceptos = ConceptoPrecio::where('activo', true)->orderBy('orden')->get()
            ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre, 'ambito' => $c->ambito ?? 'costo', 'porcentaje' => (float) $c->porcentaje])
            ->toArray();
    }

    public function agregarConcepto(): void
    {
        $nombre = trim($this->nuevoConceptoNombre);
        if ($nombre === '') {
            return;
        }
        ConceptoPrecio::create([
            'nombre' => $nombre,
            'ambito' => $this->nuevoConceptoAmbito === 'venta' ? 'venta' : 'costo',
            'porcentaje' => (float) ($this->nuevoConceptoPct ?: 0),
            'orden' => (ConceptoPrecio::max('orden') ?? 0) + 1,
        ]);
        $this->nuevoConceptoNombre = '';
        $this->nuevoConceptoPct = '';
        $this->nuevoConceptoAmbito = 'costo';
        $this->cargarConceptos();
        $this->mensaje = "Concepto \"{$nombre}\" agregado.";
    }

    public function guardarConceptos(): void
    {
        foreach ($this->conceptos as $c) {
            ConceptoPrecio::where('id', $c['id'])->update([
                'porcentaje' => (float) $c['porcentaje'],
                'ambito' => ($c['ambito'] ?? 'costo') === 'venta' ? 'venta' : 'costo',
            ]);
        }
        $this->mensaje = 'Conceptos de precio guardados.';
    }

    public function eliminarConcepto(int $id): void
    {
        ConceptoPrecio::where('id', $id)->delete();
        $this->cargarConceptos();
        $this->mensaje = 'Concepto eliminado.';
    }

    // ===== Planes de crédito (productos de crédito) =====
    private function cargarPlanesCredito(): void
    {
        $this->planesCredito = PlanCredito::orderBy('orden')->get()
            ->map(fn (PlanCredito $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'modalidad' => $p->modalidad,
                'anticipo_pct' => (float) $p->anticipo_pct,
                'tasa_periodo' => (float) $p->tasa_periodo,
                'plazo_default' => (int) $p->plazo_default,
                'cuotas_incobrable' => (int) $p->cuotas_incobrable,
                'unidad' => $p->unidad,
                'activo' => (bool) $p->activo,
            ])->toArray();
    }

    private function unidadDeModalidad(string $m): string
    {
        return match ($m) {
            'mensual' => 'meses',
            'semanal' => 'semanas',
            'contado' => '',
            default => 'días',
        };
    }

    public function agregarPlanCredito(): void
    {
        $nombre = trim($this->nuevoPlanNombre);
        if ($nombre === '') {
            return;
        }
        $mod = in_array($this->nuevoPlanModalidad, ['contado', 'diario', 'semanal', 'mensual'], true) ? $this->nuevoPlanModalidad : 'diario';
        $codigo = Str::slug($nombre, '_');
        if ($codigo === '' || PlanCredito::where('codigo', $codigo)->exists()) {
            $codigo = 'plan_' . ((PlanCredito::max('id') ?? 0) + 1);
        }
        PlanCredito::create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'modalidad' => $mod,
            'anticipo_pct' => (float) ($this->nuevoPlanAnticipo ?: 0),
            'tasa_periodo' => (float) ($this->nuevoPlanTasa ?: 0),
            'plazo_default' => (int) ($this->nuevoPlanPlazo ?: 0),
            'cuotas_incobrable' => max(0, (int) ($this->nuevoPlanIncobrable ?: 0)),
            'unidad' => $this->unidadDeModalidad($mod),
            'orden' => (PlanCredito::max('orden') ?? 0) + 1,
        ]);
        $this->reset(['nuevoPlanNombre', 'nuevoPlanAnticipo', 'nuevoPlanTasa', 'nuevoPlanPlazo', 'nuevoPlanIncobrable']);
        $this->nuevoPlanModalidad = 'diario';
        $this->cargarPlanesCredito();
        $this->mensaje = "Plan de crédito «{$nombre}» agregado.";
    }

    public function guardarPlanesCredito(): void
    {
        foreach ($this->planesCredito as $p) {
            $mod = in_array($p['modalidad'] ?? 'diario', ['contado', 'diario', 'semanal', 'mensual'], true) ? $p['modalidad'] : 'diario';
            PlanCredito::where('id', $p['id'])->update([
                'nombre' => trim($p['nombre']) ?: 'Plan',
                'modalidad' => $mod,
                'anticipo_pct' => (float) ($p['anticipo_pct'] ?: 0),
                'tasa_periodo' => (float) ($p['tasa_periodo'] ?: 0),
                'plazo_default' => (int) ($p['plazo_default'] ?: 0),
                'cuotas_incobrable' => max(0, (int) ($p['cuotas_incobrable'] ?? 0)),
                'unidad' => $this->unidadDeModalidad($mod),
                'activo' => (bool) ($p['activo'] ?? true),
            ]);
        }
        $this->cargarPlanesCredito();
        $this->mensaje = 'Planes de crédito guardados.';
    }

    public function eliminarPlanCredito(int $id): void
    {
        $p = PlanCredito::find($id);
        if ($p && $p->codigo === 'contado') {
            $this->mensaje = 'El plan Contado no se puede eliminar.';
            return;
        }
        PlanCredito::where('id', $id)->delete();
        $this->cargarPlanesCredito();
        $this->mensaje = 'Plan de crédito eliminado.';
    }

    public function agregarRol(): void
    {
        $this->autorizar('gestionar_roles');
        $nombre = trim($this->nuevoRol);
        if ($nombre === '') {
            return;
        }
        $clave = Str::slug($nombre, '_');
        if ($clave === '' || Rol::where('clave', $clave)->exists()) {
            $this->mensaje = "Ya existe un rol con un nombre similar («{$nombre}»).";
            return;
        }

        DB::transaction(function () use ($clave, $nombre) {
            Rol::create(['clave' => $clave, 'nombre' => $nombre, 'variante' => 'gray', 'es_sistema' => false]);
            // Sembrar la matriz del rol en false para que sus permisos persistan.
            foreach (Permisos::todos() as $perm) {
                PermisoRol::updateOrCreate(['rol' => $clave, 'permiso' => $perm], ['permitido' => false]);
            }
        });

        Permisos::clearCache();
        $this->cargarRoles();
        $this->nuevoRol = '';
        $this->mensaje = "Rol «{$nombre}» creado. Asignale permisos en la pestaña Permisos.";
    }

    /** Renombrar un rol custom (los de sistema no se editan). Persiste al instante. */
    public function updatedRoles($value, $key): void
    {
        $this->autorizar('gestionar_roles');
        if (in_array($key, $this->rolesSistema, true)) {
            return;
        }
        $nombre = trim((string) $value);
        if ($nombre === '') {
            return;
        }
        Rol::where('clave', $key)->update(['nombre' => $nombre]);
        $this->mensaje = 'Nombre del rol actualizado.';
    }

    /** Cambiar el color (variante) del badge de un rol custom. Persiste al instante. */
    public function cambiarColorRol(string $clave, string $color): void
    {
        $this->autorizar('gestionar_roles');
        if (in_array($clave, $this->rolesSistema, true) || ! in_array($color, self::PALETA, true)) {
            return;
        }
        Rol::where('clave', $clave)->update(['variante' => $color]);
        $this->colorRol[$clave] = $color;
        $this->mensaje = 'Color del rol actualizado.';
    }

    public function eliminarRol(string $clave): void
    {
        $this->autorizar('gestionar_roles');
        if (in_array($clave, $this->rolesSistema, true)) {
            return; // no se borran roles del sistema
        }

        $enUso = User::where('rol', $clave)->count();
        if ($enUso > 0) {
            $this->mensaje = "No se puede eliminar: {$enUso} usuario(s) tienen ese rol. Reasignalos primero.";
            return;
        }

        DB::transaction(function () use ($clave) {
            PermisoRol::where('rol', $clave)->delete();
            Rol::where('clave', $clave)->delete();
        });

        Permisos::clearCache();
        $this->cargarRoles();
        $this->mensaje = 'Rol eliminado.';
    }

    /** Cada toggle de la matriz se persiste al instante (no hace falta "Guardar"). */
    public function updatedMatriz($value, $key): void
    {
        $this->autorizar('gestionar_roles');
        [$rol, $permiso] = array_pad(explode('.', (string) $key, 2), 2, null);
        if (! $rol || ! $permiso || $rol === 'super_admin') {
            return; // el super admin siempre tiene todo
        }

        PermisoRol::updateOrCreate(
            ['rol' => $rol, 'permiso' => $permiso],
            ['permitido' => (bool) $value],
        );
        Permisos::clearCache();
        $this->mensaje = 'Permiso actualizado. (El usuario lo verá al recargar.)';
    }

    public function guardarPermisos(): void
    {
        $this->autorizar('gestionar_roles');

        DB::transaction(function () {
            foreach ($this->matriz as $rol => $perms) {
                if ($rol === 'super_admin') {
                    continue; // el super admin siempre tiene todo (no se persiste ni limita)
                }
                foreach ($perms as $permiso => $permitido) {
                    PermisoRol::updateOrCreate(
                        ['rol' => $rol, 'permiso' => $permiso],
                        ['permitido' => (bool) $permitido],
                    );
                }
            }
        });

        Permisos::clearCache();
        $this->mensaje = 'Permisos guardados. Los cambios se aplican al recargar (o al volver a ingresar el usuario).';
    }

    public function guardarGeneral(): void
    {
        $this->mensaje = "Stock mínimo global guardado en {$this->stockMinimo} unidades.";
    }

    public function render()
    {
        return view('livewire.configuracion.index', [
            'grupos' => $this->grupos(),
            'esSuper' => $this->esSuper(),
        ]);
    }
}
