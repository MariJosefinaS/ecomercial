<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 16px; background: #f3f4f6; color: #1f2937; }
        .toolbar { max-width: 1000px; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .toolbar h1 { font-size: 16px; margin: 0; }
        .btn { background: #EC6A19; color: #fff; border: 0; border-radius: 8px; padding: 8px 14px; font-weight: 700; font-size: 13px; cursor: pointer; }
        .grid { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .label { border: 2px solid #111827; border-radius: 10px; padding: 10px 12px; background: #fff; page-break-inside: avoid; }
        .label .top { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px dashed #9ca3af; padding-bottom: 5px; margin-bottom: 5px; }
        .label .marca { font-weight: 800; letter-spacing: .5px; font-size: 12px; }
        .label .marca b { color: #EC6A19; }
        .label .codigo { font-family: 'Consolas', monospace; font-size: 15px; font-weight: 800; color: #111827; letter-spacing: 1px; }
        .label .barras { height: 34px; margin: 4px 0 2px; background-size: 3px 100%;
            background-image: repeating-linear-gradient(90deg, #111 0 1px, #fff 1px 2px, #111 2px 4px, #fff 4px 5px, #111 5px 6px, #fff 6px 9px); }
        .label .nom { font-size: 14px; font-weight: 800; margin: 2px 0; line-height: 1.15; }
        .label .sku { font-family: 'Consolas', monospace; font-size: 12px; color: #374151; }
        .label .row { display: flex; justify-content: space-between; font-size: 11px; margin-top: 3px; }
        .label .row span:first-child { color: #6b7280; }
        .label .fecha { margin-top: 7px; padding-top: 5px; border-top: 1px dashed #9ca3af; font-size: 11px; font-weight: 700; }
        .vacio { max-width: 1000px; margin: 0 auto; text-align: center; color: #6b7280; padding: 40px; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .grid { gap: 6px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>{{ $titulo }} · {{ count($etiquetas) }} etiqueta(s)</h1>
        <button class="btn" onclick="window.print()">Imprimir</button>
    </div>

    @if (empty($etiquetas))
        <p class="vacio">No hay unidades recibidas en condiciones para etiquetar.</p>
    @else
        <div class="grid">
            @foreach ($etiquetas as $e)
                <div class="label">
                    <div class="top">
                        <span class="marca">E<b>.Comercial</b></span>
                        <span class="codigo">{{ $e['codigo'] }}</span>
                    </div>
                    <div class="barras" title="{{ $e['codigo'] }}"></div>
                    <div class="nom">{{ $e['nombre'] }}</div>
                    <div class="sku">SKU: {{ $e['sku'] }}</div>
                    <div class="row"><span>Proveedor</span><span>{{ $e['proveedor'] }}</span></div>
                    <div class="row"><span>Remito</span><span>{{ $e['remito'] }}</span></div>
                    <div class="row"><span>Destino</span><span>{{ $e['destino'] }}</span></div>
                    <div class="fecha">Ingreso: {{ $e['fecha'] }} · {{ $e['recibe'] }}</div>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
