<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cotización</title>
  <style>
    body {
      font-family: sans-serif;
      font-size: 12px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 6px;
      text-align: left;
    }
  </style>
</head>
<body>
<table style="width: 100%; margin-bottom: 20px; border: none;">
  <tr>
    {{-- Información de la cotización (izquierda) --}}
    <td style="width: 60%; vertical-align: top; border: none;">
      <h2>Cotización #{{ $cotizacion->id }}</h2>
      <p><strong>Cliente:</strong> {{ $cotizacion->cliente->razon_social ?? '-' }}</p>
      <p><strong>RUT:</strong> {{ $cotizacion->cliente->rut ?? '-' }}</p>
      <p><strong>Fecha:</strong> {{ $cotizacion->fecha }}</p>
      <p><strong>Estado:</strong> {{ $cotizacion->estado->nombre ?? '-' }}</p>
      <p><strong>Vendedor:</strong> {{ $cotizacion->vendedor->nombre ?? '-' }}</p>
      <p><strong>Observaciones:</strong> {{ $cotizacion->observaciones }}</p>
    </td>

    {{-- Logo + Datos empresa (derecha) --}}
    <td style="width: 40%; text-align: right; vertical-align: top; border: none;">
      <img
        src="{{ public_path('storage/logovialum.png') }}"
        alt="Logo Vialum"
        style="max-width: 180px; margin-bottom: 5px;"
      />
      <p style="margin: 0;"><strong>Dirección:</strong> Balmaceda 454, Los Ángeles</p>
      <p style="margin: 0;"><strong>Teléfono:</strong> +432311859</p>
      <p style="margin: 0;"><strong>Correo:</strong> contacto@vialum.cl</p>
      <p style="margin: 0;"><strong>Web:</strong> www.vialum.cl</p>
    </td>
  </tr>
</table>


  @foreach($cotizacion->ventanas as $index => $ventana)
    <table width="100%" style="margin-bottom: 30px; border: none;">
      <tr>
        {{-- Imagen --}}
        <td width="50%" align="center" style="vertical-align: top;">
          @if ($ventana->imagen)
            <img
              src="https://vialum.cl/laravelupload/imagenes_cotizaciones/{{ $ventana->imagen }}"
              style="max-width: 100%; height: auto; border: none;"
              alt="Vista ventana"
            />
          @else
            <p style="color: gray;">Sin imagen</p>
          @endif
        </td>

        {{-- Tabla de detalles --}}
        <td width="50%" style="vertical-align: top;">
          <table style="font-size: 12px; width: 100%; border: 1px solid #ccc; border-collapse: collapse;">
            <tr>
            <th colspan="2" style="text-align: left; background-color: #f0f0f0;">
                <strong>V{{ $index + 1 }}</strong>
            </th>
            </tr>
            <tr><th style="border: 1px solid #ccc;">Tipo</th><td style="border: 1px solid #ccc;">{{ $ventana->tipoVentana->nombre ?? 'N/A' }}</td></tr>
            <tr><th style="border: 1px solid #ccc;">Color</th><td style="border: 1px solid #ccc;">{{ $ventana->color->nombre ?? 'N/A' }}</td></tr>
                        <tr><th style="border: 1px solid #ccc;">Vidrio</th>
              <td style="border: 1px solid #ccc;">
                {{ $ventana->productoVidrioProveedor->producto->nombre ?? '' }}
                @if($ventana->productoVidrioProveedor?->proveedor)
                  ({{ $ventana->productoVidrioProveedor->proveedor->nombre }})
                @endif
              </td>
            </tr>
            <tr><th style="border: 1px solid #ccc;">Ancho</th><td style="border: 1px solid #ccc;">{{ $ventana->ancho }} mm</td></tr>
            <tr><th style="border: 1px solid #ccc;">Alto</th><td style="border: 1px solid #ccc;">{{ $ventana->alto }} mm</td></tr>
            <tr><th style="border: 1px solid #ccc;">Cantidad</th><td style="border: 1px solid #ccc;">{{ $ventana->cantidad }}</td></tr>


            <tr><th style="border: 1px solid #ccc;">Total ventana</th><td style="border: 1px solid #ccc;">${{ number_format($ventana->precio, 0, ',', '.') }}</td></tr>
          </table>
        </td>
      </tr>
    </table>
  @endforeach

  <h4 style="text-align:right; margin-top:20px;">
    Total: ${{ number_format($cotizacion->ventanas->sum(fn($v) => $v->precio * $v->cantidad), 0, ',', '.') }}
  </h4>
</body>
</html>
