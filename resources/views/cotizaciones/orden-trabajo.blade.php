x1|<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Orden de Trabajo</title>
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
    {{-- Información de la OT (izquierda) --}}
    <td style="width: 60%; vertical-align: top; border: none;">
      <h2>Orden de Trabajo #{{ $cotizacion->id }}</h2>
      <p><strong>Cliente:</strong> {{ $cotizacion->cliente->razon_social ?? '-' }}</p>
      <p><strong>RUT:</strong> {{ $cotizacion->cliente->rut ?? '-' }}</p>
      <p><strong>Fecha:</strong> {{ $cotizacion->fecha }}</p>
      <p><strong>Vendedor:</strong> {{ $cotizacion->vendedor->nombre ?? '-' }}</p>
      <p><strong>Observaciones:</strong> {{ $cotizacion->observaciones }}</p>
    </td>

    {{-- Logo + Datos empresa (derecha) --}}
    <td style="width: 40%; text-align: right; vertical-align: top; border: none;">
      @if(file_exists(public_path('storage/logovialum.png')))
        <img
          src="{{ public_path('storage/logovialum.png') }}"
          alt="Logo Vialum"
          style="max-width: 180px; margin-bottom: 5px;"
        />
      @else
        <h3 style="margin: 0;">VIALUM</h3>
      @endif
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
          @if ($ventana->imagen && isset($imagenesBase64[$ventana->id]))
            <img
              src="{{ $imagenesBase64[$ventana->id] }}"
              style="max-width: 100%; height: auto; border: none;"
              alt="Vista ventana"
            />
          @elseif ($ventana->imagen)
            <p style="color: gray; font-size: 10px;">Imagen no disponible</p>
          @else
            <p style="color: gray;">Sin imagen</p>
          @endif
        </td>

        {{-- Tabla de detalles SIN precios --}}
        <td width="50%" style="vertical-align: top;">
          <table style="font-size: 12px; width: 100%; border: 1px solid #ccc; border-collapse: collapse;">
            <tr>
              <th colspan="2" style="text-align: left; background-color: #f0f0f0;">
                <strong>V{{ $index + 1 }}</strong>
              </th>
            </tr>
            <tr><th style="border: 1px solid #ccc;">Tipo</th><td style="border: 1px solid #ccc;">{{ $ventana->tipoVentana->nombre ?? 'N/A' }}</td></tr>
            <tr><th style="border: 1px solid #ccc;">Color</th><td style="border: 1px solid #ccc;">{{ $ventana->color->nombre ?? 'N/A' }}</td></tr>
            <tr>
              <th style="border: 1px solid #ccc;">Vidrio</th>
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
          </table>
        </td>
      </tr>
    </table>
  @endforeach

  {{-- Sección de Productos Adicionales --}}
  @if($cotizacion->detalles && $cotizacion->detalles->count() > 0)
    <h3 style="margin-top: 30px; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 5px;">
      Productos Adicionales
    </h3>

    <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
      <thead style="background-color: #f0f0f0;">
        <tr>
          <th style="border: 1px solid #ccc; padding: 8px; text-align: left; width: 70%;">Descripción</th>
          <th style="border: 1px solid #ccc; padding: 8px; text-align: center; width: 30%;">Cantidad</th>
        </tr>
      </thead>
      <tbody>
        @foreach($cotizacion->detalles as $detalle)
          <tr>
            <td style="border: 1px solid #ccc; padding: 6px;">
              @if($detalle->listaPrecio)
                <strong>{{ $detalle->listaPrecio->producto->nombre ?? 'N/A' }}</strong>

                @if($detalle->esVidrio && $detalle->ancho_mm && $detalle->alto_mm)
                  <br>
                  <span style="font-size: 10px; color: #666;">
                    Dimensiones: {{ $detalle->ancho_mm }}mm x {{ $detalle->alto_mm }}mm
                    ({{ number_format($detalle->m2, 4) }} m²)
                    @if($detalle->pulido)
                      <strong>[PULIDO]</strong>
                    @endif
                  </span>
                @endif

                @php
                  $color = $detalle->listaPrecio->color ?? $detalle->listaPrecio->productoColorProveedor->color ?? null;
                @endphp
                @if($color)
                  <br>
                  <span style="font-size: 10px; color: #666;">Color: {{ $color->nombre ?? 'N/A' }}</span>
                @endif
              @elseif($detalle->producto)
                <strong>{{ $detalle->producto->nombre }}</strong>
              @else
                <strong>{{ $detalle->descripcion }}</strong>
              @endif
            </td>
            <td style="border: 1px solid #ccc; padding: 6px; text-align: center;">
              {{ number_format($detalle->cantidad, 0) }}
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <p style="margin-top: 30px; font-size: 10px; color: #666; text-align: center;">
    Documento interno — Orden de Trabajo. No incluye precios.
  </p>

</body>
</html>
