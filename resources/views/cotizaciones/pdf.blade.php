<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cotización #{{ $cotizacion->id }}</title>
  <style>
    @page { margin: 16mm 15mm 20mm 15mm; }

    body { font-family: sans-serif; font-size: 11.5px; color: #2b2f36; margin: 0; line-height: 1.5; font-variant-numeric: tabular-nums; }

    table { border-collapse: collapse; }
    .r { text-align: right; }
    .c { text-align: center; }
    .muted { color: #8a9099; }
    .nowrap { white-space: nowrap; }

    /* ── Encabezado ─────────────────────────────────────────── */
    .header td { vertical-align: top; padding: 0; border: none; }
    .brand-name { font-size: 25px; font-weight: bold; color: #1B3A6B; letter-spacing: 1px; line-height: 1; }
    .brand-tag  { font-size: 8px; font-weight: bold; letter-spacing: 3px; color: #6b7580; margin-top: 3px; }
    .company { margin-top: 13px; font-size: 10px; color: #8a9099; line-height: 1.55; }
    .company .legal { font-weight: bold; color: #4a5560; font-size: 11px; }

    /* Recuadro COTIZACIÓN */
    .quote-box { width: 188px; border: 1px solid #c9d4e2; }
    .quote-box .qb-title {
      text-align: center; color: #1B3A6B; font-weight: bold;
      letter-spacing: 2px; font-size: 12px; padding: 5px 0 4px;
      border-bottom: 1px solid #e7ecf2;
    }
    .quote-box .qb-row td { padding: 4px 11px; font-size: 10.5px; border: none; }
    .quote-box .qb-row td:first-child { color: #8a9099; }
    .quote-box .qb-row td:last-child { text-align: right; font-weight: bold; color: #2b2f36; }

    .rule { border: none; border-top: 2px solid #1B3A6B; margin: 14px 0 14px; }

    /* ── Cliente / intro ────────────────────────────────────── */
    .eyebrow { font-size: 8.5px; font-weight: bold; letter-spacing: 2px; color: #9aa1ab; text-transform: uppercase; }
    .client-name { font-size: 13.5px; font-weight: bold; color: #1B3A6B; margin-top: 2px; }
    .client-meta { font-size: 10.5px; color: #5a616b; line-height: 1.6; margin-top: 2px; }
    .intro { margin: 14px 0 10px; font-size: 11.5px; color: #3a3f47; }

    /* ── Secciones (ventanas / winperfil) ───────────────────── */
    .section-title {
      color: #1B3A6B; font-size: 9.5px; font-weight: bold; letter-spacing: 1px;
      text-transform: uppercase; border-bottom: 1px solid #dfe4ea;
      padding-bottom: 5px; margin: 16px 0 9px;
    }
    .section-title.sub { color: #7c838d; }
    .card { width: 100%; margin-bottom: 9px; border: 1px solid #dfe4ea; page-break-inside: avoid; }
    .card td { border: none; vertical-align: middle; }
    .card-img { width: 44%; background: #fbfcfe; border-right: 1px solid #dfe4ea; text-align: center; padding: 6px; }
    .card-head { background: #1B3A6B; color: #fff; padding: 9px 11px; font-size: 11px; font-weight: bold; letter-spacing: 0.3px; }
    .attr { width: 100%; }
    .attr td { padding: 5px 9px; font-size: 10.5px; border-bottom: 1px solid #eef1f5; }
    .attr td:first-child { color: #7c838d; width: 40%; }
    .attr td:last-child { color: #2b2f36; font-weight: bold; }
    .attr tr.ssub td { color: #1B3A6B; border-top: 1px solid #dfe4ea; border-bottom: none; padding-top: 5px; }
    .attr tr.ssub td:last-child { font-size: 12.5px; }
    .obs { font-size: 9.5px; color: #6b7280; font-style: italic; margin: -4px 0 12px; padding-left: 2px; }

    /* ── Tabla de productos ─────────────────────────────────── */
    .items { width: 100%; }
    .items thead th {
      background: #eef2f7; color: #5a6472; font-size: 8.5px; font-weight: bold;
      letter-spacing: 1px; text-transform: uppercase; padding: 8px 10px;
      border-top: 2px solid #1B3A6B; border-bottom: 1px solid #d5deea;
    }
    .items tbody td { padding: 9px 10px; border-bottom: 1px solid #e7ebf0; vertical-align: top; }
    .item-name { font-weight: bold; color: #2b2f36; font-size: 11px; }
    .item-sub { font-size: 9.5px; color: #8a9099; margin-top: 2px; }

    /* ── Resumen económico ──────────────────────────────────── */
    .summary { width: 100%; page-break-inside: avoid; margin-top: 16px; }
    .summary > td { border: none; padding: 0; vertical-align: top; }
    .sum-inner { width: 100%; }
    .sum-inner td { padding: 6px 12px; font-size: 11.5px; }
    .sum-inner td:first-child { color: #6b7280; }
    .sum-inner td:last-child { text-align: right; font-weight: bold; color: #2b2f36; }
    .proj-summary { font-size: 9.5px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; color: #7c838d; text-align: right; padding: 0 12px 7px; }
    .sum-line td { border-top: 1px solid #dfe4ea; }
    .total-band td {
      background: #1B3A6B; color: #fff; padding: 11px 12px; border: none;
    }
    .total-band td:first-child { font-size: 13px; font-weight: bold; letter-spacing: 2px; color: #fff; }
    .total-band td:last-child { font-size: 17.5px; font-weight: bold; text-align: right; color: #fff; }

    /* ── Condiciones + firma ────────────────────────────────── */
    .bottom { width: 100%; margin-top: 18px; page-break-inside: avoid; }
    .bottom td { border: none; vertical-align: top; padding: 0; }
    .cond-title { font-size: 9.5px; font-weight: bold; letter-spacing: 1px; color: #9aa1ab; text-transform: uppercase; margin-bottom: 7px; }
    .cond { font-size: 11px; color: #55606b; line-height: 1.95; }
    .sign { text-align: right; vertical-align: bottom; }
    .sign-label { font-size: 9px; font-weight: bold; letter-spacing: 1px; color: #9aa1ab; text-transform: uppercase; margin-bottom: 3px; }
    .sign-name { font-weight: bold; color: #2b2f36; font-size: 12.5px; }
    .sign-sub { font-size: 10px; color: #6b7280; margin-top: 1px; }

    /* ── Footer fijo ────────────────────────────────────────── */
    #pdf-footer {
      position: fixed; bottom: -12mm; left: 0; right: 0;
      border-top: 1px solid #e7ebf0; padding-top: 5px;
      font-size: 9px; color: #8a9099; text-align: center; letter-spacing: 0.4px;
    }
  </style>
</head>
<body>

@php
  $limpiar   = fn($s) => trim(preg_replace('/^\s*Nuevo\s*[íi]tem\s*/iu', '', (string) $s));
  $validez   = 15;
  $clienteNom = $cotizacion->cliente->razon_social
      ?: trim(($cotizacion->cliente->first_name ?? '') . ' ' . ($cotizacion->cliente->last_name ?? '')) ?: '—';
  $clienteRut = $cotizacion->cliente->rut ?? $cotizacion->cliente->identification ?? null;
  $clienteMail = $cotizacion->cliente->email ?? null;
  $clienteDir  = $cotizacion->cliente->address ?? $cotizacion->cliente->direccion ?? null;
  $clienteCiu  = $cotizacion->cliente->ciudad ?? null;
  $responsable = $cotizacion->vendedor->name ?? $cotizacion->vendedor->nombre ?? 'Administrador';
@endphp

{{-- Footer fijo --}}
<div id="pdf-footer">
  www.vialum.cl · contacto@vialum.cl · Los Ángeles
</div>

{{-- ── Encabezado ─────────────────────────────────────────── --}}
<table class="header" style="width:100%;">
  <tr>
    <td style="width:58%;">
      @if(!empty($logoBase64))
        <img src="{{ $logoBase64 }}" alt="VIALUM" style="width:155px; height:auto;">
      @else
        <div class="brand-name">VIALUM</div>
        <div class="brand-tag">VENTANAS PVC · ALUMINIO</div>
      @endif
      <div class="company">
        <span class="legal">HIDALGO E HIDALGO LIMITADA</span><br>
        RUT 76.096.031-4 · Vidriería, aluminios y ferretería<br>
        Balmaceda 454, Los Ángeles<br>
        contacto@vialum.cl · +56 43 2 311859
      </div>
    </td>
    <td style="width:42%;">
      <table class="quote-box" align="right">
        <tr><td colspan="2" class="qb-title">COTIZACIÓN</td></tr>
        <tr class="qb-row"><td>N°</td><td>{{ $cotizacion->id }}</td></tr>
        <tr class="qb-row"><td>Fecha</td><td>{{ \Carbon\Carbon::parse($cotizacion->fecha)->format('d/m/Y') }}</td></tr>
        <tr class="qb-row"><td>Validez</td><td>{{ $validez }} días</td></tr>
      </table>
    </td>
  </tr>
</table>

<hr class="rule">

{{-- ── Cliente ────────────────────────────────────────────── --}}
<div class="section-title" style="margin-top:4px;">Cliente</div>
<div class="client-name">{{ $clienteNom }}</div>
<div class="client-meta">
  @if($clienteRut) RUT {{ $clienteRut }}<br> @endif
  @if($clienteDir) {{ $clienteDir }}{{ $clienteCiu ? ', ' . $clienteCiu : '' }}<br> @endif
  @if($clienteMail) {{ $clienteMail }} @endif
</div>

<div class="intro">De acuerdo con lo solicitado, presentamos nuestra propuesta comercial:</div>

@if($cotizacion->observaciones)
  <div style="font-size:9.5px; color:#6b7280; margin-bottom:10px;">
    <strong>Observaciones:</strong> {{ $cotizacion->observaciones }}
  </div>
@endif

{{-- ── Ventanas (cotizador) ───────────────────────────────── --}}
@if($cotizacion->ventanas->count() > 0)
  <div class="section-title">Ventanas</div>
  @foreach($cotizacion->ventanas as $index => $ventana)
    <table class="card">
      <tr>
        <td class="card-img">
          @if($ventana->imagen && isset($imagenesBase64[$ventana->id]))
            <img src="{{ $imagenesBase64[$ventana->id] }}" style="max-width:270px; max-height:220px; width:auto; height:auto;" alt="Ventana V{{ $index + 1 }}">
          @else
            <span class="muted" style="font-size:9px;">Sin imagen</span>
          @endif
        </td>
        <td style="width:60%; vertical-align:top; padding:0;">
          <div class="card-head">V{{ $index + 1 }} · {{ mb_strtoupper($ventana->tipoVentana->nombre ?? 'VENTANA') }}</div>
          <table class="attr">
            <tr><td>Color</td><td>{{ $ventana->color->nombre ?? 'N/A' }}</td></tr>
            <tr><td>Vidrio</td><td>{{ $ventana->productoVidrioProveedor->producto->nombre ?? 'N/A' }}</td></tr>
            <tr><td>Medidas (an × al)</td><td>{{ number_format($ventana->ancho,0,',','.') }} × {{ number_format($ventana->alto,0,',','.') }} mm</td></tr>
            <tr><td>Cantidad</td><td>{{ $ventana->cantidad }} ud.</td></tr>
            <tr><td>Superficie</td><td>{{ number_format(($ventana->ancho/1000)*($ventana->alto/1000)*$ventana->cantidad, 2, ',', '.') }} m²</td></tr>
            @if($ventana->tipo_ventana_id === 55)
              <tr><td>Herraje</td><td>{{ !empty($ventana->config['manillon']) ? 'Manillón' : 'Pestillo' }}</td></tr>
            @endif
            <tr><td>Valor unitario</td><td>${{ number_format($ventana->cantidad > 0 ? round($ventana->precio / $ventana->cantidad) : $ventana->precio, 0, ',', '.') }}</td></tr>
            <tr class="ssub"><td>Subtotal</td><td>${{ number_format($ventana->precio, 0, ',', '.') }}</td></tr>
            @if(in_array($ventana->tipo_ventana_id, [59, 60]) && !empty($detallesConstructor[$ventana->id]))
              @php $det = $detallesConstructor[$ventana->id]; @endphp
              <tr>
                <td>Detalle</td>
                <td style="font-weight:normal; color:#5a616b; font-size:9px; line-height:1.6;">
                  @if(!empty($det['perfiles']))<strong>Perfiles:</strong> {{ implode(', ', $det['perfiles']) }}<br>@endif
                  @if(!empty($det['junquillos']))<strong>Junquillo:</strong> {{ implode(', ', $det['junquillos']) }}<br>@endif
                  @if(!empty($det['vidrios_templados']))<strong>Cristal:</strong> {{ implode(', ', $det['vidrios_templados']) }}<br>@endif
                  @if(!empty($det['tiradores']))<strong>Tirador:</strong> {{ implode(', ', $det['tiradores']) }}@endif
                </td>
              </tr>
            @endif
          </table>
        </td>
      </tr>
    </table>
    @php $obsV = $ventana->observacion ?? ($ventana->config['observacion'] ?? null); @endphp
    @if($obsV)
      <div class="obs">Observación: {{ $obsV }}</div>
    @endif
  @endforeach
@endif

{{-- ── Ventanas WINPERFIL ─────────────────────────────────── --}}
@php
  $winperfilItems = $cotizacion->detalles->where('tipo_item', 'winperfil')->values();
  $productosItems = $cotizacion->detalles->where('tipo_item', '!=', 'winperfil')->values();
@endphp

@if($winperfilItems->count() > 0)
  <div class="section-title">Ventanas Winperfil</div>
  @foreach($winperfilItems as $i => $detalle)
    <table class="card">
      <tr>
        <td class="card-img">
          @if(!empty($graficos[$detalle->id]))
            <img src="{{ $graficos[$detalle->id] }}" style="max-width:240px; max-height:210px; width:auto; height:auto;" alt="{{ $detalle->descripcion }}">
          @else
            <span class="muted" style="font-size:9px;">Sin imagen</span>
          @endif
        </td>
        <td style="width:60%; vertical-align:top; padding:0;">
          <div class="card-head">{{ $limpiar($detalle->descripcion) }}</div>
          <table class="attr">
            @if($detalle->ancho_mm && $detalle->alto_mm)
              <tr><td>Medidas (an × al)</td><td>{{ number_format($detalle->ancho_mm,0,',','.') }} × {{ number_format($detalle->alto_mm,0,',','.') }} mm</td></tr>
              <tr><td>Superficie</td><td>{{ number_format(($detalle->ancho_mm/1000)*($detalle->alto_mm/1000)*$detalle->cantidad, 2, ',', '.') }} m²</td></tr>
            @endif
            <tr><td>Cantidad</td><td>{{ number_format($detalle->cantidad, 0) }} ud.</td></tr>
            <tr><td>Valor unitario</td><td>${{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td></tr>
            <tr class="ssub"><td>Subtotal</td><td>${{ number_format($detalle->total, 0, ',', '.') }}</td></tr>
          </table>
        </td>
      </tr>
    </table>
  @endforeach
@endif

{{-- ── Productos ──────────────────────────────────────────── --}}
@if($productosItems->count() > 0)
  @if($cotizacion->ventanas->count() > 0 || $winperfilItems->count() > 0)
    <div class="section-title sub">Adicionales y servicios</div>
  @endif
  <table class="items">
    <thead>
      <tr>
        <th style="text-align:left; width:48%;">Detalle</th>
        <th class="c" style="width:9%;">Cant.</th>
        <th class="r" style="width:15%;">P. Unit.</th>
        <th class="c" style="width:11%;">Desc.</th>
        <th class="r" style="width:17%;">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      @foreach($productosItems as $detalle)
        @php
          if ($detalle->listaPrecio) {
            $nombre = $detalle->listaPrecio->producto->nombre ?? 'N/A';
          } elseif ($detalle->producto) {
            $nombre = $detalle->descripcion ?: $detalle->producto->nombre;
          } else {
            $nombre = $detalle->descripcion;
          }
          $nombre = $limpiar($nombre);
          $color  = $detalle->listaPrecio->color ?? $detalle->listaPrecio->productoColorProveedor->color ?? null;
          $descPct = $detalle->descuento ?? null;
        @endphp
        <tr>
          <td>
            <div class="item-name">{{ $nombre }}</div>
            @if($detalle->listaPrecio && $detalle->esVidrio && $detalle->ancho_mm && $detalle->alto_mm)
              <div class="item-sub">
                {{ $detalle->ancho_mm }} × {{ $detalle->alto_mm }} mm ({{ number_format($detalle->m2, 4) }} m²)
                @if($detalle->pulido) · Pulido @endif
              </div>
            @endif
            @if($color)
              <div class="item-sub">Color: {{ $color->nombre ?? 'N/A' }}</div>
            @endif
          </td>
          <td class="c">{{ number_format($detalle->cantidad, 0) }}</td>
          <td class="r nowrap">${{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td>
          <td class="c muted">{{ $descPct ? $descPct . '%' : '—' }}</td>
          <td class="r nowrap" style="font-weight:bold; color:#23272e;">${{ number_format($detalle->total, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

{{-- ── Resumen económico ──────────────────────────────────── --}}
@php
  $totalVentanas  = $cotizacion->ventanas->sum('precio');
  $totalProductos = $cotizacion->detalles->sum('total');       // winperfil + productos
  $subtotalNeto   = $totalVentanas + $totalProductos;
  $iva            = round($subtotalNeto * 0.19);
  $totalGeneral   = $subtotalNeto + $iva;

  $cantidadTotal  = $cotizacion->ventanas->sum('cantidad');
  $totalM2        = $cotizacion->ventanas->sum(fn($v) => ($v->ancho / 1000) * ($v->alto / 1000) * $v->cantidad);
  $wpItems        = $cotizacion->detalles->where('tipo_item', 'winperfil');
  $cantidadTotal += $wpItems->sum('cantidad');
  $totalM2       += $wpItems->sum(fn($d) => ($d->ancho_mm > 0 && $d->alto_mm > 0) ? ($d->ancho_mm / 1000) * ($d->alto_mm / 1000) * $d->cantidad : 0);
@endphp

<table class="summary">
  <tr class="summary">
    <td style="width:52%;"></td>
    <td style="width:48%;">
      @if($cantidadTotal > 0)
        <div class="proj-summary">Resumen del proyecto · {{ $cantidadTotal }} {{ $cantidadTotal == 1 ? 'ventana' : 'ventanas' }} · {{ number_format($totalM2, 2, ',', '.') }} m²</div>
      @endif
      <table class="sum-inner">
        <tr class="sum-line"><td>Neto</td><td>${{ number_format($subtotalNeto, 0, ',', '.') }}</td></tr>
        <tr><td>IVA 19%</td><td>${{ number_format($iva, 0, ',', '.') }}</td></tr>
      </table>
      <table style="width:100%; margin-top:6px;">
        <tr class="total-band"><td>TOTAL</td><td>${{ number_format($totalGeneral, 0, ',', '.') }}</td></tr>
      </table>
    </td>
  </tr>
</table>

{{-- ── Condiciones + firma ────────────────────────────────── --}}
@php
  // Condiciones variables desde el ERP si existen (una por línea); si no, las por defecto.
  $condRaw = $cotizacion->condiciones_comerciales ?? null;
  $condLineas = $condRaw
      ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $condRaw))))
      : [
          "Validez de la oferta: {$validez} días corridos.",
          'Valores expresados en pesos chilenos. Precios netos, IVA no incluido.',
          'Forma de pago y plazo de entrega: a convenir.',
          'Despacho e instalación se cotizan por separado si aplica.',
        ];
  $vend = $cotizacion->vendedor;
  $vendMail = $vend->email ?? null;
  $vendTel  = $vend->phone ?? $vend->telefono ?? null;
@endphp
<table class="bottom">
  <tr>
    <td style="width:58%;">
      <div class="cond-title">Condiciones comerciales</div>
      <div class="cond">
        @foreach($condLineas as $cl)· {{ $cl }}<br>@endforeach
      </div>
    </td>
    <td style="width:42%; vertical-align:bottom;" class="sign">
      @if($vend && ($vend->name ?? $vend->nombre ?? null))
        <div class="sign-label">Ejecutivo comercial</div>
        <div class="sign-name">{{ $vend->name ?? $vend->nombre }}</div>
        @if($vendMail)<div class="sign-sub">{{ $vendMail }}</div>@endif
        @if($vendTel)<div class="sign-sub">{{ $vendTel }}</div>@endif
      @endif
    </td>
  </tr>
</table>

{{-- Números de página + encabezado reducido desde la página 2 --}}
<script type="text/php">
  if (isset($pdf)) {
    $w    = $pdf->get_width();
    $h    = $pdf->get_height();
    $font = $fontMetrics->get_font("helvetica", "normal");
    $bold = $fontMetrics->get_font("helvetica", "bold");
    $pdf->page_text($w - 90, $h - 24, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, 7, [0.70, 0.73, 0.77]);

    // Encabezado reducido en páginas 2 en adelante (en el margen superior).
    $cotId = "{{ $cotizacion->id }}";
    $pdf->page_script('
      if ($PAGE_NUM > 1) {
        $f = $fontMetrics->get_font("helvetica", "bold");
        $pdf->text(42, 22, "VIALUM   -   Cotización N\xc2\xb0 ' . $cotId . '   -   Página " . $PAGE_NUM . " de " . $PAGE_COUNT, $f, 8, array(0.42, 0.45, 0.50));
      }
    ');
  }
</script>

</body>
</html>
