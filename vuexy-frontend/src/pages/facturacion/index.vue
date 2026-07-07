<template>
  <div>
    <!-- Stats cards -->
    <v-row class="mb-4">
      <v-col cols="6" sm="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <v-icon size="16" color="warning">mdi-clock-outline</v-icon>
            <span class="text-caption text-medium-emphasis">Por facturar</span>
          </div>
          <div class="text-h6 font-weight-bold">{{ statsPorFacturar.count }}</div>
          <div class="text-caption text-medium-emphasis">{{ clp(statsPorFacturar.monto) }}</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <v-icon size="16" color="info">mdi-receipt-text</v-icon>
            <span class="text-caption text-medium-emphasis">Facturadas</span>
          </div>
          <div class="text-h6 font-weight-bold">{{ statsFacturadas.count }}</div>
          <div class="text-caption text-medium-emphasis">{{ clp(statsFacturadas.monto) }}</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <v-icon size="16" color="success">mdi-check-circle-outline</v-icon>
            <span class="text-caption text-medium-emphasis">Cobradas</span>
          </div>
          <div class="text-h6 font-weight-bold">{{ statsPagadas.count }}</div>
          <div class="text-caption text-medium-emphasis">{{ clp(statsPagadas.monto) }}</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center gap-2 mb-1">
            <v-icon size="16" color="primary">mdi-briefcase-outline</v-icon>
            <span class="text-caption text-medium-emphasis">Total en cartera</span>
          </div>
          <div class="text-h6 font-weight-bold">{{ cotizacionesFiltradas.length }}</div>
          <div class="text-caption text-medium-emphasis">{{ clp(totalCartera) }}</div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filtros -->
    <v-card class="mb-4">
      <v-card-text class="pb-2">
        <v-row dense>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="filtros.busqueda"
              label="Buscar por cliente, ID..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filtros.estado"
              :items="estadosFacturacion"
              label="Estado"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filtros.cliente"
              :items="clientesUnicos"
              :item-title="(c) => c.razon_social || `${c.first_name || ''} ${c.last_name || ''}`.trim() || 'Sin nombre'"
              item-value="id"
              label="Cliente"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="2" class="d-flex align-center">
            <v-btn size="small" variant="text" @click="limpiarFiltros">Limpiar</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Tabla -->
    <v-card>
      <v-data-table
        v-model:items-per-page="itemsPorPagina"
        :headers="headers"
        :items="cotizacionesFiltradas"
        :loading="loading"
        item-value="id"
        show-expand
      >
        <!-- Número -->
        <template #item.id="{ item }">
          <v-chip color="primary" variant="outlined" size="small">#{{ item.id }}</v-chip>
        </template>

        <!-- Cliente -->
        <template #item.cliente="{ item }">
          <div v-if="item.cliente">
            <div class="font-weight-medium text-body-2">
              {{ item.cliente.razon_social || `${item.cliente.first_name || ''} ${item.cliente.last_name || ''}`.trim() || 'Sin nombre' }}
            </div>
            <div class="text-caption text-medium-emphasis">{{ item.cliente.identification || item.cliente.email }}</div>
          </div>
          <span v-else class="text-caption text-medium-emphasis">Sin cliente</span>
        </template>

        <!-- Total -->
        <template #item.total="{ item }">
          <span class="font-weight-bold text-success">{{ clp(item.total) }}</span>
        </template>

        <!-- Estado -->
        <template #item.estado_facturacion="{ item }">
          <v-chip :color="colorEstado(item.estado_facturacion)" size="small" variant="tonal">
            {{ textoEstado(item.estado_facturacion) }}
          </v-chip>
        </template>

        <!-- Fecha -->
        <template #item.fecha="{ item }">
          <span class="text-caption">{{ fmtFecha(item.fecha) }}</span>
        </template>

        <!-- Facturado y cobrado (dos barras) -->
        <template #item.cobrado="{ item }">
          <div v-if="item.documentos_facturacion?.some(d => d.estado === 'emitido')" style="min-width: 100px">
            <div class="d-flex justify-space-between text-caption mb-1">
              <span class="text-info">Fact: {{ pctEmitido(item) }}%</span>
              <span class="text-success">Cobr: {{ pctCobrado(item) }}%</span>
            </div>
            <v-progress-linear :model-value="pctEmitido(item)" color="info" bg-color="grey-lighten-3" rounded height="3" class="mb-1" />
            <v-progress-linear :model-value="pctCobrado(item)" color="success" bg-color="grey-lighten-3" rounded height="3" />
          </div>
          <span v-else class="text-caption text-disabled">—</span>
        </template>

        <!-- Acciones -->
        <template #item.acciones="{ item }">
          <div class="d-flex align-center gap-1" @click.stop>
            <v-btn
              v-if="item.estado_facturacion !== 'pagada'"
              color="success" variant="tonal" size="small"
              @click="abrirModalBsale(item)"
            >
              <v-icon size="16" start>mdi-receipt</v-icon>
              Emitir
            </v-btn>
            <v-menu>
              <template #activator="{ props }">
                <v-btn v-bind="props" icon="mdi-dots-vertical" variant="text" size="x-small" />
              </template>
              <v-list density="compact">
                <v-list-item @click="verDetalleCompleto(item)">
                  <v-list-item-title>Ver detalle</v-list-item-title>
                </v-list-item>
                <v-divider />
                <v-list-item class="text-error" @click="eliminarCotizacion(item)">
                  <v-list-item-title>Eliminar</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-menu>
          </div>
        </template>

        <!-- Expanded row: items + historial de documentos con estado de cobro -->
        <template #expanded-row="{ columns, item }">
          <tr>
            <td :colspan="columns.length" class="pa-0">
              <div class="pa-4 expanded-row-content">
                <v-row>
                  <!-- Izquierda: items cotización -->
                  <v-col cols="12" md="7">
                    <div v-if="item.cliente_facturacion_id && item.cliente_facturacion_id !== item.cliente_id" class="mb-3 d-flex align-center gap-2">
                      <v-icon size="14" color="warning">mdi-alert-circle-outline</v-icon>
                      <span class="text-caption text-medium-emphasis">
                        Facturado a: <strong>{{ item.cliente_facturacion?.razon_social || `${item.cliente_facturacion?.first_name || ''} ${item.cliente_facturacion?.last_name || ''}`.trim() }}</strong>
                        — {{ item.cliente_facturacion?.identification }}
                      </span>
                    </div>
                    <p class="text-caption font-weight-medium mb-2">
                      Items ({{ (item.ventanas?.length || 0) + (item.detalles?.length || 0) }})
                    </p>
                    <v-table density="compact">
                      <thead>
                        <tr>
                          <th>Descripción</th>
                          <th>Cant.</th>
                          <th class="text-right">P. Unit. (neto)</th>
                          <th class="text-right">Total (neto)</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="v in item.ventanas" :key="`v-${v.id}`">
                          <td>
                            <span class="text-body-2">{{ v.tipo_ventana?.nombre || 'Ventana' }}</span>
                            <span class="text-caption text-medium-emphasis ml-1">{{ v.ancho }}×{{ v.alto }}mm</span>
                          </td>
                          <td>{{ v.cantidad }}</td>
                          <td class="text-right">{{ clp(v.precio_unitario) }}</td>
                          <td class="text-right font-weight-medium">{{ clp(v.precio_unitario * v.cantidad) }}</td>
                        </tr>
                        <tr v-for="d in item.detalles" :key="`d-${d.id}`">
                          <td>
                            <span class="text-body-2">{{ d.descripcion || d.lista_precio?.producto?.nombre || 'Producto' }}</span>
                            <span v-if="d.lista_precio?.color" class="text-caption text-medium-emphasis ml-1">— {{ d.lista_precio.color.nombre }}</span>
                          </td>
                          <td>{{ d.cantidad }}</td>
                          <td class="text-right">{{ clp(d.precio_unitario) }}</td>
                          <td class="text-right font-weight-medium">{{ clp(d.total) }}</td>
                        </tr>
                      </tbody>
                    </v-table>
                    <div class="d-flex justify-end mt-2">
                      <div style="min-width:180px">
                        <div class="d-flex justify-space-between font-weight-bold py-1">
                          <span>Total Neto</span><span class="text-success">{{ clp(item.total) }}</span>
                        </div>
                      </div>
                    </div>
                  </v-col>

                  <!-- Derecha: historial de emisiones con estado de cobro -->
                  <v-col cols="12" md="5">
                    <p class="text-caption font-weight-medium mb-2">Historial de facturación y cobro</p>

                    <div v-if="item.documentos_facturacion?.length">
                      <div
                        v-for="doc in item.documentos_facturacion"
                        :key="doc.id"
                        class="mb-3 pa-3 rounded"
                        style="border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity))"
                      >
                        <!-- Cabecera del doc -->
                        <div class="d-flex align-center justify-space-between mb-2">
                          <div class="d-flex align-center gap-1">
                            <v-icon size="14" :color="doc.estado === 'emitido' ? 'success' : 'warning'">
                              {{ doc.estado === 'emitido' ? 'mdi-check-circle' : 'mdi-clock-outline' }}
                            </v-icon>
                            <span class="text-body-2 font-weight-medium text-capitalize">{{ doc.tipo }}</span>
                            <span class="text-caption text-medium-emphasis">({{ doc.porcentaje }}%)</span>
                          </div>
                          <v-btn
                            v-if="doc.url_pdf_bsale"
                            icon size="x-small" variant="text" color="info"
                            :href="doc.url_pdf_bsale" target="_blank"
                          >
                            <v-icon size="14">mdi-file-pdf-box</v-icon>
                          </v-btn>
                        </div>

                        <!-- Monto y N° doc -->
                        <div class="text-caption text-medium-emphasis mb-2">
                          <span class="font-weight-medium text-body-2">{{ clp(doc.monto) }}</span>
                          <span v-if="doc.numero_documento_bsale" class="ml-2">· Doc #{{ doc.numero_documento_bsale }}</span>
                          <span v-if="doc.fecha_emision" class="ml-2">· {{ doc.fecha_emision }}</span>
                        </div>

                        <!-- Estado de cobro (solo docs emitidos) -->
                        <template v-if="doc.estado === 'emitido'">
                          <!-- Boletas: el cobro se gestiona en el módulo Boletas (resumen mensual por forma de pago) -->
                          <div v-if="esBoleta(doc)" class="d-flex align-center gap-2 mt-1">
                            <v-chip size="x-small" color="info" variant="tonal">
                              <v-icon size="12" start>mdi-receipt-text-outline</v-icon>
                              Boleta — cobro por resumen mensual
                            </v-chip>
                            <v-btn size="x-small" variant="text" color="info" to="/boletas" @click.stop>
                              Ir a módulo Boletas
                            </v-btn>
                          </div>
                          <!-- Facturas / anticipos: se concilian aquí contra los movimientos bancarios -->
                          <template v-else>
                            <div class="d-flex justify-space-between text-caption mb-1">
                              <span class="text-success">Cobrado: {{ clp(doc.monto_cobrado || 0) }}</span>
                              <span :class="(doc.pendiente || 0) > 0 ? 'text-warning' : 'text-success'">
                                {{ (doc.pendiente || 0) > 0 ? `Por cobrar: ${clp(doc.pendiente)}` : '✓ Cobrado' }}
                              </span>
                            </div>
                            <v-progress-linear
                              :model-value="doc.monto > 0 ? ((doc.monto_cobrado || 0) / doc.monto) * 100 : 0"
                              color="success"
                              bg-color="warning"
                              height="4"
                              rounded
                              class="mb-2"
                            />
                            <v-btn
                              size="x-small"
                              variant="tonal"
                              color="success"
                              @click.stop="abrirConciliarCobro(doc, item)"
                            >
                              <v-icon size="12" start>mdi-link-variant</v-icon>
                              Conciliar cobro
                            </v-btn>
                          </template>
                        </template>
                        <v-chip v-else size="x-small" color="warning" variant="tonal">Pendiente de emisión</v-chip>
                      </div>

                      <!-- Vincular doc Bsale huérfano -->
                      <div class="mt-2 d-flex justify-end">
                        <v-btn size="x-small" color="info" variant="tonal" @click.stop="abrirDialogVincular(item)">
                          <v-icon size="12" start>mdi-link-variant-plus</v-icon>
                          Vincular doc Bsale
                        </v-btn>
                      </div>

                      <!-- Totales globales cobro -->
                      <div class="mt-2 pa-2 rounded bg-surface-variant">
                        <div class="d-flex justify-space-between text-caption mb-1">
                          <span>Facturado: {{ clp(item.total_emitido || 0) }}</span>
                          <span>Cobrado: {{ clp(item.total_cobrado || 0) }}</span>
                        </div>
                        <v-progress-linear
                          :model-value="(item.total_emitido || 0) > 0 ? ((item.total_cobrado || 0) / item.total_emitido) * 100 : 0"
                          color="success"
                          bg-color="warning"
                          rounded height="6"
                        />
                        <div v-if="(item.saldo_por_cobrar || 0) > 0" class="text-caption text-warning text-end mt-1">
                          Por cobrar: {{ clp(item.saldo_por_cobrar) }}
                        </div>
                        <div v-else-if="(item.total_cobrado || 0) > 0" class="text-caption text-success text-end mt-1">
                          ✓ Completamente cobrada
                        </div>
                      </div>
                    </div>

                    <div v-else class="text-center pa-4">
                      <v-icon size="32" color="grey" class="mb-1">mdi-receipt-text-outline</v-icon>
                      <p class="text-caption text-medium-emphasis">Sin documentos emitidos aún</p>
                      <div class="d-flex gap-2 justify-center mt-2 flex-wrap">
                        <v-btn size="small" color="success" variant="tonal" @click.stop="abrirModalBsale(item)">
                          Emitir primer documento
                        </v-btn>
                        <v-btn size="small" color="info" variant="tonal" @click.stop="abrirDialogVincular(item)">
                          <v-icon size="14" start>mdi-link-variant-plus</v-icon>
                          Vincular doc Bsale
                        </v-btn>
                      </div>
                    </div>
                  </v-col>
                </v-row>
              </div>
            </td>
          </tr>
        </template>

        <!-- Footer -->
        <template #bottom>
          <div class="d-flex justify-space-between align-center px-4 py-2 text-caption text-medium-emphasis">
            <span>{{ cotizacionesFiltradas.length }} de {{ cotizacionesAprobadas.length }} cotizaciones</span>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Modal Bsale -->
    <ModalBsale
      v-model="mostrarModalBsale"
      :cotizacion="cotizacionSeleccionada"
      @documento-generado="onDocumentoGenerado"
    />

    <!-- Modal detalle -->
    <v-dialog v-model="mostrarModalDetalle" max-width="1200px">
      <DetalleCotizacion
        v-if="mostrarModalDetalle"
        :cotizacion="cotizacionSeleccionada"
        @cerrar="mostrarModalDetalle = false"
      />
    </v-dialog>

    <!-- ── Modal Conciliar Cobro ────────────────────────────────────────────── -->
    <v-dialog v-model="dialogConciliarCobro" max-width="1100" scrollable>
      <v-card v-if="docConciliando">
        <v-card-title class="d-flex align-center pa-4 pb-2">
          <span>Conciliar Cobro</span>
          <v-chip size="x-small" color="info" variant="tonal" class="ml-2 text-capitalize">{{ docConciliando.tipo }}</v-chip>
          <v-chip size="x-small" color="success" variant="tonal" class="ml-1">{{ clp(docConciliando.monto) }}</v-chip>
          <v-spacer />
          <v-btn icon variant="text" @click="dialogConciliarCobro = false"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>

        <v-card-text class="pa-0">
          <v-row no-gutters style="min-height: 440px">

            <!-- Izquierda: ingresos bancarios disponibles -->
            <v-col cols="12" md="8" class="border-e">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Ingresos bancarios (Créditos disponibles)</p>

                <!-- Ya asignados -->
                <div v-if="cobrosAsignados.length" class="mb-4">
                  <p class="text-caption text-medium-emphasis mb-2">Asignados a esta factura:</p>
                  <v-table density="compact">
                    <tbody>
                      <tr v-for="a in cobrosAsignados" :key="a.pivot_id">
                        <td class="text-caption">{{ fmtFecha(a.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ a.descripcion }}</td>
                        <td class="text-end text-caption font-weight-bold text-success">{{ clp(a.monto_asignado) }}</td>
                        <td>
                          <v-btn size="x-small" icon variant="text" color="error"
                            :loading="loadingDesasignarCobro[a.pivot_id]"
                            @click="desasignarCobro(a.pivot_id)">
                            <v-icon size="14">mdi-close</v-icon>
                          </v-btn>
                        </td>
                      </tr>
                    </tbody>
                  </v-table>
                  <v-divider class="my-3" />
                </div>

                <!-- Buscador -->
                <v-text-field
                  v-model="buscarMovCobro"
                  placeholder="Buscar por descripción del ingreso..."
                  density="compact"
                  variant="outlined"
                  prepend-inner-icon="mdi-magnify"
                  hide-details
                  class="mb-3"
                  clearable
                  @update:modelValue="cargarMovDisponibles"
                />

                <!-- Lista -->
                <div v-if="loadingMovDisp" class="text-center py-6">
                  <v-progress-circular indeterminate size="28" />
                </div>
                <div v-else style="overflow-x: auto">
                  <v-table density="compact">
                    <thead>
                      <tr>
                        <th>Saldo disponible</th>
                        <th>Monto total</th>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="mov in movDisponibles" :key="mov.id">
                        <td class="font-weight-bold text-success">{{ clp(mov.saldo_por_asignar) }}</td>
                        <td>{{ clp(mov.monto) }}</td>
                        <td class="text-caption">{{ fmtFecha(mov.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                          {{ mov.descripcion }}
                          <span v-if="mov.glosa" class="text-medium-emphasis d-block">{{ mov.glosa }}</span>
                        </td>
                        <td>
                          <v-btn size="x-small" color="success" variant="tonal"
                            :loading="loadingAsignarCobro[mov.id]"
                            :disabled="saldoPorCobrar <= 0"
                            @click="asignarCobro(mov)">
                            Seleccionar
                          </v-btn>
                        </td>
                      </tr>
                      <tr v-if="!movDisponibles.length">
                        <td colspan="5" class="text-center text-caption text-medium-emphasis py-4">
                          Sin ingresos bancarios disponibles
                        </td>
                      </tr>
                    </tbody>
                  </v-table>
                </div>
              </div>
            </v-col>

            <!-- Derecha: resumen doc -->
            <v-col cols="12" md="4">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Resumen</p>
                <v-card variant="outlined" class="pa-4">
                  <p class="font-weight-bold mb-0">{{ cotizConciliando?.cliente?.razon_social || `${cotizConciliando?.cliente?.first_name || ''} ${cotizConciliando?.cliente?.last_name || ''}`.trim() }}</p>
                  <p class="text-caption text-medium-emphasis mb-3">Cot. #{{ cotizConciliando?.id }}</p>
                  <v-divider class="mb-3" />
                  <div class="d-flex justify-space-between">
                    <span class="text-body-2">Total factura</span>
                    <span class="font-weight-bold">{{ clp(docConciliando.monto) }}</span>
                  </div>
                  <div class="d-flex justify-space-between mt-1">
                    <span class="text-body-2 text-success">Cobrado</span>
                    <span class="text-success">{{ clp(docConciliando.monto - saldoPorCobrar) }}</span>
                  </div>
                  <v-divider class="my-2" />
                  <div class="d-flex justify-space-between">
                    <span class="text-body-2 font-weight-bold" :class="saldoPorCobrar > 0 ? 'text-warning' : 'text-success'">Por cobrar</span>
                    <span class="font-weight-bold text-h6" :class="saldoPorCobrar > 0 ? 'text-warning' : 'text-success'">
                      {{ clp(saldoPorCobrar) }}
                    </span>
                  </div>
                  <v-progress-linear
                    :model-value="docConciliando.monto > 0 ? ((docConciliando.monto - saldoPorCobrar) / docConciliando.monto) * 100 : 0"
                    color="success" bg-color="warning" height="8" rounded class="mt-3"
                  />
                  <v-chip v-if="saldoPorCobrar <= 0" color="success" variant="tonal" class="mt-3 w-100" style="justify-content:center">
                    <v-icon start size="14">mdi-check-circle</v-icon> Cobrada completamente
                  </v-chip>
                </v-card>
              </div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- ── Modal Vincular Doc Bsale Huérfano ──────────────────────────────── -->
    <v-dialog v-model="dialogVincular" max-width="820" scrollable>
      <v-card v-if="cotizVinculando">
        <v-card-title class="d-flex align-center pa-4 pb-2">
          <v-icon start color="info">mdi-link-variant-plus</v-icon>
          Vincular documento Bsale
          <v-spacer />
          <v-btn icon variant="text" @click="dialogVincular = false"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>
        <v-card-subtitle class="px-4 pb-3">
          Cot. #{{ cotizVinculando.id }} ·
          <span class="text-medium-emphasis">
            {{ cotizVinculando.cliente?.razon_social || `${cotizVinculando.cliente?.first_name || ''} ${cotizVinculando.cliente?.last_name || ''}`.trim() }}
          </span>
          · Total: <span class="font-weight-bold">{{ clp(cotizVinculando.total) }}</span>
        </v-card-subtitle>
        <v-divider />

        <v-card-text class="pa-4">
          <p class="text-body-2 text-medium-emphasis mb-4">
            Documentos emitidos directamente en Bsale (sin cotización asignada). Al vincular, el porcentaje se calculará automáticamente respecto al total de la cotización.
          </p>

          <v-text-field
            v-model="buscarHuerfano"
            placeholder="Buscar por N° doc, tipo, N° comprobante..."
            density="compact"
            variant="outlined"
            prepend-inner-icon="mdi-magnify"
            hide-details
            class="mb-4"
            clearable
            @update:modelValue="cargarHuerfanos"
          />

          <div v-if="loadingHuerfanos" class="text-center py-8">
            <v-progress-circular indeterminate size="32" color="info" />
          </div>

          <v-table v-else density="compact">
            <thead>
              <tr>
                <th>Cliente</th>
                <th>RUT</th>
                <th>Tipo</th>
                <th>N° Bsale</th>
                <th>Monto</th>
                <th>% sobre cot.</th>
                <th>Fecha</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="doc in huerfanos" :key="doc.id">
                <td class="text-caption" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                  {{ doc.cliente_nombre_local || doc.bsale_cliente_nombre || '-' }}
                </td>
                <td class="text-caption text-medium-emphasis">{{ doc.bsale_cliente_rut || '-' }}</td>
                <td>
                  <v-chip size="x-small" variant="tonal" color="info" class="text-capitalize">{{ doc.tipo }}</v-chip>
                </td>
                <td class="text-caption">{{ doc.numero_documento_bsale || '-' }}</td>
                <td class="text-caption font-weight-bold text-success">{{ clp(doc.monto) }}</td>
                <td class="text-caption text-medium-emphasis">
                  {{ cotizVinculando.total > 0 ? Math.round((doc.monto / cotizVinculando.total) * 100) + '%' : '-' }}
                </td>
                <td class="text-caption">{{ fmtFecha(doc.fecha_emision) }}</td>
                <td>
                  <v-btn
                    size="x-small"
                    color="info"
                    variant="tonal"
                    :loading="loadingVincular[doc.id]"
                    @click="vincularDoc(doc)"
                  >
                    <v-icon size="12" start>mdi-link</v-icon>
                    Vincular
                  </v-btn>
                </td>
              </tr>
              <tr v-if="!huerfanos.length">
                <td colspan="8" class="text-center text-caption text-medium-emphasis py-8">
                  <v-icon size="32" color="grey" class="d-block mx-auto mb-2">mdi-receipt-text-check-outline</v-icon>
                  No hay documentos Bsale sin cotización asignada
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
      </v-card>
    </v-dialog>

    <!-- Snackbar -->
    <v-snackbar v-model="snack.show" :color="snack.color" location="top right" :timeout="4000">
      {{ snack.text }}
      <template #actions>
        <v-btn variant="text" @click="snack.show = false">Cerrar</v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/axiosInstance'
import ModalBsale from '@/components/facturacion/ModalBsale.vue'
import DetalleCotizacion from '@/components/DetalleCotizacion.vue'

// ── Estado ───────────────────────────────────────────────────────────
const loading               = ref(false)
const cotizacionesAprobadas = ref([])
const mostrarModalBsale     = ref(false)
const mostrarModalDetalle   = ref(false)
const cotizacionSeleccionada = ref(null)
const itemsPorPagina        = ref(15)
const snack = ref({ show: false, text: '', color: 'success' })

const filtros = ref({ busqueda: '', estado: null, cliente: null })

// ── Conciliar cobro ──────────────────────────────────────────────────
const dialogConciliarCobro   = ref(false)
const docConciliando         = ref(null)
const cotizConciliando       = ref(null)
const cobrosAsignados        = ref([])
const movDisponibles         = ref([])
const saldoPorCobrar         = ref(0)
const buscarMovCobro         = ref('')
const loadingMovDisp         = ref(false)
const loadingAsignarCobro    = ref({})
const loadingDesasignarCobro = ref({})

// ── Vincular doc Bsale huérfano ───────────────────────────────────────
const dialogVincular   = ref(false)
const cotizVinculando  = ref(null)
const huerfanos        = ref([])
const buscarHuerfano   = ref('')
const loadingHuerfanos = ref(false)
const loadingVincular  = ref({})

// ── Headers ──────────────────────────────────────────────────────────
const headers = [
  { title: '#',               key: 'id',                 sortable: true,  width: '80px' },
  { title: 'Cliente',         key: 'cliente',            sortable: false },
  { title: 'Total Neto',      key: 'total',              sortable: true },
  { title: 'Estado',          key: 'estado_facturacion', sortable: true },
  { title: 'Fecha',           key: 'fecha',              sortable: true },
  { title: 'Facturado/Cobrado', key: 'cobrado',          sortable: false, width: '130px' },
  { title: 'Acciones',        key: 'acciones',           sortable: false, width: '130px' },
]

const estadosFacturacion = [
  { title: 'Por facturar', value: 'aprobada' },
  { title: 'Facturada',    value: 'facturada' },
  { title: 'Cobrada',      value: 'pagada' },
]

// ── Computed ─────────────────────────────────────────────────────────
const clientesUnicos = computed(() => {
  const seen = new Set()
  return cotizacionesAprobadas.value
    .map(c => c.cliente)
    .filter(c => c && !seen.has(c.id) && seen.add(c.id))
})

const cotizacionesFiltradas = computed(() => {
  let list = cotizacionesAprobadas.value
  if (filtros.value.busqueda) {
    const q = filtros.value.busqueda.toLowerCase()
    list = list.filter(c =>
      String(c.id).includes(q) ||
      c.cliente?.razon_social?.toLowerCase().includes(q) ||
      c.cliente?.first_name?.toLowerCase().includes(q) ||
      c.cliente?.last_name?.toLowerCase().includes(q) ||
      c.cliente?.identification?.toLowerCase().includes(q)
    )
  }
  if (filtros.value.estado)  list = list.filter(c => c.estado_facturacion === filtros.value.estado)
  if (filtros.value.cliente) list = list.filter(c => c.cliente?.id === filtros.value.cliente)
  return list
})

const statsPorFacturar = computed(() => stats('aprobada'))
const statsFacturadas  = computed(() => stats('facturada'))
const statsPagadas     = computed(() => stats('pagada'))
const totalCartera     = computed(() => cotizacionesFiltradas.value.reduce((s, c) => s + Number(c.total), 0))

function stats(estado) {
  const list = cotizacionesAprobadas.value.filter(c => c.estado_facturacion === estado)
  return { count: list.length, monto: list.reduce((s, c) => s + Number(c.total), 0) }
}

// ── Helpers de cobro por cotización ─────────────────────────────────
function totalEmitido(item) {
  return item.documentos_facturacion?.filter(d => d.estado === 'emitido').reduce((s, d) => s + Number(d.monto), 0) || 0
}
function totalCobrado(item) {
  return item.documentos_facturacion?.filter(d => d.estado === 'emitido').reduce((s, d) => s + Number(d.monto_cobrado || 0), 0) || 0
}
function pctEmitido(item) {
  return item.total > 0 ? Math.round((totalEmitido(item) / item.total) * 100) : 0
}
function pctCobrado(item) {
  const emitido = totalEmitido(item)
  return emitido > 0 ? Math.round((totalCobrado(item) / emitido) * 100) : 0
}

// ── Carga ────────────────────────────────────────────────────────────
async function cargarCotizaciones() {
  loading.value = true
  try {
    const { data } = await api.get('/api/cotizaciones/aprobadas')
    cotizacionesAprobadas.value = data.cotizaciones || []
  } catch (e) {
    mostrarSnack('Error al cargar cotizaciones', 'error')
  } finally {
    loading.value = false
  }
}

// ── Acciones ─────────────────────────────────────────────────────────
function abrirModalBsale(item) {
  cotizacionSeleccionada.value = item
  mostrarModalBsale.value = true
}

async function verDetalleCompleto(item) {
  try {
    const { data } = await api.get(`/api/cotizaciones/${item.id}`)
    cotizacionSeleccionada.value = data
  } catch {
    cotizacionSeleccionada.value = item
  }
  mostrarModalDetalle.value = true
}

async function eliminarCotizacion(item) {
  if (!confirm(`¿Eliminar cotización #${item.id}?`)) return
  try {
    await api.delete(`/api/cotizaciones/${item.id}`)
    cotizacionesAprobadas.value = cotizacionesAprobadas.value.filter(c => c.id !== item.id)
    mostrarSnack('Cotización eliminada')
  } catch {
    mostrarSnack('Error al eliminar', 'error')
  }
}

async function onDocumentoGenerado() {
  mostrarSnack('Documento generado correctamente')
  await cargarCotizaciones()
}

function limpiarFiltros() {
  filtros.value = { busqueda: '', estado: null, cliente: null }
}

// ── Conciliar cobro ──────────────────────────────────────────────────
function abrirConciliarCobro(doc, cot) {
  docConciliando.value    = doc
  cotizConciliando.value  = cot
  buscarMovCobro.value    = ''
  cobrosAsignados.value   = []
  movDisponibles.value    = []
  saldoPorCobrar.value    = 0
  dialogConciliarCobro.value = true
  cargarEstadoCobro()
}

async function cargarEstadoCobro() {
  if (!docConciliando.value) return
  try {
    const { data } = await api.get(`/api/ventas/${docConciliando.value.id}/movimientos`)
    cobrosAsignados.value = data.asignados
    saldoPorCobrar.value  = data.saldo_por_cobrar
  } catch (e) { console.error(e) }
  await cargarMovDisponibles()
}

async function cargarMovDisponibles() {
  if (!docConciliando.value) return
  loadingMovDisp.value = true
  try {
    const { data } = await api.get(`/api/ventas/${docConciliando.value.id}/movimientos-disponibles`, {
      params: { buscar: buscarMovCobro.value || undefined }
    })
    movDisponibles.value = data.data ?? data
  } catch (e) { console.error(e) }
  finally { loadingMovDisp.value = false }
}

async function asignarCobro(mov) {
  loadingAsignarCobro.value[mov.id] = true
  try {
    const monto = Math.min(mov.saldo_por_asignar, saldoPorCobrar.value)
    await api.post(`/api/ventas/${docConciliando.value.id}/movimientos`, {
      movimiento_id: mov.id,
      monto,
    })
    await cargarEstadoCobro()
    await cargarCotizaciones()
  } catch (e) {
    console.error(e)
  } finally {
    loadingAsignarCobro.value[mov.id] = false
  }
}

async function desasignarCobro(pivotId) {
  loadingDesasignarCobro.value[pivotId] = true
  try {
    await api.delete(`/api/ventas/${docConciliando.value.id}/movimientos/${pivotId}`)
    await cargarEstadoCobro()
    await cargarCotizaciones()
  } catch (e) {
    console.error(e)
  } finally {
    loadingDesasignarCobro.value[pivotId] = false
  }
}

// ── Vincular doc Bsale huérfano ──────────────────────────────────────
async function abrirDialogVincular(item) {
  cotizVinculando.value = item
  buscarHuerfano.value  = ''
  huerfanos.value       = []
  dialogVincular.value  = true
  await cargarHuerfanos()
}

async function cargarHuerfanos() {
  loadingHuerfanos.value = true
  try {
    const { data } = await api.get('/api/documentos-facturacion/huerfanos', {
      params: { buscar: buscarHuerfano.value || undefined },
    })
    huerfanos.value = data
  } catch (e) {
    console.error(e)
  } finally {
    loadingHuerfanos.value = false
  }
}

async function vincularDoc(doc) {
  loadingVincular.value[doc.id] = true
  try {
    await api.patch(`/api/documentos-facturacion/${doc.id}/vincular`, {
      cotizacion_id: cotizVinculando.value.id,
    })
    dialogVincular.value = false
    mostrarSnack(`Documento ${doc.numero_documento_bsale ? '#' + doc.numero_documento_bsale : ''} vinculado correctamente`)
    await cargarCotizaciones()
  } catch (e) {
    const msg = e.response?.data?.message || 'Error al vincular documento'
    mostrarSnack(msg, 'error')
  } finally {
    loadingVincular.value[doc.id] = false
  }
}

// ── Helpers ──────────────────────────────────────────────────────────
const clp = (n) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(Number(n) || 0)
const fmtFecha = (f) => f ? new Date(f).toLocaleDateString('es-CL') : '-'
const colorEstado = (e) => ({ aprobada: 'warning', facturada: 'info', pagada: 'success' }[e] || 'grey')
const textoEstado = (e) => ({ aprobada: 'Por facturar', facturada: 'Facturada', pagada: 'Cobrada' }[e] || e)

// Boleta electrónica (tipo_documento_bsale_id = 1): su cobro se gestiona en el módulo Boletas
const esBoleta = (doc) => Number(doc?.tipo_documento_bsale_id) === 1

function mostrarSnack(text, color = 'success') {
  snack.value = { show: true, text, color }
}

onMounted(cargarCotizaciones)
</script>

<style scoped>
.expanded-row-content {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
