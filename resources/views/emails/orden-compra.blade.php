<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background:#f4f5f7; font-family: Arial, Helvetica, sans-serif; color:#2f2f2f;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.08);">
                    <tr>
                        <td style="background:#6a1b9a; padding:20px 28px;">
                            <span style="color:#ffffff; font-size:20px; font-weight:bold;">Vialum</span>
                            <span style="color:#e1bee7; font-size:14px; float:right; padding-top:4px;">Orden de compra {{ $orden->numero }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="font-size:15px; line-height:1.6; white-space:pre-line; margin:0 0 20px;">{{ $cuerpo }}</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee; border-radius:6px;">
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#555;">N° de orden</td>
                                    <td style="padding:12px 16px; font-size:14px; font-weight:bold; text-align:right;">{{ $orden->numero }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px; font-size:14px; color:#555; border-top:1px solid #eee;">Ítems</td>
                                    <td style="padding:12px 16px; font-size:14px; font-weight:bold; text-align:right; border-top:1px solid #eee;">{{ is_array($orden->items) ? count($orden->items) : 0 }}</td>
                                </tr>
                            </table>

                            <p style="font-size:13px; color:#888; margin:22px 0 0;">Adjuntamos el detalle completo en el PDF de esta orden.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#fafafa; padding:16px 28px; font-size:12px; color:#999; border-top:1px solid #eee;">
                            Este correo fue enviado desde el sistema de gestión de Vialum.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
