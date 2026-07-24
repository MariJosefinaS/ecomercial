<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    @php
        $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.');
        $esFactura = in_array($c->tipo, ['factura', 'nota_credito', 'nota_debito'], true);
        $destinatario = $c->cliente ?? $c->proveedor;
    @endphp
    <style>
        @page { margin: 14mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 11px; margin: 0; }
        .head { width: 100%; border-bottom: 3px solid #EC6A19; padding-bottom: 8px; margin-bottom: 12px; }
        .head td { vertical-align: top; }
        .marca { font-weight: bold; font-size: 20px; letter-spacing: .5px; color: #333; }
        .marca span { color: #EC6A19; }
        .sub { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .doc-box { text-align: right; }
        .doc-lbl { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .doc-num { font-size: 16px; font-weight: bold; color: #EC6A19; }
        .doc-fecha { font-size: 11px; color: #374151; }
        .letra { display: inline-block; border: 2px solid #1f2937; border-radius: 6px; padding: 2px 12px; font-size: 26px; font-weight: bold; line-height: 1.1; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 10px; margin-bottom: 10px; }
        .card h4 { margin: 0 0 4px; font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; }
        .card .big { font-size: 13px; font-weight: bold; color: #1f2937; }
        .card .row { font-size: 10px; color: #4b5563; margin-top: 1px; }
        table.t { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.t th, table.t td { padding: 6px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        table.t th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; color: #6b7280; }
        .num { text-align: right; }
        .tot { width: 100%; margin-top: 10px; }
        .tot td { vertical-align: top; }
        .tot .lineas { font-size: 11px; }
        .tot .lineas div { padding: 3px 0; }
        .total-box { background: #111827; color: #fff; border-radius: 8px; padding: 10px 14px; text-align: right; }
        .total-box .lbl { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #d1d5db; }
        .total-box .val { font-size: 20px; font-weight: bold; }
        .anulado { border: 3px solid #dc2626; color: #dc2626; text-align: center; font-size: 22px; font-weight: bold; padding: 8px; margin-bottom: 12px; border-radius: 8px; letter-spacing: 4px; }
        .foot { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 9px; color: #9ca3af; text-align: center; }
        .nota { font-size: 9px; color: #9ca3af; margin-top: 6px; }
    </style>
</head>
<body>

@if ($c->estaAnulado())
    <div class="anulado">A N U L A D O</div>
@endif

<table class="head">
    <tr>
        <td>
            <div class="marca">E<span>.</span>COMERCIAL</div>
            <div class="sub">Equipamiento comercial y hogar</div>
            <div class="row" style="font-size:10px;color:#6b7280;margin-top:4px;">{{ $empresa['condicion'] }}</div>
        </td>
        <td class="doc-box">
            @if ($c->letra)<div class="letra">{{ $c->letra }}</div>@endif
            <div class="doc-lbl">{{ $c->tipoLabel() }}</div>
            <div class="doc-num">{{ $c->numero_completo }}</div>
            <div class="doc-fecha">{{ $c->fecha?->format('d/m/Y') }}</div>
            @if ($c->fecha_vencimiento)
                <div class="doc-fecha" style="font-size:10px;color:#6b7280;">Vence {{ $c->fecha_vencimiento->format('d/m/Y') }}</div>
            @endif
        </td>
    </tr>
</table>

<div class="card">
    <h4>{{ $c->proveedor_id ? 'Pagar a' : 'Cliente' }}</h4>
    <div class="big">{{ $destinatario?->nombre ?? 'Consumidor final' }}</div>
    @if ($c->cliente)
        @if ($c->cliente->numero_cuenta)<div class="row">Cuenta N° {{ $c->cliente->numero_cuenta }}</div>@endif
        <div class="row">{{ $c->cliente->tipo_doc }} {{ $c->cliente->documento ?: '—' }} · {{ $condiciones[$c->cliente->tipo_iva] ?? $c->cliente->tipo_iva }}</div>
        @if ($c->cliente->ingresos_brutos)<div class="row">Ingresos Brutos: {{ $c->cliente->ingresos_brutos }}</div>@endif
        @if ($domicilio)<div class="row">{{ $domicilio }}</div>@endif
    @elseif ($c->proveedor)
        <div class="row">CUIT {{ $c->proveedor->cuit ?: '—' }}</div>
        @if ($c->proveedor->direccion)<div class="row">{{ $c->proveedor->direccion }}</div>@endif
    @endif
</div>

<table class="t">
    <thead>
        <tr>
            <th>Concepto</th>
            @if ($c->discriminaIva())
                <th class="num">Neto</th>
                <th class="num">IVA {{ rtrim(rtrim(number_format((float) $c->iva_pct, 2, ',', '.'), '0'), ',') }}%</th>
            @endif
            <th class="num">Importe</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $c->concepto }}</td>
            @if ($c->discriminaIva())
                <td class="num">{{ $money($c->neto) }}</td>
                <td class="num">{{ $money($c->iva) }}</td>
            @endif
            <td class="num">{{ $money($c->total) }}</td>
        </tr>
        @foreach ($detalle as $d)
            <tr>
                <td>{{ $d['descripcion'] }}</td>
                @if ($c->discriminaIva())<td class="num"></td><td class="num"></td>@endif
                <td class="num">{{ $d['importe'] !== null ? $money($d['importe']) : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="tot">
    <tr>
        <td style="width:55%;" class="lineas">
            @if ($esFactura)
                <div>Neto gravado: <b>{{ $money($c->neto) }}</b></div>
                <div>IVA {{ rtrim(rtrim(number_format((float) $c->iva_pct, 2, ',', '.'), '0'), ',') }}%: <b>{{ $money($c->iva) }}</b></div>
                @if (! $c->discriminaIva())
                    <div class="nota">El IVA no se discrimina en los comprobantes tipo {{ $c->letra }} (va incluido en el precio).</div>
                @endif
            @endif
        </td>
        <td style="width:45%;">
            <div class="total-box">
                <div class="lbl">Total</div>
                <div class="val">{{ $money($c->total) }}</div>
            </div>
        </td>
    </tr>
</table>

<div class="foot">
    Documento generado por E.Comercial el {{ now()->format('d/m/Y H:i') }} · Emitió: {{ $c->emisor?->name ?? '—' }}<br>
    Comprobante de gestión interna — no reemplaza la documentación fiscal electrónica de AFIP/ARCA.
</div>

</body>
</html>
