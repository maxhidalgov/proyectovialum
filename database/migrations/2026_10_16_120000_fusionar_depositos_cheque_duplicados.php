<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El Portal BCH entrega un depósito de cheque dos veces: provisorio ("Dep. Docto … Autoservicio")
 * y, de noche, contabilizado con otro id y nombre ("Deposito Con Cheque…", "Dep.cheq.otros Bancos").
 * La importación lo guardaba como 2 movimientos. Aquí se elimina la copia más antigua de cada par
 * (misma cuenta, fecha, monto, tipo y número de documento) SOLO si ninguna de las dos está
 * conciliada ni enlazada a nada. Idempotente: sin pares, no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $grupos = DB::table('movimientos_bancarios')
            ->whereNull('chipax_id')
            ->where('tipo', 'C')
            ->whereNotNull('numero_documento')->where('numero_documento', '<>', '')
            ->where('bch_codigo', 'like', 'PORTAL-%')
            ->select('cuenta', 'fecha_contable', 'monto', 'numero_documento')
            ->groupBy('cuenta', 'fecha_contable', 'monto', 'numero_documento')
            ->havingRaw('COUNT(*) = 2')
            ->get();

        // Tablas que referencian un movimiento (pivots de conciliación, sueldos, Transbank…)
        $refs = DB::select(
            "SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.KEY_COLUMN_USAGE
             WHERE REFERENCED_TABLE_NAME = 'movimientos_bancarios' AND TABLE_SCHEMA = DATABASE()"
        );

        foreach ($grupos as $g) {
            $filas = DB::table('movimientos_bancarios')
                ->where('cuenta', $g->cuenta)->where('fecha_contable', $g->fecha_contable)
                ->where('monto', $g->monto)->where('numero_documento', $g->numero_documento)
                ->where('tipo', 'C')->whereNull('chipax_id')->where('bch_codigo', 'like', 'PORTAL-%')
                ->orderBy('id')->get();
            if ($filas->count() !== 2) continue;

            // Solo depósitos (provisorio + contabilizado), nunca dos movimientos de otra clase
            if ($filas->contains(fn($f) => !preg_match('/^dep/i', (string) $f->descripcion))) continue;
            if ($filas->contains(fn($f) => $f->conciliado || $f->compra_id || $f->cotizacion_id)) continue;

            $enUso = false;
            foreach ($refs as $r) {
                if (DB::table($r->t)->whereIn($r->c, $filas->pluck('id'))->exists()) { $enUso = true; break; }
            }
            if ($enUso) continue;

            // Se conserva la contabilizada (más reciente); si la antigua tenía nota, se traspasa
            $viejo = $filas[0];
            $nuevo = $filas[1];
            if ($viejo->nota && !$nuevo->nota) {
                DB::table('movimientos_bancarios')->where('id', $nuevo->id)->update(['nota' => $viejo->nota]);
            }
            DB::table('movimientos_bancarios')->where('id', $viejo->id)->delete();
        }
    }

    public function down(): void
    {
        // Limpieza de datos; no se revierte.
    }
};
