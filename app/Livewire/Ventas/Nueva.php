<?php

namespace App\Livewire\Ventas;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\ActivityLog;
use App\Models\ChequeCliente;
use App\Models\Cliente;
use App\Models\Devolucion;
use App\Models\Local;
use App\Models\MovimientoCliente;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\Zona;
use App\Support\Cuit;
use App\Support\PlanesCredito;
use App\Support\SugerenciasVenta;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Nueva venta = Nota de Pedido (wizard de página completa).
 * Pasos: Cliente → Artículos → Plan/Financiación → Confirmar.
 * La venta nace en estado "pendiente" (= Solicitado); la aprueba un admin.
 */
#[Layout('components.layouts.app')]
#[Title('Nueva venta — E.Comercial')]
class Nueva extends Component
{
    use AutorizaPermisos;

    public int $paso = 1;                 // 1 cliente · 2 artículos · 3 plan · 4 confirmar
    public ?string $mensaje = null;

    public string $vLocal = '';

    // ===== Cliente =====
    public string $cliBuscar = '';
    public ?int $cliId = null;
    public string $cliNombre = '';
    public string $cliDoc = '';
    public string $cliRiesgo = 'bajo';
    public bool $cliNuevo = false;
    public bool $altaCliente = false;
    public string $ncNombre = '';
    public string $ncTipoDoc = 'CUIT';
    public string $ncDoc = '';
    public string $ncTel = '';
    public string $ncEmail = '';
    public string $ncFechaNac = '';

    // ===== Artículos =====
    /** @var array<int,array{producto_id:?int,cod:string,desc:string,cant:int,precio:string,sugerido:bool}> */
    public array $items = [];
    public ?int $buscandoEn = null;

    // ===== Plan / financiación =====
    public string $planCodigo = 'contado';
    public ?int $plazo = null;
    public ?float $cuota = null;
    public ?float $anticipo = null;
    public string $fechaPrimeraCuota = '';   // R4: vencimiento de la 1ª cuota (configurable)
    public string $medioAnticipo = 'Efectivo';
    public ?int $zonaId = null;          // zona de cobranza elegida → auto-completa el cobrador
    public string $zonaCobranza = '';    // snapshot del nombre de zona (display/legado)
    public string $cobrador = '';        // snapshot del nombre del cobrador (display/legado)

    // Cheque (si el medio es Cheque)
    public string $chqNumero = '';
    public string $chqBanco = '';
    public string $chqVencimiento = '';

    public const MEDIOS = ['Efectivo', 'Transferencia', 'Cheque'];

    public function mount(): void
    {
        $this->autorizar('crear_venta');
        $this->vLocal = $this->locales()[0] ?? 'Local A';
        $this->items = [$this->itemVacio()];

        // Prefill desde "Cargar venta con este producto" (consulta de stock).
        if ($cod = request('producto')) {
            $p = Producto::with('stock')->where('codigo', $cod)->first();
            if ($p) {
                $this->items[0]['producto_id'] = $p->id;
                $this->items[0]['cod'] = $p->codigo;
                $this->items[0]['desc'] = $p->nombre;
                $precio = $p->stockEn($this->localId())?->precio_venta;
                $this->items[0]['precio'] = $precio !== null ? (string) (float) $precio : '';
            }
        }
    }

    private function itemVacio(): array
    {
        return ['producto_id' => null, 'cod' => '', 'desc' => '', 'cant' => 1, 'precio' => '', 'sugerido' => false];
    }

    private function locales(): array
    {
        return Local::where('activo', true)->orderBy('id')->pluck('nombre')->all();
    }

    private function localId(): ?int
    {
        return Local::where('nombre', $this->vLocal)->value('id')
            ?? auth()->user()?->local_id
            ?? Local::where('activo', true)->orderBy('id')->value('id');
    }

