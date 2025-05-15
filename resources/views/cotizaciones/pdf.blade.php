<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
    </style>
</head>
<body>
    <h2>Cotización #{{ $cotizacion->id }}</h2>
    <p><strong>Cliente:</strong> {{ $cotizacion->cliente->nombre }}</p>
    <p><strong>Fecha:</strong> {{ $cotizacion->fecha }}</p>
    <p><strong>Estado:</strong> {{ $cotizacion->estado }}</p>
    <p><strong>Observaciones:</strong> {{ $cotizacion->observaciones }}</p>

    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Ancho</th>
                <th>Alto</th>
                <th>Color</th>
                <th>Vidrio</th>
                <th>Precio</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizacion->ventanas as $ventana)
            <tr>
                <td>{{ $ventana->tipo_ventana->nombre ?? '-' }}</td>
                <td>{{ $ventana->ancho }} mm</td>
                <td>{{ $ventana->alto }} mm</td>
                <td>{{ $ventana->color_id }}</td>
                <td>{{ $ventana->producto_vidrio_proveedor_id }}</td>
                <td>${{ number_format($ventana->precio, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h4 style="text-align:right; margin-top:20px;">
        Total: ${{ number_format($cotizacion->total, 0, ',', '.') }}
    </h4>
</body>
</html>
