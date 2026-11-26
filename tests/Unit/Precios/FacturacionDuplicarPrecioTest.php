<?php

namespace Tests\Unit\Precios;

use App\Http\Controllers\BsaleController;
use App\Http\Controllers\CotizacionController;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\EstadoCotizacion;
use App\Models\Ventana;
use Illuminate\Support\Facades\Schema;

/**
 * Payload de Bsale (NUNCA se llama a la API ni se emite nada: solo se construye el arreglo) y duplicar().
 * Convención: ventanas.precio = TOTAL neto de la línea; netUnitValue = precio / cantidad.
 */
class FacturacionDuplicarPrecioTest extends PreciosVentanaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('documentos_facturacion', function ($t) {
            $t->id(); $t->unsignedBigInteger('cotizacion_id')->nullable(); $t->string('estado')->nullable(); $t->timestamps();
        });
    }

    private function ventana(int $cant, float $precioTotal, string $desc = ''): Ventana
    {
        return new Ventana(['cantidad' => $cant, 'precio' => $precioTotal, 'ancho' => 1000, 'alto' => 1000]);
    }

    private function cot(float $total, array $ventanas = [], array $detalles = []): Cotizacion
    {
        $cot = new Cotizacion(['total' => $total]);
        $cot->id = 1;
        $cot->setRelation('cliente', new Cliente());
        $cot->setRelation('detalles', collect($detalles));
        $cot->setRelation('ventanas', collect($ventanas));
        return $cot;
    }

    private function payload(Cotizacion $cot, float $porcentaje = 100, $metodoPago = null): array
    {
        $m = new \ReflectionMethod(BsaleController::class, 'construirPayloadBsale');
        $m->setAccessible(true);
        return $m->invoke(new BsaleController(), $cot, 5, 1, $metodoPago, 'contado', null, null, $porcentaje);
    }

    private function totalLineas(array $payload): float
    {
        return array_sum(array_map(fn ($d) => $d['netUnitValue'] * $d['quantity'], $payload['details']));
    }

    public function test_una_ventana_cantidad_3_factura_su_total_y_unitario_correctos(): void
    {
        $p = $this->payload($this->cot(450000, [$this->ventana(3, 450000)]));

        $this->assertSame(3, $p['details'][0]['quantity']);
        $this->assertEqualsWithDelta(150000, $p['details'][0]['netUnitValue'], 0.0001);
        $this->assertEqualsWithDelta(450000, $this->totalLineas($p), 0.01);
    }

    public function test_cada_linea_factura_su_precio_de_linea_con_distintas_cantidades(): void
    {
        // 1 un. de $100.000 + 3 un. a $100.000 c/u (total de línea $300.000). Cotización neta $400.000.
        $p = $this->payload($this->cot(400000, [$this->ventana(1, 100000), $this->ventana(3, 300000)]));

        $lineas = array_map(fn ($d) => (int) round($d['netUnitValue'] * $d['quantity']), $p['details']);
        $this->assertSame([100000, 300000], $lineas);
        $this->assertSame([100000, 100000], array_map(fn ($d) => (int) round($d['netUnitValue']), $p['details']));
    }

    public function test_redondeo_decimales_la_suma_de_lineas_no_se_desvia_del_neto_en_ningun_caso(): void
    {
        mt_srand(2026);
        $peorDesvio = 0.0;
        for ($i = 0; $i < 400; $i++) {
            $n = mt_rand(1, 6);
            $ventanas = []; $suma = 0;
            for ($k = 0; $k < $n; $k++) {
                $cant = mt_rand(1, 24);
                $tot  = mt_rand(10000, 9000000);       // totales que NO son divisibles por la cantidad
                $ventanas[] = $this->ventana($cant, $tot);
                $suma += $tot;
            }
            $p = $this->payload($this->cot($suma, $ventanas));
            $desvio = abs($this->totalLineas($p) - $suma);
            $peorDesvio = max($peorDesvio, $desvio);
            $this->assertLessThan(0.5, $desvio, "caso $i: las líneas suman $desvio de diferencia");
        }
        fwrite(STDERR, sprintf("\n[info] peor desvío de redondeo en 400 casos aleatorios (neto): %.4f pesos\n", $peorDesvio));
    }

    public function test_cuando_el_total_de_la_cotizacion_difiere_de_las_lineas_se_cuadra_al_total(): void
    {
        // Winperfil: la suma de líneas puede diferir del total en unos pesos; el documento debe salir por el total.
        mt_srand(7);
        for ($i = 0; $i < 100; $i++) {
            $tot1 = mt_rand(100000, 900000); $tot2 = mt_rand(100000, 900000);
            $objetivo = $tot1 + $tot2 + mt_rand(-40, 40);
            $p = $this->payload($this->cot($objetivo, [$this->ventana(mt_rand(1, 9), $tot1), $this->ventana(mt_rand(1, 9), $tot2)]));
            $this->assertEqualsWithDelta($objetivo, $this->totalLineas($p), 0.05, "caso $i");
        }
    }

    public function test_documento_parcial_el_monto_del_pago_coincide_con_neto_mas_iva_en_pesos_enteros(): void
    {
        // Anticipo/saldo: UNA línea con neto × %. Se compara el monto del pago con lo que da neto redondeado + IVA.
        mt_srand(11);
        $peor = 0;
        foreach ([30, 33.33, 40, 50, 66.67, 70, 25.5] as $pct) {
            for ($i = 0; $i < 60; $i++) {
                $neto = mt_rand(150000, 12000000);
                $p = $this->payload($this->cot($neto, [$this->ventana(mt_rand(1, 5), $neto)]), $pct, 'transferencia');

                $netoDoc = (int) round($p['details'][0]['netUnitValue'] * $p['details'][0]['quantity']);
                $brutoEsperado = $netoDoc + (int) round($netoDoc * 0.19);
                $monto = $p['payments'][0]['amount'];
                $dif = abs($monto - $brutoEsperado);
                $peor = max($peor, $dif);
                $this->assertLessThanOrEqual(1, $dif, "pct=$pct neto=$neto: pago $monto vs neto+IVA $brutoEsperado");
            }
        }
        fwrite(STDERR, "\n[info] peor diferencia pago vs neto+IVA (parciales): $peor peso(s)\n");
    }

    public function test_lineas_winperfil_con_igual_descripcion_y_distinto_precio_no_se_fusionan(): void
    {
        $d1 = new CotizacionDetalle(['descripcion' => 'Ventana corredera', 'cantidad' => 1, 'precio_unitario' => 100000, 'total' => 100000, 'tipo_item' => 'winperfil']);
        $d2 = new CotizacionDetalle(['descripcion' => 'Ventana corredera', 'cantidad' => 2, 'precio_unitario' => 150000, 'total' => 300000, 'tipo_item' => 'winperfil']);
        $d3 = new CotizacionDetalle(['descripcion' => 'Ventana corredera', 'cantidad' => 3, 'precio_unitario' => 150000, 'total' => 450000, 'tipo_item' => 'winperfil']);
        $p = $this->payload($this->cot(850000, [], [$d1, $d2, $d3]));

        $lineas = array_map(fn ($d) => [(int) round($d['netUnitValue']), $d['quantity']], $p['details']);
        $this->assertSame([[100000, 1], [150000, 5]], $lineas, 'mismo precio se agrupa (2+3=5); distinto precio queda aparte');
        $this->assertEqualsWithDelta(850000, $this->totalLineas($p), 0.01);
    }

    public function test_duplicar_copia_la_linea_completa_y_los_unitarios_derivados(): void
    {
        $eval = EstadoCotizacion::create(['nombre' => 'Evaluación']);
        $orig = Cotizacion::create(['cliente_id' => 1, 'vendedor_id' => 1, 'fecha' => now()->toDateString(), 'estado_cotizacion_id' => $eval->id, 'total' => 450000]);
        Ventana::create([
            'cotizacion_id' => $orig->id, 'tipo_ventana_id' => 3, 'ancho' => 1200, 'alto' => 1000, 'cantidad' => 3,
            'costo' => 225000, 'precio' => 450000,
            'hojas_totales' => 2, 'hojas_moviles' => 1, 'config' => ['tipo_vidrio' => 1, 'manillon' => true, 'proveedor_vidrio' => 4],
        ]);

        $resp  = app(CotizacionController::class)->duplicar($orig->id);
        $nueva = Cotizacion::with('ventanas')->find($resp->getData()->id);
        $v     = $nueva->ventanas->first();

        $this->assertNotEquals($orig->id, $nueva->id);
        $this->assertEquals(450000, $v->precio);
        $this->assertEquals(225000, $v->costo);
        $this->assertEquals(150000, $v->precio_unitario);
        $this->assertEquals(75000, $v->costo_unitario);
        $this->assertEquals(2, $v->hojas_totales);
        $this->assertSame(['tipo_vidrio' => 1, 'manillon' => true, 'proveedor_vidrio' => 4], $v->config);
        $this->assertSame(1, Ventana::where('cotizacion_id', $orig->id)->count(), 'el original no se toca');
    }
}
