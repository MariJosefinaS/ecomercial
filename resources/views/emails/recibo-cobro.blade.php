@php $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.'); @endphp
<div style="font-family: Arial, Helvetica, sans-serif; max-width: 560px; margin: 0 auto; color: #1f2937;">
    <div style="border-bottom: 3px solid #EC6A19; padding-bottom: 12px; margin-bottom: 16px;">
        <span style="font-size: 22px; font-weight: bold; color: #333;">E<span style="color:#EC6A19;">.Comercial</span></span>
        <div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px;">Comprobante de cobro · {{ $numero }}</div>
    </div>

    <p style="font-size: 15px; margin: 0 0 8px;">Hola {{ $cliente?->nombre ?? '' }},</p>
    <p style="font-size: 14px; color: #4b5563; margin: 0 0 16px;">Registramos tu pago. Te dejamos el detalle y adjuntamos el recibo en PDF.</p>

    <div style="background: #111827; border-radius: 10px; padding: 16px 18px; color: #fff; margin-bottom: 16px;">
        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #d1d5db;">Total cobrado</div>
        <div style="font-size: 26px; font-weight: bold;">{{ $money($monto) }}</div>
        <div style="font-size: 12px; color: #d1d5db; margin-top: 4px;">
            {{ $fecha?->format('d/m/Y H:i') ?? '' }} · Cuota #{{ $cuota?->numero ?? '—' }} · Venta {{ $venta?->numero ?? '—' }}
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 16px;">
        <tr><td style="padding: 6px 0; color: #6b7280;">Medio(s) de pago</td>
            <td style="padding: 6px 0; text-align: right; font-weight: bold;">
                @foreach ($medios as $m){{ $m['medio'] }} {{ $money($m['monto']) }}@if (! $loop->last) · @endif @endforeach
            </td></tr>
        <tr><td style="padding: 6px 0; color: #6b7280; border-top: 1px solid #e5e7eb;">Cuotas que faltan</td>
            <td style="padding: 6px 0; text-align: right; font-weight: bold; border-top: 1px solid #e5e7eb;">{{ $cuotas_faltan }} de {{ $total_cuotas }}</td></tr>
        <tr><td style="padding: 6px 0; color: #6b7280; border-top: 1px solid #e5e7eb;">Restante si pagás en término</td>
            <td style="padding: 6px 0; text-align: right; font-weight: bold; color: #EC6A19; border-top: 1px solid #e5e7eb;">{{ $money($restante_termino) }}</td></tr>
        @if ($prox_vence)
            <tr><td style="padding: 6px 0; color: #6b7280; border-top: 1px solid #e5e7eb;">Próximo vencimiento</td>
                <td style="padding: 6px 0; text-align: right; font-weight: bold; border-top: 1px solid #e5e7eb;">{{ $prox_vence->format('d/m/Y') }}</td></tr>
        @endif
    </table>

    <p style="font-size: 12px; color: #16a34a; font-weight: bold; margin: 0 0 4px;">✔ Pago registrado — conservá este comprobante como respaldo.</p>
    <p style="font-size: 11px; color: #9ca3af; margin: 16px 0 0; border-top: 1px solid #e5e7eb; padding-top: 12px;">
        Este es un mensaje automático de E.Comercial. Si no reconocés este pago, comunicate con nosotros.
    </p>
</div>
