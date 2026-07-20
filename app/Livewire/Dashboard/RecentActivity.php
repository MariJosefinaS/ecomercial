<?php

namespace App\Livewire\Dashboard;

use App\Models\ActivityLog;
use Illuminate\Support\Carbon;
use Livewire\Component;

class RecentActivity extends Component
{
    private const MAPA = [
        'venta'  => ['icon' => 'shopping_cart',          'tone' => 'brand'],
        'compra' => ['icon' => 'local_shipping',         'tone' => 'blue'],
        'stock'  => ['icon' => 'inventory_2',            'tone' => 'blue'],
        'alerta' => ['icon' => 'notification_important', 'tone' => 'red'],
        'login'  => ['icon' => 'lock',                   'tone' => 'gray'],
    ];

    /** Feed real desde activity_logs. */
    public function events(): array
    {
        Carbon::setLocale('es');

        return ActivityLog::latest()->limit(6)->get()->map(function (ActivityLog $a) {
            $m = self::MAPA[$a->tipo] ?? ['icon' => 'bolt', 'tone' => 'gray'];

            return [
                'icon' => $m['icon'],
                'tone' => $m['tone'],
                'title' => $a->titulo,
                'detail' => e($a->detalle ?? ''),
                'ago' => $a->created_at?->diffForHumans() ?? '',
            ];
        })->all();
    }

    public function render()
    {
        return view('livewire.dashboard.recent-activity', ['events' => $this->events()]);
    }
}