    // ===================================================================
    //  Navegación de pasos
    // ===================================================================
    public function siguiente(): void
    {
        if ($this->paso === 1) {
            $this->validate(['cliId' => 'required'], ['cliId.required' => 'Elegí un cliente o dá de alta uno nuevo.']);
        }
        if ($this->paso === 2) {
            $this->validate([
                'items' => 'required|array|min:1',
                'items.*.producto_id' => 'required',
                'items.*.cant' => 'required|numeric|min:1',
                'items.*.precio' => 'required|numeric|min:0',
            ], [
                'items.*.producto_id.required' => 'Elegí un producto del buscador en cada renglón.',
            ], ['items.*.cant' => 'cantidad', 'items.*.precio' => 'precio']);

            // Al entrar al paso de plan, precargar cálculo.
            $this->updatedPlanCodigo();
        }
        $this->paso = min(4, $this->paso + 1);
        $this->buscandoEn = null;
    }

    public function atras(): void
    {
        $this->paso = max(1, $this->paso - 1);
        $this->buscandoEn = null;
    }

    // ===================================================================
    //  Cliente
    // ===================================================================
    public function getClientesEncontradosProperty(): array
    {
        $q = trim($this->cliBuscar);
        if (mb_strlen($q) < 2 || $this->cliId) {
            return [];
        }

        return Cliente::where('aprobado', true)
            ->where(fn ($w) => $w->where('nombre', 'like', "%{$q}%")->orWhere('documento', 'like', "%{$q}%"))
            ->orderBy('nombre')->limit(8)->get()
            ->map(fn (Cliente $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'doc' => trim(($c->tipo_doc ?: '') . ' ' . ($c->documento ?: '')),
                'riesgo' => $c->riesgo,
            ])->all();
    }

    public function updatedCliBuscar(): void
    {
        $this->cliId = null;
        $this->cliNombre = '';
        $this->cliDoc = '';
        $this->cliNuevo = false;
        $this->altaCliente = false;
    }

    /** Deselecciona el cliente para elegir otro (botón "Cambiar"). */
    public function cambiarCliente(): void
    {
        $this->reset(['cliId', 'cliNombre', 'cliDoc', 'cliRiesgo', 'cliNuevo', 'altaCliente', 'cliBuscar']);
        $this->resetValidation('cliId');
    }

    public function elegirCliente(int $id): void
    {
        $c = Cliente::find($id);
        if (! $c) {
            return;
        }
        $this->cliId = $c->id;
        $this->cliNombre = $c->nombre;
        $this->cliDoc = trim(($c->tipo_doc ?: '') . ' ' . ($c->documento ?: ''));
        $this->cliRiesgo = $c->riesgo;
        $this->cliNuevo = ! $c->aprobado;
        $this->cliBuscar = $c->nombre;
        $this->altaCliente = false;
    }

    /**
     * Semáforo del cliente para el VENDEDOR (solo la advertencia de riesgo, sin cifras).
     * El detalle financiero completo se muestra recién al aprobar (admin/super_admin).
     */
    public function getSemaforoClienteProperty(): ?array
    {
        if (! $this->cliId) {
            return null;
        }

        return \App\Support\Semaforo::deCliente($this->cliId, \Illuminate\Support\Carbon::today());
    }

