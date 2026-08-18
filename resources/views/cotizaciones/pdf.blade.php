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

    /* ── Estilo carta (moderno) ── */
    .muted { color: #6b7180; }
    .hdr { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .hdr td { border: none; vertical-align: top; padding: 0; }
    .sub { color: #1B3A6B; font-size: 9px; font-weight: bold; letter-spacing: 2px; margin: 2px 0 8px; }
    .coti-box { border: 1.5px solid #1B3A6B; border-collapse: collapse; }
    .coti-box td { border: none; padding: 4px 14px; font-size: 11px; }
    .coti-box td.t { color: #1B3A6B; font-weight: bold; letter-spacing: 2px; text-align: center; font-size: 13px; border-bottom: 1px solid #cdd8e6; padding: 7px; }
    .coti-box td.k { color: #6b7180; }
    .coti-box td.v { text-align: right; font-weight: bold; }
    .rule { height: 3px; background: #1B3A6B; margin: 14px 0; font-size: 0; line-height: 0; }
    .eyebrow { text-transform: uppercase; letter-spacing: 1px; font-size: 9px; color: #6b7180; font-weight: bold; margin-bottom: 3px; }
    .intro { margin: 14px 0 4px; }
    .cond-title { text-transform: uppercase; letter-spacing: 1px; font-size: 9px; color: #6b7180; font-weight: bold; margin: 24px 0 6px; }
    .cond td { padding: 2px 0; font-size: 10px; color: #4a5060; vertical-align: top; }
    .firma-line { border-top: 1px solid #9aa1b0; width: 220px; }
  </style>
</head>
<body>

{{-- Footer fijo en todas las páginas --}}
<div id="pdf-footer">
  Cotización #{{ $cotizacion->id }} &nbsp;·&nbsp; {{ $cotizacion->cliente->razon_social ?: trim(($cotizacion->cliente->first_name ?? '') . ' ' . ($cotizacion->cliente->last_name ?? '')) ?: '-' }} &nbsp;·&nbsp; Válida 15 días
</div>

{{-- Header moderno: logo + empresa (izq) · caja COTIZACIÓN (der) --}}
@php
  $cli       = $cotizacion->cliente;
  $cliNombre = optional($cli)->razon_social ?: trim((optional($cli)->first_name ?? '') . ' ' . (optional($cli)->last_name ?? '')) ?: 'Consumidor Final';
  $rut       = optional($cli)->rut ?? optional($cli)->identification ?? null;
  $telefono  = optional($cli)->phone ?? optional($cli)->telefono ?? null;
  $correo    = optional($cli)->email ?? null;
  $direccion = optional($cli)->address ?? optional($cli)->direccion ?? null;
  $ciudad    = optional($cli)->ciudad ?? null;
@endphp

<table class="hdr">
  <tr>
    <td style="width: 58%;">
      @if(!empty($logoBase64))
        <img src="{{ $logoBase64 }}" alt="VIALUM" width="150" style="display: block; margin-bottom: 8px;" />
      @else
        <div style="font-size: 26px; font-weight: bold; color: #1B3A6B;">VIALUM</div>
        <div class="sub">VENTANAS PVC · ALUMINIO</div>
      @endif
      <div style="line-height: 1.6;">
        <strong>HIDALGO E HIDALGO LIMITADA</strong><br>
        <span class="muted">RUT 76.096.031-4 · Vidriería, aluminios y ferretería</span><br>
        <span class="muted">Balmaceda 454, Los Ángeles</span><br>
        <span class="muted">contacto@vialum.cl · +56 43 2 311859 · www.vialum.cl</span>
      </div>
    </td>
    <td style="width: 42%; vertical-align: top;">
      <table class="coti-box">
        <tr><td class="t" colspan="2">COTIZACIÓN</td></tr>
        <tr><td class="k">N°</td><td class="v">#{{ $cotizacion->id }}</td></tr>
        <tr><td class="k">Fecha</td><td class="v">{{ \Carbon\Carbon::parse($cotizacion->fecha)->locale('es')->isoFormat('DD-MM-YYYY') }}</td></tr>
        <tr><td class="k">Validez</td><td class="v">15 días</td></tr>
      </table>
    </td>
  </tr>
</table>

<div class="rule"></div>

{{-- Cliente --}}
<div class="eyebrow">Señor(es)</div>
<div style="font-weight: bold; font-size: 13px;">{{ $cliNombre }}</div>
@if($rut)<div class="muted">RUT {{ $rut }}</div>@endif
@if($direccion)<div class="muted">{{ $direccion }}{{ $ciudad ? ', ' . $ciudad : '' }}</div>@endif
@if($telefono)<div class="muted">{{ $telefono }}</div>@endif
@if($correo)<div class="muted">{{ $correo }}</div>@endif
@if($cotizacion->observaciones)<div style="margin-top: 5px;"><strong>Observaciones:</strong> {{ $cotizacion->observaciones }}</div>@endif

<div class="intro">Junto con saludar, tenemos el agrado de presentar la siguiente cotización según su solicitud:</div>

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
          @if($ventana->tipo_ventana_id === 55)
            <tr>
              <th style="{{ $labelStyle }}">Herraje</th>
              <td style="{{ $valStyle }}">{{ !empty($ventana->config['manillon']) ? 'Manillón' : 'Pestillo' }}</td>
            </tr>
          @endif
          <tr>
            <th style="{{ $labelStyle }}">Valor Neto</th>
            <td style="{{ $valStyle }}">${{ number_format($ventana->cantidad > 0 ? round($ventana->precio / $ventana->cantidad) : $ventana->precio, 0, ',', '.') }}</td>
          </tr>
          <tr>
            <th style="{{ $labelStyle }}">Total Neto</th>
            <td style="{{ $valStyle }}"><strong>${{ number_format($ventana->precio, 0, ',', '.') }}</strong></td>
          </tr>
          @if(in_array($ventana->tipo_ventana_id, [59, 60]) && !empty($detallesConstructor[$ventana->id]))
            @php $det = $detallesConstructor[$ventana->id]; @endphp
            <tr>
              <th style="{{ $labelStyle }}">Detalle</th>
              <td style="{{ $valStyle }}; font-size: 10px; line-height: 1.6;">
                @if(!empty($det['perfiles']))
                  <strong>Perfiles:</strong> {{ implode(', ', $det['perfiles']) }}<br>
                @endif
                @if(!empty($det['junquillos']))
                  <strong>Junquillo:</strong> {{ implode(', ', $det['junquillos']) }}<br>
                @endif
                @if(!empty($det['vidrios_templados']))
                  <strong>Cristal:</strong> {{ implode(', ', $det['vidrios_templados']) }}<br>
                @endif
                @if(!empty($det['tiradores']))
                  <strong>Tirador:</strong> {{ implode(', ', $det['tiradores']) }}
                @endif
              </td>
            </tr>
          @endif
        </table>
      </td>
    </tr>
  </table>
@endforeach

{{-- ── Ventanas WINPERFIL ─────────────────────────────────────────────── --}}
@php
  $winperfilItems = $cotizacion->detalles->where('tipo_item', 'winperfil')->values();
  $productosItems = $cotizacion->detalles->where('tipo_item', '!=', 'winperfil')->values();
@endphp

@if($winperfilItems->count() > 0)
  <div class="section-title">Ventanas WINPERFIL</div>

  @foreach($winperfilItems as $i => $detalle)
    {{-- Una tarjeta por ventana: imagen izq. | datos der. --}}
    <table style="width:100%; border-collapse:collapse; margin-bottom:10px; border:1px solid #ddd; page-break-inside:avoid;">
      <tr>

        {{-- Celda imagen: ancho fijo 38%, imagen centrada con max-height --}}
        <td style="width:38%; background:#f7f9fb; border-right:1px solid #ddd; padding:10px; text-align:center; vertical-align:middle;">
          @if(!empty($graficos[$detalle->id]))
            <img
              src="{{ $graficos[$detalle->id] }}"
              style="display:block; margin:0 auto; max-width:190px; max-height:160px; width:auto; height:auto;"
              alt="{{ $detalle->descripcion }}"
            />
          @else
            <span style="color:#bbb; font-size:10px;">Sin imagen</span>
          @endif
        </td>

        {{-- Celda datos --}}
        <td style="width:62%; vertical-align:top; padding:0;">
          {{-- Encabezado azul --}}
          <div style="background:#1B3A6B; color:#fff; padding:7px 10px; font-size:11px; font-weight:bold; line-height:1.4;">
            {{ $detalle->descripcion }}
          </div>
          {{-- Tabla de atributos --}}
          @php
            $lbl = 'padding:5px 8px; font-weight:bold; color:#555; background:#f5f5f5; border:1px solid #eee; width:40%; font-size:10px;';
            $val = 'padding:5px 8px; border:1px solid #eee; font-size:10px;';
          @endphp
          <table style="width:100%; border-collapse:collapse;">
            @if($detalle->ancho_mm && $detalle->alto_mm)
            <tr>
              <td style="{{ $lbl }}">Dimensiones</td>
              <td style="{{ $val }}">{{ number_format($detalle->ancho_mm,0,',','.') }} × {{ number_format($detalle->alto_mm,0,',','.') }} mm</td>
            </tr>
            @endif
            <tr>
              <td style="{{ $lbl }}">Cantidad</td>
              <td style="{{ $val }}">{{ number_format($detalle->cantidad, 0) }} ud.</td>
            </tr>
            <tr>
              <td style="{{ $lbl }}">Precio Unitario</td>
              <td style="{{ $val }}">${{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td>
            </tr>
            <tr>
              <td style="padding:6px 8px; font-weight:bold; color:#fff; background:#1B3A6B; font-size:10px;">Total</td>
              <td style="padding:6px 8px; font-weight:bold; color:#1B3A6B; border:1px solid #ddd; font-size:11px;">${{ number_format($detalle->total, 0, ',', '.') }}</td>
            </tr>
          </table>
        </td>

      </tr>
    </table>
  @endforeach
@endif

{{-- ── Productos Adicionales (sin winperfil) ─────────────────────────── --}}
@if($productosItems->count() > 0)
  <div class="section-title">Productos</div>
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
      @foreach($productosItems as $detalle)
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
              <strong>{{ $detalle->descripcion ?: $detalle->producto->nombre }}</strong>
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
  $totalVentanas    = $cotizacion->ventanas->sum('precio');
  $totalProductos   = $cotizacion->detalles->sum('total');   // incluye winperfil + productos
  $subtotalNeto     = $totalVentanas + $totalProductos;
  $iva              = $subtotalNeto * 0.19;
  $totalGeneral     = $subtotalNeto + $iva;

  // Cantidad y m² de ventanas del cotizador
  $cantidadTotal    = $cotizacion->ventanas->sum('cantidad');
  $totalM2          = $cotizacion->ventanas->sum(fn($v) => ($v->ancho / 1000) * ($v->alto / 1000) * $v->cantidad);

  // Sumar también las ventanas Winperfil (tipo_item = 'winperfil' en detalles)
  $wpItems = $cotizacion->detalles->where('tipo_item', 'winperfil');
  $cantidadTotal += $wpItems->sum('cantidad');
  $totalM2       += $wpItems->sum(
      fn($d) => ($d->ancho_mm > 0 && $d->alto_mm > 0)
          ? ($d->ancho_mm / 1000) * ($d->alto_mm / 1000) * $d->cantidad
          : 0
  );
@endphp

<div class="totals-section">
  <table class="totals-wrapper">
    <tr>
      <td style="width: 58%; border: none;"></td>
      <td style="width: 42%; border: none;">
        <table class="totals-inner">
          @if($cantidadTotal > 0)
          <tr>
            <td><strong>Cantidad ventanas:</strong></td>
            <td>{{ $cantidadTotal }} ud.</td>
          </tr>
          <tr>
            <td><strong>Total m²:</strong></td>
            <td>{{ number_format($totalM2, 2, ',', '.') }} m²</td>
          </tr>
          @endif
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

{{-- Condiciones comerciales --}}
<div class="cond-title">Condiciones comerciales</div>
<table class="cond" style="border-collapse: collapse;">
  <tr><td style="padding-right: 6px;">·</td><td>Validez de la oferta: 15 días corridos.</td></tr>
  <tr><td style="padding-right: 6px;">·</td><td>Valores en pesos chilenos, IVA incluido.</td></tr>
  <tr><td style="padding-right: 6px;">·</td><td>Forma de pago y plazo de entrega: a convenir.</td></tr>
  <tr><td style="padding-right: 6px;">·</td><td>Despacho e instalación se cotizan por separado si aplica.</td></tr>
</table>

{{-- Firma --}}
<table style="width: 100%; border-collapse: collapse; margin-top: 40px; page-break-inside: avoid;">
  <tr>
    <td style="width: 58%; border: none;"></td>
    <td style="width: 42%; border: none; text-align: center;">
      <div class="firma-line"></div>
      <div style="font-weight: bold; font-size: 11px; margin-top: 2px;">{{ $cotizacion->vendedor?->nombre ?? 'VIALUM' }}</div>
      <div class="muted" style="font-size: 9px;">VIALUM · contacto@vialum.cl</div>
    </td>
  </tr>
</table>

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
