<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalencias de códigos entre lo que pedimos (códigos de Winperfil) y lo que cotiza el proveedor
 * (sus propios códigos). Las usa el validador de cotizaciones de proveedor. Se parte con las que
 * quedaron confirmadas comparando una cotización real de Haustek con la lista de materiales.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('proveedor_equivalencias')) {
            Schema::create('proveedor_equivalencias', function (Blueprint $table) {
                $table->id();
                $table->string('proveedor', 40)->index();          // clave corta: 'haustek'
                $table->string('codigo_pedido', 80);               // código como sale en Winperfil / la orden de compra
                $table->string('codigo_proveedor', 80);            // código que usa el proveedor en su cotización
                $table->string('nota')->nullable();
                $table->timestamps();
                $table->unique(['proveedor', 'codigo_pedido']);
            });
        }

        $semilla = [
            ['105010', 'HL-BR-PR-10-FB', 'Brazo proyección 10"'],
            ['105008', 'HL-BR-PRAB-8-FB', 'Brazo proyección 8"'],
            ['3402690123', 'HI-SEG-ANT', 'Seguro antielevación (el proveedor lo junta con HI-SEG-ANT)'],
            ['5072150004', 'HI-CC-E75-1000', 'Cremona 7,5 mm de 1000 mm'],
            ['5072150003', 'HI-CC-E75-800', 'Cremona 7,5 mm de 800 mm'],
            ['3402123919', 'HI-TOR-AUT-39-19-SLK', 'Tornillo autoperforante 3,9 x 19'],
            ['HL-TOR-AMO-75-80-MA', 'HI-TOR-AMO-75-80', 'Tornillo AMO 7,5 x 80'],
            ['HL-ACC-TAM-13-GH', 'HL-ACC-TAMOR-13-MA', 'Tope amortiguador 13 mm'],
            ['PI-SLD-MDR-S75-RD', 'PI-SLD-NW-MDR-S75-RD', 'Marco doble riel S75 (versión NEW)'],
        ];
        foreach ($semilla as [$pedido, $prov, $nota]) {
            $existe = DB::table('proveedor_equivalencias')->where('proveedor', 'haustek')->where('codigo_pedido', $pedido)->exists();
            if (!$existe) {
                DB::table('proveedor_equivalencias')->insert([
                    'proveedor' => 'haustek', 'codigo_pedido' => $pedido, 'codigo_proveedor' => $prov,
                    'nota' => $nota, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_equivalencias');
    }
};
