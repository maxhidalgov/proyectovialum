<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #222; margin: 0; }
        .header { border-bottom: 2px solid #6a1b9a; padding-bottom: 10px; margin-bottom: 16px; }
        .header h1 { margin: 0; font-size: 22px; color: #6a1b9a; }
        .empresa { font-size: 11px; color: #555; margin-top: 2px; }
        .meta { width: 100%; margin-bottom: 16px; }
        .meta td { padding: 3px 0; font-size: 12px; vertical-align: top; }
        .meta .label { color: #777; width: 110px; }
        .num-chip { display: inline-block; background: #6a1b9a; color: #fff; padding: 4px 12px;
                    border-radius: 4px; font-size: 14px; font-weight: bold; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th { background: #6a1b9a; color: #fff; padding: 6px 8px; text-align: left; font-size: 11px; }
        table.items td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; font-size: 11px; }
        table.items tr:nth-child(even) td { background: #f7f4fa; }
        .cat { background: #ede7f6 !important; font-weight: bold; color: #4a148c; }
        .text-right { text-align: right; }
        .obs { margin-top: 18px; padding: 10px; background: #f5f5f5; border-radius: 4px; font-size: 11px; }
        .footer { margin-top: 30px; font-size: 10px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Orden de Compra</h1>
        <div class="empresa">Vialum — Fabricación de ventanas de aluminio</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">N° Orden:</td>
            <td><span class="num-chip">{{ $orden->numero }}</span></td>
            <td class="label">Fecha:</td>
            <td>{{ $orden->created_at?->format('d-m-Y') }}</td>
        </tr>
        <tr>
            <td class="label">Proveedor:</td>
            <td colspan="3"><strong>{{ $orden->proveedor_nombre ?? $orden->proveedor?->nombre ?? '—' }}</strong></td>
        </tr>
        @if ($orden->cotizacion)
        <tr>
            <td class="label">Obra / Cliente:</td>
            <td colspan="3">
                {{ $orden->cotizacion->cliente?->razon_social
                   ?? trim(($orden->cotizacion->cliente?->first_name ?? '') . ' ' . ($orden->cotizacion->cliente?->last_name ?? '')) }}
                @if ($orden->cotizacion->winperfil_numero)
                    <span style="color:#777"> · Winperfil {{ $orden->cotizacion->winperfil_serie }}-{{ $orden->cotizacion->winperfil_numero }}</span>
                @endif
            </td>
        </tr>
        @endif
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:130px">Referencia</th>
                <th>Descripción</th>
                <th style="width:120px">Detalle</th>
                <th class="text-right" style="width:70px">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @php $catActual = null; @endphp
            @foreach ($orden->items as $it)
                @if (($it['categoria'] ?? '') !== $catActual)
                    @php $catActual = $it['categoria'] ?? ''; @endphp
                    <tr><td colspan="4" class="cat">{{ strtoupper($catActual) }}</td></tr>
                @endif
                <tr>
                    <td>{{ $it['referencia'] ?? '' }}</td>
                    <td>{{ $it['descripcion'] ?? '' }}</td>
                    <td>{{ $it['detalle'] ?? '' }}</td>
                    <td class="text-right"><strong>{{ $it['cantidad'] ?? '' }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($orden->observaciones)
        <div class="obs">
            <strong>Observaciones:</strong><br>
            {{ $orden->observaciones }}
        </div>
    @endif

    <div class="footer">
        Orden generada el {{ $orden->created_at?->format('d-m-Y H:i') }} — Vialum
    </div>
</body>
</html>
