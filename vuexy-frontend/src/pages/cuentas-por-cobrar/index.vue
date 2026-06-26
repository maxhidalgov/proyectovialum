<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Cuentas por Cobrar</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Facturas de venta emitidas a clientes (fuente: Bsale)</p>
      </VCol>
      <VCol cols="auto" class="d-flex align-center" style="gap:8px">
        <!-- Badge "por revisar" -->
        <VBtn
          v-if="totales.facturas_por_revisar > 0"
          color="warning"
          variant="tonal"
          size="small"
          prepend-icon="mdi-alert-circle"
          @click="abrirPorRevisar"
        >
          {{ totales.facturas_por_revisar }} por revisar
        </VBtn>
        <VBtn
          color="primary"
          variant="tonal"
          size="small"
          :loading="sincronizando"
          prepend-icon="mdi-cloud-sync"
          @click="sincronizarDesideBsale"
        >
          Sincronizar desde Bsale
        </VBtn>
      </VCol>
    </VRow>

    <!-- Snackbar sync -->
    <VSnackbar v-model="syncSnack.show" :color="syncSnack.color" location="top right" :timeout="5000">
      {{ syncSnack.text }}
      <template #actions><VBtn variant="text" @click="syncSnack.show = false">Cerrar</VBtn></template>
    </VSnackbar>
    <!-- Snackbar acciones NC -->
    <VSnackbar v-model="snackNc.show" :color="snackNc.color" location="top right" :timeout="4000">
      {{ snackNc.text }}
      <template #actions><VBtn variant="text" @click="snackNc.show = false">Cerrar</VBtn></template>
    </VSnackbar>

    <!-- Filtros -->
    <VCard class="mb-4">
      <VCardText>
        <VRow dense align="center">
          <VCol cols="12" sm="3">
            <VTextField
              v-model="filtros.desde"
              label="Desde"
              type="date"
              density="compact"
              variant="outlined"
              hide-details
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" sm="3">
            <VTextField
              v-model="filtros.hasta"
              label="Hasta"
              type="date"
              density="compact"
              variant="outlined"
              hide-details
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" sm="3">
            <VTextField
              v-model="filtros.buscar"
              label="Buscar cliente"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-magnify"
              clearable
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" sm="2">
            <VTextField
              v-model="filtros.monto"
              label="Monto exacto"
              density="compact"
              variant="outlined"
              hide-details
              prepend-inner-icon="mdi-currency-usd"
              clearable
              @update:modelValue="cargar"
            />
          </VCol>
          <VCol cols="12" sm="1">
            <VSwitch
              v-model="filtros.solo_pendientes"
              label="Solo pendientes"
              density="compact"
              hide-details
              color="warning"
              @update:modelValue="cargar"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Cards resumen -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Clientes con deuda</p>
            <p class="text-h5 font-weight-bold text-warning mb-0">{{ totales.total_clientes || 0 }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">
              Total facturado neto
              <VChip v-if="totales.total_ncs > 0" size="x-small" color="success" variant="tonal" class="ml-1">
                {{ totales.total_ncs }} NC
              </VChip>
            </p>
            <p class="text-h5 font-weight-bold mb-0">{{ formatMonto(totales.total_facturado || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Total cobrado</p>
            <p class="text-h5 font-weight-bold text-success mb-0">{{ formatMonto(totales.total_cobrado || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Por cobrar</p>
            <p class="text-h5 font-weight-bold text-warning mb-0">{{ formatMonto(totales.total_pendiente || 0) }}</p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla clientes -->
    <VCard>
      <VDataTable
        :headers="headers"
        :items="clientes"
        :loading="loading"
        item-value="_row_key"
        density="compact"
        v-model:page="tablePage"
        v-model:items-per-page="tablePageSize"
        :expanded="expanded"
        show-expand
        @update:expanded="onExpand"
      >
        <!-- Cliente -->
        <template #item.razon_social="{ item }">
          <div>
            <span class="font-weight-medium">{{ item.razon_social }}</span>
            <div class="text-caption text-medium-emphasis">{{ item.identification }}</div>
          </div>
        </template>

        <!-- N° Docs -->
        <template #item.cantidad_docs="{ item }">
          <div class="d-flex align-center" style="gap:4px; justify-content:center">
            <VChip size="x-small" variant="tonal" color="secondary">{{ item.cantidad_docs }}</VChip>
            <VChip v-if="item.cantidad_ncs > 0" size="x-small" variant="tonal" color="success">
              {{ item.cantidad_ncs }} NC
            </VChip>
            <VChip
              v-if="item.facturas_por_revisar > 0"
              size="x-small"
              color="warning"
              variant="flat"
              prepend-icon="mdi-alert"
            >{{ item.facturas_por_revisar }}</VChip>
          </div>
        </template>

        <!-- Total facturado -->
        <template #item.total_facturado="{ item }">
          {{ formatMonto(item.total_facturado) }}
        </template>

        <!-- Cobrado -->
        <template #item.total_cobrado="{ item }">
          <span class="text-success">{{ formatMonto(item.total_cobrado) }}</span>
        </template>

        <!-- Pendiente -->
        <template #item.total_pendiente="{ item }">
          <span class="font-weight-bold" :class="item.total_pendiente > 0 ? 'text-warning' : 'text-success'">
            {{ formatMonto(item.total_pendiente) }}
          </span>
        </template>

        <!-- Barra progreso cobro -->
        <template #item.progreso="{ item }">
          <div style="min-width: 100px">
            <VProgressLinear
              :model-value="item.total_facturado > 0 ? (item.total_cobrado / item.total_facturado) * 100 : 0"
              color="success"
              bg-color="warning"
              height="6"
              rounded
            />
            <div class="text-caption text-medium-emphasis text-center mt-1">
              {{ item.total_facturado > 0 ? Math.round((item.total_cobrado / item.total_facturado) * 100) : 0 }}%
            </div>
          </div>
        </template>

        <!-- Fila expandida: documentos del cliente -->
        <template #expanded-row="{ item }">
          <tr>
            <td :colspan="headers.length + 1" class="pa-0">
              <div class="pa-4 bg-surface">
                <p class="text-body-2 font-weight-medium mb-3">Documentos de {{ item.razon_social }}</p>

                <div v-if="loadingFacturas[item._row_key]" class="text-center py-4">
                  <VProgressCircular indeterminate size="24" />
                </div>

                <VTable v-else-if="facturasCliente[item._row_key]?.length" density="compact">
                  <thead>
                    <tr>
                      <th>N° Doc</th>
                      <th>Tipo</th>
                      <th>Cotización</th>
                      <th>Fecha</th>
                      <th class="text-end">Monto</th>
                      <th class="text-end">Cobrado</th>
                      <th class="text-end">Pendiente</th>
                      <th>Estado / NC</th>
                      <th></th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="f in facturasCliente[item._row_key]"
                      :key="f.id"
                      :class="{ 'nc-row': !!f.es_nc }"
                    >
                      <!-- N° Doc -->
                      <td class="font-weight-medium">
                        <span v-if="f.es_nc" class="text-success text-caption font-weight-bold">NC</span>
                        {{ f.numero_documento_bsale || '—' }}
                      </td>

                      <!-- Tipo -->
                      <td>
                        <VChip size="x-small" variant="tonal" :color="f.es_nc ? 'success' : 'info'">
                          {{ f.es_nc ? 'Nota Créd.' : f.tipo }}
                        </VChip>
                      </td>

                      <!-- Cotización -->
                      <td class="text-caption text-medium-emphasis">
                        {{ f.cotizacion_id ? '#' + f.cotizacion_id : '—' }}
                      </td>

                      <!-- Fecha -->
                      <td class="text-caption">{{ formatFecha(f.fecha_emision) }}</td>

                      <!-- Monto -->
                      <td class="text-end font-weight-medium" :class="f.es_nc ? 'text-success' : ''">
                        {{ f.es_nc ? '−' : '' }}{{ formatMonto(f.monto) }}
                      </td>

                      <!-- Cobrado -->
                      <td class="text-end text-success">{{ formatMonto(f.monto_cobrado) }}</td>

                      <!-- Pendiente -->
                      <td class="text-end font-weight-bold">
                        <span v-if="f.es_nc" class="text-success">
                          {{ formatMonto(Math.abs(f.pendiente)) }}
                        </span>
                        <span v-else :class="f.pendiente > 0 ? 'text-warning' : 'text-success'">
                          {{ formatMonto(f.pendiente) }}
                        </span>
                      </td>

                      <!-- Estado / NC -->
                      <td>
                        <!-- NC row: vincular / estado -->
                        <div v-if="f.es_nc" class="d-flex align-center flex-wrap" style="gap:4px">
                          <template v-if="f.nc_referencia_df_id">
                            <VChip size="x-small" color="success" variant="tonal">
                              <VIcon start size="11">mdi-link</VIcon>Vinculada
                            </VChip>
                            <VBtn
                              size="x-small"
                              variant="text"
                              color="error"
                              :loading="loadingDesvincular[f.id]"
                              @click="desvincularNC(f, item)"
                            >
                              <VIcon size="13">mdi-link-off</VIcon>
                            </VBtn>
                          </template>
                          <template v-else>
                            <VBtn
                              size="x-small"
                              variant="tonal"
                              color="success"
                              @click="abrirVincularNC(f, item)"
                            >
                              <VIcon start size="13">mdi-link-variant</VIcon>Vincular
                            </VBtn>
                          </template>
                        </div>

                        <!-- Factura normal: estado NC si existe -->
                        <div v-else class="d-flex align-center flex-wrap" style="gap:4px">
                          <VChip
                            v-if="f.nc_revision_estado"
                            size="x-small"
                            :color="ncRevisionColor(f.nc_revision_estado)"
                            variant="tonal"
                          >
                            <VIcon start size="11">{{ ncRevisionIcon(f.nc_revision_estado) }}</VIcon>
                            {{ ncRevisionLabel(f.nc_revision_estado) }}
                          </VChip>
                          <VMenu v-if="f.nc_revision_estado === 'requiere_revision'" location="bottom end">
                            <template #activator="{ props: mp }">
                              <VBtn v-bind="mp" size="x-small" icon variant="text" color="warning">
                                <VIcon size="13">mdi-dots-vertical</VIcon>
                              </VBtn>
                            </template>
                            <VList density="compact" min-width="200">
                              <VListItem @click="cambiarEstadoFactura(f, 'reembolso_pendiente', item)">
                                <VListItemTitle class="text-caption">Reembolso pendiente</VListItemTitle>
                              </VListItem>
                              <VListItem @click="cambiarEstadoFactura(f, 'ignorado', item)">
                                <VListItemTitle class="text-caption">Ignorar</VListItemTitle>
                              </VListItem>
                            </VList>
                          </VMenu>
                          <template v-else-if="!f.nc_revision_estado">
                            <VChip
                              size="x-small"
                              :color="f.pendiente <= 0 ? 'success' : 'warning'"
                              variant="tonal"
                              :style="f.pendiente <= 0 ? 'cursor:pointer' : ''"
                              @click="f.pendiente <= 0 ? abrirConciliar(f, item) : null"
                            >
                              <VIcon v-if="f.pendiente <= 0" start size="11">mdi-eye-outline</VIcon>
                              {{ f.pendiente <= 0 ? 'Cobrada' : 'Pendiente' }}
                            </VChip>
                            <VChip
                              v-if="f.monto_cobrado_manual > 0"
                              size="x-small"
                              color="secondary"
                              variant="tonal"
                              class="ml-1"
                              closable
                              :disabled="!!loadingMarcarCobrada[f.id]"
                              @click:close="desmarcarCobradoManual(f, item)"
                            >
                              <VIcon start size="11">mdi-cash</VIcon>Manual
                            </VChip>
                          </template>
                        </div>
                      </td>

                      <!-- Acciones -->
                      <td>
                        <div v-if="f.es_nc" class="d-flex align-center" style="gap:4px">
                          <!-- Aplicar NC (Escenario B) -->
                          <VTooltip location="bottom" text="Aplicar NC a factura pendiente (sin movimiento bancario)">
                            <template #activator="{ props: tp }">
                              <VBtn
                                v-bind="tp"
                                size="x-small"
                                variant="tonal"
                                color="info"
                                @click="abrirAplicarNC(f, item)"
                              >
                                <VIcon start size="13">mdi-file-document-edit</VIcon>Aplicar
                              </VBtn>
                            </template>
                          </VTooltip>
                        </div>
                        <div v-else class="d-flex align-center" style="gap:4px">
                          <!-- ⚡ Rayo: si hay movimiento de monto exacto sugerido -->
                          <VTooltip
                            v-if="sugerenciasPorVenta[f.id] && f.pendiente > 0"
                            location="bottom"
                            max-width="280"
                          >
                            <template #activator="{ props: tp }">
                              <VBtn
                                v-bind="tp"
                                size="x-small"
                                icon
                                variant="flat"
                                color="warning"
                                style="border-radius:4px;min-width:26px;height:26px"
                                :loading="loadingRayoVenta[f.id]"
                                @click="conciliarRayoVenta(f, item)"
                              >
                                <VIcon size="15">mdi-lightning-bolt</VIcon>
                              </VBtn>
                            </template>
                            <div style="font-size:11px;line-height:1.7">
                              <div class="font-weight-bold mb-1">⚡ Conciliar directamente</div>
                              <div class="text-medium-emphasis">Ingreso bancario:</div>
                              <div>{{ sugerenciasPorVenta[f.id].movimiento.descripcion }}</div>
                              <div class="font-weight-bold mt-1">{{ formatMonto(sugerenciasPorVenta[f.id].monto_sugerido) }}</div>
                            </div>
                          </VTooltip>
                          <VBtn
                            v-if="f.pendiente > 0"
                            size="x-small"
                            variant="tonal"
                            color="primary"
                            @click="abrirConciliar(f, item)"
                          >
                            <VIcon size="14" class="mr-1">mdi-link-variant</VIcon>Conciliar
                          </VBtn>
                          <VTooltip v-if="f.pendiente > 0" location="bottom" text="Marcar como cobrada (pago sin mov. bancario)">
                            <template #activator="{ props: tp }">
                              <VBtn
                                v-bind="tp"
                                size="x-small"
                                icon
                                variant="tonal"
                                color="secondary"
                                :loading="loadingMarcarCobrada[f.id]"
                                @click="abrirMarcarCobrada(f, item)"
                              >
                                <VIcon size="15">mdi-cash-check</VIcon>
                              </VBtn>
                            </template>
                          </VTooltip>
                          <VBtn
                            v-if="f.url_pdf_bsale"
                            size="x-small"
                            variant="text"
                            icon
                            :href="f.url_pdf_bsale"
                            target="_blank"
                          >
                            <VIcon size="14">mdi-file-pdf-box</VIcon>
                          </VBtn>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </VTable>

                <p v-else class="text-body-2 text-medium-emphasis">Sin documentos emitidos en este período.</p>
              </div>
            </td>
          </tr>
        </template>

        <template #bottom>
          <div class="d-flex align-center justify-space-between px-3 py-2">
            <span class="text-caption text-medium-emphasis">{{ clientes.length }} clientes</span>
            <div class="d-flex align-center gap-2">
              <VSelect
                v-model="tablePageSize"
                :items="[10, 25, 50, 100, { value: -1, title: 'Todos' }]"
                density="compact"
                variant="outlined"
                hide-details
                style="width:100px"
                @update:model-value="tablePage = 1"
              />
              <span class="text-caption text-medium-emphasis">
                Pág {{ tablePage }} de {{ tablePageSize === -1 ? 1 : Math.ceil(clientes.length / tablePageSize) }}
              </span>
              <VBtn icon size="x-small" variant="text" :disabled="tablePage <= 1" @click="tablePage--">
                <VIcon size="16">mdi-chevron-left</VIcon>
              </VBtn>
              <VBtn icon size="x-small" variant="text" :disabled="tablePage >= (tablePageSize === -1 ? 1 : Math.ceil(clientes.length / tablePageSize))" @click="tablePage++">
                <VIcon size="16">mdi-chevron-right</VIcon>
              </VBtn>
            </div>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- ── Modal Conciliar Factura de Venta ──────────────────────────────────── -->
    <VDialog v-model="dialogConciliar" max-width="1200" scrollable>
      <VCard v-if="facturaActiva">
        <VCardTitle class="d-flex align-center pa-4 pb-2">
          <span>{{ facturaActiva.pendiente <= 0 ? 'Detalle de cobros — Factura de Venta' : 'Conciliar Factura de Venta' }}</span>
          <VSpacer />
          <VBtn icon variant="text" @click="dialogConciliar = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>

        <VCardText class="pa-0">
          <VRow no-gutters style="min-height: 480px">

            <!-- Panel izquierdo: movimientos disponibles -->
            <VCol cols="12" md="8" class="border-e">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Ingresos Bancarios (Créditos)</p>

                <!-- Asignados ya -->
                <div v-if="asignados.length" class="mb-4">
                  <p class="text-caption text-medium-emphasis mb-2">Asignados a esta factura:</p>
                  <VTable density="compact">
                    <tbody>
                      <tr v-for="a in asignados" :key="a.pivot_id">
                        <td class="text-caption">{{ formatFecha(a.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                          {{ a.descripcion }}
                        </td>
                        <td class="text-end text-caption font-weight-bold text-success">{{ formatMonto(a.monto_asignado) }}</td>
                        <td>
                          <VBtn size="x-small" icon variant="text" color="error"
                            :loading="loadingDesasignar[a.pivot_id]"
                            @click="desasignar(a.pivot_id)">
                            <VIcon size="14">mdi-close</VIcon>
                          </VBtn>
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                  <VDivider class="my-3" />
                </div>

                <!-- Buscador -->
                <VTextField
                  v-model="buscarMov"
                  placeholder="Buscar por descripción del ingreso..."
                  density="compact"
                  variant="outlined"
                  prepend-inner-icon="mdi-magnify"
                  hide-details
                  class="mb-3"
                  clearable
                  @update:modelValue="cargarDisponibles"
                />

                <!-- Lista movimientos crédito disponibles -->
                <div v-if="loadingDisponibles" class="text-center py-6">
                  <VProgressCircular indeterminate size="28" />
                </div>
                <div v-else style="overflow-x: auto">
                  <VTable density="compact">
                    <thead>
                      <tr>
                        <th style="white-space:nowrap">Saldo disponible</th>
                        <th>Monto total</th>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="mov in disponibles" :key="mov.id">
                        <td class="font-weight-bold text-success">{{ formatMonto(mov.saldo_por_asignar) }}</td>
                        <td>{{ formatMonto(mov.monto) }}</td>
                        <td class="text-caption">{{ formatFecha(mov.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:320px; word-break:break-word">
                          {{ mov.descripcion }}
                          <span v-if="mov.glosa" class="text-medium-emphasis d-block">{{ mov.glosa }}</span>
                        </td>
                        <td>
                          <VBtn
                            size="x-small"
                            color="success"
                            variant="tonal"
                            :loading="loadingAsignar[mov.id]"
                            :disabled="saldoPorCobrar <= 0"
                            @click="asignar(mov)"
                          >Seleccionar</VBtn>
                        </td>
                      </tr>
                      <tr v-if="!disponibles.length">
                        <td colspan="5" class="text-center text-caption text-medium-emphasis py-4">
                          Sin ingresos bancarios disponibles
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </div>
              </div>
            </VCol>

            <!-- Panel derecho: documento -->
            <VCol cols="12" md="4">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Factura de venta</p>
                <VCard variant="outlined" class="pa-4">
                  <div class="d-flex align-center justify-space-between mb-2">
                    <span class="text-caption text-medium-emphasis">{{ formatFecha(facturaActiva.fecha_emision) }}</span>
                    <VChip size="x-small" color="info" variant="tonal">{{ facturaActiva.tipo }}</VChip>
                  </div>
                  <p class="font-weight-bold mb-0">{{ clienteActivo?.razon_social }}</p>
                  <p class="text-caption text-medium-emphasis mb-1">{{ clienteActivo?.identification }}</p>
                  <p v-if="facturaActiva.numero_documento_bsale" class="text-caption mb-3">
                    Doc. N° <strong>{{ facturaActiva.numero_documento_bsale }}</strong>
                    <span v-if="facturaActiva.cotizacion_id"> · Cot. #{{ facturaActiva.cotizacion_id }}</span>
                  </p>
                  <VDivider class="mb-3" />
                  <div class="d-flex justify-space-between">
                    <span class="text-body-2">Total factura</span>
                    <span class="font-weight-bold">{{ formatMonto(facturaActiva.monto) }}</span>
                  </div>
                  <div class="d-flex justify-space-between mt-1">
                    <span class="text-body-2 text-success">Cobrado (banco)</span>
                    <span class="text-success">{{ formatMonto(facturaActiva.monto - saldoPorCobrar - cobradoTransbank) }}</span>
                  </div>
                  <div v-if="cobradoTransbank > 0" class="d-flex justify-space-between mt-1">
                    <span class="text-body-2 text-info">Cobrado ({{ esTarjeta ? 'Tarjeta' : 'Chipax' }})</span>
                    <span class="text-info">{{ formatMonto(cobradoTransbank) }}</span>
                  </div>
                  <VDivider class="my-2" />
                  <div class="d-flex justify-space-between">
                    <span class="text-body-2 font-weight-bold" :class="saldoPorCobrar > 0 ? 'text-warning' : 'text-success'">
                      Por cobrar
                    </span>
                    <span class="font-weight-bold text-h6" :class="saldoPorCobrar > 0 ? 'text-warning' : 'text-success'">
                      {{ formatMonto(saldoPorCobrar) }}
                    </span>
                  </div>
                  <VProgressLinear
                    :model-value="facturaActiva.monto > 0 ? ((facturaActiva.monto - saldoPorCobrar) / facturaActiva.monto) * 100 : 0"
                    color="success"
                    bg-color="warning"
                    height="8"
                    rounded
                    class="mt-3"
                  />
                  <VChip v-if="saldoPorCobrar <= 0" color="success" variant="tonal" class="mt-3 w-100" style="justify-content:center">
                    <VIcon start size="14">mdi-check-circle</VIcon> Factura completamente cobrada
                  </VChip>
                </VCard>
              </div>
            </VCol>

          </VRow>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- ── Modal Vincular NC a Factura ───────────────────────────────────────── -->
    <VDialog v-model="dialogVincular" max-width="700" scrollable>
      <VCard v-if="ncActivo">
        <VCardTitle class="d-flex align-center pa-4 pb-2">
          <VIcon start color="success">mdi-link-variant</VIcon>
          Vincular NC a Factura
          <VSpacer />
          <VBtn icon variant="text" @click="dialogVincular = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>

        <VCardText>
          <!-- Info NC -->
          <VAlert type="info" variant="tonal" density="compact" class="mb-4">
            <div class="text-caption">
              <strong>Nota de Crédito N° {{ ncActivo.numero_documento_bsale }}</strong>
              · {{ formatFecha(ncActivo.fecha_emision) }}
              · Monto: <strong>{{ formatMonto(ncActivo.monto) }}</strong>
            </div>
          </VAlert>

          <p class="text-body-2 text-medium-emphasis mb-3">
            Selecciona la factura de venta que esta nota de crédito anula o modifica:
          </p>

          <!-- Buscador -->
          <VTextField
            v-model="buscarVincular"
            placeholder="Buscar por N° documento..."
            density="compact"
            variant="outlined"
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
            class="mb-3"
          />

          <div v-if="loadingFacturasVincular" class="text-center py-6">
            <VProgressCircular indeterminate size="28" />
          </div>
          <VTable v-else density="compact" style="max-height:380px; overflow-y:auto">
            <thead>
              <tr>
                <th>N° Doc</th>
                <th>Fecha</th>
                <th class="text-end">Monto</th>
                <th class="text-end">Pendiente</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="fac in facturasParaVincularFiltradas"
                :key="fac.id"
                :class="{ 'bg-success-subtle': facturaSeleccionadaVincular === fac.id }"
                style="cursor:pointer"
                @click="facturaSeleccionadaVincular = fac.id"
              >
                <td class="font-weight-medium">{{ fac.numero_documento_bsale || '—' }}</td>
                <td class="text-caption">{{ formatFecha(fac.fecha_emision) }}</td>
                <td class="text-end">{{ formatMonto(fac.monto) }}</td>
                <td class="text-end" :class="fac.pendiente > 0 ? 'text-warning font-weight-bold' : 'text-success'">
                  {{ formatMonto(fac.pendiente) }}
                </td>
                <td>
                  <VIcon v-if="facturaSeleccionadaVincular === fac.id" color="success" size="16">mdi-check-circle</VIcon>
                </td>
              </tr>
              <tr v-if="!facturasParaVincularFiltradas.length">
                <td colspan="5" class="text-center text-caption text-medium-emphasis py-4">
                  Sin facturas disponibles
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn variant="text" @click="dialogVincular = false">Cancelar</VBtn>
          <VBtn
            color="success"
            variant="flat"
            :disabled="!facturaSeleccionadaVincular"
            :loading="loadingVincular"
            @click="vincularNC"
          >
            <VIcon start size="16">mdi-link-variant</VIcon>Vincular
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Modal Aplicar NC a Factura (Escenario B) ──────────────────────────── -->
    <VDialog v-model="dialogAplicar" max-width="700" scrollable>
      <VCard v-if="ncActivo">
        <VCardTitle class="d-flex align-center pa-4 pb-2">
          <VIcon start color="info">mdi-file-document-edit</VIcon>
          Aplicar NC a Factura
          <VSpacer />
          <VBtn icon variant="text" @click="dialogAplicar = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>

        <VCardText>
          <!-- Info NC -->
          <VAlert type="info" variant="tonal" density="compact" class="mb-3">
            <div class="text-caption">
              <strong>NC N° {{ ncActivo.numero_documento_bsale }}</strong>
              · Saldo disponible: <strong>{{ formatMonto(Math.abs(ncActivo.pendiente)) }}</strong>
            </div>
          </VAlert>
          <VAlert type="info" variant="tonal" density="compact" class="mb-4" icon="mdi-information">
            <div class="text-caption">
              Escenario B: el proveedor aplicó el crédito directamente a una factura futura,
              sin transferencia bancaria.
            </div>
          </VAlert>

          <!-- Selección de factura destino -->
          <p class="text-body-2 font-weight-medium mb-2">Factura donde se aplica la NC:</p>
          <div v-if="loadingFacturasVincular" class="text-center py-4">
            <VProgressCircular indeterminate size="24" />
          </div>
          <VTable v-else density="compact" class="mb-4" style="max-height:260px; overflow-y:auto">
            <thead>
              <tr>
                <th>N° Doc</th>
                <th>Fecha</th>
                <th class="text-end">Pendiente</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="fac in facturasParaAplicar"
                :key="fac.id"
                :class="{ 'bg-info-subtle': facturaSeleccionadaAplicar === fac.id }"
                style="cursor:pointer"
                @click="facturaSeleccionadaAplicar = fac.id; montoAplicar = Math.min(Math.abs(ncActivo.pendiente), fac.pendiente)"
              >
                <td class="font-weight-medium">{{ fac.numero_documento_bsale || '—' }}</td>
                <td class="text-caption">{{ formatFecha(fac.fecha_emision) }}</td>
                <td class="text-end text-warning font-weight-bold">{{ formatMonto(fac.pendiente) }}</td>
                <td>
                  <VIcon v-if="facturaSeleccionadaAplicar === fac.id" color="info" size="16">mdi-check-circle</VIcon>
                </td>
              </tr>
              <tr v-if="!facturasParaAplicar.length">
                <td colspan="4" class="text-center text-caption text-medium-emphasis py-3">
                  Sin facturas pendientes para aplicar
                </td>
              </tr>
            </tbody>
          </VTable>

          <VRow dense>
            <VCol cols="6">
              <VTextField
                v-model.number="montoAplicar"
                label="Monto a aplicar"
                type="number"
                :max="Math.abs(ncActivo?.pendiente || 0)"
                min="1"
                density="compact"
                variant="outlined"
                hide-details
                prefix="$"
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model="fechaAplicar"
                label="Fecha de aplicación"
                type="date"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>
            <VCol cols="12" class="mt-2">
              <VTextField
                v-model="notaAplicar"
                label="Nota (opcional)"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Ej: NC aplicada según acuerdo con cliente"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn variant="text" @click="dialogAplicar = false">Cancelar</VBtn>
          <VBtn
            color="info"
            variant="flat"
            :disabled="!facturaSeleccionadaAplicar || !montoAplicar || montoAplicar <= 0"
            :loading="loadingAplicar"
            @click="aplicarNC"
          >
            <VIcon start size="16">mdi-check</VIcon>Aplicar NC
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Modal Por Revisar ─────────────────────────────────────────────────── -->
    <VDialog v-model="dialogPorRevisar" max-width="900" scrollable>
      <VCard>
        <VCardTitle class="d-flex align-center pa-4 pb-2">
          <VIcon start color="warning">mdi-alert-circle</VIcon>
          Facturas de venta por revisar
          <VChip v-if="porRevisarList.length" size="small" color="warning" class="ml-2">{{ porRevisarList.length }}</VChip>
          <VSpacer />
          <VBtn icon variant="text" @click="dialogPorRevisar = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>

        <VCardText>
          <VAlert type="warning" variant="tonal" density="compact" class="mb-4" icon="mdi-information">
            <div class="text-caption">
              Estas facturas tienen una Nota de Crédito asociada emitida después de haber sido cobradas.
              Revisa si corresponde un reembolso al cliente o si la NC se aplicará a una futura factura.
            </div>
          </VAlert>

          <div v-if="loadingPorRevisar" class="text-center py-6">
            <VProgressCircular indeterminate size="32" />
          </div>
          <VTable v-else density="compact">
            <thead>
              <tr>
                <th>Factura</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th class="text-end">Monto</th>
                <th class="text-end">Cobrado</th>
                <th>NC asociada</th>
                <th>Acción</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="fac in porRevisarList" :key="fac.id">
                <td class="font-weight-medium">N° {{ fac.numero_documento_bsale }}</td>
                <td>
                  <div class="text-caption font-weight-medium">{{ fac.cliente_nombre }}</div>
                  <div class="text-caption text-medium-emphasis">{{ fac.cliente_rut }}</div>
                </td>
                <td class="text-caption">{{ formatFecha(fac.fecha_emision) }}</td>
                <td class="text-end">{{ formatMonto(fac.monto) }}</td>
                <td class="text-end text-success">{{ formatMonto(fac.monto_cobrado) }}</td>
                <td>
                  <div v-if="fac.nc_id" class="text-caption">
                    <span class="font-weight-medium text-success">NC N° {{ fac.nc_numero }}</span>
                    <span class="text-medium-emphasis d-block">{{ formatMonto(fac.nc_monto) }} · {{ formatFecha(fac.nc_fecha) }}</span>
                  </div>
                  <span v-else class="text-caption text-medium-emphasis">Sin NC vinculada</span>
                </td>
                <td>
                  <div class="d-flex" style="gap:4px">
                    <VBtn
                      size="x-small"
                      variant="tonal"
                      color="info"
                      :loading="loadingEstado[fac.id]"
                      @click="cambiarEstadoDesdeModal(fac, 'reembolso_pendiente')"
                    >Reembolso</VBtn>
                    <VBtn
                      size="x-small"
                      variant="tonal"
                      color="secondary"
                      :loading="loadingEstado[fac.id]"
                      @click="cambiarEstadoDesdeModal(fac, 'ignorado')"
                    >Ignorar</VBtn>
                  </div>
                </td>
              </tr>
              <tr v-if="!porRevisarList.length">
                <td colspan="7" class="text-center text-caption text-medium-emphasis py-6">
                  <VIcon size="32" color="success" class="mb-2 d-block">mdi-check-circle</VIcon>
                  Sin facturas pendientes de revisión
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn variant="text" @click="dialogPorRevisar = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Modal Marcar cobrada manualmente ──────────────────────────────────── -->
    <VDialog v-model="dialogMarcarCobrada" max-width="460">
      <VCard v-if="facturaParaMarcar">
        <VCardTitle class="d-flex align-center pa-4 pb-2">
          <VIcon start color="secondary">mdi-cash-check</VIcon>
          Registrar cobro manual
          <VSpacer />
          <VBtn icon variant="text" @click="dialogMarcarCobrada = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>

        <VCardText>
          <VAlert type="info" variant="tonal" density="compact" class="mb-4" icon="mdi-information">
            <div class="text-caption">
              Usa esta opción para registrar cobros realizados fuera del banco (Transbank, efectivo, etc.)
              que no aparecen en los movimientos bancarios.
            </div>
          </VAlert>

          <div class="text-caption text-medium-emphasis mb-1">Factura N° {{ facturaParaMarcar.numero_documento_bsale }}</div>
          <div class="text-body-2 mb-4">
            Total: <strong>{{ formatMonto(facturaParaMarcar.monto) }}</strong>
            · Pendiente: <strong class="text-warning">{{ formatMonto(facturaParaMarcar.pendiente) }}</strong>
          </div>

          <VTextField
            v-model.number="montoMarcar"
            label="Monto a registrar"
            type="number"
            :min="1"
            :max="facturaParaMarcar.monto"
            density="compact"
            variant="outlined"
            prefix="$"
            class="mb-3"
            hide-details
          />
          <VTextField
            v-model="notaMarcar"
            label="Nota (opcional)"
            density="compact"
            variant="outlined"
            hide-details
            placeholder="Ej: Pago via Transbank nov 2024"
          />
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn variant="text" @click="dialogMarcarCobrada = false">Cancelar</VBtn>
          <VBtn
            color="secondary"
            variant="flat"
            :disabled="!montoMarcar || montoMarcar <= 0"
            :loading="!!loadingMarcarCobrada[facturaParaMarcar?.id]"
            @click="confirmarMarcarCobrada"
          >
            <VIcon start size="16">mdi-check</VIcon>Registrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/axiosInstance'

// ── Estado principal ──────────────────────────────────────────────────────────
const loading        = ref(false)
const sincronizando  = ref(false)
const syncSnack      = ref({ show: false, text: '', color: 'success' })
const snackNc        = ref({ show: false, text: '', color: 'success' })
const clientes       = ref([])
const totales        = ref({})
const expanded       = ref([])
const tablePage      = ref(1)
const tablePageSize  = ref(25)
const facturasCliente  = ref({})
const loadingFacturas  = ref({})

// ── Sugerencias / Rayo ⚡ ─────────────────────────────────────────────────────
const sugerenciasPorVenta = ref({})
const loadingRayoVenta    = ref({})

// ── Dialog Conciliar ──────────────────────────────────────────────────────────
const dialogConciliar   = ref(false)
const facturaActiva     = ref(null)
const clienteActivo     = ref(null)
const asignados         = ref([])
const disponibles       = ref([])
const saldoPorCobrar    = ref(0)
const cobradoTransbank  = ref(0)
const esTarjeta         = ref(false)
const buscarMov         = ref('')
const loadingDisponibles = ref(false)
const loadingAsignar    = ref({})
const loadingDesasignar = ref({})

// ── Dialog Vincular NC ────────────────────────────────────────────────────────
const dialogVincular              = ref(false)
const ncActivo                    = ref(null)
const clienteActivoNc             = ref(null)
const facturasParaVincular        = ref([]) // todas las facturas no-NC del cliente
const facturasParaAplicar         = ref([]) // facturas pendientes para Escenario B
const facturaSeleccionadaVincular = ref(null)
const buscarVincular              = ref('')
const loadingVincular             = ref(false)
const loadingDesvincular          = ref({})
const loadingFacturasVincular     = ref(false)

// ── Dialog Aplicar NC ─────────────────────────────────────────────────────────
const dialogAplicar              = ref(false)
const facturaSeleccionadaAplicar = ref(null)
const montoAplicar               = ref(null)
const fechaAplicar               = ref(new Date().toISOString().slice(0, 10))
const notaAplicar                = ref('')
const loadingAplicar             = ref(false)

// ── Dialog Cobro Manual ───────────────────────────────────────────────────────
const dialogMarcarCobrada  = ref(false)
const facturaParaMarcar    = ref(null)
const clienteParaMarcar    = ref(null)
const montoMarcar          = ref(0)
const notaMarcar           = ref('')
const loadingMarcarCobrada = ref({})

// ── Dialog Por Revisar ────────────────────────────────────────────────────────
const dialogPorRevisar  = ref(false)
const porRevisarList    = ref([])
const loadingPorRevisar = ref(false)
const loadingEstado     = ref({})

// ── Filtros ───────────────────────────────────────────────────────────────────
const hoy       = new Date().toISOString().slice(0, 10)
const haceUnAño = new Date(new Date().getFullYear() - 1, 0, 1).toISOString().slice(0, 10)
const inicioAño = new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10)

const filtros = ref({
  desde:           inicioAño,
  hasta:           hoy,
  buscar:          '',
  monto:           '',
  solo_pendientes: true,
})

// ── Headers tabla ─────────────────────────────────────────────────────────────
const headers = [
  { title: 'Cliente',          key: 'razon_social',    sortable: true },
  { title: 'Docs',             key: 'cantidad_docs',   align: 'center', sortable: true },
  { title: 'Total facturado',  key: 'total_facturado', align: 'end',    sortable: true },
  { title: 'Cobrado',          key: 'total_cobrado',   align: 'end',    sortable: true },
  { title: 'Por cobrar',       key: 'total_pendiente', align: 'end',    sortable: true },
  { title: 'Avance',           key: 'progreso',        align: 'center', sortable: false },
]

// ── Computed ──────────────────────────────────────────────────────────────────
const facturasParaVincularFiltradas = computed(() => {
  if (!buscarVincular.value) return facturasParaVincular.value
  const q = buscarVincular.value.toLowerCase()
  return facturasParaVincular.value.filter(f =>
    (f.numero_documento_bsale || '').includes(q) ||
    (f.tipo || '').toLowerCase().includes(q)
  )
})

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatMonto(v) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v || 0)
}

function formatFecha(f) {
  if (!f) return '—'
  return new Date(f + 'T12:00:00').toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function ncRevisionColor(estado) {
  const map = { requiere_revision: 'warning', reembolso_pendiente: 'info', aplicado: 'success', ignorado: 'secondary' }
  return map[estado] || 'default'
}

function ncRevisionLabel(estado) {
  const map = { requiere_revision: 'Por revisar', reembolso_pendiente: 'Reembolso pend.', aplicado: 'NC aplicada', ignorado: 'Ignorada' }
  return map[estado] || estado
}

function ncRevisionIcon(estado) {
  const map = { requiere_revision: 'mdi-alert', reembolso_pendiente: 'mdi-bank-transfer', aplicado: 'mdi-check', ignorado: 'mdi-minus-circle' }
  return map[estado] || 'mdi-help'
}

// ── Carga principal ───────────────────────────────────────────────────────────
async function cargar() {
  loading.value = true
  try {
    const params = {
      desde:           filtros.value.desde,
      hasta:           filtros.value.hasta,
      buscar:          filtros.value.buscar || undefined,
      monto:           filtros.value.monto   || undefined,
      solo_pendientes: filtros.value.solo_pendientes,
    }
    const { data } = await axios.get('/api/cuentas-por-cobrar', { params })
    // _row_key: clave única por fila (cliente_id cuando existe, RUT/nombre si es null)
    clientes.value = data.clientes.map(c => ({
      ...c,
      _row_key: c.cliente_id != null ? String(c.cliente_id) : (c.identification ?? c.razon_social ?? `_${Math.random()}`),
    }))
    totales.value  = data.totales
    facturasCliente.value = {}
    expanded.value = []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function cargarFacturasCliente(rowKey, forzar = false) {
  if (facturasCliente.value[rowKey] && !forzar) return
  loadingFacturas.value[rowKey] = true
  try {
    const params = { desde: filtros.value.desde, hasta: filtros.value.hasta }
    const { data } = await axios.get(`/api/cuentas-por-cobrar/${encodeURIComponent(rowKey)}/facturas`, { params })
    facturasCliente.value[rowKey] = data
  } catch (e) {
    console.error(e)
  } finally {
    loadingFacturas.value[rowKey] = false
  }
}

function onExpand(newExpanded) {
  expanded.value = newExpanded
  newExpanded.forEach(rowKey => cargarFacturasCliente(rowKey))
}

async function refrescarCliente(rowKey) {
  delete facturasCliente.value[rowKey]
  await cargarFacturasCliente(rowKey)
  await cargar()
}

// ── Conciliar (banco) ─────────────────────────────────────────────────────────
async function abrirConciliar(factura, cliente) {
  facturaActiva.value  = factura
  clienteActivo.value  = cliente
  buscarMov.value      = ''
  asignados.value      = []
  disponibles.value    = []
  dialogConciliar.value = true
  await cargarEstadoConciliar()
}

async function cargarEstadoConciliar() {
  if (!facturaActiva.value) return
  try {
    let data
    if (facturaActiva.value.es_boleta_resumen) {
      const res = await axios.get(`/api/boletas/resumenes/${facturaActiva.value.boleta_resumen_id}/estado`)
      data = res.data
      cobradoTransbank.value = data.cobrado_transbank ?? 0
    } else {
      const res = await axios.get(`/api/ventas/${facturaActiva.value.id}/movimientos`)
      data = res.data
      cobradoTransbank.value = (data.cobrado_transbank ?? 0) + (data.cobrado_chipax ?? 0)
    }
    asignados.value      = data.asignados
    esTarjeta.value      = false
    saldoPorCobrar.value = data.saldo_por_cobrar
  } catch (e) { console.error(e) }
  await cargarDisponibles()
}

async function cargarDisponibles() {
  if (!facturaActiva.value) return
  loadingDisponibles.value = true
  try {
    const url = facturaActiva.value.es_boleta_resumen
      ? `/api/boletas/resumenes/${facturaActiva.value.boleta_resumen_id}/movimientos-disponibles`
      : `/api/ventas/${facturaActiva.value.id}/movimientos-disponibles`
    const { data } = await axios.get(url, { params: { buscar: buscarMov.value || undefined } })
    disponibles.value = data.data ?? data
  } catch (e) { console.error(e) }
  finally { loadingDisponibles.value = false }
}

async function asignar(mov) {
  loadingAsignar.value[mov.id] = true
  try {
    const monto = Math.min(mov.saldo_por_asignar, saldoPorCobrar.value)
    if (facturaActiva.value.es_boleta_resumen) {
      await axios.post(`/api/boletas/resumenes/${facturaActiva.value.boleta_resumen_id}/conciliar`, {
        movimiento_id: mov.id,
        monto,
      })
    } else {
      await axios.post(`/api/ventas/${facturaActiva.value.id}/movimientos`, {
        movimiento_id: mov.id,
        monto,
      })
    }
    await cargarEstadoConciliar()
    await refrescarCliente(clienteActivo.value._row_key)
  } catch (e) {
    console.error(e)
  } finally {
    loadingAsignar.value[mov.id] = false
  }
}

async function desasignar(pivotId) {
  loadingDesasignar.value[pivotId] = true
  try {
    if (facturaActiva.value.es_boleta_resumen) {
      await axios.delete(`/api/boletas/resumenes/movimiento/${pivotId}`)
    } else {
      await axios.delete(`/api/ventas/${facturaActiva.value.id}/movimientos/${pivotId}`)
    }
    await cargarEstadoConciliar()
    await refrescarCliente(clienteActivo.value._row_key)
  } catch (e) {
    console.error(e)
  } finally {
    delete loadingDesasignar.value[pivotId]
  }
}

// ── NC: Vincular ──────────────────────────────────────────────────────────────
async function abrirVincularNC(nc, cliente) {
  ncActivo.value                    = nc
  clienteActivoNc.value             = cliente
  facturaSeleccionadaVincular.value = null
  buscarVincular.value              = ''
  facturasParaVincular.value        = []
  dialogVincular.value              = true
  loadingFacturasVincular.value     = true
  try {
    const { data } = await axios.get(`/api/cuentas-por-cobrar/${encodeURIComponent(cliente._row_key)}/facturas`)
    // Solo facturas normales (no NCs) para poder vincular
    facturasParaVincular.value = data.filter(f => !f.es_nc)
  } catch (e) {
    console.error(e)
  } finally {
    loadingFacturasVincular.value = false
  }
}

async function vincularNC() {
  if (!facturaSeleccionadaVincular.value) return
  loadingVincular.value = true
  try {
    await axios.post(`/api/nc/venta/${ncActivo.value.id}/vincular`, {
      factura_id: facturaSeleccionadaVincular.value,
    })
    snackNc.value = { show: true, color: 'success', text: 'NC vinculada correctamente' }
    dialogVincular.value = false
    await refrescarCliente(clienteActivoNc.value._row_key)
  } catch (e) {
    const msg = e.response?.data?.message || 'Error al vincular NC'
    snackNc.value = { show: true, color: 'error', text: msg }
  } finally {
    loadingVincular.value = false
  }
}

async function desvincularNC(nc, cliente) {
  loadingDesvincular.value[nc.id] = true
  try {
    await axios.delete(`/api/nc/venta/${nc.id}/vincular`)
    snackNc.value = { show: true, color: 'info', text: 'Vínculo NC eliminado' }
    await refrescarCliente(cliente._row_key)
  } catch (e) {
    snackNc.value = { show: true, color: 'error', text: 'Error al desvincular NC' }
  } finally {
    delete loadingDesvincular.value[nc.id]
  }
}

// ── NC: Aplicar (Escenario B) ─────────────────────────────────────────────────
async function abrirAplicarNC(nc, cliente) {
  ncActivo.value                   = nc
  clienteActivoNc.value            = cliente
  facturaSeleccionadaAplicar.value = null
  montoAplicar.value               = null
  fechaAplicar.value               = new Date().toISOString().slice(0, 10)
  notaAplicar.value                = ''
  facturasParaAplicar.value        = []
  dialogAplicar.value              = true
  loadingFacturasVincular.value    = true
  try {
    const { data } = await axios.get(`/api/cuentas-por-cobrar/${encodeURIComponent(cliente._row_key)}/facturas`)
    // Solo facturas normales con saldo pendiente
    facturasParaAplicar.value = data.filter(f => !f.es_nc && f.pendiente > 0)
  } catch (e) {
    console.error(e)
  } finally {
    loadingFacturasVincular.value = false
  }
}

async function aplicarNC() {
  if (!facturaSeleccionadaAplicar.value || !montoAplicar.value) return
  loadingAplicar.value = true
  try {
    await axios.post(`/api/nc/venta/${ncActivo.value.id}/aplicar`, {
      factura_id: facturaSeleccionadaAplicar.value,
      monto:      montoAplicar.value,
      fecha:      fechaAplicar.value,
      nota:       notaAplicar.value || null,
    })
    snackNc.value = { show: true, color: 'success', text: 'NC aplicada correctamente' }
    dialogAplicar.value = false
    await refrescarCliente(clienteActivoNc.value._row_key)
  } catch (e) {
    const msg = e.response?.data?.message || 'Error al aplicar NC'
    snackNc.value = { show: true, color: 'error', text: msg }
  } finally {
    loadingAplicar.value = false
  }
}

// ── NC: Cambiar estado factura ────────────────────────────────────────────────
async function cambiarEstadoFactura(factura, estado, cliente) {
  loadingEstado.value[factura.id] = true
  try {
    await axios.patch(`/api/nc/venta/factura/${factura.id}/estado`, { estado })
    const rowKey = cliente?._row_key ?? clienteActivoNc.value?._row_key
    if (rowKey) await refrescarCliente(rowKey)
    else await cargar()
  } catch (e) {
    console.error(e)
  } finally {
    delete loadingEstado.value[factura.id]
  }
}

async function cambiarEstadoDesdeModal(fac, estado) {
  loadingEstado.value[fac.id] = true
  try {
    await axios.patch(`/api/nc/venta/factura/${fac.id}/estado`, { estado })
    // Quitar de la lista local
    porRevisarList.value = porRevisarList.value.filter(f => f.id !== fac.id)
    await cargar()
    snackNc.value = { show: true, color: 'success', text: 'Estado actualizado' }
  } catch (e) {
    snackNc.value = { show: true, color: 'error', text: 'Error al cambiar estado' }
  } finally {
    delete loadingEstado.value[fac.id]
  }
}

// ── Cobro Manual ──────────────────────────────────────────────────────────────
function abrirMarcarCobrada(f, cliente) {
  facturaParaMarcar.value = f
  clienteParaMarcar.value = cliente
  montoMarcar.value       = f.pendiente > 0 ? Math.round(f.pendiente) : Math.round(f.monto)
  notaMarcar.value        = ''
  dialogMarcarCobrada.value = true
}

async function confirmarMarcarCobrada() {
  const f = facturaParaMarcar.value
  if (!f) return
  loadingMarcarCobrada.value[f.id] = true
  try {
    await axios.put(`/api/cuentas-cobrar/${f.id}/cobro-manual`, {
      monto: montoMarcar.value,
      nota:  notaMarcar.value || 'Cobro registrado manualmente',
    })
    dialogMarcarCobrada.value = false
    await refrescarCliente(clienteParaMarcar.value._row_key)
  } catch (e) {
    console.error(e)
  } finally {
    delete loadingMarcarCobrada.value[f.id]
  }
}

async function desmarcarCobradoManual(f, cliente) {
  loadingMarcarCobrada.value[f.id] = true
  try {
    await axios.delete(`/api/cuentas-cobrar/${f.id}/cobro-manual`)
    await refrescarCliente(cliente._row_key)
  } catch (e) {
    console.error(e)
  } finally {
    delete loadingMarcarCobrada.value[f.id]
  }
}

// ── Por Revisar ───────────────────────────────────────────────────────────────
async function abrirPorRevisar() {
  dialogPorRevisar.value  = true
  loadingPorRevisar.value = true
  try {
    const { data } = await axios.get('/api/cuentas-por-cobrar/por-revisar')
    porRevisarList.value = data.facturas
  } catch (e) {
    console.error(e)
  } finally {
    loadingPorRevisar.value = false
  }
}

// ── Sincronizar Bsale ─────────────────────────────────────────────────────────
async function sincronizarDesideBsale() {
  sincronizando.value = true
  try {
    const anioActual = new Date().getFullYear()
    const { data } = await axios.post('/api/ventas/sincronizar', {
      años: [anioActual - 1, anioActual],
    })
    syncSnack.value = {
      show: true,
      color: 'success',
      text: `Sync completado: ${data.nuevos} nuevos, ${data.omitidos} ya existían${data.errores ? `, ${data.errores} errores` : ''}`,
    }
    await cargar()
  } catch (e) {
    syncSnack.value = { show: true, color: 'error', text: 'Error al sincronizar con Bsale' }
  } finally {
    sincronizando.value = false
  }
}

// ── Sugerencias automáticas (Rayo ⚡) ─────────────────────────────────────────
async function cargarSugerencias() {
  try {
    const { data } = await axios.get('/api/conciliacion/sugerencias')
    const mapa = {}
    for (const s of data) {
      if (s.monto_exacto && s.tipo_documento === 'venta') {
        mapa[s.documento.id] = s
      }
    }
    sugerenciasPorVenta.value = mapa
  } catch (e) {
    console.error('sugerencias CxC:', e)
  }
}

async function conciliarRayoVenta(factura, cliente) {
  const sug = sugerenciasPorVenta.value[factura.id]
  if (!sug || loadingRayoVenta.value[factura.id]) return
  loadingRayoVenta.value[factura.id] = true
  try {
    await axios.post(`/api/ventas/${factura.id}/movimientos`, {
      movimiento_id: sug.movimiento.id,
      monto: sug.monto_sugerido,
    })
    delete sugerenciasPorVenta.value[factura.id]
    await refrescarCliente(cliente?._row_key ?? Object.keys(facturasCliente.value).find(key =>
      facturasCliente.value[key]?.some(f => f.id === factura.id)
    ))
  } catch (e) {
    console.error('conciliar rayo venta:', e)
  } finally {
    delete loadingRayoVenta.value[factura.id]
  }
}

onMounted(async () => {
  await cargar()
  cargarSugerencias()
})
</script>

<style scoped>
.nc-row {
  background: rgba(var(--v-theme-success), 0.04);
  border-left: 3px solid rgb(var(--v-theme-success));
}

.bg-success-subtle {
  background: rgba(var(--v-theme-success), 0.08) !important;
}

.bg-info-subtle {
  background: rgba(var(--v-theme-info), 0.08) !important;
}
</style>
