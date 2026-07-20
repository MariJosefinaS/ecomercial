<?php

namespace App\Livewire\Dashboard;

use App\Models\Local;
use App\Models\Producto;
use Livewire\Component;

class PriceDifferenceAlerts extends Component
{
    /** Productos con distinto precio de venta entre los dos locales. */
    public function rows(): array
    {
        $locales = Local::orderBy('id')->take(2)->get();
        if ($locales->count() < 2) {
            return [];
        }
        [$a, $b] = [$locales[0], $locales[1]];
        $fmt = fn ($n) => '$' . number_format((float) $n, 2, ',', '.');

        return Producto::with(['categoria:id,icono', 'stock'])->orderBy('nombre')->get()
            ->map(function (Producto $p) use ($a, $b, $fmt) {
                $pa = $p->stock->firstWhere('local_id', $a->id)?->precio_venta;
                $pb = $p->stock->firstWhere('local_id', $b->id)?->precio_venta;

                // Sólo alerta si el producto tiene precio en AMBOS locales y difieren.
                if ($pa === null || $pb === null || (float) $pa === (float) $pb) {
                    return null;
                }

                $higher = (float) $pa >= (float) $pb ? 'a' : 'b';

                return [
                    'icon' => $p->categoria?->icono ?? 'inventory_2',
                    'name' => $p->nombre,
                    'a' => $fmt($pa),
                    'b' => $fmt($pb),
                    'higher' => $higher,
                    'diff' => $fmt(abs((float) $pa - (float) $pb)),
                    'dir' => $higher === 'b' ? 'up' : 'down',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.dashboard.price-difference-alerts', ['rows' => $this->rows()]);
    }
}
