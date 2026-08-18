<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cotización #{{ $cotizacion->id }}</title>
  <style>
    @page { margin: 16mm 16mm 22mm 16mm; }

    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #2b2f42; margin: 0; line-height: 1.5; }
    p { margin: 0; }
    .muted { color: #6b7180; }
    .b { font-weight: bold; }

    /* ── Header ── */
    .hdr { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    .hdr td { border: none; vertical-align: top; padding: 0; }
    .company { line-height: 1.6; }
    .company .name { font-weight: bold; color: #2b2f42; }

    .coti-box { border: 1.5px solid #1B3A6B; border-collapse: collapse; }
    .coti-box td { border: none; padding: 4px 14px; font-size: 11px; }
    .coti-box td.t { color: #1B3A6B; font-weight: bold; letter-spacing: 2px; text-align: center; font-size: 13px; border-bottom: 1px solid #cdd8e6; padding: 7px 14px; }
    .coti-box td.k { color: #6b7180; }
    .coti-box td.v { text-align: right; font-weight: bold; }

    .rule { height: 3px; background: #1B3A6B; margin: 14px 0; font-size: 0; line-height: 0; }
    .eyebrow { text-transform: uppercase; letter-spacing: 1px; font-size: 9px; color: #6b7180; font-weight: bold; margin-bottom: 3px; }
    .cli-name { font-weight: bold; font-size: 13px; }
    .intro { margin: 14px 0 6px; }

    /* ── Título de sección ── */
    .sec { color: #1B3A6B; font-size: 12px; font-weight: bold; letter-spacing: .3px; margin: 22px 0 8px; }

    /* ── Tarjeta de ventana ── */
    .card { width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 1px solid #e6e9ef; page-break-inside: avoid; }
    .card > tbody > tr > td { border: none; vertical-align: top; }
    .card .img { width: 42%; background: #f7f9fb; border-right: 1px solid #e6e9ef; text-align: center; vertical-align: middle; padding: 10px; }
    .card .hd { background: #eef2f7; color: #1B3A6B; font-weight: bold; font-size: 12px; padding: 8px 10px; border-bottom: 2px solid #1B3A6B; }
    .attr { width: 100%; border-collapse: collapse; }
    .attr td { padding: 5px 10px; font-size: 10.5px; border-bottom: 1px solid #eef1f5; }
    .attr td.k { color: #6b7180; width: 38%; }
    .attr td.v { color: #2b2f42; }
    .attr tr:last-child td { border-bottom: none; }

    /* ── Tabla de productos ── */
    .tbl { width: 100%; border-collapse: collapse; }
    .tbl th { background: #eef2f7; color: #42506b; font-size: 10px; text-transform: uppercase; letter-spacing: .4px; padding: 8px 10px; text-align: left; border-bottom: 2px solid #1B3A6B; }
    .tbl td { padding: 8px 10px; font-size: 11px; border-bottom: 1px solid #eef1f5; }
    .tbl .r { text-align: right; }
    .tbl .c { text-align: center; }

    /* ── Totales ── */
    .totals { margin-top: 18px; page-break-inside: avoid; }
    .tot { width: 100%; border-collapse: collapse; }
    .tot td { padding: 4px 0; font-size: 11px; border: none; }
    .tot .k { text-align: right; color: #6b7180; padding-right: 24px; }
    .tot .v { text-align: right; color: #2b2f42; font-weight: bold; white-space: nowrap; }
    .tot .grand td { border-top: 2px solid #d9dee6; padding-top: 10px; font-size: 16px; font-weight: bold; color: #1B3A6B; }

    /* ── Condiciones / firma ── */
    .cond-title { text-transform: uppercase; letter-spacing: 1px; font-size: 9px; color: #6b7180; font-weight: bold; margin: 24px 0 6px; }
    .cond td { padding: 2px 0; font-size: 10px; color: #4a5060; vertical-align: top; }
    .firma-line { border-top: 1px solid #9aa1b0; width: 220px; }

    /* ── Footer ── */
    #pdf-footer { position: fixed; bottom: -14mm; left: 0; right: 0; border-top: 1px solid #e6e9ef; padding-top: 4px; font-size: 9px; color: #aab; text-align: center; }
  </style>
</head>
<body>

@php
  $cli       = $cotizacion->cliente;
  $cliNombre = optional($cli)->razon_social ?: trim((optional($cli)->first_name ?? '') . ' ' . (optional($cli)->last_name ?? '')) ?: 'Consumidor Final';
  $rut       = optional($cli)->rut ?? optional($cli)->identification ?? null;
  $telefono  = optional($cli)->phone ?? optional($cli)->telefono ?? null;
  $correo    = optional($cli)->email ?? null;
  $direccion = optional($cli)->address ?? optional($cli)->direccion ?? null;
  $ciudad    = optional($cli)->ciudad ?? null;
@endphp

{{-- Footer fijo --}}
<div id="pdf-footer">
  Cotización #{{ $cotizacion->id }} &nbsp;·&nbsp; {{ $cliNombre }} &nbsp;·&nbsp; Válida 15 días
</div>

{{-- Header: logo + empresa (izq) · caja COTIZACIÓN (der) --}}
<table class="hdr">
  <tr>
    <td style="width: 60%;">
      @if(!empty($logoBase64))
        <img src="{{ $logoBase64 }}" alt="VIALUM" width="96" style="display: block; margin-bottom: 10px;" />
      @else
        <div style="font-size: 24px; font-weight: bold; color: #1B3A6B;">VIALUM</div>
      @endif
      <div class="company">
        <span class="name">HIDALGO E HIDALGO LIMITADA</span><br>
        <span class="muted">RUT 76.096.031-4 · Vidriería, aluminios y ferretería</span><br>
        <span class="muted">Balmaceda 454, Los Ángeles</span><br>
        <span class="muted">contacto@vialum.cl · +56 43 2 311859 · www.vialum.cl</span>
      </div>
    </td>
    <td style="width: 40%; vertical-align: top;">
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
<div class="cli-name">{{ $cliNombre }}</div>
@if($rut)<div class="muted">RUT {{ $rut }}</div>@endif
@if($direccion)<div class="muted">{{ $direccion }}{{ $ciudad ? ', ' . $ciudad : '' }}</div>@endif
@if($telefono)<div class="muted">{{ $telefono }}</div>@endif
@if($correo)<div class="muted">{{ $correo }}</div>@endif
@if($cotizacion->observaciones)<div style="margin-top: 5px;"><span class="b">Observaciones:</span> {{ $cotizacion->observaciones }}</div>@endif

<div class="intro">Junto con saludar, tenemos el agrado de presentar la siguiente cotización según su solicitud:</div>

{{-- ── Ventanas (cotizador) ─────────────────────────────────────────────── --}}
@if($cotizacion->ventanas->count() > 0)
  <div class="sec">Ventanas</div>
@endif
@foreach($cotizacion->ventanas as $index => $ventana)
  <table class="card">
    <tr>
      <td class="img">
        @if($ventana->imagen && isset($imagenesBase64[$ventana->id]))
          <img src="{{ $imagenesBase64[$ventana->id] }}" style="display: block; margin: 0 auto; max-width: 260px; max-height: 180px;" alt="Vista ventana" />
        @elseif($ventana->imagen)
          <span class="muted" style="font-size: 10px;">Imagen no disponible</span>
        @else
          <span class="muted" style="font-size: 10px;">Sin imagen</span>
        @endif
      </td>
      <td style="width: 58%; padding: 0;">
        <div class="hd">V{{ $index + 1 }} &mdash; {{ $ventana->tipoVentana->nombre ?? 'N/A' }}</div>
        <table class="attr">
          <tr><td class="k">Color</td><td class="v">{{ $ventana->color->nombre ?? 'N/A' }}</td></tr>
          <tr><td class="k">Vidrio</td><td class="v">{{ $ventana->productoVidrioProveedor->producto->nombre ?? 'N/A' }}</td></tr>
          <tr><td class="k">Ancho</td><td class="v">{{ $ventana->ancho }} mm</td></tr>
          <tr><td class="k">Alto</td><td class="v">{{ $ventana->alto }} mm</td></tr>
          <tr><td class="k">Cantidad</td><td class="v">{{ $ventana->cantidad }}</td></tr>
          @if($ventana->tipo_ventana_id === 55)
            <tr><td class="k">Herraje</td><td class="v">{{ !empty($ventana->config['manillon']) ? 'Manillón' : 'Pestillo' }}</td></tr>
          @endif
          <tr><td class="k">Valor Neto</td><td class="v">${{ number_format($ventana->cantidad > 0 ? round($ventana->precio / $ventana->cantidad) : $ventana->precio, 0, ',', '.') }}</td></tr>
          <tr><td class="k">Total Neto</td><td class="v b">${{ number_format($ventana->precio, 0, ',', '.') }}</td></tr>
          @if(in_array($ventana->tipo_ventana_id, [59, 60]) && !empty($detallesConstructor[$ventana->id]))
            @php $det = $detallesConstructor[$ventana->id]; @endphp
            <tr>
              <td class="k">Detalle</td>
              <td class="v" style="font-size: 9.5px; line-height: 1.6;">
                @if(!empty($det['perfiles']))<span class="b">Perfiles:</span> {{ implode(', ', $det['perfiles']) }}<br>@endif
                @if(!empty($det['junquillos']))<span class="b">Junquillo:</span> {{ implode(', ', $det['junquillos']) }}<br>@endif
                @if(!empty($det['vidrios_templados']))<span class="b">Cristal:</span> {{ implode(', ', $det['vidrios_templados']) }}<br>@endif
                @if(!empty($det['tiradores']))<span class="b">Tirador:</span> {{ implode(', ', $det['tiradores']) }}@endif
              </td>
            </tr>
          @endif
        </table>
      </td>
    </tr>
  </table>
@endforeach

{{-- ── Ventanas WINPERFIL ───────────────────────────────────────────────── --}}
@php
  $winperfilItems = $cotizacion->detalles->where('tipo_item', 'winperfil')->values();
  $productosItems = $cotizacion->detalles->where('tipo_item', '!=', 'winperfil')->values();
@endphp

@if($winperfilItems->count() > 0)
  <div class="sec">Ventanas WINPERFIL</div>
  @foreach($winperfilItems as $detalle)
    <table class="card">
      <tr>
        <td class="img">
          @if(!empty($graficos[$detalle->id]))
            <img src="{{ $graficos[$detalle->id] }}" style="display: block; margin: 0 auto; max-width: 190px; max-height: 160px;" alt="{{ $detalle->descripcion }}" />
          @else
            <span class="muted" style="font-size: 10px;">Sin imagen</span>
          @endif
        </td>
        <td style="width: 58%; padding: 0;">
          <div class="hd">{{ $detalle->descripcion }}</div>
          <table class="attr">
            @if($detalle->ancho_mm && $detalle->alto_mm)
              <tr><td class="k">Dimensiones</td><td class="v">{{ number_format($detalle->ancho_mm,0,',','.') }} × {{ number_format($detalle->alto_mm,0,',','.') }} mm</td></tr>
            @endif
            <tr><td class="k">Cantidad</td><td class="v">{{ number_format($detalle->cantidad, 0) }} ud.</td></tr>
            <tr><td class="k">Precio Unitario</td><td class="v">${{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td></tr>
            <tr><td class="k">Total</td><td class="v b">${{ number_format($detalle->total, 0, ',', '.') }}</td></tr>
          </table>
        </td>
      </tr>
    </table>
  @endforeach
@endif

{{-- ── Productos ─────────────────────────────────────────────────────────── --}}
@if($productosItems->count() > 0)
  <div class="sec">Productos</div>
  <table class="tbl">
    <thead>
      <tr>
        <th style="width: 52%;">Descripción</th>
        <th class="c" style="width: 10%;">Cant.</th>
        <th class="r" style="width: 19%;">P. Unit.</th>
        <th class="r" style="width: 19%;">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach($productosItems as $detalle)
        <tr>
          <td>
            @if($detalle->listaPrecio)
              <span class="b">{{ $detalle->listaPrecio->producto->nombre ?? 'N/A' }}</span>
              @if($detalle->esVidrio && $detalle->ancho_mm && $detalle->alto_mm)
                <br><span class="muted" style="font-size: 10px;">{{ $detalle->ancho_mm }}mm × {{ $detalle->alto_mm }}mm ({{ number_format($detalle->m2, 4) }} m²)@if($detalle->pulido) · PULIDO @endif</span>
              @endif
              @php $color = $detalle->listaPrecio->color ?? $detalle->listaPrecio->productoColorProveedor->color ?? null; @endphp
              @if($color)<br><span class="muted" style="font-size: 10px;">Color: {{ $color->nombre ?? 'N/A' }}</span>@endif
            @elseif($detalle->producto)
              <span class="b">{{ $detalle->descripcion ?: $detalle->producto->nombre }}</span>
            @else
              <span class="b">{{ $detalle->descripcion }}</span>
            @endif
          </td>
          <td class="c">{{ number_format($detalle->cantidad, 0) }}</td>
          <td class="r">${{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td>
          <td class="r b">${{ number_format($detalle->total, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

{{-- ── Totales ───────────────────────────────────────────────────────────── --}}
@php
  $totalVentanas  = $cotizacion->ventanas->sum('precio');
  $totalProductos = $cotizacion->detalles->sum('total');
  $subtotalNeto   = $totalVentanas + $totalProductos;
  $iva            = $subtotalNeto * 0.19;
  $totalGeneral   = $subtotalNeto + $iva;

  $cantidadTotal  = $cotizacion->ventanas->sum('cantidad');
  $totalM2        = $cotizacion->ventanas->sum(fn($v) => ($v->ancho / 1000) * ($v->alto / 1000) * $v->cantidad);
  $wpItems        = $cotizacion->detalles->where('tipo_item', 'winperfil');
  $cantidadTotal += $wpItems->sum('cantidad');
  $totalM2       += $wpItems->sum(fn($d) => ($d->ancho_mm > 0 && $d->alto_mm > 0) ? ($d->ancho_mm / 1000) * ($d->alto_mm / 1000) * $d->cantidad : 0);
@endphp

<div class="totals">
  <table style="width: 100%; border-collapse: collapse;">
    <tr>
      <td style="width: 55%; border: none;"></td>
      <td style="width: 45%; border: none;">
        @if($cantidadTotal > 0)
          <div class="muted" style="font-size: 10px; margin-bottom: 6px; text-align: right;">{{ $cantidadTotal }} ventana(s) · {{ number_format($totalM2, 2, ',', '.') }} m²</div>
        @endif
        <table class="tot">
          {{-- Subtotales solo si la cotización mezcla ventanas y productos (si no, serían iguales al Neto) --}}
          @if($totalVentanas > 0 && $totalProductos > 0)
            <tr><td class="k">Subtotal Ventanas</td><td class="v">${{ number_format($totalVentanas, 0, ',', '.') }}</td></tr>
            <tr><td class="k">Subtotal Productos</td><td class="v">${{ number_format($totalProductos, 0, ',', '.') }}</td></tr>
          @endif
          <tr><td class="k">Neto</td><td class="v">${{ number_format($subtotalNeto, 0, ',', '.') }}</td></tr>
          <tr><td class="k">IVA 19%</td><td class="v">${{ number_format($iva, 0, ',', '.') }}</td></tr>
          <tr class="grand"><td class="k" style="color:#1B3A6B;">TOTAL</td><td class="v" style="color:#1B3A6B;">${{ number_format($totalGeneral, 0, ',', '.') }}</td></tr>
        </table>
      </td>
    </tr>
  </table>
</div>

{{-- ── Condiciones comerciales ──────────────────────────────────────────── --}}
<div class="cond-title">Condiciones comerciales</div>
<table class="cond" style="border-collapse: collapse;">
  <tr><td style="padding-right: 6px;">·</td><td>Validez de la oferta: 15 días corridos.</td></tr>
  <tr><td style="padding-right: 6px;">·</td><td>Valores en pesos chilenos, IVA incluido.</td></tr>
  <tr><td style="padding-right: 6px;">·</td><td>Forma de pago y plazo de entrega: a convenir.</td></tr>
  <tr><td style="padding-right: 6px;">·</td><td>Despacho e instalación se cotizan por separado si aplica.</td></tr>
</table>

{{-- ── Firma ────────────────────────────────────────────────────────────── --}}
<table style="width: 100%; border-collapse: collapse; margin-top: 40px; page-break-inside: avoid;">
  <tr>
    <td style="width: 58%; border: none;"></td>
    <td style="width: 42%; border: none; text-align: center;">
      <div class="firma-line"></div>
      <div class="b" style="font-size: 11px; margin-top: 2px;">{{ $cotizacion->vendedor?->nombre ?? 'VIALUM' }}</div>
      <div class="muted" style="font-size: 9px;">VIALUM · contacto@vialum.cl</div>
    </td>
  </tr>
</table>

{{-- Números de página --}}
<script type="text/php">
  if (isset($pdf)) {
    $w = $pdf->get_width();
    $h = $pdf->get_height();
    $font = $fontMetrics->get_font("DejaVu Sans", "normal");
    $pdf->page_text($w / 2 - 30, $h - 22, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, 8, [0.6, 0.6, 0.6]);
  }
</script>

</body>
</html>
