<?php

namespace Tests\Unit\Precios;

use App\Models\Ventana;
use Illuminate\Support\Facades\DB;

/**
 * Los unitarios de Ventana son un dato derivado (total / cantidad) que se fija al guardar.
 */
class VentanaUnitariosTest extends PreciosVentanaTestCase
{
    private function crear(array $attrs): Ventana
    {
        return Ventana::create(array_merge(['cotizacion_id' => 1, 'tipo_ventana_id' => 3, 'ancho' => 1000, 'alto' => 1000], $attrs));
    }

    public function test_al_crear_deriva_unitarios_desde_los_totales(): void
    {
        $v = $this->crear(['cantidad' => 3, 'precio' => 450000, 'costo' => 225000]);
        $this->assertEquals(150000, $v->fresh()->getRawOriginal('precio_unitario'));
        $this->assertEquals(75000, $v->fresh()->getRawOriginal('costo_unitario'));
    }

    public function test_un_unitario_enviado_por_el_cliente_no_prevalece_sobre_total_sobre_cantidad(): void
    {
        // El frontend antiguo enviaba precio_unitario = 0 o inconsistente.
        $v = $this->crear(['cantidad' => 2, 'precio' => 206760, 'costo' => 113718, 'precio_unitario' => 0, 'costo_unitario' => 999]);
        $raw = $v->fresh();
        $this->assertEquals(103380, $raw->getRawOriginal('precio_unitario'));
        $this->assertEquals(56859, $raw->getRawOriginal('costo_unitario'));
    }

    public function test_redondeo_a_peso_entero(): void
    {
        $this->assertSame(33333, Ventana::unitario(100000, 3));
        $this->assertSame(33334, Ventana::unitario(100001, 3));
        $this->assertSame(50000, Ventana::unitario(100000, 2));
        $this->assertSame(1, Ventana::unitario(2, 3));   // 0,667 → 1
        $this->assertSame(0, Ventana::unitario(1, 3));   // 0,333 → 0
    }

    public function test_cantidad_nula_o_cero_cuenta_como_uno(): void
    {
        $this->assertSame(1000, Ventana::unitario(1000, null));
        $this->assertSame(1000, Ventana::unitario(1000, 0));
        $this->assertSame(1000, Ventana::unitario('1000.00', '1'));
    }

    public function test_al_cambiar_la_cantidad_se_recalculan_los_unitarios(): void
    {
        $v = $this->crear(['cantidad' => 1, 'precio' => 300000, 'costo' => 150000]);
        $v->update(['cantidad' => 3]);
        $this->assertEquals(100000, $v->fresh()->getRawOriginal('precio_unitario'));
        $this->assertEquals(50000, $v->fresh()->getRawOriginal('costo_unitario'));
    }

    public function test_un_guardado_que_no_toca_precios_no_modifica_una_fila_antigua(): void
    {
        // Fila "legada": unitarios NULL en la BD (como las 170 de producción).
        $id = DB::table('ventanas')->insertGetId([
            'cotizacion_id' => 1, 'tipo_ventana_id' => 3, 'cantidad' => 2, 'precio' => 206760, 'costo' => 113718,
            'precio_unitario' => null, 'costo_unitario' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Ventana::find($id)->update(['imagen' => 'x.png']);   // p. ej. guardar la imagen del dibujo
        $this->assertNull(DB::table('ventanas')->where('id', $id)->value('precio_unitario'), 'no se reescribe la fila antigua');

        // …pero al leerla el unitario se deriva, sin tocar la BD.
        $this->assertEquals(103380, Ventana::find($id)->precio_unitario);
        $this->assertEquals(56859, Ventana::find($id)->costo_unitario);
    }

    public function test_lectura_de_fila_antigua_con_unitario_cero_deriva_total_sobre_cantidad(): void
    {
        $id = DB::table('ventanas')->insertGetId([
            'cotizacion_id' => 1, 'cantidad' => 4, 'precio' => 800000, 'costo' => 400000,
            'precio_unitario' => 0, 'costo_unitario' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertEquals(200000, Ventana::find($id)->precio_unitario);
    }
}
