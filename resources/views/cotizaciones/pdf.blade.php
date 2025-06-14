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
    <p><strong>Cliente:</strong> {{ $cotizacion->cliente->razon_social ?? '-' }}</p>
    <p><strong>RUT:</strong> {{ $cotizacion->cliente->rut ?? '-' }}</p>
    <p><strong>Fecha:</strong> {{ $cotizacion->fecha }}</p>
    <p><strong>Estado:</strong> {{ $cotizacion->estado->nombre ?? '-' }}</p>
    <p><strong>Vendedor:</strong> {{ $cotizacion->vendedor->nombre ?? '-' }}</p>
    <p><strong>Observaciones:</strong> {{ $cotizacion->observaciones }}</p>

<table>
    <thead>
        <tr>
            <th>Tipo</th>
            <th>Ancho</th>
            <th>Alto</th>
            <th>Cantidad</th>
            <th>Color</th>
            <th>Vidrio</th>
            <th>Total ventana</th>
        </tr>
    </thead>
    <tbody>
        @foreach($cotizacion->ventanas as $ventana)
        <tr>
            <td>{{ $ventana->tipoVentana->nombre ?? '-' }}</td>
            <td>{{ $ventana->ancho }} mm</td>
            <td>{{ $ventana->alto }} mm</td>
            <td>{{ $ventana->cantidad }}</td>
            <td>{{ $ventana->color->nombre ?? '-' }}</td>
            <td>
                {{ $ventana->productoVidrioProveedor->producto->nombre ?? '-' }}
                ({{ $ventana->productoVidrioProveedor->proveedor->nombre ?? '' }})
            </td>
            <td>${{ number_format($ventana->precio * $ventana->cantidad, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>


<h4 style="text-align:right; margin-top:20px;">
    Total: ${{ number_format($cotizacion->ventanas->sum(fn($v) => $v->precio * $v->cantidad), 0, ',', '.') }}
</h4>
</body>
</html>
