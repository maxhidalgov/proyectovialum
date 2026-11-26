<?php

namespace Tests\Unit\Precios;

use App\Http\Controllers\Api\AgenteController;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\EstadoCotizacion;
use App\Models\ProductoColorProveedor;
use App\Models\TipoVentana;
use App\Models\User;
use App\Models\Ventana;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Agente cotizador: precios y costos (ideas del 2026-11-26).
 * Convención: precio y costo = TOTAL de la línea; precio_unitario/costo_unitario = total / cantidad.
 * El agente ignora los precios del modelo: PHP recalcula, y si falla o da 0 no crea nada.
 * Usa un servicio de cálculo falso con la forma REAL del retorno (ver PreciosVentanaTestCase).
 */
class AgentePrecioTest extends PreciosVentanaTestCase
{
    private function llamar(AgenteController $c, string $metodo, array $args)
    {
        $m = new \ReflectionMethod($c, $metodo);
        $m->setAccessible(true);
        return $m->invoke($c, ...$args);
    }

    private function inputCalculo(int $tipo, int $cant): array
    {
        return [
            'tipo_ventana_id' => $tipo, 'ancho' => 1200, 'alto' => 1000, 'color_id' => 1, 'cantidad' => $cant,
            'producto_vidrio_id' => 1, 'proveedor_vidrio_id' => 1, 'tipo_vidrio' => 1,
        ];
    }

    private function preparar(): User
    {
        EstadoCotizacion::create(['nombre' => 'Evaluación']);
        Cliente::create(['id' => 1, 'razon_social' => 'Cliente Test']);
        ProductoColorProveedor::forceCreate(['id' => 9, 'producto_id' => 258, 'proveedor_id' => 1, 'color_id' => 1, 'costo' => 1000]);
        $user = new User();
        $user->id = 7;
        return $user;
    }

    /** Línea tal como la manda el modelo; los precios que incluya DEBEN ser ignorados. */
    private function lineaModelo(array $extra = []): array
    {
        return array_merge([
            'tipo_ventana_id' => 3, 'ancho' => 1200, 'alto' => 1000, 'color_id' => 1, 'producto_vidrio_proveedor_id' => 9,
            'tipo_vidrio' => 1, 'cantidad' => 3,
        ], $extra);
    }

    #[DataProvider('tiposYCantidades')]
    public function test_calcular_precio_devuelve_totales_y_unitarios_sin_contar_la_cantidad_dos_veces(int $tipo, int $cant): void
    {
        TipoVentana::create(['id' => $tipo, 'nombre' => "tipo $tipo"]);
        $c   = new AgenteController($this->servicioFalso(100000));
        $res = $this->llamar($c, 'toolCalcularPrecio', [$this->inputCalculo($tipo, $cant)]);

        $costoUnit  = 100000 + $tipo * 1000;
        $costoTotal = $costoUnit * $cant;                       // el servicio ya incluye la cantidad
        $precioTot  = (int) ceil($costoTotal / (1 - 0.50));     // margen por defecto 50 %

        $this->assertArrayNotHasKey('error', $res, json_encode($res));
        $this->assertSame($costoTotal, $res['costo_total'], 'costo_total = total de la línea (la cantidad una sola vez)');
        $this->assertSame($precioTot, $res['precio_total']);
        $this->assertSame(Ventana::unitario($costoTotal, $cant), $res['costo_unitario']);
        $this->assertSame(Ventana::unitario($precioTot, $cant), $res['precio_unitario'], 'unitario = total / cantidad');
        $this->assertGreaterThan(0, $res['precio_total']);
    }

    public function test_calcular_precio_con_costo_cero_devuelve_error_y_nunca_un_precio(): void
    {
        TipoVentana::create(['id' => 3, 'nombre' => 'x']);
        $svc = $this->servicioFalso();
        FakeCalculoVentanaService::$override = fn() => ['materiales' => [], 'costo_total' => 0, 'costo_unitario' => 0];
        $res = (new \ReflectionMethod(AgenteController::class, 'toolCalcularPrecio'));
        $res->setAccessible(true);
        $out = $res->invoke(new AgenteController($svc), $this->inputCalculo(3, 2));

        $this->assertArrayHasKey('error', $out);
        $this->assertArrayHasKey('diagnostico', $out);
        $this->assertSame(0, $out['precio_total']);
    }

