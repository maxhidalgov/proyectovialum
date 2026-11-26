<?php

namespace Tests\Unit\Precios;

use App\Http\Controllers\CotizacionController;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\EstadoCotizacion;
use App\Models\Ventana;
use Illuminate\Http\Request;

/**
 * update() (botón de lápiz) y duplicar() de CotizacionController:
 *  - una cotización Winperfil NO se guarda desde el cotizador común (su total quedaría sin sus líneas);
 *  - editar una cotización normal no pierde productos ni ítems libres y el total siempre cuadra con lo guardado;
 *  - duplicar copia ventanas y TODOS los detalles dentro de una transacción, con total cuadrado y sin copiar números ni vínculos.
 */
class EditarDuplicarCotizacionTest extends PreciosVentanaTestCase
{
    private function ctrl(): CotizacionController
    {
        return app(CotizacionController::class);
    }

    private function crearNormal(array $extra = []): Cotizacion
    {
        $eval = EstadoCotizacion::firstOrCreate(['nombre' => 'Evaluación']);
        $c = Cotizacion::create(array_merge(['cliente_id' => 1, 'vendedor_id' => 1, 'fecha' => '2026-11-01', 'estado_cotizacion_id' => $eval->id, 'total' => 0], $extra));
        // Ventana cantidad 3 (precio = total de línea) y ventana cantidad 1
        Ventana::create(['cotizacion_id' => $c->id, 'tipo_ventana_id' => 3, 'ancho' => 1200, 'alto' => 1000, 'color_id' => 1, 'cantidad' => 3, 'costo' => 225000, 'precio' => 450000, 'config' => ['tipo_vidrio' => 1, 'manillon' => false]]);
        Ventana::create(['cotizacion_id' => $c->id, 'tipo_ventana_id' => 2, 'ancho' => 1000, 'alto' => 1000, 'color_id' => 1, 'cantidad' => 1, 'costo' => 50000, 'precio' => 100000, 'config' => ['tipo_vidrio' => 2]]);
        CotizacionDetalle::create(['cotizacion_id' => $c->id, 'tipo_item' => 'producto', 'descripcion' => 'Cristal 5 mm', 'cantidad' => 2, 'precio_unitario' => 30000, 'total' => 60000,
            'esVidrio' => true, 'ancho_mm' => 800, 'alto_mm' => 600, 'm2' => 0.96, 'pulido' => true]);
        CotizacionDetalle::create(['cotizacion_id' => $c->id, 'tipo_item' => 'item_libre', 'descripcion' => 'Flete', 'cantidad' => 1, 'precio_unitario' => 15000, 'total' => 15000]);
        $c->update(['total' => 450000 + 100000 + 60000 + 15000]);
        return $c->fresh();
    }

    /** Petición de edición tal como la arma el cotizador (ventanas con id, productos e ítems con id). */
    private function peticionEdicion(Cotizacion $c, array $cambios = []): Request
    {
        $payload = array_merge([
            'cliente_id' => 1, 'fecha' => '2026-11-02', 'estado_cotizacion_id' => $c->estado_cotizacion_id, 'observaciones' => 'editada',
            'ventanas' => $c->ventanas->map(fn($v) => [
                'id' => $v->id, 'tipo_ventana_id' => $v->tipo_ventana_id, 'ancho' => $v->ancho, 'alto' => $v->alto, 'color_id' => 1,
                'producto_vidrio_proveedor_id' => null, 'costo' => $v->costo, 'precio' => $v->precio, 'cantidad' => $v->cantidad,
                'tipo_vidrio' => 1,
            ])->all(),
            'productos' => $c->detalles->where('tipo_item', 'producto')->map(fn($d) => [
                'id' => $d->id, 'producto_lista_id' => null, 'lista_precio_id' => null, 'descripcion' => $d->descripcion, 'cantidad' => $d->cantidad,
                'precio_unitario' => $d->precio_unitario, 'total' => $d->total,
            ])->values()->all(),
            'items_libres' => $c->detalles->where('tipo_item', 'item_libre')->map(fn($d) => [
                'id' => $d->id, 'descripcion' => $d->descripcion, 'cantidad' => $d->cantidad, 'precio_unitario' => $d->precio_unitario, 'total' => $d->total,
            ])->values()->all(),
        ], $cambios);
        return Request::create('/', 'PUT', $payload);
    }

    private function sumaGuardada(int $id): float
    {
        return (float) Ventana::where('cotizacion_id', $id)->sum('precio') + (float) CotizacionDetalle::where('cotizacion_id', $id)->sum('total');
    }

    // ───────────────────────────── update() ─────────────────────────────

