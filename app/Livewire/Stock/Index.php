<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Categoria;
use App\Models\ConceptoPrecio;
use App\Models\Local;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\SolicitudCompra;
use App\Models\StockLocal;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Stock — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;
    use WithFileUploads;

    // Subsección: consulta (vendedor) | catalogo (admin).
    #[Url(as: 'sub')]
    public string $sub = 'consulta';

    public string $buscar = '';
    public string $local = 'todos';       // 'todos' | local_id (para "stock bajo")
    public string $categoria = 'todas';   // 'todas' | categoria_id
    public string $proveedor = 'todos';   // 'todos' | proveedor_id (mismo producto, distinto proveedor)
    public bool $soloBajo = false;
    public bool $soloDiferencia = false;

    // ===== Consulta de stock =====
    public ?string $sel = null;           // código del producto seleccionado
    public ?string $consultaMsg = null;

    // ===== Modal alta / edición =====
    public bool $modal = false;
    public ?int $editando = null;         // producto_id en edición (null = alta)
    public string $pNombre = '';
    public string $pCodigo = '';
    public ?int $pCategoriaId = null;
    public ?int $pProveedorId = null;
    public string $pPrecioCompra = '';    // NETO de lista/factura (base del costeo)
    public array $pStock = [];            // [local_id => cantidad]
    public string $pMin = '5';
    public array $pConceptos = [];        // conceptos [id, nombre, ambito, aplica, porcentaje, orden] (costo + venta)
    public ?int $conceptoAgregar = null;  // concepto elegido en el selector "agregar concepto"

    // ===== Imagen + ficha del producto (cargados a mano) =====
    public $pImagen = null;               // archivo nuevo (temporal)
    public ?string $pImagenActual = null; // ruta de la imagen ya guardada
    public string $pDescripcion = '';
    public array $pDetalles = [];         // [['clave','valor'], ...]

    // ===== Sugerencias de venta cruzada (curado manual) =====
    public array $pSugeridos = [];        // [['id','nom','cod'], ...]
    public string $sugBuscar = '';
    public bool $pSugReciproco = false;   // agregar también la relación inversa

    public function mount(): void
    {
        if (request()->boolean('nuevo') && $this->puede('gestionar_stock')) {
            $this->nuevoProducto();
        }
    }

    private function localesActivos()
    {
        return Local::where('activo', true)->orderBy('id')->get(['id', 'nombre']);
    }

    /** Conceptos por defecto del proveedor (costo + venta), con su % del pivot. */
    private function conceptosDe(?int $provId): array
    {
        if (! $provId) {
            return [];
        }

        $prov = Proveedor::with('conceptos')->find($provId);
        $coleccion = $prov && $prov->conceptos->isNotEmpty()
            ? $prov->conceptos
            : ConceptoPrecio::where('activo', true)->orderBy('orden')->get();

        return $coleccion->map(fn ($c) => [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'ambito' => $c->ambito ?? 'costo',
            'aplica' => true,
            'porcentaje' => $this->pctTexto((float) ($c->pivot->porcentaje ?? $c->porcentaje)),
            'orden' => (int) $c->orden,
        ])->all();
    }

    /** Conceptos del catálogo que todavía no están en el snapshot del producto (para "agregar"). */
    public function getConceptosDisponiblesProperty()
    {
        $usados = collect($this->pConceptos)->pluck('id')->all();

        return ConceptoPrecio::where('activo', true)
            ->when($usados, fn ($q) => $q->whereNotIn('id', $usados))
            ->orderBy('orden')->get(['id', 'nombre', 'ambito']);
    }

    /** Agrega un concepto (del catálogo) al snapshot de este producto. */
    public function agregarConceptoAProducto(): void
    {
        $c = $this->conceptoAgregar ? ConceptoPrecio::find($this->conceptoAgregar) : null;
        if (! $c || collect($this->pConceptos)->contains('id', $c->id)) {
            return;
        }
        $this->pConceptos[] = [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'ambito' => $c->ambito ?? 'costo',
            'aplica' => true,
            'porcentaje' => $this->pctTexto((float) $c->porcentaje),
            'orden' => (int) $c->orden,
        ];
        $this->conceptoAgregar = null;
    }

    /** Quita un concepto del snapshot de este producto. */
    public function quitarConceptoDeProducto(int $i): void
    {
        unset($this->pConceptos[$i]);
        $this->pConceptos = array_values($this->pConceptos);
    }

    private function pctTexto(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    }

    public function updatedPProveedorId($value): void
    {
        $this->pConceptos = $this->conceptosDe($value ? (int) $value : null);
    }

    /** Proveedor elegido en el modal (para IVA/remarque del costeo). */
    private function proveedorModal(): ?Proveedor
    {
        return $this->pProveedorId ? Proveedor::with('conceptos')->find($this->pProveedorId) : null;
    }

    /** COSTO puesto en depósito = neto × conceptos × (IVA si el proveedor costea con IVA). */
    public function getCostoProperty(): float
    {
        return \App\Support\Costeo::costo((float) ($this->pPrecioCompra ?: 0), $this->proveedorModal(), $this->pConceptos);
    }

    /** PRECIO DE VENTA = costo en cascada por los conceptos de venta. */
    public function getPrecioVentaProperty(): float
    {
        return \App\Support\Costeo::precioVenta($this->costo, $this->proveedorModal(), $this->pConceptos);
    }

    /** Desglose completo (neto → conceptos costo → IVA → costo → conceptos venta) para la vista. */
    public function getDesgloseCostoProperty(): array
    {
        return \App\Support\Costeo::desglose(
            (float) ($this->pPrecioCompra ?: 0),
            $this->proveedorModal(),
            $this->pConceptos,
        );
    }

    public function nuevoProducto(): void
    {
        $this->autorizar('gestionar_stock');
        $this->editando = null;
        $this->reset(['pNombre', 'pCodigo', 'pProveedorId', 'pPrecioCompra', 'pConceptos', 'conceptoAgregar', 'pSugeridos', 'sugBuscar', 'pSugReciproco', 'pImagen', 'pImagenActual', 'pDescripcion', 'pDetalles']);
        $this->pCategoriaId = Categoria::orderBy('nombre')->value('id');
        $this->pMin = '5';
        $this->pStock = [];
        foreach ($this->localesActivos() as $l) {
            $this->pStock[$l->id] = 0;
        }
        $this->resetValidation();
        $this->modal = true;
    }

    public function editarProducto(int $id): void
    {
        $this->autorizar('gestionar_stock');
        $p = Producto::with('stock')->find($id);
        if (! $p) {
            return;
        }

        $this->editando = $p->id;
        $this->pNombre = $p->nombre;
        $this->pCodigo = $p->codigo;
        $this->pCategoriaId = $p->categoria_id;
        $this->pProveedorId = $p->proveedor_id;
        // Base del costeo = neto (si se cargó); fallback al precio_compra histórico.
        $this->pPrecioCompra = (string) (float) ($p->precio_neto ?? $p->precio_compra);
        $this->pStock = [];
        $min = 5;
        foreach ($this->localesActivos() as $l) {
            $sl = $p->stock->firstWhere('local_id', $l->id);
            $this->pStock[$l->id] = $sl?->cantidad ?? 0;
            if ($sl) {
                $min = $sl->stock_minimo;
            }
        }
        $this->pMin = (string) $min;
        $this->pConceptos = ! empty($p->conceptos) ? $p->conceptos : $this->conceptosDe($p->proveedor_id);
        $this->conceptoAgregar = null;
        $this->pImagen = null;
        $this->pImagenActual = $p->imagen;
        $this->pDescripcion = $p->descripcion ?? '';
        $this->pDetalles = $p->detalles ?: [];
        $this->pSugeridos = $p->sugeridos()->get(['productos.id', 'nombre', 'codigo'])
            ->map(fn ($s) => ['id' => $s->id, 'nom' => $s->nombre, 'cod' => $s->codigo])->all();
        $this->sugBuscar = '';
        $this->pSugReciproco = false;
        $this->resetValidation();
        $this->modal = true;
    }

    /** Resultados del buscador de productos para sugerir (excluye el propio y los ya agregados). */
    public function getSugResultadosProperty(): array
    {
        $q = trim($this->sugBuscar);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $excluir = collect($this->pSugeridos)->pluck('id')->push($this->editando)->filter()->all();

        return Producto::where('activo', true)
            ->when($excluir, fn ($w) => $w->whereNotIn('id', $excluir))
            ->where(fn ($w) => $w->where('nombre', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%"))
            ->orderBy('nombre')->limit(6)->get(['id', 'nombre', 'codigo'])
            ->map(fn ($p) => ['id' => $p->id, 'nom' => $p->nombre, 'cod' => $p->codigo])->all();
    }

    public function agregarSugerencia(int $id): void
    {
        if (collect($this->pSugeridos)->contains('id', $id)) {
            return;
        }
        $p = Producto::find($id);
        if ($p) {
            $this->pSugeridos[] = ['id' => $p->id, 'nom' => $p->nombre, 'cod' => $p->codigo];
        }
        $this->sugBuscar = '';
    }

    public function quitarSugerencia(int $id): void
    {
        $this->pSugeridos = array_values(array_filter($this->pSugeridos, fn ($s) => $s['id'] !== $id));
    }

    public function agregarDetalle(): void
    {
        $this->pDetalles[] = ['clave' => '', 'valor' => ''];
    }

    public function quitarDetalle(int $i): void
    {
        unset($this->pDetalles[$i]);
        $this->pDetalles = array_values($this->pDetalles);
    }

    /** Quita la imagen ya guardada (al guardar quedará sin imagen). */
    public function quitarImagen(): void
    {
        $this->pImagen = null;
        $this->pImagenActual = null;
    }

    public function guardarProducto(): void
    {
        $this->autorizar('gestionar_stock');
        $this->validate([
            'pNombre' => 'required|min:2',
            'pCodigo' => 'required',
            'pCategoriaId' => 'required',
            'pProveedorId' => 'required',
            'pPrecioCompra' => 'required|numeric|min:0',
            'pMin' => 'numeric|min:0',
            'pConceptos.*.porcentaje' => 'numeric|min:0',
            'pImagen' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'pDescripcion' => 'nullable|string|max:1000',
        ], attributes: [
            'pImagen' => 'imagen', 'pDescripcion' => 'descripción',
            'pNombre' => 'nombre', 'pCodigo' => 'código', 'pCategoriaId' => 'categoría',
            'pProveedorId' => 'proveedor', 'pPrecioCompra' => 'precio neto',
        ]);

        $existe = Producto::where('codigo', $this->pCodigo)
            ->when($this->editando, fn ($q) => $q->where('id', '!=', $this->editando))
            ->exists();
        if ($existe) {
            $this->addError('pCodigo', 'Ya existe un producto con ese código.');
            return;
        }

        $costo = $this->costo;
        $precio = $this->precioVenta;

        DB::transaction(function () use ($costo, $precio) {
            $p = $this->editando ? Producto::find($this->editando) : new Producto();
            $p->codigo = $this->pCodigo;
            $p->nombre = $this->pNombre;
            $p->categoria_id = $this->pCategoriaId;
            $p->proveedor_id = $this->pProveedorId;
            // El neto es la base; el precio_compra guarda el COSTO puesto en depósito.
            $p->precio_neto = (float) $this->pPrecioCompra;
            $p->precio_compra = $costo;
            $p->conceptos = array_values($this->pConceptos);

            // Imagen: subir la nueva si la hay; si no, conservar/limpiar la actual.
            $p->imagen = $this->pImagen
                ? $this->pImagen->store('productos', 'public')
                : $this->pImagenActual;

            // Descripción + mini-ficha de detalles (filtra filas vacías; null si no hay nada).
            $p->descripcion = trim($this->pDescripcion) ?: null;
            $detalles = collect($this->pDetalles)
                ->map(fn ($d) => ['clave' => trim($d['clave'] ?? ''), 'valor' => trim($d['valor'] ?? '')])
                ->filter(fn ($d) => $d['clave'] !== '' || $d['valor'] !== '')
                ->values()->all();
            $p->detalles = $detalles ?: null;

            $p->save();

            foreach ($this->localesActivos() as $l) {
                StockLocal::updateOrCreate(
                    ['producto_id' => $p->id, 'local_id' => $l->id],
                    [
                        'cantidad' => (int) ($this->pStock[$l->id] ?? 0),
                        'stock_minimo' => (int) $this->pMin,
                        'precio_venta' => $precio,
                    ]
                );
            }

            // Sugerencias de venta cruzada (curado manual) → sync del pivot con orden.
            $sugIds = collect($this->pSugeridos)->pluck('id')->map(fn ($id) => (int) $id)
                ->reject(fn ($id) => $id === $p->id)->values();
            $sync = [];
            foreach ($sugIds as $orden => $id) {
                $sync[$id] = ['orden' => $orden];
            }
            $p->sugeridos()->sync($sync);

            // Recíproco: que cada sugerido también sugiera este producto (sin pisar su orden).
            if ($this->pSugReciproco) {
                foreach ($sugIds as $id) {
                    $otro = Producto::find($id);
                    $otro?->sugeridos()->syncWithoutDetaching([$p->id]);
                }
            }
        });

        $editaba = $this->editando !== null;
        $this->modal = false;
        $this->editando = null;
        session()->flash('stockMsg', $editaba
            ? "Producto «{$this->pNombre}» actualizado."
            : "Producto «{$this->pNombre}» creado. Precio de venta: $" . number_format($precio, 2, ',', '.'));
    }

    public function limpiar(): void
    {
        $this->reset(['buscar', 'local', 'categoria', 'proveedor', 'soloBajo', 'soloDiferencia']);
        $this->local = 'todos';
        $this->categoria = 'todas';
        $this->proveedor = 'todos';
    }

    // ===================================================================
    //  Consulta de stock (subsección, mobile-friendly; sin precio de compra)
    // ===================================================================
    private function localesConsulta(): array
    {
        return Local::where('activo', true)->orderBy('id')->take(2)->get()->all();
    }

    private function aArrayConsulta(Producto $p, array $locales): array
    {
        $a = $locales[0] ?? null;
        $b = $locales[1] ?? null;
        $slA = $a ? $p->stock->firstWhere('local_id', $a->id) : null;
        $slB = $b ? $p->stock->firstWhere('local_id', $b->id) : null;

        return [
            'cod' => $p->codigo,
            'nom' => $p->nombre,
            'icon' => $p->categoria?->icono ?? 'inventory_2',
            'img' => $p->imagenUrl(),
            'la' => $a?->nombre ?? 'Local A',
            'lb' => $b?->nombre ?? 'Local B',
            'sa' => $slA?->cantidad ?? 0,
            'sb' => $slB?->cantidad ?? 0,
            'pa' => (float) ($slA?->precio_venta ?? 0),
            'pb' => (float) ($slB?->precio_venta ?? 0),
            'prov' => $p->proveedor?->nombre ?? '—',
            'desc' => $p->descripcion ?: '',
            'detalles' => $p->detalles ?: [],
        ];
    }

    public function seleccionar(string $cod): void { $this->sel = $cod; $this->consultaMsg = null; }
    public function volverConsulta(): void { $this->sel = null; }

    public function getProductoConsultaProperty(): ?array
    {
        if (! $this->sel) {
            return null;
        }
        $p = Producto::with(['categoria:id,icono', 'proveedor:id,nombre', 'stock'])->where('codigo', $this->sel)->first();

        return $p ? $this->aArrayConsulta($p, $this->localesConsulta()) : null;
    }

    private function resultadosConsulta(): array
    {
        $q = trim($this->buscar);
        $locales = $this->localesConsulta();

        // Con búsqueda vacía mostramos una previsualización corta (no todo el catálogo,
        // que puede ser enorme); al escribir, filtra y amplía el límite.
        return Producto::with(['categoria:id,icono', 'proveedor:id,nombre', 'stock'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('nombre', 'like', "%{$q}%")
                ->orWhere('codigo', 'like', "%{$q}%")))
            ->orderBy('nombre')->limit($q === '' ? 8 : 20)->get()
            ->map(fn (Producto $p) => $this->aArrayConsulta($p, $locales))
            ->all();
    }

    /** Crea una solicitud de reposición real (pendiente de aprobación del admin). */
    public function solicitarReposicion(string $cod): void
    {
        $p = Producto::with('stock')->where('codigo', $cod)->first();
        if (! $p) {
            return;
        }
        $localId = auth()->user()?->local_id ?? Local::where('activo', true)->orderBy('id')->value('id');
        $min = $p->stock->firstWhere('local_id', $localId)?->stock_minimo ?? 1;
        $maxNum = (int) (SolicitudCompra::selectRaw("MAX(CAST(REGEXP_REPLACE(numero, '[^0-9]', '') AS UNSIGNED)) as n")->value('n') ?? 76);

        SolicitudCompra::create([
            'numero' => 'SOL-' . ($maxNum + 1),
            'producto_id' => $p->id,
            'local_id' => $localId,
            'solicitante_id' => auth()->id(),
            'cantidad' => max(1, (int) $min),
            'estado' => 'pendiente',
            'nota' => 'Solicitud desde consulta de stock',
        ]);

        $this->consultaMsg = "Solicitud de reposición enviada al administrador ({$cod}).";
    }

    public function render()
    {
        // Subsección Consulta: liviana, solo búsqueda + detalle.
        if ($this->sub === 'consulta') {
            return view('livewire.stock.index', [
                'resultados' => $this->resultadosConsulta(),
                'productoConsulta' => $this->productoConsulta,
            ]);
        }

        $locales = $this->localesActivos();

        $filas = Producto::with(['categoria', 'proveedor', 'stock'])
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nombre', 'like', "%{$this->buscar}%")
                ->orWhere('codigo', 'like', "%{$this->buscar}%")))
            ->when($this->categoria !== 'todas', fn ($q) => $q->where('categoria_id', (int) $this->categoria))
            ->when($this->proveedor !== 'todos', fn ($q) => $q->where('proveedor_id', (int) $this->proveedor))
            ->orderBy('nombre')
            ->get()
            ->map(function (Producto $p) use ($locales) {
                $porLocal = [];
                foreach ($locales as $l) {
                    $sl = $p->stock->firstWhere('local_id', $l->id);
                    $porLocal[$l->id] = [
                        'existe' => $sl !== null,
                        'cantidad' => $sl?->cantidad ?? 0,
                        'precio' => (float) ($sl?->precio_venta ?? 0),
                        'min' => $sl?->stock_minimo ?? 0,
                        'bajo' => $sl ? $sl->cantidad <= $sl->stock_minimo : false,
                    ];
                }
                $precios = collect($porLocal)->pluck('precio')->filter(fn ($x) => $x > 0);
                $dif = $precios->count() > 1 ? round($precios->max() - $precios->min(), 2) : 0;

                return [
                    'id' => $p->id, 'cod' => $p->codigo, 'nom' => $p->nombre,
                    'cat' => $p->categoria?->nombre ?? '—',
                    'icon' => $p->categoria?->icono ?? 'inventory_2',
                    'img' => $p->imagenUrl(),
                    'prov' => $p->proveedor?->nombre ?? '—',
                    'porLocal' => $porLocal,
                    'dif' => $dif,
                ];
            })
            ->filter(function (array $p) {
                // Filtro por sucursal: ocultar productos que NO existen en el local elegido.
                if ($this->local !== 'todos' && empty($p['porLocal'][(int) $this->local]['existe'])) {
                    return false;
                }
                if ($this->soloDiferencia && $p['dif'] <= 0) {
                    return false;
                }
                if ($this->soloBajo) {
                    $bajo = $this->local !== 'todos'
                        ? ($p['porLocal'][(int) $this->local]['bajo'] ?? false)
                        : collect($p['porLocal'])->contains(fn ($x) => $x['bajo']);
                    if (! $bajo) {
                        return false;
                    }
                }
                return true;
            })
            ->values();

        return view('livewire.stock.index', [
            'filas' => $filas,
            'locales' => $locales,
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'proveedores' => Proveedor::orderBy('nombre')->get(['id', 'nombre']),
            'stats' => [
                'total' => $filas->count(),
                'bajo' => $filas->filter(fn ($p) => collect($p['porLocal'])->contains(fn ($x) => $x['bajo']))->count(),
                'dif' => $filas->filter(fn ($p) => $p['dif'] > 0)->count(),
            ],
        ]);
    }
}