    public function test_crear_cotizacion_ignora_precios_del_modelo_y_recalcula_en_php(): void
    {
        $user = $this->preparar();
        $c    = new AgenteController($this->servicioFalso());

        // El modelo "inventa" precio 1 y costo 1: no deben usarse.
        $res = $this->llamar($c, 'toolCrearCotizacion', [[
            'cliente_id' => 1, 'ventanas' => [$this->lineaModelo(['precio' => 1, 'costo' => 1])],
        ], $user]);

        $this->assertTrue($res['success'] ?? false, json_encode($res));
        $cot = Cotizacion::with('ventanas')->find($res['cotizacion_id']);
        $v   = $cot->ventanas->first();

        $costoTotal = (100000 + 3 * 1000) * 3;                  // 309.000
        $precioTot  = (int) ceil($costoTotal / 0.5);            // 618.000
        $this->assertEquals($costoTotal, $v->costo, 'costo = total de línea recalculado por PHP');
        $this->assertEquals($precioTot, $v->precio, 'precio = total de línea recalculado por PHP');
        $this->assertEquals(Ventana::unitario($precioTot, 3), $v->precio_unitario);
        $this->assertEquals(Ventana::unitario($costoTotal, 3), $v->costo_unitario);
        $this->assertEquals($cot->total, $cot->ventanas->sum('precio'), 'cotizaciones.total = suma de ventanas.precio');
        $this->assertEquals($precioTot, $res['total']);
    }

    public function test_pdf_muestra_el_valor_unitario_correcto_para_una_ventana_del_agente(): void
    {
        $user = $this->preparar();
        $res  = $this->llamar(new AgenteController($this->servicioFalso()), 'toolCrearCotizacion', [[
            'cliente_id' => 1, 'ventanas' => [$this->lineaModelo()],
        ], $user]);
        $v = Cotizacion::with('ventanas')->find($res['cotizacion_id'])->ventanas->first();

        // Misma fórmula que resources/views/cotizaciones/pdf.blade.php:206
        $unitarioPdf = $v->cantidad > 0 ? round($v->precio / $v->cantidad) : $v->precio;
        $this->assertEquals($v->precio_unitario, $unitarioPdf);
        $this->assertEquals(206000, $unitarioPdf, '618.000 / 3');
    }

    public function test_si_una_linea_da_cero_no_se_crea_nada(): void
    {
        $user = $this->preparar();
        $svc  = $this->servicioFalso();
        // Línea 1 con costo normal; línea 2 (tipo 99) sin costo en catálogo.
        FakeCalculoVentanaService::$override = function (array $v) {
            return $v['tipo'] == 99
                ? ['materiales' => [], 'costo_total' => 0, 'costo_unitario' => 0]
                : ['materiales' => [], 'costo_total' => 50000 * $v['cantidad'], 'costo_unitario' => 50000];
        };
        $res = $this->llamar(new AgenteController($svc), 'toolCrearCotizacion', [[
            'cliente_id' => 1, 'ventanas' => [$this->lineaModelo(['cantidad' => 1]), $this->lineaModelo(['tipo_ventana_id' => 99])],
        ], $user]);

        $this->assertFalse($res['success']);
        $this->assertSame(2, $res['lineas_con_error'][0]['linea']);
        $this->assertSame(0, Cotizacion::count(), 'todo o nada: no queda cotización');
        $this->assertSame(0, Ventana::count());
    }

    public function test_si_el_servicio_lanza_excepcion_no_se_crea_nada(): void
    {
        $user = $this->preparar();
        $svc  = $this->servicioFalso();
        FakeCalculoVentanaService::$override = fn() => throw new \RuntimeException('catálogo incompleto');
        $res = $this->llamar(new AgenteController($svc), 'toolCrearCotizacion', [[
            'cliente_id' => 1, 'ventanas' => [$this->lineaModelo()],
        ], $user]);

        $this->assertFalse($res['success']);
        $this->assertSame(0, Cotizacion::count());
    }

    public function test_cliente_inexistente_o_vidrio_inexistente_no_crean(): void
    {
        $user = $this->preparar();
        $c    = new AgenteController($this->servicioFalso());

        $r1 = $this->llamar($c, 'toolCrearCotizacion', [['cliente_id' => 999, 'ventanas' => [$this->lineaModelo()]], $user]);
        $r2 = $this->llamar($c, 'toolCrearCotizacion', [['cliente_id' => 1, 'ventanas' => [$this->lineaModelo(['producto_vidrio_proveedor_id' => 12345])]], $user]);

        $this->assertFalse($r1['success']);
        $this->assertFalse($r2['success']);
        $this->assertSame(0, Cotizacion::count());
    }

    public function test_la_ventana_del_agente_guarda_config_para_recalcular_y_marca_de_origen(): void
    {
        $user = $this->preparar();
        $res  = $this->llamar(new AgenteController($this->servicioFalso()), 'toolCrearCotizacion', [[
            'cliente_id' => 1, 'ventanas' => [$this->lineaModelo(['manillon' => true])],
        ], $user]);
        $cfg = Cotizacion::with('ventanas')->find($res['cotizacion_id'])->ventanas->first()->config;

        $this->assertSame('agente', $cfg['origen']);
        $this->assertSame(1, $cfg['proveedor_vidrio']);
        $this->assertSame(1, $cfg['tipo_vidrio']);
        $this->assertTrue($cfg['manillon']);
    }
}