    public function getPerfilClienteProperty(): ?array
    {
        if (! $this->cliId) {
            return null;
        }
        $saldo = (float) MovimientoCliente::where('cliente_id', $this->cliId)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'debe' THEN monto ELSE -monto END), 0) as s")->value('s');
        $ultima = Venta::where('cliente_id', $this->cliId)->where('estado', 'aprobada')->latest('fecha')->first();

        return [
            'saldo' => $saldo,
            'ultima_fecha' => $ultima?->fecha?->format('d/m/Y'),
            'ultima_monto' => (float) ($ultima->total ?? 0),
            'cheques_rechazados' => ChequeCliente::where('cliente_id', $this->cliId)->where('estado', 'rechazado')->count(),
            'devoluciones' => Devolucion::where('cliente_id', $this->cliId)->count(),
        ];
    }

    public function mostrarAltaCliente(): void
    {
        $this->altaCliente = true;
        $this->ncNombre = $this->cliBuscar;
        $this->ncTipoDoc = 'CUIT';
        $this->ncDoc = '';
        $this->ncTel = '';
        $this->ncEmail = '';
        $this->ncFechaNac = '';
        $this->resetValidation();
    }

    /** Da formato al documento mientras se escribe: CUIT/CUIL con guiones (XX-XXXXXXXX-X), DNI solo dígitos. */
    private function formatearDoc(): void
    {
        $d = preg_replace('/\D/', '', (string) $this->ncDoc);

        if (in_array($this->ncTipoDoc, ['CUIT', 'CUIL'], true)) {
            $d = substr($d, 0, 11);
            $out = $d;
            if (strlen($d) > 2) {
                $out = substr($d, 0, 2) . '-' . substr($d, 2);
            }
            if (strlen($d) > 10) {
                $out = substr($d, 0, 2) . '-' . substr($d, 2, 8) . '-' . substr($d, 10);
            }
            $this->ncDoc = $out;
        } else {
            $this->ncDoc = $d; // DNI: solo números, sin guiones
        }
    }

    public function updatedNcDoc(): void
    {
        $this->formatearDoc();
    }

    public function updatedNcTipoDoc(): void
    {
        $this->formatearDoc(); // reformatea según el nuevo tipo (agrega/quita guiones)
    }

    /** Estado de validez del CUIT/CUIL en vivo (para el mensaje bajo el input). */
    public function getDocEstadoProperty(): array
    {
        if (! in_array($this->ncTipoDoc, ['CUIT', 'CUIL'], true)) {
            return ['estado' => 'na', 'msg' => ''];
        }

        $d = preg_replace('/\D/', '', (string) $this->ncDoc);
        if ($d === '') {
            return ['estado' => 'na', 'msg' => ''];
        }
        if (strlen($d) < 11) {
            return ['estado' => 'incompleto', 'msg' => "El {$this->ncTipoDoc} tiene 11 dígitos (faltan " . (11 - strlen($d)) . ')'];
        }

        return Cuit::valida($d)
            ? ['estado' => 'valido', 'msg' => "{$this->ncTipoDoc} válido"]
            : ['estado' => 'invalido', 'msg' => "El dígito verificador no coincide. Revisá el {$this->ncTipoDoc}."];
    }

    public function guardarClienteNuevo(): void
    {
        $this->autorizar('crear_venta');

        $esPersona = in_array($this->ncTipoDoc, ['DNI', 'CUIL'], true);
        $mayoria = now()->subYears(18)->toDateString();

        $this->validate([
            'ncNombre' => 'required|min:2',
            'ncTipoDoc' => 'required|in:CUIT,CUIL,DNI',
            'ncDoc' => ['required', function ($attr, $value, $fail) {
                if (in_array($this->ncTipoDoc, ['CUIT', 'CUIL'], true) && ! \App\Support\Cuit::valida($value)) {
                    $fail("El {$this->ncTipoDoc} ingresado no es válido. Revisá los 11 dígitos.");
                }
            }],
            'ncEmail' => 'nullable|email',
            // Fecha de nacimiento: obligatoria para personas (DNI/CUIL); si se carga, debe ser mayor de edad.
            'ncFechaNac' => ($esPersona ? 'required|' : 'nullable|') . 'date|before_or_equal:' . $mayoria,
        ], [
            'ncFechaNac.required' => 'La fecha de nacimiento es obligatoria para personas (DNI/CUIL).',
            'ncFechaNac.before_or_equal' => 'El cliente debe ser mayor de 18 años.',
        ], [
            'ncNombre' => 'nombre', 'ncTipoDoc' => 'tipo de documento', 'ncDoc' => 'documento',
            'ncEmail' => 'email', 'ncFechaNac' => 'fecha de nacimiento',
        ]);

        $c = Cliente::create([
            'nombre' => $this->ncNombre,
            'tipo_doc' => $this->ncTipoDoc,
            'documento' => $this->ncDoc,
            'telefono' => $this->ncTel ?: null,
            'email' => $this->ncEmail ?: null,
            'fecha_nacimiento' => $this->ncFechaNac ?: null,
            'riesgo' => 'bajo',
            'activo' => true,
            'aprobado' => false,
        ]);

        ActivityLog::create([
            'tipo' => 'alerta',
            'titulo' => 'Cliente nuevo pendiente de aprobación',
            'detalle' => "«{$c->nombre}» dado de alta por " . (auth()->user()?->name ?? 'un vendedor') . ' durante una nota de pedido.',
            'usuario_id' => auth()->id(),
        ]);

        $this->cliId = $c->id;
        $this->cliNombre = $c->nombre;
        $this->cliDoc = trim($c->tipo_doc . ' ' . $c->documento);
        $this->cliRiesgo = 'bajo';
        $this->cliNuevo = true;
        $this->cliBuscar = $c->nombre;
        $this->altaCliente = false;
    }

    // ===================================================================
    //  Artículos
    // ===================================================================
    public function getResultadosProperty(): array
    {
        if ($this->buscandoEn === null) {
            return [];
        }
        $q = trim($this->items[$this->buscandoEn]['desc'] ?? '');
        if (mb_strlen($q) < 2) {
            return [];
        }
        $localId = $this->localId();

        return Producto::query()
            ->with(['proveedor:id,nombre', 'stock'])
            ->where(fn ($w) => $w
                ->where('nombre', 'like', "%{$q}%")
                ->orWhere('codigo', 'like', "%{$q}%")
                ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', "%{$q}%")))
            ->limit(8)->get()
            ->map(fn (Producto $p) => [
                'id' => $p->id,
                'cod' => $p->codigo,
                'nom' => $p->nombre,
                'prov' => $p->proveedor?->nombre ?? '—',
                'precio' => (float) (($localId ? $p->stockEn($localId)?->precio_venta : null) ?? 0),
            ])->all();
    }

    public function updatedItems($value, $key): void
    {
        [$i, $campo] = array_pad(explode('.', (string) $key, 2), 2, null);
        if ($campo === 'desc') {
            $this->buscandoEn = (int) $i;
        }
    }

    public function elegirProducto(int $i, int $productoId): void
    {
        $p = Producto::with('stock')->find($productoId);
        if (! $p) {
            return;
        }
        $this->items[$i]['producto_id'] = $p->id;
        $this->items[$i]['cod'] = $p->codigo;
        $this->items[$i]['desc'] = $p->nombre;
        $precio = $this->localId() ? $p->stockEn($this->localId())?->precio_venta : null;
        if ($precio !== null) {
            $this->items[$i]['precio'] = (float) $precio;
        }
        $this->buscandoEn = null;
    }

    public function agregarItem(): void
    {
        $this->items[] = $this->itemVacio();
    }

    /** Sugerencias de venta cruzada para los productos ya cargados (manual + histórico + categoría). */
    public function getSugerenciasProperty(): array
    {
        $ids = array_filter(array_map(fn ($it) => $it['producto_id'] ?? null, $this->items));

        return SugerenciasVenta::para(array_values($ids), $this->localId(), 6);
    }

    /** Agrega un producto sugerido como renglón nuevo (marcado como "sugerido"). */
    public function agregarSugerido(int $productoId): void
    {
        $p = Producto::with('stock')->find($productoId);
        if (! $p) {
            return;
        }

        // No duplicar si ya está en la nota.
        foreach ($this->items as $it) {
            if ((int) ($it['producto_id'] ?? 0) === $p->id) {
                return;
            }
        }

        $precio = $this->localId() ? $p->stockEn($this->localId())?->precio_venta : null;
        $nuevo = [
            'producto_id' => $p->id,
            'cod' => $p->codigo,
            'desc' => $p->nombre,
            'cant' => 1,
            'precio' => $precio !== null ? (float) $precio : '',
            'sugerido' => true,
        ];

        // Si el último renglón está vacío, ocuparlo; si no, agregar uno nuevo.
        $ultimo = end($this->items);
        if ($ultimo !== false && empty($ultimo['producto_id']) && trim((string) ($ultimo['desc'] ?? '')) === '') {
            $this->items[array_key_last($this->items)] = $nuevo;
        } else {
            $this->items[] = $nuevo;
        }
        $this->buscandoEn = null;
    }

    public function quitarItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
        if (empty($this->items)) {
            $this->items = [$this->itemVacio()];
        }
        $this->buscandoEn = null;
    }

    public function getTotalProperty(): float
    {
        return array_sum(array_map(
            fn ($it) => (float) ($it['cant'] ?: 0) * (float) ($it['precio'] ?: 0),
            $this->items
        ));
    }

    // ===================================================================
    //  Plan / financiación
    // ===================================================================
    public function getPlanProperty(): array
    {
        return PlanesCredito::calcular($this->planCodigo, $this->total, $this->plazo);
    }

    public function updatedPlanCodigo(): void
    {
        $calc = PlanesCredito::calcular($this->planCodigo, $this->total);
        $this->plazo = $calc['plazo'] ?: null;
        $this->anticipo = $calc['anticipo_min'];
        $this->cuota = $calc['cuota_min'] ?: null;

        // R4: sugerir la 1ª cuota un período después de hoy; el vendedor la puede mover.
        $this->fechaPrimeraCuota = PlanesCredito::esCredito($this->planCodigo)
            ? PlanesCredito::primeraCuotaPorDefecto($this->planCodigo)->toDateString()
            : '';
    }

    public function updatedPlazo(): void
    {
        $calc = PlanesCredito::calcular($this->planCodigo, $this->total, $this->plazo);
        $this->cuota = $calc['cuota_min'] ?: null;
    }

    /** Al elegir la zona, el sistema completa el cobrador asignado (snapshot de nombres). */
    public function updatedZonaId(): void
    {
        $zona = $this->zonaId ? Zona::with('cobrador')->find($this->zonaId) : null;
        $this->zonaCobranza = $zona?->nombre ?? '';
        $this->cobrador = $zona?->cobrador?->name ?? '';
    }

    // ===================================================================
    //  Confirmar
    // ===================================================================
    public function confirmar(): void
    {
        $this->autorizar('crear_venta');

        $this->validate([
            'cliId' => 'required',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required',
            'items.*.cant' => 'required|numeric|min:1',
            'items.*.precio' => 'required|numeric|min:0',
        ], ['cliId.required' => 'Elegí un cliente.', 'items.*.producto_id.required' => 'Cada renglón necesita un producto.']);

        $calc = $this->plan;
        $esCredito = PlanesCredito::esCredito($this->planCodigo);

        if ($esCredito) {
            $this->validate([
                'anticipo' => 'required|numeric|min:' . $calc['anticipo_min'],
                'plazo' => 'required|integer|min:1',
                'cuota' => 'required|numeric|min:' . max(0.01, $calc['cuota_min']),
                'fechaPrimeraCuota' => 'required|date|after_or_equal:today',
            ], [
                'anticipo.min' => 'El anticipo no puede ser menor a $' . number_format($calc['anticipo_min'], 2, ',', '.') . '.',
                'cuota.min' => 'La cuota no puede ser menor a $' . number_format($calc['cuota_min'], 2, ',', '.') . '.',
                'fechaPrimeraCuota.after_or_equal' => 'La 1ª cuota no puede vencer antes de hoy.',
            ], ['anticipo' => 'anticipo', 'plazo' => 'plazo', 'cuota' => 'cuota', 'fechaPrimeraCuota' => 'fecha de la 1ª cuota']);
        }

        if ($this->medioAnticipo === 'Cheque') {
            $this->validate([
                'chqNumero' => 'required',
                'chqVencimiento' => 'required|date',
            ], attributes: ['chqNumero' => 'número de cheque', 'chqVencimiento' => 'vencimiento']);
        }

        $plan = PlanesCredito::get($this->planCodigo) ?? PlanesCredito::get('contado');
        $maxNum = (int) (Venta::selectRaw("MAX(CAST(REGEXP_REPLACE(numero, '[^0-9]', '') AS UNSIGNED)) as n")->value('n') ?? 1042);
        $num = 'FAC-' . ($maxNum + 1);

        DB::transaction(function () use ($num, $plan, $calc, $esCredito) {
            $venta = Venta::create([
                'numero' => $num,
                'local_id' => $this->localId(),
                'vendedor_id' => auth()->id(),
                'cliente_id' => $this->cliId,
                'cliente_nombre' => $this->cliNombre,
                'medio_pago' => $esCredito ? ($plan['modalidad'] === 'semanal' ? 'Pago semanal' : 'Pago diario') : $this->medioAnticipo,
                'credito' => $esCredito,
                'fecha' => now(),
                'total' => $this->total,
                'estado' => 'pendiente',
                'plan_codigo' => $this->planCodigo,
                'plan_nombre' => $plan['nombre'],
                'modalidad' => $calc['modalidad'],
                'anticipo' => $esCredito ? (float) $this->anticipo : $this->total,
                'saldo_financiado' => $esCredito ? $calc['saldo'] : 0,
                'plazo' => $esCredito ? (int) $this->plazo : null,
                'cuota' => $esCredito ? (float) $this->cuota : 0,
                'fecha_primera_cuota' => $esCredito ? $this->fechaPrimeraCuota : null,
                'zona_cobranza' => $this->zonaCobranza ?: null,
                'zona_id' => $esCredito ? $this->zonaId : null,
                'cobrador' => $this->cobrador ?: null,
            ]);

            // El cliente "adopta" la zona de su venta a crédito si aún no tiene una (alta → zona).
            if ($esCredito && $this->zonaId && $this->cliId) {
                Cliente::where('id', $this->cliId)->whereNull('zona_id')->update(['zona_id' => $this->zonaId]);
            }

            foreach ($this->items as $it) {
                VentaItem::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $it['producto_id'],
                    'cantidad' => (int) $it['cant'],
                    'precio_unitario' => (float) $it['precio'],
                    'sugerido' => (bool) ($it['sugerido'] ?? false),
                ]);
            }

            // Cheque del anticipo/cobro → entra "en cartera" (pendiente).
            if ($this->medioAnticipo === 'Cheque') {
                $venc = $this->chqVencimiento;
                ChequeCliente::create([
                    'cliente_id' => $this->cliId,
                    'venta_id' => $venta->id,
                    'numero' => $this->chqNumero,
                    'banco' => $this->chqBanco ?: null,
                    'monto' => $esCredito ? (float) $this->anticipo : $this->total,
                    'fecha_vencimiento' => $venc,
                    'fecha_deposito' => ChequeCliente::calcularDeposito($venc),
                    'estado' => 'pendiente',
                ]);
            }
        });

        session()->flash('ventaMsg', "Nota de pedido {$num} cargada (estado: Solicitado). Queda pendiente de aprobación del administrador.");

        $this->redirectRoute('ventas', navigate: true);
    }

    public function render()
    {
        return view('livewire.ventas.nueva', [
            'planes' => PlanesCredito::planes(),
            'locales' => $this->locales(),
            'zonas' => Zona::where('activo', true)->with('cobrador')->orderBy('nombre')->get(),
        ]);
    }
}
