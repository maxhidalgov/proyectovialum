<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La oferta 725 de Ar Alena SPA (presupuesto 649) fue pisada por el A-725 de PESA, porque
 * NUMFACTURA no es único en Winperfil. Devuelve cliente/fecha/estado a Ar Alena para que
 * el A-725 de PESA se importe como cotización aparte. Idempotente y acotada a ese caso.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cot = DB::table('cotizaciones')
            ->where('winperfil_serie', 'A')->where('winperfil_numero', 725)
            ->where('fecha', '2026-07-30')
            ->first();
        if (!$cot) return;

        // Debe ser la mezclada: 10 ventanas de Ar Alena (total 3.263.002) con cliente PESA
        $nombre = DB::table('clientes')->where('id', $cot->cliente_id)->value('razon_social');
        if (stripos((string) $nombre, 'PESA') === false || (int) round($cot->total) !== 3263002) return;

        // Cliente propio de esta oferta (las hermanas 649/724/727/728 usan 1878/1879/1881/1882)
        $arAlena = 1880;
        $nombreAr = DB::table('clientes')->where('id', $arAlena)->value('razon_social');
        $enUso = DB::table('cotizaciones')->where('cliente_id', $arAlena)->exists();
        if (stripos((string) $nombreAr, 'Ar Alena') === false || $enUso) return;

        // Estado: el de la hermana del mismo presupuesto (A-649)
        $hermana = DB::table('cotizaciones')->where('winperfil_serie', 'A')->where('winperfil_numero', 649)->first();
        if (!$hermana) return;

        DB::table('cotizaciones')->where('id', $cot->id)->update([
            'cliente_id'           => $arAlena,
            'fecha'                => '2026-05-12',
            'estado_cotizacion_id' => $hermana->estado_cotizacion_id,
        ]);
    }

    public function down(): void
    {
        // Corrección de datos; no se revierte.
    }
};