    public function test_update_bloquea_cotizacion_winperfil_sincronizada_y_no_cambia_nada(): void
    {
        $c = $this->crearNormal(['winperfil_numero' => 676, 'winperfil_serie' => 'A']);
        $antes = $this->sumaGuardada($c->id);

        $resp = $this->ctrl()->update($this->peticionEdicion($c, ['observaciones' => 'no debe guardarse']), $c->id);

        $this->assertSame(422, $resp->getStatusCode());
        $this->assertStringContainsString('Winperfil', $resp->getData()->message);
        $this->assertSame('', (string) $c->fresh()->observaciones);
        $this->assertEquals(625000, $c->fresh()->total);
        $this->assertEquals($antes, $this->sumaGuardada($c->id));
    }

    public function test_update_bloquea_cotizacion_con_lineas_winperfil_aunque_no_tenga_numero(): void
    {
        $c = $this->crearNormal();
        CotizacionDetalle::create(['cotizacion_id' => $c->id, 'tipo_item' => 'winperfil', 'descripcion' => 'V1', 'cantidad' => 1, 'precio_unitario' => 100, 'total' => 100]);
        $totalAntes = $c->fresh()->total;

        $resp = $this->ctrl()->update($this->peticionEdicion($c->fresh()), $c->id);

        $this->assertSame(422, $resp->getStatusCode());
        $this->assertEquals($totalAntes, $c->fresh()->total);
    }

    public function test_update_bloquea_cotizacion_con_pdf_de_winperfil_adjunto(): void
    {
        $c = $this->crearNormal(['adjunto_winperfil' => 'https://r2.example/winperfil/x.pdf']);
        $resp = $this->ctrl()->update($this->peticionEdicion($c), $c->id);
        $this->assertSame(422, $resp->getStatusCode());
    }

    public function test_update_normal_conserva_productos_e_items_libres_y_cuadra_el_total(): void
    {
        $c = $this->crearNormal();
        $req = $this->peticionEdicion($c);
        // cambio real: la ventana de cantidad 3 sube a 480.000 (total de línea)
        $payload = $req->all();
        $payload['ventanas'][0]['precio'] = 480000;
        $resp = $this->ctrl()->update(Request::create('/', 'PUT', $payload), $c->id);

        $this->assertSame(200, $resp->getStatusCode(), json_encode($resp->getData()));
        $c = $c->fresh();
        $this->assertSame(1, CotizacionDetalle::where('cotizacion_id', $c->id)->where('tipo_item', 'producto')->count());
        $this->assertSame(1, CotizacionDetalle::where('cotizacion_id', $c->id)->where('tipo_item', 'item_libre')->count());
        $this->assertEquals(480000 + 100000 + 60000 + 15000, $c->total);
        $this->assertEquals($this->sumaGuardada($c->id), $c->total, 'el total debe ser igual a lo guardado');
        $this->assertEquals(160000, Ventana::where('cotizacion_id', $c->id)->where('cantidad', 3)->first()->precio_unitario, 'unitario derivado = 480.000 / 3');
    }

    public function test_update_sin_las_claves_productos_ni_items_libres_no_los_pierde_ni_los_deja_fuera_del_total(): void
    {
        // Es el caso de la cotización 93 de producción: total sin sumar los detalles que siguen guardados.
        $c = $this->crearNormal();
        $payload = $this->peticionEdicion($c)->all();
        unset($payload['productos'], $payload['items_libres']);

        $resp = $this->ctrl()->update(Request::create('/', 'PUT', $payload), $c->id);

        $this->assertSame(200, $resp->getStatusCode(), json_encode($resp->getData()));
        $this->assertSame(2, CotizacionDetalle::where('cotizacion_id', $c->id)->count(), 'los detalles siguen guardados');
        $this->assertEquals(625000, $c->fresh()->total, 'y el total los incluye');
    }

    public function test_update_con_un_item_libre_quitado_lo_elimina_y_baja_el_total(): void
    {
        $c = $this->crearNormal();
        $payload = $this->peticionEdicion($c)->all();
        $payload['items_libres'] = [];

        $this->ctrl()->update(Request::create('/', 'PUT', $payload), $c->id);

        $this->assertSame(0, CotizacionDetalle::where('cotizacion_id', $c->id)->where('tipo_item', 'item_libre')->count());
        $this->assertEquals(610000, $c->fresh()->total);
    }

