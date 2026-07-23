<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.'); @endphp
    <title>Planilla {{ ucfirst($modalidad) }} · {{ $cobrador?->name ?? '—' }} · {{ $fecha->format('d/m/Y') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 16px; background: #f3f4f6; color: #1f2937; font-size: 12px; }
        .sheet { max-width: 1000px; margin: 0 auto; background: #fff; border: 2px solid #111827; border-radius: 10px; padding: 14px 16px; }
        .toolbar { max-width: 1000px; margin: 0 auto 12px; display: flex; justify-content: flex-end; }
        .btn { background: #EC6A19; color: #fff; border: 0; border-radius: 8px; padding: 8px 14px; font-weight: 700; font-size: 13px; cursor: pointer; }
        .head { display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 8px; }
        .head .marca { font-weight: 800; font-size: 16px; letter-spacing: .5px; } .head .marca b { color: #EC6A19; }
        .head .titulo { font-size: 15px; font-weight: 800; text-transform: uppercase; }
        .head .meta { text-align: right; font-size: 12px; } .head .meta b { font-size: 13px; }
        .tabla-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; min-width: 720px; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; text-align: left; word-break: break-word; }
        th { background: #f9fafb; font-size: 10px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .cli { font-weight: 700; } .dom { color: #6b7280; font-size: 11px; }
        .atraso { color: #b91c1c; font-weight: 700; } .aldia { color: #6b7280; }
        .tot { margin-top: 10px; display: flex; justify-content: flex-end; gap: 24px; font-size: 13px; }
        .tot b { font-size: 15px; }
        .firma { margin-top: 26px; display: flex; justify-content: space-between; gap: 40px; }
        .firma div { flex: 1; border-top: 1px solid #111827; padding-top: 4px; text-align: center; font-size: 11px; color: #374151; }
        .vacio { text-align: center; color: #6b7280; padding: 30px; }
        /* Imprime apaisado para que las 10 columnas entren completas en la hoja. */
        @page { size: A4 landscape; margin: 8mm; }
        @media print {
            body { background: #fff; padding: 0; font-size: 10px; }
            .toolbar { display: none; }
            .sheet { border: 0; border-radius: 0; padding: 0; max-width: none; }
            .tabla-wrap { overflow: visible; }
            table { min-width: 0; }
        }
        /* En pantallas chicas (celular) no achicamos la tabla: se desliza dentro de su caja. */
        @media (max-width: 640px) {
            body { padding: 8px; }
            .sheet { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><button class="btn" onclick="window.print()">Imprimir</button></div>

    <div class="sheet">
        <div class="head">
            <div>
                <div class="marca">E<b>.Comercial</b></div>
                <div class="titulo">Planilla de cobros · {{ ucfirst($modalidad) }}</div>
            </div>
            <div class="meta">
                <div>Cobrador: <b>{{ $cobrador?->name ?? '—' }}</b></div>
                <div>Fecha: <b>{{ $fecha->format('d/m/Y') }}</b></div>
                <div>Cuotas a cobrar: <b>{{ count($filas) }}</b></div>
            </div>
        </div>

        @if (empty($filas))
            <p class="vacio">No hay cuotas para cobrar en esta fecha/modalidad.</p>
        @else
            <div class="tabla-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Cliente / Domicilio</th><th>Zona</th><th>Crédito · Plan</th>
                        <th class="num">Cuota</th><th class="num">Vence</th><th class="num">Atraso</th>
                        <th class="num">Saldo</th><th class="num">Total a cobrar</th><th>Cobrado ✔</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $i => $r)
                        <tr>
                            <td class="num">{{ $i + 1 }}</td>
                            <td><div class="cli">{{ $r['cliente'] }}</div><div class="dom">@if ($r['domicilio_etiqueta']){{ $r['domicilio_etiqueta'] }}: @endif{{ $r['domicilio'] ?: '—' }} @if ($r['telefono']) · {{ $r['telefono'] }} @endif @if ($r['referencia'])<br>({{ $r['referencia'] }})@endif</div></td>
                            <td>{{ $r['zona'] }}</td>
                            <td>{{ $r['credito'] }}<div class="dom">{{ $r['plan'] }}</div></td>
                            <td class="num">#{{ $r['numero'] }}</td>
                            <td class="num">{{ $r['vence'] }}</td>
                            <td class="num {{ $r['dias'] > 0 ? 'atraso' : 'aldia' }}">{{ $r['dias'] > 0 ? $r['dias'] . 'd' : '—' }}</td>
                            <td class="num">{{ $money($r['saldo']) }}</td>
                            <td class="num"><b>{{ $money($r['total']) }}</b></td>
                            <td>{{ $r['cobrada'] ? '✔' : '☐' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div class="tot">
                <span>Esperado: <b>{{ $money($tot['esperado']) }}</b></span>
                <span>Cobrado: <b>{{ $money($tot['cobrado']) }}</b></span>
                <span>Eficacia: <b>{{ number_format($tot['eficacia'], 1, ',', '.') }}%</b></span>
            </div>

            <div class="firma">
                <div>Firma del cobrador</div>
                <div>Rendición / total entregado</div>
                <div>Auditoría (administración)</div>
            </div>
        @endif
    </div>
</body>
</html>
