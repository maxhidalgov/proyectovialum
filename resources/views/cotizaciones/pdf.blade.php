<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cotización #{{ $cotizacion->id }}</title>
  <style>
    @page { margin: 14mm 14mm 22mm 14mm; }

    body { font-family: sans-serif; font-size: 11px; color: #222; margin: 0; }

    /* ── Barra de marca ── */
    .brand-bar {
      background-color: #1B3A6B;
      height: 7px;
      margin-bottom: 14px;
    }

    /* ── Header ── */
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .header-table td { border: none; vertical-align: top; padding: 0; }
    .cotizacion-title { margin: 0 0 10px 0; font-size: 20px; color: #1B3A6B; }
    .info-row td { border: none; padding: 2px 0; }
    .info-label { font-weight: bold; color: #333; padding-right: 6px; white-space: nowrap; }
    .company-info { font-size: 10px; color: #444; line-height: 1.7; text-align: right; }
    .company-info strong { color: #222; }

    /* ── Tarjeta de ventana ── */
    .ventana-card {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 18px;
      border: 1px solid #ddd;
    }
    .ventana-card td { border: none; vertical-align: middle; padding: 10px; }
    .ventana-img-cell { width: 50%; text-align: center; background-color: #fafafa; border-right: 1px solid #ddd; }

    /* ── Tabla de detalles ── */
    .detail-table { width: 100%; border-collapse: collapse; }
    .detail-header {
      background-color: #1B3A6B;
      color: #fff;
      padding: 8px 10px;
      font-size: 13px;
      font-weight: bold;
      text-align: left;
    }
    .detail-table tr td {
      border-bottom: 1px solid #eee;
      padding: 5px 8px;
      font-size: 11px;
    }
    .detail-table tr td:first-child {
      font-weight: bold;
      color: #555;
      background-color: #f5f5f5;
      width: 42%;
      border-right: 1px solid #eee;
    }
    .detail-table tr:last-child td { border-bottom: none; }

    /* ── Sección adicionales ── */
    .section-title {
      color: #1B3A6B;
      font-size: 13px;
      font-weight: bold;
      border-bottom: 2px solid #1B3A6B;
      padding-bottom: 4px;
      margin: 24px 0 10px 0;
    }
    .products-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .products-table th {
      background-color: #1B3A6B;
      color: #fff;
      padding: 7px 8px;
      font-weight: bold;
    }
    .products-table td { border: 1px solid #ddd; padding: 6px 8px; }
    .products-table tbody tr:nth-child(even) td { background-color: #fafafa; }

    /* ── Totales ── */
    .totals-section { page-break-inside: avoid; margin-top: 20px; }
    .totals-wrapper { width: 100%; border-collapse: collapse; }
    .totals-wrapper td { border: none; padding: 0; }
    .totals-inner { width: 100%; border-collapse: collapse; }
    .totals-inner td { padding: 5px 10px; border-top: 1px solid #ddd; font-size: 11px; }
    .totals-inner td:first-child { text-align: right; }
    .totals-inner td:last-child { text-align: right; white-space: nowrap; }
    .total-final { background-color: #1B3A6B; color: #fff; font-size: 14px; font-weight: bold; }
    .total-final td { border-top: none !important; padding: 8px 10px; }

    /* ── Pie de nota ── */
    .nota { font-size: 9px; color: #aaa; text-align: center; margin-top: 16px; }

    /* ── Footer fijo ── */
    #pdf-footer {
      position: fixed;
      bottom: -14mm;
      left: 0;
      right: 0;
      border-top: 1px solid #ddd;
      padding-top: 4px;
      font-size: 9px;
      color: #aaa;
      text-align: center;
    }
  </style>
</head>
<body>

{{-- Footer fijo en todas las páginas --}}
<div id="pdf-footer">
  Cotización #{{ $cotizacion->id }} &nbsp;·&nbsp; {{ $cotizacion->cliente->razon_social ?: trim(($cotizacion->cliente->first_name ?? '') . ' ' . ($cotizacion->cliente->last_name ?? '')) ?: '-' }} &nbsp;·&nbsp; Válida 5 días
</div>

{{-- Barra de marca --}}
<div class="brand-bar"></div>

{{-- Header --}}
<table class="header-table">
  <tr>
    <td style="width: 55%;">
      <h2 class="cotizacion-title">Cotización #{{ $cotizacion->id }}</h2>
      <table style="border-collapse: collapse;">
        <tr class="info-row">
          <td class="info-label">Cliente:</td>
          <td style="border:none; padding: 2px 0;">{{ $cotizacion->cliente->razon_social ?: trim(($cotizacion->cliente->first_name ?? '') . ' ' . ($cotizacion->cliente->last_name ?? '')) ?: '-' }}</td>
        </tr>
        @if($cotizacion->cliente->rut ?? null)
        <tr class="info-row">
          <td class="info-label">RUT:</td>
          <td style="border:none; padding: 2px 0;">{{ $cotizacion->cliente->rut }}</td>
        </tr>
        @endif
        @php
          $contacto  = trim(($cotizacion->cliente->first_name ?? '') . ' ' . ($cotizacion->cliente->last_name ?? ''));
          $telefono  = $cotizacion->cliente->phone ?? $cotizacion->cliente->telefono ?? null;
          $correo    = $cotizacion->cliente->email ?? null;
          $direccion = $cotizacion->cliente->address ?? $cotizacion->cliente->direccion ?? null;
          $ciudad    = $cotizacion->cliente->ciudad ?? null;
        @endphp
        @if($telefono)
        <tr class="info-row">
          <td class="info-label">Teléfono:</td>
          <td style="border:none; padding: 2px 0;">{{ $telefono }}</td>
        </tr>
        @endif
        @if($correo)
        <tr class="info-row">
          <td class="info-label">Correo:</td>
          <td style="border:none; padding: 2px 0;">{{ $correo }}</td>
        </tr>
        @endif
        @if($direccion)
        <tr class="info-row">
          <td class="info-label">Dirección:</td>
          <td style="border:none; padding: 2px 0;">{{ $direccion }}{{ $ciudad ? ', ' . $ciudad : '' }}</td>
        </tr>
        @endif
        <tr class="info-row">
          <td class="info-label">Fecha:</td>
          <td style="border:none; padding: 2px 0;">
            {{ \Carbon\Carbon::parse($cotizacion->fecha)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}
          </td>
        </tr>
        <tr class="info-row">
          <td class="info-label">Estado:</td>
          <td style="border:none; padding: 2px 0;">{{ $cotizacion->estado->nombre ?? '-' }}</td>
        </tr>
        @if($cotizacion->vendedor?->nombre)
        <tr class="info-row">
          <td class="info-label">Vendedor:</td>
          <td style="border:none; padding: 2px 0;">{{ $cotizacion->vendedor->nombre }}</td>
        </tr>
        @endif
        @if($cotizacion->observaciones)
        <tr class="info-row">
          <td class="info-label" style="vertical-align: top;">Observaciones:</td>
          <td style="border:none; padding: 2px 0;">{{ $cotizacion->observaciones }}</td>
        </tr>
        @endif
      </table>
    </td>
    <td style="width: 45%; text-align: right; vertical-align: top;">
      @if(!empty($logoBase64))
        <img src="{{ $logoBase64 }}" alt="Logo Vialum" width="130" style="margin-bottom: 6px;" />
      @else
        <h3 style="margin: 0; color: #1B3A6B;">VIALUM</h3>
      @endif
      <div class="company-info">
        <strong>Dirección:</strong> Balmaceda 454, Los Ángeles<br>
        <strong>Teléfono:</strong> +432311859<br>
        <strong>Correo:</strong> contacto@vialum.cl<br>
        <strong>Web:</strong> www.vialum.cl
      </div>
    </td>
  </tr>
</table>

{{-- Ventanas --}}
@foreach($cotizacion->ventanas as $index => $ventana)
  <table class="ventana-card">
    <tr>
      {{-- Imagen --}}
      <td class="ventana-img-cell">
        @if($ventana->imagen && isset($imagenesBase64[$ventana->id]))
          <img
            src="{{ $imagenesBase64[$ventana->id] }}"
            width="300"
            style="display: block; margin: 0 auto;"
            alt="Vista ventana"
          />
        @elseif($ventana->imagen)
          <p style="color: #bbb; font-size: 10px;">Imagen no disponible</p>
        @else
          <p style="color: #bbb;">Sin imagen</p>
        @endif
      </td>

      {{-- Detalles --}}
      <td style="width: 50%; vertical-align: top; padding: 0;">
        <table style="width: 100%; border-collapse: collapse;">
          <tr>
            <th colspan="2" style="background-color: #1B3A6B; color: #fff; font-size: 13px; font-weight: bold; padding: 8px 10px; text-align: left;">
              V{{ $index + 1 }} &mdash; {{ $ventana->tipoVentana->nombre ?? 'N/A' }}
            </th>
          </tr>
          @php $labelStyle = 'font-weight: bold; color: #555; background-color: #f5f5f5; border: 1px solid #eee; padding: 5px 8px; width: 42%;'; $valStyle = 'border: 1px solid #eee; padding: 5px 8px;'; @endphp
          <tr><th style="{{ $labelStyle }}">Color</th><td style="{{ $valStyle }}">{{ $ventana->color->nombre ?? 'N/A' }}</td></tr>
          <tr>
            <th style="{{ $labelStyle }}">Vidrio</th>
            <td style="{{ $valStyle }}">
              {{ $ventana->productoVidrioProveedor->producto->nombre ?? 'N/A' }}
            </td>
          </tr>
          <tr><th style="{{ $labelStyle }}">Ancho</th><td style="{{ $valStyle }}">{{ $ventana->ancho }} mm</td></tr>
          <tr><th style="{{ $labelStyle }}">Alto</th><td style="{{ $valStyle }}">{{ $ventana->alto }} mm</td></tr>
          <tr><th style="{{ $labelStyle }}">Cantidad</th><td style="{{ $valStyle }}">{{ $ventana->cantidad }}</td></tr>
          <tr>
            <th style="{{ $labelStyle }}">Total ventana</th>
            <td style="{{ $valStyle }}"><strong>${{ number_format($ventana->precio, 0, ',', '.') }}</strong></td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
@endforeach

{{-- Productos Adicionales --}}
@if($cotizacion->detalles && $cotizacion->detalles->count() > 0)
  <div class="section-title">Productos Adicionales</div>
  <table class="products-table">
    <thead>
      <tr>
        <th style="text-align: left; width: 50%;">Descripción</th>
        <th style="text-align: center; width: 10%;">Cant.</th>
        <th style="text-align: right; width: 20%;">P. Unit.</th>
        <th style="text-align: right; width: 20%;">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach($cotizacion->detalles as $detalle)
        <tr>
          <td>
            @if($detalle->listaPrecio)
              <strong>{{ $detalle->listaPrecio->producto->nombre ?? 'N/A' }}</strong>
              @if($detalle->esVidrio && $detalle->ancho_mm && $detalle->alto_mm)
                <br><span style="font-size: 10px; color: #888;">
                  {{ $detalle->ancho_mm }}mm × {{ $detalle->alto_mm }}mm
                  ({{ number_format($detalle->m2, 4) }} m²)
                  @if($detalle->pulido) <strong>[PULIDO]</strong> @endif
                </span>
              @endif
              @php $color = $detalle->listaPrecio->color ?? $detalle->listaPrecio->productoColorProveedor->color ?? null; @endphp
              @if($color)
                <br><span style="font-size: 10px; color: #888;">Color: {{ $color->nombre ?? 'N/A' }}</span>
              @endif
            @elseif($detalle->producto)
              <strong>{{ $detalle->producto->nombre }}</strong>
            @else
              <strong>{{ $detalle->descripcion }}</strong>
            @endif
          </td>
          <td style="text-align: center;">{{ number_format($detalle->cantidad, 0) }}</td>
          <td style="text-align: right;">${{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td>
          <td style="text-align: right; font-weight: bold;">${{ number_format($detalle->total, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

{{-- Totales --}}
@php
  $totalVentanas    = $cotizacion->ventanas->sum(fn($v) => $v->precio * $v->cantidad);
  $totalProductos   = $cotizacion->detalles->sum('total');
  $subtotalNeto     = $totalVentanas + $totalProductos;
  $iva              = $subtotalNeto * 0.19;
  $totalGeneral     = $subtotalNeto + $iva;
  $cantidadTotal    = $cotizacion->ventanas->sum('cantidad');
  $totalM2          = $cotizacion->ventanas->sum(fn($v) => ($v->ancho / 1000) * ($v->alto / 1000) * $v->cantidad);
@endphp

<div class="totals-section">
  <table class="totals-wrapper">
    <tr>
      <td style="width: 58%; border: none;"></td>
      <td style="width: 42%; border: none;">
        <table class="totals-inner">
          <tr>
            <td><strong>Cantidad ventanas:</strong></td>
            <td>{{ $cantidadTotal }} ud.</td>
          </tr>
          <tr>
            <td><strong>Total m²:</strong></td>
            <td>{{ number_format($totalM2, 2, ',', '.') }} m²</td>
          </tr>
          @if($totalVentanas > 0)
          <tr>
            <td><strong>Subtotal Ventanas:</strong></td>
            <td>${{ number_format($totalVentanas, 0, ',', '.') }}</td>
          </tr>
          @endif
          @if($totalProductos > 0)
          <tr>
            <td><strong>Subtotal Productos:</strong></td>
            <td>${{ number_format($totalProductos, 0, ',', '.') }}</td>
          </tr>
          @endif
          <tr>
            <td><strong>IVA 19%:</strong></td>
            <td>${{ number_format($iva, 0, ',', '.') }}</td>
          </tr>
          <tr class="total-final">
            <td>TOTAL</td>
            <td>${{ number_format($totalGeneral, 0, ',', '.') }}</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</div>

<!-- <p class="nota">Precios netos, no incluyen IVA &nbsp;·&nbsp; Cotización válida por 30 días</p> -->

{{-- Números de página vía dompdf --}}
<script type="text/php">
  if (isset($pdf)) {
    $w    = $pdf->get_width();
    $h    = $pdf->get_height();
    $font = $fontMetrics->get_font("helvetica", "normal");
    $pdf->page_text($w / 2 - 15, $h - 16, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, 8, [0.6, 0.6, 0.6]);
  }
</script>

</body>
</html>
