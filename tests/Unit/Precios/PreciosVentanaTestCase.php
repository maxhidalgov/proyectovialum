<?php

namespace Tests\Unit\Precios;

use App\Services\CalculoVentanaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Base de los tests de precios/costos de ventanas.
 * Usa SQLite en memoria (nunca MySQL ni producción) con el esquema mínimo.
 */
abstract class PreciosVentanaTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Guardia: jamás correr estos tests contra MySQL.
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'Los tests de precios solo corren en SQLite en memoria');

        Schema::create('tipos_ventana', function ($t) {
            $t->id(); $t->string('nombre'); $t->unsignedBigInteger('material_id')->nullable(); $t->string('descripcion')->nullable(); $t->timestamps();
        });
        Schema::create('colores', function ($t) { $t->id(); $t->string('nombre'); $t->timestamps(); });
        Schema::create('clientes', function ($t) { $t->id(); $t->string('razon_social')->nullable(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->timestamps(); });
        Schema::create('producto_color_proveedor', function ($t) {
            $t->id(); $t->unsignedBigInteger('producto_id')->nullable(); $t->unsignedBigInteger('proveedor_id')->nullable(); $t->unsignedBigInteger('color_id')->nullable();
            $t->integer('costo')->nullable(); $t->timestamps();
        });
        Schema::create('estados_cotizacion', function ($t) { $t->id(); $t->string('nombre'); $t->timestamps(); });
        Schema::create('cotizaciones', function ($t) {
            $t->id(); $t->unsignedBigInteger('cliente_id')->nullable(); $t->unsignedBigInteger('vendedor_id')->nullable();
            $t->date('fecha')->nullable(); $t->unsignedBigInteger('estado_cotizacion_id')->nullable(); $t->text('observaciones')->nullable();
            $t->decimal('total', 14, 2)->default(0); $t->string('public_token')->nullable(); $t->unsignedBigInteger('origen_id')->nullable();
            $t->string('estado_produccion')->nullable();
            // Winperfil / Bsale / facturación (para comprobar bloqueos y que duplicar NO los copia)
            $t->integer('winperfil_numero')->nullable(); $t->string('winperfil_serie')->nullable(); $t->string('winperfil_presupuesto')->nullable();
            $t->integer('winperfil_oferta')->nullable(); $t->boolean('winperfil_precio_lock')->default(false); $t->string('adjunto_winperfil')->nullable();
            $t->string('numero_documento_bsale')->nullable(); $t->string('id_documento_bsale')->nullable(); $t->string('url_pdf_bsale')->nullable();
            $t->string('token_bsale')->nullable(); $t->string('estado_facturacion')->nullable(); $t->boolean('facturacion_cerrada')->default(false);
            $t->timestamps();
        });
        Schema::create('cotizacion_estado_historial', function ($t) {
            $t->id(); $t->unsignedBigInteger('cotizacion_id'); $t->string('tipo')->nullable(); $t->string('estado')->nullable(); $t->string('estado_anterior')->nullable(); $t->dateTime('fecha')->nullable(); $t->timestamps();
        });
        Schema::create('cotizacion_detalles', function ($t) {
            $t->id(); $t->unsignedBigInteger('cotizacion_id'); $t->unsignedBigInteger('producto_id')->nullable(); $t->string('tipo_item')->default('ventana');
            $t->unsignedBigInteger('producto_lista_id')->nullable(); $t->unsignedBigInteger('lista_precio_id')->nullable(); $t->text('descripcion')->nullable();
            $t->decimal('cantidad', 10, 2)->default(1); $t->decimal('precio_unitario', 12, 2)->default(0); $t->decimal('total', 12, 2)->default(0);
            $t->boolean('esVidrio')->default(false); $t->decimal('ancho_mm', 10, 2)->nullable(); $t->decimal('alto_mm', 10, 2)->nullable(); $t->decimal('m2', 10, 4)->nullable();
            $t->boolean('pulido')->default(false); $t->text('winperfil_grafico')->nullable(); $t->text('winperfil_grafico_png')->nullable(); $t->timestamps();
        });
        Schema::create('ventanas', function ($t) {
            $t->id(); $t->unsignedBigInteger('cotizacion_id'); $t->unsignedBigInteger('tipo_ventana_id')->nullable();
            $t->integer('ancho')->nullable(); $t->integer('alto')->nullable(); $t->unsignedBigInteger('color_id')->nullable();
            $t->unsignedBigInteger('producto_vidrio_proveedor_id')->nullable(); $t->string('imagen')->nullable();
            $t->decimal('costo', 12, 2)->default(0); $t->decimal('precio', 12, 2)->default(0);
            $t->integer('hojas_totales')->nullable(); $t->integer('hojas_moviles')->nullable();
            $t->integer('hoja_movil_seleccionada')->nullable(); $t->boolean('hoja1_al_frente')->nullable();
            $t->integer('cantidad')->default(1); $t->integer('costo_unitario')->nullable(); $t->integer('precio_unitario')->nullable();
            $t->text('config')->nullable();
            foreach (['ancho_izquierda', 'ancho_centro', 'ancho_derecha'] as $c) { $t->integer($c)->nullable(); }
            foreach (['tipo_ventana_izquierda', 'tipo_ventana_centro', 'tipo_ventana_derecha'] as $c) { $t->text($c)->nullable(); }
            $t->timestamps();
        });
    }

    /**
     * Servicio de cálculo falso con la FORMA REAL del retorno de calcularMateriales
     * ({materiales, costo_total, costo_unitario}) y su escalado real: costo_total YA incluye
     * la cantidad (ver CalculoVentanaService, p. ej. calcularFijaAL42 línea ~256-273).
     */
    protected function servicioFalso(int $costoUnitarioBase = 100000): CalculoVentanaService
    {
        FakeCalculoVentanaService::$costoUnitarioBase = $costoUnitarioBase;
        FakeCalculoVentanaService::$override = null;
        return new FakeCalculoVentanaService();
    }

    /** Tipos de ventana representativos (IDs reales del despachador de calcularMateriales). */
    public static function tiposYCantidades(): array
    {
        $casos = [];
        foreach ([1 => 'Fija AL42', 3 => 'Corredera Sliding', 45 => 'Proyectante S60', 46 => 'Corredera Andes', 53 => 'Corredera Monorriel', 50 => 'Puerta S60'] as $id => $n) {
            foreach ([1, 3] as $cant) {
                $casos["$n x$cant"] = [$id, $cant];
            }
        }
        return $casos;
    }
}

class FakeCalculoVentanaService extends CalculoVentanaService
{
    public static int $costoUnitarioBase = 100000;
    /** Si se define, reemplaza el cálculo (recibe el arreglo de la ventana, devuelve el retorno). */
    public static $override = null;

    public static function calcularMateriales(array $ventana): array
    {
        if (self::$override) {
            return (self::$override)($ventana);
        }
        $cant  = (int) ($ventana['cantidad'] ?? 1);
        $unit  = self::$costoUnitarioBase + ((int) ($ventana['tipo'] ?? 0)) * 1000;
        $total = $unit * $cant;
        return [
            'materiales'     => [['nombre' => 'perfil', 'unidad' => 'm', 'cantidad' => $cant, 'costo_unitario' => $unit, 'costo_total' => $total]],
            'costo_total'    => $total,
            'costo_unitario' => $unit,
        ];
    }
}
