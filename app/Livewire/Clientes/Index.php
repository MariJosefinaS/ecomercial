<?php

namespace App\Livewire\Clientes;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Cliente;
use App\Models\ChequeCliente;
use App\Models\Cuota;
use App\Models\Devolucion;
use App\Models\MovimientoCliente;
use App\Models\Venta;
use App\Support\Cobranza;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Clientes — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;

    public string $buscar = '';
    public string $riesgo = 'todos';
    public ?int $sel = null;
    public string $tab = 'cuenta';
    public ?string $mensaje = null;

    // ===== Modal alta / edición =====
    public bool $modal = false;
    public ?int $editId = null;
    public string $fNombre = '';
    public string $fTipoDoc = 'CUIT';
    public string $fDoc = '';
    public string $fTel = '';
    public string $fEmail = '';
    public string $fDir = '';
    public string $fLimite = '0';
    public string $fRiesgo = 'bajo';

    private function saldoDe(int $clienteId): float
    {
        $debe = (float) MovimientoCliente::where('cliente_id', $clienteId)->where('tipo', 'debe')->sum('monto');
        $haber = (float) MovimientoCliente::where('cliente_id', $clienteId)->where('tipo', 'haber')->sum('monto');
        return $debe - $haber;
    }

    /** Forma base que espera la vista para un cliente. */
    private function aArray(Cliente $c): array
    {
        return [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'doc' => trim(($c->tipo_doc ?: '') . ' ' . ($c->documento ?: '')) ?: '—',
            'tel' => $c->telefono ?: '—',
            'riesgo' => $c->riesgo,
            'limite' => (float) $c->limite_credito,
            'saldo' => $this->saldoDe($c->id),
        ];
    }

    /** Datos ricos de la ficha desde la DB. */
    private function ficha(Cliente $c): array
    {
        $base = $this->aArray($c);

        $base['movimientos'] = MovimientoCliente::where('cliente_id', $c->id)->orderBy('fecha')->orderBy('id')->get()
            ->map(fn ($m) => ['fecha' => $m->fecha?->format('d/m/Y'), 'tipo' => $m->tipo, 'concepto' => $m->concepto, 'monto' => (float) $m->monto])->all();

        // Cronograma de cuotas de crédito + saldo vencido / a vencer / mora (calculados al vuelo).
        $hoy = Carbon::today();
        $cuotas = Cuota::with('venta:id,numero')->where('cliente_id', $c->id)
            ->orderBy('fecha_vencimiento')->orderBy('numero')->get();
        $base['cuotas'] = $cuotas->map(fn (Cuota $q) => [
            'id' => $q->id,
            'venta' => $q->venta?->numero ?? '—',
            'numero' => $q->numero,
            'venc' => $q->fecha_vencimiento?->format('d/m/Y'),
            'monto' => (float) $q->monto,
            'estado' => $q->estado,
            'dias_atraso' => $q->diasAtraso($hoy),
            'mora' => $q->mora($hoy),
            'total_cobrar' => $q->totalAcobrar($hoy),
        ])->all();
        $pend = $cuotas->where('estado', 'pendiente');
        $base['credito'] = [
            'a_vencer' => round($pend->filter(fn ($q) => $q->fecha_vencimiento->gt($hoy))->sum(fn ($q) => $q->saldo()), 2),
            'vencido' => round($pend->filter(fn ($q) => $q->fecha_vencimiento->lte($hoy))->sum(fn ($q) => $q->saldo()), 2),
            'mora' => round($pend->sum(fn ($q) => $q->mora($hoy)), 2),
            'total' => round($pend->sum(fn ($q) => $q->totalAcobrar($hoy)), 2),
        ];

        // Semáforo del cliente (en vivo) + detalle por crédito.
        $base['semaforo'] = \App\Support\Semaforo::deCliente($c->id, $hoy);

        // ===== Cuenta corriente POR CRÉDITO (doble vista: además del saldo único fiscal) =====
        $base['numero_cuenta'] = $c->numero_cuenta;
        $base['creditos'] = Venta::where('cliente_id', $c->id)->where('credito', true)->orderByDesc('id')->get()
            ->map(function (Venta $v) use ($c, $hoy) {
                $cuotasV = Cuota::where('venta_id', $v->id)->get();
                $pend = $cuotasV->where('estado', 'pendiente');
                $total = $cuotasV->count();
                $pagadas = $cuotasV->where('estado', 'cobrada')->count();
                $movs = MovimientoCliente::where('cliente_id', $c->id)->where('referencia', $v->numero)
                    ->orderBy('fecha')->orderBy('id')->get();

                return [
                    'barra' => ($c->numero_cuenta ?? '—') . ($v->credito_barra ? '/' . $v->credito_barra : ''),
                    'numero' => $v->numero,
                    'plan' => $v->plan_nombre ?? ucfirst((string) $v->modalidad),
                    'garante' => $v->garante_nombre,
                    'garante_doc' => $v->garante_documento,
                    'garante_tel' => $v->garante_telefono,
                    'fecha' => $v->fecha?->format('d/m/Y'),
                    'estado' => $v->estado,
                    'saldo' => round($pend->sum(fn (Cuota $q) => $q->totalAcobrar($hoy)), 2),
                    'vencido' => round($pend->filter(fn (Cuota $q) => $q->estaVencida($hoy))->sum(fn (Cuota $q) => $q->saldo()), 2),
                    'a_vencer' => round($pend->filter(fn (Cuota $q) => ! $q->estaVencida($hoy))->sum(fn (Cuota $q) => $q->saldo()), 2),
                    'mora' => round($pend->sum(fn (Cuota $q) => $q->mora($hoy)), 2),
                    'pagadas' => $pagadas, 'total_cuotas' => $total,
                    'avance' => $total > 0 ? round($pagadas / $total * 100, 1) : 0.0,
                    'movimientos' => $movs->map(fn ($m) => ['fecha' => $m->fecha?->format('d/m/Y'), 'concepto' => $m->concepto, 'tipo' => $m->tipo, 'monto' => (float) $m->monto])->all(),
                ];
            })->all();

        $base['compras'] = Venta::where('cliente_id', $c->id)->whereIn('estado', ['pendiente', 'aprobada'])->orderByDesc('id')->get()
            ->map(fn ($v) => ['fecha' => $v->fecha?->format('d/m/Y'), 'comp' => $v->numero, 'pago' => $v->medio_pago, 'monto' => (float) $v->total])->all();

        $base['cheques'] = ChequeCliente::where('cliente_id', $c->id)->orderByDesc('id')->get()
            ->map(fn ($ch) => [
                'id' => $ch->id, 'num' => $ch->numero, 'banco' => $ch->banco, 'monto' => (float) $ch->monto,
                'venc' => $ch->fecha_vencimiento?->format('Y-m-d'), 'estado' => $ch->estado, 'motivo' => $ch->motivo_rechazo,
            ])->all();

        $base['devoluciones'] = Devolucion::where('cliente_id', $c->id)->orderByDesc('id')->get()
            ->map(fn ($d) => ['fecha' => $d->fecha?->format('d/m/Y'), 'producto' => $d->producto, 'monto' => (float) $d->monto, 'motivo' => $d->motivo, 'estado' => $d->estado])->all();

        return $base;
    }

    public function abrir(int $id): void
    {
        $this->autorizar('ver_cuenta_cliente');
        $this->sel = $id;
        $this->tab = 'cuenta';
        $this->mensaje = null;
    }

    public function volver(): void { $this->sel = null; }
    public function setTab(string $t): void { $this->tab = $t; }

    // ===== ABM =====
    public function nuevoCliente(): void
    {
        $this->autorizar('gestionar_clientes');
        $this->editId = null;
        $this->reset(['fNombre', 'fDoc', 'fTel', 'fEmail', 'fDir']);
        $this->fTipoDoc = 'CUIT';
        $this->fLimite = '0';
        $this->fRiesgo = 'bajo';
        $this->resetValidation();
        $this->modal = true;
    }

    public function editarCliente(int $id): void
    {
        $this->autorizar('gestionar_clientes');
        $c = Cliente::find($id);
        if (! $c) {
            return;
        }
        $this->editId = $c->id;
        $this->fNombre = $c->nombre;
        $this->fTipoDoc = $c->tipo_doc ?: 'CUIT';
        $this->fDoc = $c->documento ?? '';
        $this->fTel = $c->telefono ?? '';
        $this->fEmail = $c->email ?? '';
        $this->fDir = $c->direccion ?? '';
        $this->fLimite = (string) (float) $c->limite_credito;
        $this->fRiesgo = $c->riesgo;
        $this->resetValidation();
        $this->modal = true;
    }

    public function guardarCliente(): void
    {
        $this->autorizar('gestionar_clientes');
        $this->validate([
            'fNombre' => 'required|min:2',
            'fTipoDoc' => 'required|in:CUIT,CUIL,DNI',
            'fEmail' => 'nullable|email',
            'fLimite' => 'numeric|min:0',
            'fRiesgo' => 'required|in:bajo,medio,alto',
        ], attributes: ['fNombre' => 'nombre', 'fTipoDoc' => 'tipo de documento', 'fEmail' => 'email', 'fLimite' => 'límite de crédito']);

        $attrs = [
            'nombre' => $this->fNombre,
            'tipo_doc' => $this->fTipoDoc,
            'documento' => $this->fDoc ?: null,
            'telefono' => $this->fTel ?: null,
            'email' => $this->fEmail ?: null,
            'direccion' => $this->fDir ?: null,
            'limite_credito' => (float) $this->fLimite,
            'riesgo' => $this->fRiesgo,
        ];

        if ($this->editId) {
            Cliente::where('id', $this->editId)->update($attrs);
            $msg = "Cliente «{$this->fNombre}» actualizado.";
        } else {
            Cliente::create($attrs + ['activo' => true, 'aprobado' => true]);
            $msg = "Cliente «{$this->fNombre}» creado.";
        }

        $this->modal = false;
        $this->editId = null;
        $this->mensaje = $msg;
    }

    // ===== Cheques =====
    public function depositarCheque(int $id): void
    {
        $this->autorizar('ver_cuenta_cliente');
        ChequeCliente::where('id', $id)->update(['estado' => 'acreditado', 'fecha_deposito' => now()]);
        $this->mensaje = 'Cheque depositado y acreditado como pago.';
    }

    public function rechazarCheque(int $id): void
    {
        $this->autorizar('ver_cuenta_cliente');
        ChequeCliente::where('id', $id)->update(['estado' => 'rechazado', 'motivo_rechazo' => 'Rechazado por el usuario']);
        $this->mensaje = 'Cheque marcado como RECHAZADO: no impacta como pago en la cuenta del cliente.';
    }

    /**
     * Registra el cobro de una cuota (puente mínimo hacia la planilla de cobranza).
     * Si está vencida, cobra cuota + mora acumulada: la mora se asienta como interés
     * ganado (debe) e inmediatamente se cancela todo (haber) + ingreso de caja.
     */
    public function registrarCobroCuota(int $cuotaId): void
    {
        $this->autorizar('ver_cuenta_cliente');
        $cuota = Cuota::with('venta:id,numero')->find($cuotaId);
        if (! $cuota) {
            return;
        }

        // Asiento de cobro centralizado (misma lógica que la planilla de Cobranza).
        $r = Cobranza::cobrarCuota($cuota);
        $this->mensaje = $r['mensaje'];
    }

    public function render()
    {
        $sel = $this->sel ? Cliente::find($this->sel) : null;

        $filas = Cliente::query()
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nombre', 'like', "%{$this->buscar}%")
                ->orWhere('documento', 'like', "%{$this->buscar}%")))
            ->when($this->riesgo !== 'todos', fn ($q) => $q->where('riesgo', $this->riesgo))
            ->orderBy('nombre')
            ->get()
            ->map(fn (Cliente $c) => $this->aArray($c));

        // Semáforo (en vivo) de todos los clientes de la lista, en una tanda eficiente.
        $sem = \App\Support\Semaforo::paraClientes($filas->pluck('id')->all(), Carbon::today());
        $filas = $filas->map(fn ($f) => $f + ['semaforo' => $sem[$f['id']] ?? ['estado' => 'gris', 'vencidas' => 0, 'avance' => 0.0]]);

        $debe = (float) MovimientoCliente::where('tipo', 'debe')->sum('monto');
        $haber = (float) MovimientoCliente::where('tipo', 'haber')->sum('monto');

        return view('livewire.clientes.index', [
            'filas' => $filas,
            'cliente' => $sel ? $this->ficha($sel) : null,
            'stats' => [
                'total' => Cliente::count(),
                'riesgo_alto' => Cliente::where('riesgo', 'alto')->count(),
                'deuda_total' => $debe - $haber,
            ],
        ]);
    }
}
