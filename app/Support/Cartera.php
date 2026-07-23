<?php

namespace App\Support;

use App\Models\Cheque;
use App\Models\ChequeCliente;
use App\Models\PedidoPago;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cartera de cheques (pedido del cliente: "cheques propios y de terceros" + "cheques para mañana").
 *  - TERCEROS = `cheques_cliente`: los que recibimos de clientes. En cartera hasta que se
 *    depositan (ingreso de caja), se endosan a un proveedor o rebotan.
 *  - PROPIOS  = `cheques`: los que emitimos a proveedores. Se debitan al vencimiento (egreso).
 * Fuente única compartida por la pantalla de Cheques y el calendario.
 */
class Cartera
{
    /** Cheques de terceros disponibles en cartera (aún no depositados/endosados/rechazados). */
    public static function terceros(?string $estado = null, string $buscar = ''): Collection
    {
        return ChequeCliente::with('cliente:id,nombre', 'endosadoA:id,nombre')
            ->when($estado && $estado !== 'todos', fn ($q) => $q->where('estado', $estado))
            ->when(trim($buscar) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('numero', 'like', "%{$buscar}%")
                ->orWhere('banco', 'like', "%{$buscar}%")
                ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$buscar}%"))))
            ->orderByRaw("FIELD(estado, 'pendiente', 'depositado', 'acreditado', 'endosado', 'rechazado')")
            ->orderBy('fecha_deposito')
            ->get();
    }

    /** Cheques propios emitidos a proveedores. */
    public static function propios(?string $estado = null, string $buscar = ''): Collection
    {
        return Cheque::with('proveedor:id,nombre')
            ->when($estado && $estado !== 'todos', fn ($q) => $q->where('estado', $estado))
            ->when(trim($buscar) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('numero', 'like', "%{$buscar}%")
                ->orWhere('banco', 'like', "%{$buscar}%")
                ->orWhereHas('proveedor', fn ($c) => $c->where('nombre', 'like', "%{$buscar}%"))))
            ->orderByRaw("FIELD(estado, 'pendiente', 'cobrado', 'rechazado')")
            ->orderBy('fecha_vencimiento')
            ->get();
    }

    /** Totales de arriba: qué hay en cartera, qué entra/sale hoy y mañana. */
    public static function kpis(?Carbon $hoy = null): array
    {
        $hoy ??= Carbon::today();
        $manana = $hoy->copy()->addDay();

        $enCartera = ChequeCliente::enCartera()->get();
        $aDebitar = Cheque::where('estado', 'pendiente')->get();

        $depHoy = $enCartera->filter(fn ($c) => $c->fechaEfectiva()?->lte($hoy));
        $depManana = $enCartera->filter(fn ($c) => $c->fechaEfectiva()?->isSameDay($manana));
        $debHoy = $aDebitar->filter(fn ($c) => $c->fecha_vencimiento?->lte($hoy));
        $debManana = $aDebitar->filter(fn ($c) => $c->fecha_vencimiento?->isSameDay($manana));

        return [
            'cartera_cant' => $enCartera->count(),
            'cartera_monto' => round((float) $enCartera->sum('monto'), 2),
            'depositar_hoy_cant' => $depHoy->count(),
            'depositar_hoy' => round((float) $depHoy->sum('monto'), 2),
            'manana_ingreso' => round((float) $depManana->sum('monto'), 2),
            'manana_ingreso_cant' => $depManana->count(),
            'manana_egreso' => round((float) $debManana->sum('monto'), 2),
            'manana_egreso_cant' => $debManana->count(),
            'debitar_hoy' => round((float) $debHoy->sum('monto'), 2),
            'debitar_hoy_cant' => $debHoy->count(),
            'propios_cant' => $aDebitar->count(),
            'propios_monto' => round((float) $aDebitar->sum('monto'), 2),
        ];
    }

    /**
     * Calendario de cheques: un renglón por día con los que INGRESAN (terceros a depositar)
     * y los que EGRESAN (propios a debitar). Los atrasados se agrupan en el día de hoy.
     * @return array<int,array<string,mixed>>
     */
    public static function calendario(int $dias = 30, ?Carbon $hoy = null): array
    {
        $hoy ??= Carbon::today();
        $fin = $hoy->copy()->addDays($dias - 1);

        $entran = ChequeCliente::with('cliente:id,nombre')->enCartera()->get();
        $salen = Cheque::with('proveedor:id,nombre')->where('estado', 'pendiente')->get();

        // Los vencidos/atrasados se muestran en HOY (siguen pendientes de resolver).
        $slot = fn (?Carbon $f) => ($f === null || $f->lt($hoy)) ? $hoy->toDateString() : $f->toDateString();

        $porDia = [];
        foreach ($entran as $c) {
            $f = $c->fechaEfectiva();
            $k = $slot($f);
            if ($k > $fin->toDateString()) {
                continue;
            }
            $porDia[$k]['ingresos'][] = [
                'id' => $c->id, 'numero' => $c->numero, 'banco' => $c->banco,
                'quien' => $c->cliente?->nombre ?? '—', 'monto' => (float) $c->monto,
                'atrasado' => $f !== null && $f->lt($hoy),
            ];
        }
        foreach ($salen as $c) {
            $f = $c->fecha_vencimiento;
            $k = $slot($f);
            if ($k > $fin->toDateString()) {
                continue;
            }
            $porDia[$k]['egresos'][] = [
                'id' => $c->id, 'numero' => $c->numero, 'banco' => $c->banco,
                'quien' => $c->proveedor?->nombre ?? '—', 'monto' => (float) $c->monto,
                'atrasado' => $f !== null && $f->lt($hoy),
            ];
        }

        $out = [];
        for ($i = 0; $i < $dias; $i++) {
            $d = $hoy->copy()->addDays($i);
            $k = $d->toDateString();
            $ing = $porDia[$k]['ingresos'] ?? [];
            $egr = $porDia[$k]['egresos'] ?? [];
            if (! $ing && ! $egr) {
                continue;   // solo días con movimiento
            }
            $out[] = [
                'fecha' => $d,
                'ingresos' => $ing,
                'egresos' => $egr,
                'total_ingreso' => round(array_sum(array_column($ing, 'monto')), 2),
                'total_egreso' => round(array_sum(array_column($egr, 'monto')), 2),
            ];
        }

        return $out;
    }

    /** IDs de cheques de terceros con un endoso ya solicitado (pendiente o autorizado, sin procesar). */
    public static function chequesConEndosoEnCurso(): array
    {
        return PedidoPago::whereNotNull('cheque_cliente_id')
            ->whereIn('estado', ['pendiente', 'autorizado'])
            ->pluck('cheque_cliente_id')->all();
    }
}