    public function test_update_producto_nuevo_conserva_los_campos_de_vidrio(): void
    {
        $c = $this->crearNormal();
        $payload = $this->peticionEdicion($c)->all();
        $payload['productos'][] = [
            'producto_lista_id' => null, 'lista_precio_id' => null, 'descripcion' => 'Termopanel a medida', 'cantidad' => 1, 'precio_unitario' => 90000, 'total' => 90000,
            'esVidrio' => true, 'ancho_mm' => 1000, 'alto_mm' => 900, 'm2' => 0.9, 'pulido' => true,
        ];

        $this->ctrl()->update(Request::create('/', 'PUT', $payload), $c->id);

        $nuevo = CotizacionDetalle::where('cotizacion_id', $c->id)->where('descripcion', 'Termopanel a medida')->first();
        $this->assertNotNull($nuevo);
        $this->assertTrue((bool) $nuevo->esVidrio);
        $this->assertEquals(1000, $nuevo->ancho_mm);
        $this->assertEquals(900, $nuevo->alto_mm);
        $this->assertEquals(0.9, $nuevo->m2);
        $this->assertTrue((bool) $nuevo->pulido);
        $this->assertEquals(625000 + 90000, $c->fresh()->total);
    }

    public function test_update_de_producto_existente_conserva_sus_campos_de_vidrio_aunque_la_peticion_los_mande_en_falso(): void
    {
        // El cotizador NO carga esVidrio/m2/medidas/pulido al editar y los reenvía como false/null: no deben borrarse.
        $c = $this->crearNormal();
        $payload = $this->peticionEdicion($c)->all();
        foreach ($payload['productos'] as &$p) { $p += ['esVidrio' => false, 'ancho_mm' => null, 'alto_mm' => null, 'm2' => null, 'pulido' => false]; }
        unset($p);

        $this->ctrl()->update(Request::create('/', 'PUT', $payload), $c->id);                 // como lo manda el frontend real
        $this->ctrl()->update($this->peticionEdicion($c), $c->id);                            // y sin esas claves

        $p = CotizacionDetalle::where('cotizacion_id', $c->id)->where('tipo_item', 'producto')->first();
        $this->assertTrue((bool) $p->esVidrio);
        $this->assertEquals(800, $p->ancho_mm);
        $this->assertEquals(600, $p->alto_mm);
        $this->assertEquals(0.96, $p->m2);
        $this->assertTrue((bool) $p->pulido);
    }

    public function test_update_sin_lista_de_ventanas_se_rechaza_y_no_borra_nada(): void
    {
        $c = $this->crearNormal();
        $payload = $this->peticionEdicion($c)->all();
        unset($payload['ventanas']);

        $resp = $this->ctrl()->update(Request::create('/', 'PUT', $payload), $c->id);

        $this->assertSame(422, $resp->getStatusCode());
        $this->assertSame(2, Ventana::where('cotizacion_id', $c->id)->count());
    }

    // ───────────────── importar desde PDF / actualizar desde PDF siguen funcionando ─────────────────

    public function test_importar_y_actualizar_winperfil_desde_pdf_siguen_funcionando(): void
    {
        EstadoCotizacion::create(['nombre' => 'Evaluación']);
        \App\Models\Cliente::create(['id' => 1, 'razon_social' => 'Cliente Test']);
        $items = [['descripcion' => 'V1 corredera', 'cantidad' => 2, 'precio_unitario' => 150000], ['descripcion' => 'V2 fija', 'cantidad' => 1, 'precio_unitario' => 90000]];

        $resp = $this->ctrl()->importarWinperfil(Request::create('/', 'POST', ['cliente_id' => 1, 'fecha' => '2026-11-01', 'items' => $items]));
        $this->assertSame(201, $resp->getStatusCode(), json_encode($resp->getData()));
        $id = $resp->getData()->cotizacion_id;
        $this->assertEquals(390000, Cotizacion::find($id)->total);
        $this->assertEquals(390000, CotizacionDetalle::where('cotizacion_id', $id)->where('tipo_item', 'winperfil')->sum('total'));

        $items[1]['precio_unitario'] = 100000;
        $resp2 = $this->ctrl()->actualizarWinperfil(Request::create('/', 'POST', ['cliente_id' => 1, 'fecha' => '2026-11-01', 'items' => $items]), $id);
        $this->assertSame(200, $resp2->getStatusCode(), json_encode($resp2->getData()));
        $this->assertEquals(400000, Cotizacion::find($id)->total, 'actualizar desde PDF cambia total y líneas juntos');
        $this->assertEquals(400000, CotizacionDetalle::where('cotizacion_id', $id)->where('tipo_item', 'winperfil')->sum('total'));
    }

    // ───────────────────────────── duplicar() ─────────────────────────────

