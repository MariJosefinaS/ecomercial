<?php

namespace App\Livewire\Dashboard;

use App\Models\Compra;
use App\Models\SolicitudCompra;
use App\Models\Venta;
use Livewire\Component;

class PendingApprovals extends Component
{
    /** Aprobaciones pendientes reales (ventas + compras + solicitudes en estado 'pendiente'). */
    public function approvals(): array
    {
        $ventas = Venta::with(['vendedor:id,name', 'local:id,nombre'])->where('estado', 'pendiente')->orderByDesc('id')->get()
            ->map(fn (Venta $v) => [
                'id' => $v->numero, 'type' => 'Venta', 'variant' => 'brand', 'icon' => 'point_of_sale',
                'title' => ($v->cliente_nombre ?: 'Venta') . ' · ' . $v->items()->count() . ' ítem(s)',
                'who' => $v->vendedor?->name ?? '—', 'local' => $v->local?->nombre ?? '—',
                'amount' => '$' . number_format((float) $v->total, 2, ',', '.'),
                'url' => route('ventas', ['highlight' => $v->numero]),
            ]);

        $compras = Compra::with(['proveedor:id,nombre', 'local:id,nombre', 'usuario:id,name'])->where('estado', 'pendiente')->orderByDesc('id')->get()
            ->map(fn (Compra $c) => [
                'id' => $c->numero, 'type' => 'Compra', 'variant' => 'blue', 'icon' => 'shopping_cart',
                'title' => 'Orden de compra · ' . ($c->proveedor?->nombre ?? '—'),
                'who' => $c->usuario?->name ?? '—', 'local' => $c->local?->nombre ?? '—',
                'amount' => '$' . number_format((float) $c->total, 2, ',', '.'),
                'url' => route('compras', ['highlight' => $c->numero]),
            ]);

        $solicitudes = SolicitudCompra::with(['producto:id,nombre', 'local:id,nombre', 'solicitante:id,name'])->where('estado', 'pendiente')->orderByDesc('id')->get()
            ->map(fn (SolicitudCompra $s) => [
                'id' => $s->numero, 'type' => 'Solicitud de stock', 'variant' => 'gray', 'icon' => 'inventory_2',
                'title' => 'Reposición · ' . ($s->producto?->nombre ?? '—') . ' (x' . $s->cantidad . ')',
                'who' => $s->solicitante?->name ?? '—', 'local' => $s->local?->nombre ?? '—',
                'amount' => '', 'url' => route('compras'),
            ]);

        return $ventas->concat($compras)->concat($solicitudes)->all();
    }

    public function render()
    {
        return view('livewire.dashboard.pending-approvals', ['approvals' => $this->approvals()]);
    }
}
