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

  {{-- Sección de Productos Adicionales --}}
  @if($cotizacion->detalles && $cotizacion->detalles->count() > 0)
    <h3 style="margin-top: 30px; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 5px;">
      Productos Adicionales
    </h3>
    
    <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
      <thead style="background-color: #f0f0f0;">
        <tr>
          <th style="border: 1px solid #ccc; padding: 8px; text-align: left; width: 50%;">Descripción</th>
          <th style="border: 1px solid #ccc; padding: 8px; text-align: center; width: 10%;">Cant.</th>
          <th style="border: 1px solid #ccc; padding: 8px; text-align: right; width: 20%;">P. Unit.</th>
          <th style="border: 1px solid #ccc; padding: 8px; text-align: right; width: 20%;">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($cotizacion->detalles as $detalle)
          <tr>
            <td style="border: 1px solid #ccc; padding: 6px;">
              @if($detalle->listaPrecio)
                {{-- Producto de lista de precios --}}
                <strong>{{ $detalle->listaPrecio->producto->nombre ?? 'N/A' }}</strong>
                
                {{-- Mostrar dimensiones y detalles si es vidrio --}}
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
                
                {{-- Mostrar color directo o desde productoColorProveedor (compatibilidad) --}}
                @php
                  $color = $detalle->listaPrecio->color ?? $detalle->listaPrecio->productoColorProveedor->color ?? null;
                @endphp
                @if($color)
                  <br>
                  <span style="font-size: 10px; color: #666;">
                    Color: {{ $color->nombre ?? 'N/A' }}
                  </span>
                @endif
              @elseif($detalle->producto)
                {{-- Producto directo --}}
                <strong>{{ $detalle->producto->nombre }}</strong>
              @else
                {{-- Descripción manual --}}
                <strong>{{ $detalle->descripcion }}</strong>
              @endif
            </td>
            <td style="border: 1px solid #ccc; padding: 6px; text-align: center;">
              {{ number_format($detalle->cantidad, 0) }}
            </td>
            <td style="border: 1px solid #ccc; padding: 6px; text-align: right;">
              ${{ number_format($detalle->precio_unitario, 0, ',', '.') }}
            </td>
            <td style="border: 1px solid #ccc; padding: 6px; text-align: right; font-weight: bold;">
              ${{ number_format($detalle->total, 0, ',', '.') }}
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  {{-- Total General --}}
  @php
    $totalVentanas = $cotizacion->ventanas->sum(fn($v) => $v->precio * $v->cantidad);
    $totalProductos = $cotizacion->detalles->sum('total'); // Total productos SIN IVA
    $subtotalNeto = $totalVentanas + $totalProductos; // Subtotal sin IVA
    $iva = $subtotalNeto * 0.19; // IVA 19% sobre el subtotal completo
    $totalGeneral = $subtotalNeto + $iva; // Total con IVA
  @endphp

  <table style="width: 100%; margin-top: 20px; border: none;">
    <tr>
      <td style="width: 70%; border: none;"></td>
      <td style="width: 30%; border: none;">
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
          @if($totalVentanas > 0)
            <tr>
              <td style="padding: 5px; text-align: right; border-top: 1px solid #ccc;">
                <strong>Subtotal Ventanas:</strong>
              </td>
              <td style="padding: 5px; text-align: right; border-top: 1px solid #ccc;">
                ${{ number_format($totalVentanas, 0, ',', '.') }}
              </td>
            </tr>
          @endif
          @if($totalProductos > 0)
            <tr>
              <td style="padding: 5px; text-align: right;">
                <strong>Subtotal Productos:</strong>
              </td>
              <td style="padding: 5px; text-align: right;">
                ${{ number_format($totalProductos, 0, ',', '.') }}
              </td>
            </tr>
          @endif
          <tr>
            <td style="padding: 5px; text-align: right; border-top: 1px solid #ccc;">
              <strong>Subtotal (neto):</strong>
            </td>
            <td style="padding: 5px; text-align: right; border-top: 1px solid #ccc;">
              ${{ number_format($subtotalNeto, 0, ',', '.') }}
            </td>
          </tr>
          <tr>
            <td style="padding: 5px; text-align: right;">
              <strong>IVA 19%:</strong>
            </td>
            <td style="padding: 5px; text-align: right;">
              ${{ number_format($iva, 0, ',', '.') }}
            </td>
          </tr>
          <tr style="background-color: #f0f0f0;">
            <td style="padding: 8px; text-align: right; border-top: 2px solid #333;">
              <strong>TOTAL:</strong>
            </td>
            <td style="padding: 8px; text-align: right; border-top: 2px solid #333; font-size: 14px;">
              <strong>${{ number_format($totalGeneral, 0, ',', '.') }}</strong>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <p style="margin-top: 20px; font-size: 10px; color: #666; text-align: center;">
    Los precios no incluyen IVA. Cotización válida por 30 días.
  </p>

</body>
</html>
