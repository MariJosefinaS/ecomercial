<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="utf-8">
    @php $money = fn ($n) => '$' . number_format((float) $n, 2, ',', '.'); @endphp
    <style>
        @page { margin: 12mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 9px; margin: 0; }
        .head { width: 100%; border-bottom: 2px solid #111827; padding-bottom: 6px; margin-bottom: 8px; }
        .head td { vertical-align: top; }
        .marca { font-weight: bold; font-size: 15px; letter-spacing: .5px; }
        .marca span { color: #EC6A19; }
        .titulo { font-size: 12px; font-weight: bold; text-transform: uppercase; margin-top: 2px; }
        .meta { text-align: right; font-size: 10px; }
        .meta b { font-size: 11px; }
        table.datos { width: 100%; border-collapse: collapse; }
        table.datos th, table.datos td { padding: 4px 5px; border-bottom: 1px solid #e5e7eb; text-align: left; word-wrap: break-word; }
        table.datos th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; color: #6b7280; }
        .num { text-align: right; }
        .cli { font-weight: bold; }
        .dom { color: #6b7280; font-size: 8px; }
        .atraso { color: #b91c1c; font-weight: bold; }
        .aldia { color: #9ca3af; }
        .tot { width: 100%; margin-top: 10px; }
        .tot td { text-align: right; font-size: 11px; padding: 2px 8px; }
        .tot b { font-size: 13px; }
        .firma { width: 100%; margin-top: 30px; border-collapse: collapse; }
        .firma td { border-top: 1px solid #111827; padding-top: 4px; text-align: center; font-size: 9px; color: #374151; width: 33%; }
        .firma-sep { border: 0 !important; width: 20px; }
        .vacio { text-align: center; color: #6b7280; padding: 30px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="marca">E<span>.Comercial</span></div>
                <div class="titulo">Planilla de cobros · {{ ucfirst($modalidad) }}</div>
            </td>
            <td class="meta">
                <div>Cobrador: <b>{{ $cobrador?->name ?? '—' }}</b></div>
                <div>Fecha: <b>{{ $fecha->format('d/m/Y') }}</b></div>
                <div>Cuotas a cobrar: <b>{{ count($filas) }}</b></div>
            </td>
        </tr>
    </table>

    @if (empty($filas))
        <p class="vacio">No hay cuotas para cobrar en esta fecha/modalidad.</p>
    @else
        <table class="datos">
            <thead>
                <tr>
                    <th style="width:3%">#</th>
                    <th style="width:24%">Cliente / Domicilio</th>
                    <th style="width:11%">Zona</th>
                    <th style="width:17%">Crédito · Plan</th>
                    <th class="num" style="width:6%">Cuota</th>
                    <th class="num" style="width:8%">Vence</th>
                    <th class="num" style="width:6%">Atraso</th>
                    <th class="num" style="width:9%">Saldo</th>
                    <th class="num" style="width:10%">Total</th>
                    <th style="width:6%">Cobr.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $i => $r)
                    <tr>
                        <td class="num">{{ $i + 1 }}</td>
                        <td><span class="cli">{{ $r['cliente'] }}</span><div class="dom">@if ($r['domicilio_etiqueta']){{ $r['domicilio_etiqueta'] }}: @endif{{ $r['domicilio'] ?: '—' }}@if ($r['telefono']) · {{ $r['telefono'] }}@endif @if ($r['referencia'])<br>({{ $r['referencia'] }})@endif</div></td>
                        <td>{{ $r['zona'] }}</td>
                        <td>{{ $r['credito'] }}<div class="dom">{{ $r['plan'] }}</div></td>
                        <td class="num">#{{ $r['numero'] }}</td>
                        <td class="num">{{ $r['vence'] }}</td>
                        <td class="num {{ $r['dias'] > 0 ? 'atraso' : 'aldia' }}">{{ $r['dias'] > 0 ? $r['dias'] . 'd' : '—' }}</td>
                        <td class="num">{{ $money($r['saldo']) }}</td>
                        <td class="num"><b>{{ $money($r['total']) }}</b></td>
                        <td style="text-align:center">{{ $r['cobrada'] ? '✔' : '☐' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="tot">
            <tr>
                <td>Esperado: <b>{{ $money($tot['esperado']) }}</b></td>
                <td>Cobrado: <b>{{ $money($tot['cobrado']) }}</b></td>
                <td>Eficacia: <b>{{ number_format($tot['eficacia'], 1, ',', '.') }}%</b></td>
            </tr>
        </table>

        <table class="firma">
            <tr>
                <td>Firma del cobrador</td>
                <td class="firma-sep"></td>
                <td>Rendición / total entregado</td>
                <td class="firma-sep"></td>
                <td>Auditoría (administración)</td>
            </tr>
        </table>
    @endif
</body>
</html>
