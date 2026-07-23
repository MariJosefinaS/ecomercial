<?php

namespace App\Livewire\Tesoreria;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Cheque;
use App\Models\ChequeCliente;
use App\Models\Cuota;
use App\Models\MovimientoCaja;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Tesorería — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;

    #[Url(as: 'sub')]
    public string $tab = 'resumen';   // resumen | caja | proyeccion
    public ?string $mensaje = null;

    public function mount()
    {
        $this->autorizar('ver_tesoreria'); // defensa en profundidad (además del middleware de ruta)

        // Los cheques viven ahora en su propia pantalla (cartera + calendario + endoso).
        if (in_array($this->tab, ['depositar', 'debitar'], true)) {
            return $this->redirectRoute('tesoreria.cheques', navigate: true);
        }
    }

    /** Saldo actual de caja = ingresos - egresos registrados. */
    private function saldoCaja(): float
    {
        $in = (float) MovimientoCaja::where('tipo', 'ingreso')->sum('monto');
        $eg = (float) MovimientoCaja::where('tipo', 'egreso')->sum('monto');
        return $in - $eg;
    }

    /** Cuotas de crédito pendientes de cobro (cronograma real). */
    private function cuotasPendientes()
    {
        return Cuota::with('cliente:id,nombre')->where('estado', 'pendiente')->get();
    }

    /** Cheques de clientes a depositar (pendientes/depositados). */
    private function depositarColeccion()
    {
        return ChequeCliente::with('cliente:id,nombre')
            ->whereIn('estado', ['pendiente', 'depositado'])
            ->orderBy('fecha_deposito')->get();
    }

    /** Cheques emitidos a proveedores a debitar (pendientes/cobrados). */
    private function debitarColeccion()
    {
        return Cheque::with('proveedor:id,nombre')
            ->whereIn('estado', ['pendiente', 'cobrado'])
            ->orderBy('fecha_vencimiento')->get();
    }

    /**
     * Ingreso esperado de un día = cuotas que vencen ese día + cheques a depositar.
     * Si es hoy, además arrastra las cuotas YA vencidas e impagas (siguen a cobrar).
     */
    private function ingresoDelDia(Carbon $d, $depositar, $cuotas, bool $esHoy): float
    {
        $i = 0.0;
        foreach ($cuotas as $c) {
            $venc = $c->fecha_vencimiento;
            $cuenta = $esHoy ? $venc->lte($d) : $venc->isSameDay($d);
            if ($cuenta) {
                $i += $c->saldo();
            }
        }
        foreach ($depositar as $c) {
            if ($c->estado === 'pendiente' && $c->fecha_deposito && $c->fecha_deposito->isSameDay($d)) {
                $i += (float) $c->monto;
            }
        }
        return $i;
    }

    private function egresoDelDia(Carbon $d, $debitar): float
    {
        $e = 0;
        foreach ($debitar as $c) {
            if ($c->estado === 'pendiente' && $c->fecha_vencimiento && $c->fecha_vencimiento->isSameDay($d)) {
                $e += (float) $c->monto;
            }
        }
        return $e;
    }

    public function render()
    {
        $hoy = Carbon::today();
        $manana = $hoy->copy()->addDay();
        $fmtDateStr = fn ($d) => $d?->toDateString();

        $depositar = $this->depositarColeccion();
        $debitar = $this->debitarColeccion();
        $cuotas = $this->cuotasPendientes();

        // Movimientos de caja (tab caja)
        $movimientos = MovimientoCaja::orderByDesc('fecha')->orderByDesc('id')->limit(30)->get()
            ->map(fn (MovimientoCaja $m) => [
                'fecha' => $m->fecha?->toDateString(),
                'concepto' => $m->concepto,
                'medio' => $m->medio ?? '—',
                'tipo' => $m->tipo,
                'monto' => (float) $m->monto,
            ])->all();

        // Proyección a 7 días
        $proyeccion = [];
        $saldo = $this->saldoCaja();
        for ($i = 0; $i < 7; $i++) {
            $d = $hoy->copy()->addDays($i);
            $in = $this->ingresoDelDia($d, $depositar, $cuotas, $i === 0);
            $eg = $this->egresoDelDia($d, $debitar);
            $saldo += $in - $eg;
            $proyeccion[] = ['fecha' => $d->copy(), 'in' => $in, 'eg' => $eg, 'neto' => $in - $eg, 'saldo' => $saldo];
        }

        // Avisos del día
        $aDebitarHoy = $debitar->filter(fn ($c) => $c->estado === 'pendiente' && $c->fecha_vencimiento?->isSameDay($hoy));
        $aDebitarManana = $debitar->filter(fn ($c) => $c->estado === 'pendiente' && $c->fecha_vencimiento?->isSameDay($manana));
        $aDepositarHoy = $depositar->filter(fn ($c) => $c->estado === 'pendiente' && ($c->fecha_deposito ?? $c->fecha_vencimiento)?->isSameDay($hoy));

        return view('livewire.tesoreria.index', [
            'movimientos' => $movimientos,
            'proyeccion' => $proyeccion,
            'avisos' => [
                'debitar_hoy' => $aDebitarHoy->map(fn ($c) => ['num' => $c->numero, 'proveedor' => $c->proveedor?->nombre ?? '—', 'monto' => (float) $c->monto])->values()->all(),
                'debitar_manana' => $aDebitarManana->map(fn ($c) => ['num' => $c->numero, 'proveedor' => $c->proveedor?->nombre ?? '—', 'monto' => (float) $c->monto])->values()->all(),
                'depositar_hoy' => $aDepositarHoy->map(fn ($c) => ['num' => $c->numero, 'cliente' => $c->cliente?->nombre ?? '—', 'monto' => (float) $c->monto])->values()->all(),
            ],
            'kpis' => [
                'saldo' => $this->saldoCaja(),
                'ingresos_hoy' => $this->ingresoDelDia($hoy, $depositar, $cuotas, true),
                'egresos_hoy' => $this->egresoDelDia($hoy, $debitar),
            ],
        ]);
    }
}
