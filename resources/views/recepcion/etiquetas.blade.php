<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas de recepción · {{ $compra->numero }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 16px; background: #f3f4f6; color: #1f2937; }
        .toolbar { max-width: 900px; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .toolbar h1 { font-size: 16px; margin: 0; }
        .btn { background: #EC6A19; color: #fff; border: 0; border-radius: 8px; padding: 8px 14px; font-weight: 700; font-size: 13px; cursor: pointer; }
        .grid { max-width: 900px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .label { border: 2px solid #111827; border-radius: 10px; padding: 12px 14px; background: #fff; page-break-inside: avoid; }
        .label .top { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px dashed #9ca3af; padding-bottom: 6px; margin-bottom: 6px; }
        .label .marca { font-weight: 800; letter-spacing: .5px; }
        .label .marca b { color: #EC6A19; }
        .label .nom { font-size: 16px; font-weight: 800; margin: 2px 0; }
        .label .sku { font-family: 'Consolas', monospace; font-size: 13px; color: #374151; }
        .label .row { display: flex; justify-content: space-between; font-size: 12px; margin-top: 4px; }
        .label .row span:first-child { color: #6b7280; }
        .label .fecha { margin-top: 8px; padding-top: 6px; border-top: 1px dashed #9ca3af; font-size: 13px; font-weight: 700; }
        .label .qty { font-size: 22px; font-weight: 800; }
        .vacio { max-width: 900px; margin: 0 auto; text-align: center; color: #6b7280; padding: 40px; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .grid { gap: 8px; }
        }
    </style>
</head>
<body>
    @php
        $recibidos = $compra->items->filter(fn ($it) => $it->estado_item === 'ok' && (int) $it->cantidad_recibida > 0);
        $fechaHora = $compra->recibido_at?->format('d/m/Y H:i') ?? '—';
        $recibe = $compra->recibidoPor?->name ?? '—';
    @endphp

    <div class="toolbar">
        <h1>Etiquetas de recepción · {{ $compra->numero }} · {{ $compra->proveedor?->nombre ?? '—' }}</h1>
        <button class="btn" onclick="window.print()">Imprimir</button>
    </div>

    @if ($recibidos->isEmpty())
        <p class="vacio">Esta recepción no tiene ítems recibidos en condiciones para etiquetar.</p>
    @else
        <div class="grid">
            @foreach ($recibidos as $it)
                <div class="label">
                    <div class="top">
                        <span class="marca">E<b>.Comercial</b></span>
                        <span class="qty">×{{ (int) $it->cantidad_recibida }}</span>
                    </div>
                    <div class="nom">{{ $it->producto?->nombre ?? '—' }}</div>
                    <div class="sku">SKU: {{ $it->producto?->sku ?: ($it->producto?->codigo ?? '—') }}</div>
                    <div class="row"><span>Proveedor</span><span>{{ $compra->proveedor?->nombre ?? '—' }}</span></div>
                    <div class="row"><span>Factura</span><span>{{ $compra->factura_numero ?: $compra->numero }}</span></div>
                    <div class="row"><span>Destino</span><span>{{ $compra->local?->nombre ?? '—' }}</span></div>
                    <div class="fecha">Ingreso: {{ $fechaHora }} · Recibió: {{ $recibe }}</div>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
