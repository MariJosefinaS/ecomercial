<?php

namespace App\Livewire\Dashboard;

use App\Models\Venta;
use Livewire\Component;

class TopSellers extends Component
{
    /** Ranking real: ventas aprobadas por vendedor (monto + unidades). */
    public function sellers(): array
    {
        // Unidades por vendedor (join con items en query aparte para no inflar la suma de total).
        $unidades = Venta::query()
            ->where('ventas.estado', 'aprobada')
            ->join('venta_items', 'venta_items.venta_id', '=', 'ventas.id')
            ->groupBy('ventas.vendedor_id')
            ->selectRaw('ventas.vendedor_id, SUM(venta_items.cantidad) as uni')
            ->pluck('uni', 'vendedor_id');

        $top = Venta::with('vendedor:id,name')
            ->where('estado', 'aprobada')
            ->select('vendedor_id')
            ->selectRaw('SUM(total) as monto')
            ->groupBy('vendedor_id')
            ->orderByDesc('monto')
            ->limit(5)
            ->get();

        $max = (float) ($top->max('monto') ?: 1);

        return $top->values()->map(function ($v, $i) use ($max, $unidades) {
            $nombre = $v->vendedor?->name ?? '—';
            $ini = collect(explode(' ', $nombre))->filter()->take(2)
                ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: 'NN';

            return [
                'initials' => $ini,
                'variant' => $i === 0 ? 'brand' : ($i % 2 ? 'gray' : 'blue'),
                'name' => $nombre,
                'total' => '$' . number_format((float) $v->monto, 2, ',', '.'),
                'units' => (int) ($unidades[$v->vendedor_id] ?? 0),
                'pct' => (int) round((float) $v->monto / $max * 100),
                'bar' => $i === 0 ? 'bg-brand' : 'bg-graphite',
            ];
        })->all();
    }

    public function render()
    {
        return view('livewire.dashboard.top-sellers', ['sellers' => $this->sellers()]);
    }
}