    public function test_duplicar_copia_ventanas_y_todos_los_detalles_y_el_total_cuadra(): void
    {
        $orig = $this->crearNormal(['numero_documento_bsale' => '5301', 'id_documento_bsale' => 'abc', 'url_pdf_bsale' => 'https://bsale/x.pdf', 'token_bsale' => 't', 'estado_facturacion' => 'parcial']);

        $resp = $this->ctrl()->duplicar($orig->id);
        $this->assertSame(200, $resp->getStatusCode(), json_encode($resp->getData()));
        $nueva = Cotizacion::with('ventanas', 'detalles')->find($resp->getData()->id);

        $this->assertNotEquals($orig->id, $nueva->id);
        $this->assertSame(2, $nueva->ventanas->count());
        $this->assertSame(2, $nueva->detalles->count());
        $this->assertEquals(625000, $nueva->total);
        $this->assertEquals($this->sumaGuardada($nueva->id), $nueva->total, 'el total de la copia cuadra con sus líneas');

        $v3 = $nueva->ventanas->firstWhere('cantidad', 3);
        $this->assertEquals(450000, $v3->precio);
        $this->assertEquals(150000, $v3->precio_unitario);
        $this->assertSame(['tipo_vidrio' => 1, 'manillon' => false], $v3->config);

        $prod = $nueva->detalles->firstWhere('tipo_item', 'producto');
        $this->assertTrue((bool) $prod->esVidrio);
        $this->assertEquals(0.96, $prod->m2);
        $this->assertTrue((bool) $prod->pulido);
        $this->assertSame('Flete', $nueva->detalles->firstWhere('tipo_item', 'item_libre')->descripcion);

        // No copia números ni vínculos del original
        foreach (['numero_documento_bsale', 'id_documento_bsale', 'url_pdf_bsale', 'token_bsale', 'estado_facturacion', 'winperfil_numero', 'winperfil_serie', 'adjunto_winperfil', 'estado_produccion'] as $campo) {
            $this->assertNull($nueva->$campo, "no debe copiar $campo");
        }
        $this->assertNotEquals($orig->public_token, $nueva->public_token);
        $this->assertEquals($orig->id, $nueva->origen_id);

        // El original no se toca
        $this->assertSame(2, Ventana::where('cotizacion_id', $orig->id)->count());
        $this->assertSame(2, CotizacionDetalle::where('cotizacion_id', $orig->id)->count());
        $this->assertEquals(625000, $orig->fresh()->total);
    }

    public function test_duplicar_recalcula_el_total_de_la_copia_aunque_el_original_estuviera_descuadrado(): void
    {
        $orig = $this->crearNormal();
        $orig->update(['total' => 444009]);   // como la cotización 93 de producción: total sin los detalles

        $nueva = Cotizacion::find($this->ctrl()->duplicar($orig->id)->getData()->id);

        $this->assertEquals(625000, $nueva->total);
        $this->assertEquals(444009, $orig->fresh()->total, 'el original no se modifica');
    }

    public function test_duplicar_es_transaccional_si_falla_una_linea_no_queda_nada(): void
    {
        $orig = $this->crearNormal();
        $cotizacionesAntes = Cotizacion::count(); $ventanasAntes = Ventana::count(); $detallesAntes = CotizacionDetalle::count();

        CotizacionDetalle::creating(function () { throw new \RuntimeException('fallo simulado al copiar un detalle'); });
        try {
            $this->ctrl()->duplicar($orig->id);
            $this->fail('debía lanzar la excepción');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('simulado', $e->getMessage());
        } finally {
            CotizacionDetalle::flushEventListeners();
        }

        $this->assertSame($cotizacionesAntes, Cotizacion::count(), 'no queda una cotización a medias');
        $this->assertSame($ventanasAntes, Ventana::count());
        $this->assertSame($detallesAntes, CotizacionDetalle::count());
    }

    public function test_duplicar_bloquea_cotizaciones_winperfil_con_mensaje_claro(): void
    {
        foreach ([['winperfil_numero' => 676], ['adjunto_winperfil' => 'https://r2.example/x.pdf']] as $extra) {
            $orig = $this->crearNormal($extra);
            $antes = Cotizacion::count();
            $resp = $this->ctrl()->duplicar($orig->id);
            $this->assertSame(422, $resp->getStatusCode());
            $this->assertStringContainsString('no se pueden duplicar', $resp->getData()->message);
            $this->assertSame($antes, Cotizacion::count());
        }
        // con líneas winperfil
        $orig = $this->crearNormal();
        CotizacionDetalle::create(['cotizacion_id' => $orig->id, 'tipo_item' => 'winperfil', 'descripcion' => 'V1', 'cantidad' => 1, 'precio_unitario' => 100, 'total' => 100]);
        $this->assertSame(422, $this->ctrl()->duplicar($orig->id)->getStatusCode());
    }

    public function test_duplicar_solo_permite_estado_evaluacion(): void
    {
        $orig = $this->crearNormal();
        $orig->update(['estado_cotizacion_id' => EstadoCotizacion::create(['nombre' => 'Aprobada'])->id]);
        $this->assertSame(403, $this->ctrl()->duplicar($orig->id)->getStatusCode());
    }
}
