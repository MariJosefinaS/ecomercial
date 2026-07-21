<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    @php $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.'); @endphp
    <style>
        @page { margin: 14mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 11px; margin: 0; }
        .head { width: 100%; border-bottom: 3px solid #EC6A19; padding-bottom: 8px; margin-bottom: 12px; }
        .head td { vertical-align: top; }
        .marca { font-weight: bold; font-size: 20px; letter-spacing: .5px; color: #333; }
        .marca span { color: #EC6A19; }
        .sub { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .recibo-box { text-align: right; }
        .recibo-lbl { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .recibo-num { font-size: 16px; font-weight: bold; color: #EC6A19; }
        .recibo-fecha { font-size: 11px; color: #374151; }
        .grid { width: 100%; margin-bottom: 12px; }
        .grid td { width: 50%; vertical-align: top; padding-right: 12px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 10px; }
        .card h4 { margin: 0 0 4px; font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; }
        .card .big { font-size: 13px; font-weight: bold; color: #1f2937; }
        .card .row { font-size: 10px; color: #4b5563; margin-top: 1px; }
        table.t { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.t th, table.t td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        table.t th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; color: #6b7280; }
        .num { text-align: right; }
        .section-title { font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; margin: 12px 0 2px; font-weight: bold; }
        .pago { width: 100%; margin-top: 6px; }
        .pago .total-box { background: #111827; color: #fff; border-radius: 8px; padding: 10px 14px; text-align: right; }
        .pago .total-box .lbl { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #d1d5db; }
        .pago .total-box .val { font-size: 20px; font-weight: bold; }
        .estado { width: 100%; margin-top: 12px; border-collapse: collapse; }
        .estado td { border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; text-align: center; width: 25%; }
        .estado .n { font-size: 16px; font-weight: bold; color: #1f2937; }
        .estado .l { font-size: 8px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        .estado .brand { color: #EC6A19; }
        .foot { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 9px; color: #9ca3af; text-align: center; }
        .sello { margin-top: 4px; font-size: 10px; color: #16a34a; font-weight: bold; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="marca">E<span>.Comercial</span></div>
                <div class="sub">Comprobante de cobro</div>
            </td>
            <td class="recibo-box">
                <div class="recibo-lbl">Recibo N°</div>
                <div class="recibo-num">{{ $numero }}</div>
                <div class="recibo-fecha">{{ $fecha?->format('d/m/Y H:i') ?? '—' }}</div>
            </td>
        </tr>
    </table>

    {{-- Cliente + Crédito --}}
    <table class="grid">
        <tr>
            <td>
                <div class="card">
                    <h4>Cliente</h4>
                    <div class="big">{{ $cliente?->nombre ?? '—' }}</div>
                    @if ($cliente?->documento)<div class="row">{{ strtoupper($cliente->tipo_doc ?? 'DOC') }}: {{ $cliente->documento }}</div>@endif
                    @if ($cliente?->direccion)<div class="row">{{ $cliente->direccion }}</div>@endif
                    @if ($cliente?->email)<div class="row">{{ $cliente->email }}</div>@endif
                </div>
            </td>
            <td style="padding-right:0">
                <div class="card">
                    <h4>Crédito / Plan</h4>
                    <div class="big">Venta {{ $venta?->numero ?? '—' }}</div>
                    <div class="row">Plan: {{ $venta?->plan_nombre ?? '—' }} @if ($venta?->modalidad)· {{ ucfirst($venta->modalidad) }}@endif</div>
                    <div class="row">Cuota imputada: <b>#{{ $cuota?->numero ?? '—' }}</b> de {{ $total_cuotas }}</div>
                    <div class="row">Cobrador: {{ $cobrador?->name ?? '—' }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Productos del crédito --}}
    @if (! empty($productos))
        <div class="section-title">Detalle de la compra</div>
        <table class="t">
            <thead><tr><th>Producto</th><th class="num">Cant.</th><th class="num">Precio unit.</th><th class="num">Subtotal</th></tr></thead>
            <tbody>
                @foreach ($productos as $p)
                    <tr>
                        <td>{{ $p['nombre'] }}</td>
                        <td class="num">{{ $p['cantidad'] }}</td>
                        <td class="num">{{ $money($p['precio']) }}</td>
                        <td class="num">{{ $money($p['subtotal']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Medios del pago + total --}}
    <div class="section-title">Pago recibido</div>
    <table class="pago">
        <tr>
            <td style="vertical-align:top; padding-right:12px;">
                <table class="t">
                    <thead><tr><th>Medio</th><th>Detalle</th><th class="num">Importe</th></tr></thead>
                    <tbody>
                        @foreach ($medios as $m)
                            <tr>
                                <td>{{ $m['medio'] }}</td>
                                <td>@if ($m['banco']){{ $m['banco'] }}@endif @if ($m['cheque_numero'])· Cheque {{ $m['cheque_numero'] }}@endif</td>
                                <td class="num">{{ $money($m['monto']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($excedente > 0)
                    <div class="row" style="margin-top:4px; font-size:10px; color:#16a34a;">Excedente aplicado a adelantar cuota/s: <b>{{ $money($excedente) }}</b></div>
                @endif
            </td>
            <td style="width:38%; vertical-align:top;">
                <div class="total-box">
                    <div class="lbl">Total cobrado</div>
                    <div class="val">{{ $money($monto) }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Estado del crédito tras el pago --}}
    <div class="section-title">Estado del crédito</div>
    <table class="estado">
        <tr>
            <td><div class="n">{{ $cuotas_pagadas }}</div><div class="l">Cuotas pagadas</div></td>
            <td><div class="n">{{ $cuotas_faltan }}</div><div class="l">Cuotas que faltan</div></td>
            <td><div class="n brand">{{ $money($restante_termino) }}</div><div class="l">Restante si paga en término</div></td>
            <td><div class="n">{{ $prox_vence?->format('d/m/Y') ?? '—' }}</div><div class="l">Próximo vencimiento</div></td>
        </tr>
    </table>

    <div class="foot">
        Este comprobante certifica el pago recibido. Consérvelo como respaldo.
        <div class="sello">✔ Pago registrado — E.Comercial</div>
    </div>
</body>
</html>
