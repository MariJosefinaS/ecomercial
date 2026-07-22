<?php

namespace App\Support;

use App\Models\Cuota;
use App\Models\PlanCredito;
use App\Models\Venta;
use Illuminate\Support\Carbon;

/**
 * Semáforo de riesgo del cliente, derivado EN VIVO de sus créditos activos (cuotas pendientes):
 *   🟢 verde   = al día (sin cuotas vencidas)
 *   🟡 amarillo = en atraso (tiene vencidas pero por debajo del umbral de incobrable)
 *   🔴 rojo    = no paga / INCOBRABLE (≥ umbral de cuotas vencidas de su plan)
 *   ⚪ gris    = sin crédito activo
 * El estado del cliente = el PEOR de sus créditos activos. La graduación es el % de avance (cuotas pagadas).
 */
class Semaforo
{
    public const VERDE = 'verde';
    public const AMARILLO = 'amarillo';
    public const ROJO = 'rojo';
    public const GRIS = 'gris';

    public static function label(string $estado): string
    {
        return [
            self::VERDE => 'Al día',
            self::AMARILLO => 'En atraso',
            self::ROJO => 'No paga (incobrable)',
            self::GRIS => 'Sin crédito activo',
        ][$estado] ?? '—';
    }

    /** Clases Tailwind [punto, texto, fondo] por estado (literales para que Tailwind las escanee). */
    public static function clases(string $estado): array
    {
        return [
            self::VERDE => ['bg-green-500', 'text-green-700', 'bg-green-100'],
            self::AMARILLO => ['bg-amber-500', 'text-amber-700', 'bg-amber-100'],
            self::ROJO => ['bg-red-500', 'text-red-700', 'bg-red-100'],
            self::GRIS => ['bg-gray-300', 'text-graphite', 'bg-gray-100'],
        ][$estado] ?? ['bg-gray-300', 'text-graphite', 'bg-gray-100'];
    }

    /** Peor estado entre varios (rojo > amarillo > verde > gris). */
    private static function peor(string $a, string $b): string
    {
        $rank = [self::GRIS => 0, self::VERDE => 1, self::AMARILLO => 2, self::ROJO => 3];

        return ($rank[$a] ?? 0) >= ($rank[$b] ?? 0) ? $a : $b;
    }

    /** Estado de UN crédito según sus cuotas vencidas y el umbral de incobrable de su plan. */
    private static function estadoCredito(int $vencidas, int $umbral): string
    {
        if ($vencidas <= 0) {
            return self::VERDE;
        }
        if ($umbral > 0 && $vencidas >= $umbral) {
            return self::ROJO;
        }

        return self::AMARILLO;
    }

    /**
     * Semáforo de un lote de clientes (eficiente: pocas queries agregadas). Para la lista.
     *
     * @param  array<int>  $clienteIds
     * @return array<int, array{estado:string, vencidas:int, avance:float, incobrables:int, creditos:int}>
     */
    public static function paraClientes(array $clienteIds, Carbon $al): array
    {
        $out = [];
        foreach ($clienteIds as $id) {
            $out[$id] = ['estado' => self::GRIS, 'vencidas' => 0, 'avance' => 0.0, 'incobrables' => 0, 'creditos' => 0];
        }
        if (empty($clienteIds)) {
            return $out;
        }

        // Créditos activos (con cuotas pendientes) de esos clientes.
        $ventas = Venta::where('credito', true)
            ->whereIn('cliente_id', $clienteIds)
            ->whereHas('cuotas', fn ($q) => $q->where('estado', 'pendiente'))
            ->get(['id', 'cliente_id', 'plan_codigo']);
        if ($ventas->isEmpty()) {
            return $out;
        }

        $ventaIds = $ventas->pluck('id')->all();
        $umbrales = PlanCredito::pluck('cuotas_incobrable', 'codigo');

        // Vencidas por venta.
        $vencidasPorVenta = Cuota::selectRaw('venta_id, count(*) as c')
            ->whereIn('venta_id', $ventaIds)->where('estado', 'pendiente')
            ->whereDate('fecha_vencimiento', '<', $al->toDateString())
            ->groupBy('venta_id')->pluck('c', 'venta_id');

        // Total y pagadas por venta (para % de avance).
        $totales = Cuota::selectRaw("venta_id, count(*) as total, sum(case when estado='cobrada' then 1 else 0 end) as pagadas")
            ->whereIn('venta_id', $ventaIds)->groupBy('venta_id')->get()->keyBy('venta_id');

        // Acumular por cliente.
        $acc = []; // cliente_id => [estado, vencidas, total, pagadas, incobrables, creditos]
        foreach ($ventas as $v) {
            $venc = (int) ($vencidasPorVenta[$v->id] ?? 0);
            $umbral = (int) ($umbrales[$v->plan_codigo] ?? 0);
            $estado = self::estadoCredito($venc, $umbral);
            $t = $totales[$v->id] ?? null;

            $a = $acc[$v->cliente_id] ?? ['estado' => self::GRIS, 'vencidas' => 0, 'total' => 0, 'pagadas' => 0, 'incobrables' => 0, 'creditos' => 0];
            $a['estado'] = self::peor($a['estado'], $estado);
            $a['vencidas'] += $venc;
            $a['total'] += (int) ($t->total ?? 0);
            $a['pagadas'] += (int) ($t->pagadas ?? 0);
            $a['incobrables'] += $estado === self::ROJO ? 1 : 0;
            $a['creditos'] += 1;
            $acc[$v->cliente_id] = $a;
        }

        foreach ($acc as $cid => $a) {
            $out[$cid] = [
                'estado' => $a['estado'],
                'vencidas' => $a['vencidas'],
                'avance' => $a['total'] > 0 ? round($a['pagadas'] / $a['total'] * 100, 1) : 0.0,
                'incobrables' => $a['incobrables'],
                'creditos' => $a['creditos'],
            ];
        }

        return $out;
    }

    /** Semáforo de un solo cliente (para la ficha) + detalle por crédito. */
    public static function deCliente(int $clienteId, Carbon $al): array
    {
        $resumen = self::paraClientes([$clienteId], $al)[$clienteId];

        $ventas = Venta::where('credito', true)->where('cliente_id', $clienteId)
            ->whereHas('cuotas', fn ($q) => $q->where('estado', 'pendiente'))
            ->with('cuotas')->get();
        $umbrales = PlanCredito::pluck('cuotas_incobrable', 'codigo');

        $creditos = $ventas->map(function (Venta $v) use ($umbrales, $al) {
            $pend = $v->cuotas->where('estado', 'pendiente');
            $venc = $pend->filter(fn (Cuota $c) => $c->estaVencida($al))->count();
            $umbral = (int) ($umbrales[$v->plan_codigo] ?? 0);
            $total = $v->cuotas->count();
            $pagadas = $v->cuotas->where('estado', 'cobrada')->count();

            return [
                'venta' => $v->numero,
                'plan' => $v->plan_nombre ?? '—',
                'estado' => self::estadoCredito($venc, $umbral),
                'vencidas' => $venc,
                'umbral' => $umbral,
                'avance' => $total > 0 ? round($pagadas / $total * 100, 1) : 0.0,
                'pagadas' => $pagadas,
                'total' => $total,
            ];
        })->sortByDesc('vencidas')->values()->all();

        return $resumen + ['creditos_detalle' => $creditos];
    }
}
