<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #222; margin: 0; }
        .header { border-bottom: 2px solid #6a1b9a; padding-bottom: 8px; margin-bottom: 12px; width: 100%; }
        .header h1 { margin: 0; font-size: 20px; color: #6a1b9a; }
        .header .logo { height: 40px; }
        .empresa { font-size: 10px; color: #555; margin-top: 2px; }
        .meta { width: 100%; margin-bottom: 12px; }
        .meta td { padding: 2px 0; font-size: 11px; }
        .meta .label { color: #777; width: 70px; }
        table.det { width: 100%; border-collapse: collapse; }
        table.det th { background: #6a1b9a; color: #fff; padding: 6px 7px; text-align: left; font-size: 10px; }
        table.det td { padding: 5px 7px; border-bottom: 1px solid #e0e0e0; font-size: 10px; vertical-align: top; }
        table.det tr:nth-child(even) td { background: #f7f4fa; }
        .r { text-align: right; white-space: nowrap; }
        .nw { white-space: nowrap; }
        .tot td { border-top: 2px solid #6a1b9a; font-weight: bold; font-size: 11px; background: #ede7f6 !important; }
        .nota { margin-top: 14px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="vertical-align: middle;">
                <h1>Detalle de documentos por cobrar</h1>
                <div class="empresa">Vialum — Fabricación de ventanas de aluminio</div>
            </td>
            <td style="vertical-align: middle; text-align: right; width: 160px;">
                @if (!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" class="logo" alt="Vialum">
                @endif
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="label">Cliente:</td>
            <td><strong>{{ $d['cliente'] }}</strong>@if (!empty($d['rut'])) <span style="color:#777"> · RUT {{ $d['rut'] }}</span>@endif</td>
            <td class="label" style="text-align:right">Fecha:</td>
            <td class="nw" style="width:80px">{{ $d['generado'] }}</td>
        </tr>
    </table>

    <table class="det">
        <thead>
            <tr>
                <th class="nw">Folio</th>
                <th class="nw">Fecha emisión</th>
                <th class="r">Monto total</th>
                <th class="r">Saldo por cobrar</th>
                <th class="nw">Método de pago</th>
                <th>Descripción (glosa)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($d['filas'] as $f)
                <tr>
                    <td class="nw">{{ $f['folio'] }}</td>
                    <td class="nw">{{ \Carbon\Carbon::parse($f['fecha'])->format('d-m-Y') }}</td>
                    <td class="r">${{ number_format($f['monto'], 0, ',', '.') }}</td>
                    <td class="r">${{ number_format($f['pendiente'], 0, ',', '.') }}</td>
                    <td class="nw">{{ $f['metodo_pago'] }}</td>
                    <td>{{ $f['glosa'] }}</td>
                </tr>
            @endforeach
            <tr class="tot">
                <td colspan="2">Total ({{ count($d['filas']) }} documentos)</td>
                <td class="r">${{ number_format($d['total'], 0, ',', '.') }}</td>
                <td class="r">${{ number_format($d['pendiente'], 0, ',', '.') }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    <div class="nota">Montos con IVA incluido. "Saldo por cobrar" descuenta los pagos ya conciliados.</div>
</body>
</html>
