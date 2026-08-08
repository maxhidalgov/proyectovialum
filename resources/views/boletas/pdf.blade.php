<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; padding: 24px; }
  .header { width: 100%; margin-bottom: 14px; }
  .header td { vertical-align: top; }
  .logo { height: 46px; }
  .emisor { font-size: 11px; line-height: 1.35; }
  .emisor .empresa { font-size: 13px; font-weight: bold; }
  .doc-box { border: 2px solid #c0392b; border-radius: 6px; padding: 10px 14px; text-align: center; color: #c0392b; width: 210px; }
  .doc-box .rut { font-size: 13px; font-weight: bold; }
  .doc-box .tipo { font-size: 13px; font-weight: bold; margin: 3px 0; }
  .doc-box .num { font-size: 15px; font-weight: bold; }
  .doc-box .sii { font-size: 10px; margin-top: 4px; color: #444; }
  .cliente { font-size: 11px; line-height: 1.5; margin: 8px 0 14px; }
  .cliente strong { display: inline-block; min-width: 92px; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
  table.items th { text-align: left; border-bottom: 1.5px solid #333; padding: 6px 6px; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; }
  table.items td { padding: 6px 6px; border-bottom: 1px solid #e0e0e0; font-size: 11.5px; vertical-align: top; }
  table.items .num { text-align: right; white-space: nowrap; }
  table.items .c { text-align: center; }
  .totales { width: 46%; margin-left: 54%; margin-top: 14px; border: 1px solid #333; border-radius: 6px; }
  .totales td { padding: 6px 12px; font-size: 12px; }
  .totales .lbl { text-align: right; color: #333; }
  .totales .val { text-align: right; font-weight: bold; white-space: nowrap; }
  .totales .total-row td { border-top: 1.5px solid #333; font-size: 14px; }
  .foot { margin-top: 22px; text-align: center; font-size: 9px; color: #888; }
</style>
</head>
<body>
  <table class="header">
    <tr>
      <td style="width: 60%;">
        @if($logoBase64)<img src="{{ $logoBase64 }}" class="logo"><br>@endif
        <div class="emisor">
          <div class="empresa">{{ $emisor['nombre'] }}</div>
          {{ $emisor['giro'] }}<br>
          {{ $emisor['direccion'] }} — {{ $emisor['comuna'] }}<br>
          Fono: {{ $emisor['fono'] }} · {{ $emisor['mail'] }}
        </div>
      </td>
      <td style="width: 40%; text-align: right;">
        <div class="doc-box" style="display:inline-block;">
          <div class="rut">RUT: {{ $emisor['rut'] }}</div>
          <div class="tipo">{{ $tipoNombre }}</div>
          <div class="num">N° {{ $doc->numero_documento_bsale ?? '—' }}</div>
          <div class="sii">S.I.I. — {{ $emisor['comuna'] }}</div>
        </div>
      </td>
    </tr>
  </table>

  <div class="cliente">
    <strong>Señor(es):</strong> {{ $clienteNombre }}<br>
    @if($clienteRut)<strong>RUT:</strong> {{ $clienteRut }}<br>@endif
    <strong>Fecha:</strong> {{ $fecha }}<br>
    <strong>Forma de pago:</strong> {{ $formaPago }}
  </div>

  <table class="items">
    <thead>
      <tr>
        <th class="c" style="width:44px;">Cant.</th>
        <th>Ítem</th>
        <th class="num" style="width:80px;">Valor U.</th>
        <th class="num" style="width:60px;">Desc.</th>
        <th class="num" style="width:90px;">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $it)
      <tr>
        <td class="c">{{ rtrim(rtrim(number_format($it->cantidad, 2, ',', '.'), '0'), ',') }}</td>
        <td>{{ $it->nombre }}</td>
        <td class="num">${{ number_format($it->precio_unitario, 0, ',', '.') }}</td>
        <td class="num">{{ $it->descuento > 0 ? $it->descuento.'%' : '$0' }}</td>
        <td class="num">${{ number_format($it->total_neto, 0, ',', '.') }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <table class="totales">
    <tr><td class="lbl">NETO</td><td class="val">${{ number_format($neto, 0, ',', '.') }}</td></tr>
    <tr><td class="lbl">IVA 19%</td><td class="val">${{ number_format($iva, 0, ',', '.') }}</td></tr>
    <tr class="total-row"><td class="lbl">TOTAL</td><td class="val">${{ number_format($total, 0, ',', '.') }}</td></tr>
  </table>

  <div class="foot">
    Documento generado por Vialum a partir de la {{ strtolower($tipoNombre) }} electrónica emitida en el S.I.I.
    @if($doc->numero_documento_bsale) · Folio {{ $doc->numero_documento_bsale }}@endif
  </div>
</body>
</html>
