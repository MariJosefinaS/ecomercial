<?php

namespace App\Livewire\Comprobantes;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Comprobante;
use App\Support\Comprobantes as Fiscal;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Libro de comprobantes: Facturas (A/B/C), Notas de crédito/débito, Recibos y Órdenes de pago.
 * Se emiten solos desde el circuito (venta aprobada, cobro, devolución, pago procesado);
 * acá se consultan, se totalizan por período y se imprimen.
 */
#[Layout('components.layouts.app')]
#[Title('Comprobantes — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;

    #[Url(as: 'sub')]
    public string $tab = 'todos';   // todos | factura | nota_credito | recibo | orden_pago

    public string $buscar = '';
    public string $desde = '';
    public string $hasta = '';
    public ?string $mensaje = null;

    // Anulación
    public ?int $anulandoId = null;
    public string $motivoAnulacion = '';

    public function mount(): void
    {
        $this->autorizar('ver_comprobantes');
        $this->desde = Carbon::today()->startOfMonth()->toDateString();
        $this->hasta = Carbon::today()->endOfMonth()->toDateString();
    }

    public function setTab(string $t): void { $this->tab = $t; }

    private function base()
    {
        return Comprobante::with('cliente:id,nombre', 'proveedor:id,nombre')
            ->when($this->tab !== 'todos', fn ($q) => $q->where('tipo', $this->tab))
            ->when($this->desde !== '', fn ($q) => $q->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('fecha', '<=', $this->hasta))
            ->when(trim($this->buscar) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('numero_completo', 'like', "%{$this->buscar}%")
                ->orWhere('concepto', 'like', "%{$this->buscar}%")
                ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$this->buscar}%"))
                ->orWhereHas('proveedor', fn ($c) => $c->where('nombre', 'like', "%{$this->buscar}%"))));
    }

    public function pedirAnulacion(int $id): void
    {
        $this->autorizar('emitir_comprobantes');
        $this->anulandoId = $id;
        $this->motivoAnulacion = '';
    }

    public function anular(): void
    {
        $this->autorizar('emitir_comprobantes');
        $c = Comprobante::find($this->anulandoId);
        if ($c && Fiscal::anular($c, $this->motivoAnulacion, auth()->id())) {
            $this->mensaje = "{$c->etiqueta()} anulado. El número queda usado (no se reutiliza).";
        }
        $this->reset(['anulandoId', 'motivoAnulacion']);
    }

    public function render()
    {
        $filas = $this->base()->orderByDesc('fecha')->orderByDesc('id')->limit(300)->get();

        // Totales del período (los anulados no suman).
        $vigentes = $filas->where('estado', 'emitido');

        return view('livewire.comprobantes.index', [
            'filas' => $filas,
            'totales' => [
                'cantidad' => $vigentes->count(),
                'neto' => round((float) $vigentes->sum('neto'), 2),
                'iva' => round((float) $vigentes->sum('iva'), 2),
                'total' => round((float) $vigentes->sum('total'), 2),
                'facturado' => round((float) $vigentes->where('tipo', 'factura')->sum('total'), 2),
                'notas_credito' => round((float) $vigentes->where('tipo', 'nota_credito')->sum('total'), 2),
            ],
            'condicionEmpresa' => Fiscal::CONDICIONES[Fiscal::condicionEmpresa()] ?? '—',
            'puntoVenta' => Fiscal::puntoVenta(),
            'ivaPct' => Fiscal::ivaPct(),
        ]);
    }
}
