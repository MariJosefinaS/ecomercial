<?php

use App\Livewire\Auth\Login;
use App\Livewire\Clientes\Index as ClientesIndex;
use App\Livewire\Cobranza\Index as CobranzaIndex;
use App\Livewire\Cobranza\Planilla as CobranzaPlanilla;
use App\Livewire\Compras\Index as ComprasIndex;
use App\Livewire\Configuracion\Index as ConfiguracionIndex;
use App\Livewire\Devoluciones\Index as DevolucionesIndex;
use App\Livewire\Proveedores\Index as ProveedoresIndex;
use App\Livewire\Recepcion\Index as RecepcionIndex;
use App\Livewire\Traspasos\Index as TraspasosIndex;
use App\Livewire\Reportes\Index as ReportesIndex;
use App\Livewire\Stock\Index as StockIndex;
use App\Livewire\Stock\Reposicion as StockReposicion;
use App\Livewire\Tesoreria\Index as TesoreriaIndex;
use App\Livewire\Usuarios\Index as UsuariosIndex;
use App\Livewire\Ventas\Index as VentasIndex;
use App\Livewire\Ventas\Nueva as VentasNueva;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ===== Públicas =====
Route::get('/login', Login::class)->name('login')->middleware('guest');

Route::post('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

// ===== Protegidas (requieren login + permiso por ruta) =====
Route::middleware(['auth', 'permiso'])->group(function () {
    // Cada usuario va a la primera sección que su rol puede ver.
    Route::get('/', fn () => redirect()->route(\App\Support\Permisos::inicio(Auth::user()?->rol)));

    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/stock', StockIndex::class)->name('stock');
    Route::get('/stock/reposicion', StockReposicion::class)->name('stock.reposicion'); // Lote óptimo (EOQ)
    Route::get('/ventas', VentasIndex::class)->name('ventas');
    Route::get('/ventas/nueva', VentasNueva::class)->name('ventas.nueva'); // Nota de pedido (wizard)
    Route::get('/compras', ComprasIndex::class)->name('compras');
    Route::get('/recepcion', RecepcionIndex::class)->name('recepcion'); // Recepción de mercadería (encargado de depósito)
    // Etiquetas de UN remito (lote): una por cada unidad recibida en condiciones.
    Route::get('/recepcion/remito/{remito}/etiquetas', function (\App\Models\Remito $remito) {
        abort_unless(\App\Support\Permisos::puede(Auth::user()?->rol, 'ver_recepcion'), 403);
        return view('recepcion.etiquetas-lote', [
            'titulo' => 'Etiquetas · ' . ($remito->numero ?: 'Remito #' . $remito->id) . ' · ' . ($remito->compra?->proveedor?->nombre ?? '—'),
            'etiquetas' => $remito->etiquetas(),
        ]);
    })->name('recepcion.remito.etiquetas');
    // Etiquetas de TODO lo recibido HOY (todos los remitos del día, en un archivo).
    Route::get('/recepcion/etiquetas/dia', function () {
        abort_unless(\App\Support\Permisos::puede(Auth::user()?->rol, 'ver_recepcion'), 403);
        $remitos = \App\Models\Remito::with(['items.producto', 'compra.proveedor', 'local', 'recibidoPor'])
            ->whereDate('recibido_at', today())->orderBy('recibido_at')->get();
        $etiquetas = $remitos->flatMap->etiquetas()->all();
        return view('recepcion.etiquetas-lote', [
            'titulo' => 'Etiquetas del día · ' . now()->format('d/m/Y'),
            'etiquetas' => $etiquetas,
        ]);
    })->name('recepcion.etiquetas.dia');
    Route::get('/traspasos', TraspasosIndex::class)->name('traspasos'); // Traspasos entre sucursales
    Route::get('/proveedores', ProveedoresIndex::class)->name('proveedores');
    Route::redirect('/proveedores/deuda', '/tesoreria')->name('proveedores.deuda'); // movido a Tesorería
    Route::get('/clientes', ClientesIndex::class)->name('clientes');
    Route::get('/cobranza', CobranzaIndex::class)->name('cobranza'); // Apertura del día + alertas de atraso
    Route::get('/cobranza/planilla', CobranzaPlanilla::class)->name('cobranza.planilla'); // Mi planilla del cobrador (por modalidad)
    // Impresión de la planilla del cobrador (estilo cupón f_030) — reusa App\Support\Planilla.
    Route::get('/cobranza/planilla/imprimir', function () {
        abort_unless(\App\Support\Permisos::puede(Auth::user()?->rol, 'ver_cobranza'), 403);
        $esAdmin = Auth::user()?->esRol('super_admin', 'admin_local') ?? false;
        $cobradorId = $esAdmin ? (int) request('cob', Auth::id()) : (int) Auth::id();
        $f = request('fecha') ? \Illuminate\Support\Carbon::parse(request('fecha'))->startOfDay() : \Illuminate\Support\Carbon::today();
        $modalidad = in_array(request('modalidad'), ['diario', 'semanal', 'mensual'], true) ? request('modalidad') : 'diario';
        $cuotas = \App\Support\Planilla::cuotasDelDia($cobradorId, $f);
        return view('cobranza.planilla-imprimir', [
            'filas' => \App\Support\Planilla::filas($cuotas, $f, $modalidad),
            'tot' => \App\Support\Planilla::totales($cuotas, $f, $modalidad),
            'cobrador' => \App\Models\User::find($cobradorId),
            'fecha' => $f,
            'modalidad' => $modalidad,
        ]);
    })->name('cobranza.planilla.imprimir');
    Route::get('/tesoreria', TesoreriaIndex::class)->name('tesoreria');
    Route::get('/devoluciones', DevolucionesIndex::class)->name('devoluciones');
    Route::get('/reportes', ReportesIndex::class)->name('reportes');
    Route::get('/usuarios', UsuariosIndex::class)->name('usuarios');
    Route::get('/configuracion', ConfiguracionIndex::class)->name('configuracion');

    // Compat: la antigua vista del vendedor ahora vive dentro de Stock.
    Route::redirect('/consulta', '/stock?sub=consulta')->name('consulta');
});
