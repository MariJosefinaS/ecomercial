<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    @php $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.'); @endphp
    <style>
        @page { margin: 16mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
        .head { width: 100%; border-bottom: 3px solid #EC6A19; padding-bottom: 10px; margin-bottom: 16px; }
        .head td { vertical-align: top; }
        .marca { font-weight: bold; font-size: 20px; color: #333; } .marca span { color: #EC6A19; }
        .sub { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .recibo-box { text-align: right; }
        .recibo-num { font-size: 16px; font-weight: bold; color: #EC6A19; }
        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; }
        .row { width: 100%; }
        .row td { padding: 4px 0; font-size: 12px; }
        .row td.k { color: #6b7280; width: 38%; }
        .row td.v { font-weight: bold; color: #1f2937; }
        .total { background: #111827; color: #fff; border-radius: 8px; padding: 12px 16px; text-align: right; margin-bottom: 16px; }
        .total .l { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #d1d5db; }
        .total .v { font-size: 24px; font-weight: bold; }
        .decl { font-size: 11px; color: #374151; margin: 8px 0 30px; line-height: 1.5; }
        .firmas { width: 100%; margin-top: 40px; border-collapse: collapse; }
        .firmas td { text-align: center; font-size: 11px; color: #374151; padding-top: 6px; width: 42%; border-top: 1px solid #111827; }
        .firmas td.sep { border: 0; width: 16%; }
        .foot { margin-top: 26px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td><div class="marca">E<span>.Comercial</span></div><div class="sub">Recibo de pago a empleado</div></td>
            <td class="recibo-box"><div class="sub">Recibo N°</div><div class="recibo-num">{{ $pago->numero() }}</div></td>
        </tr>
    </table>

    <div class="box">
        <table class="row">
            <tr><td class="k">Empleado</td><td class="v">{{ $pago->empleado?->name ?? '—' }}</td></tr>
            <tr><td class="k">Fecha</td><td class="v">{{ $pago->fecha?->format('d/m/Y') }}</td></tr>
            <tr><td class="k">Hora</td><td class="v">{{ $pago->fecha?->format('H:i') }}</td></tr>
            <tr><td class="k">Medio de pago</td><td class="v">{{ $pago->medioLabel() }}@if ($pago->banco) · {{ $pago->banco }}@endif</td></tr>
            <tr><td class="k">Tesorero (paga)</td><td class="v">{{ $pago->tesorero?->name ?? '—' }}</td></tr>
            @if ($pago->nota)<tr><td class="k">Concepto</td><td class="v">{{ $pago->nota }}</td></tr>@endif
            <tr><td class="k">Saldo previo del empleado</td><td class="v">{{ $money($pago->saldo_antes) }}</td></tr>
            <tr><td class="k">Saldo tras el pago</td><td class="v">{{ $money($pago->saldo_despues) }}</td></tr>
        </table>
    </div>

    <div class="total">
        <div class="l">Importe pagado</div>
        <div class="v">{{ $money($pago->monto) }}</div>
    </div>

    <p class="decl">Recibí de <b>E.Comercial</b> la suma de <b>{{ $money($pago->monto) }}</b> en concepto de {{ $pago->nota ?: 'pago de comisiones/servicios de cobranza' }},
    abonada por {{ mb_strtolower($pago->medioLabel()) }} el {{ $pago->fecha?->format('d/m/Y') }} a las {{ $pago->fecha?->format('H:i') }} hs. Firmo en conformidad.</p>

    <table class="firmas">
        <tr>
            <td>Firma del empleado<br><span style="color:#9ca3af;">{{ $pago->empleado?->name ?? '' }}</span></td>
            <td class="sep"></td>
            <td>Firma del tesorero<br><span style="color:#9ca3af;">{{ $pago->tesorero?->name ?? '' }}</span></td>
        </tr>
    </table>

    <div class="foot">Comprobante interno de pago · E.Comercial · {{ $pago->numero() }}</div>
</body>
</html>
