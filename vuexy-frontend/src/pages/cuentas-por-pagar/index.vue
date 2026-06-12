<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Cuentas por Pagar</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Deuda neta con proveedores · facturas de compra menos notas de crédito</p>
      </VCol>
      <VCol cols="auto" class="d-flex align-center gap-2">
        <!-- Reglas de categorización -->
        <VBtn
          color="info"
          variant="tonal"
          size="small"
          @click="abrirReglas"
        >
          <VIcon start size="16">mdi-tag-multiple</VIcon>
          Reglas
        </VBtn>
        <!-- Vincular NCs pendientes -->
        <VBtn
          color="secondary"
          variant="tonal"
          size="small"
          :loading="loadingVincularNcs"
          @click="vincularNcsPendientes"
        >
          <VIcon start size="16">mdi-link-variant</VIcon>
          Vincular NCs
        </VBtn>
        <!-- Alerta Por Revisar -->
        <VBtn
          v-if="totales.facturas_por_revisar > 0"
          color="warning"
          variant="tonal"
          size="small"
          @click="abrirPorRevisar"
        >
          <VIcon start size="16">mdi-alert-circle</VIcon>
          {{ totales.facturas_por_revisar }} por revisar
        </VBtn>
      </VCol>
    </VRow>

    <!-- Filtros -->
    <VCard class="mb-4">
      <VCardText>
        <VRow dense align="center">
          <VCol cols="12" sm="3">
            <VTextField v-model="filtros.desde" label="Desde" type="date" density="compact" variant="outlined" hide-details @update:modelValue="cargar" />
          </VCol>
          <VCol cols="12" sm="3">
            <VTextField v-model="filtros.hasta" label="Hasta" type="date" density="compact" variant="outlined" hide-details @update:modelValue="cargar" />
          </VCol>
          <VCol cols="12" sm="3">
            <VTextField v-model="filtros.buscar" label="Buscar proveedor" density="compact" variant="outlined" hide-details prepend-inner-icon="mdi-magnify" clearable @update:modelValue="cargar" />
          </VCol>
          <VCol cols="12" sm="2">
            <VTextField v-model="filtros.monto" label="Monto exacto" density="compact" variant="outlined" hide-details prepend-inner-icon="mdi-currency-usd" clearable @update:modelValue="cargar" />
          </VCol>
          <VCol cols="12" sm="1">
            <VSwitch v-model="filtros.solo_pendientes" label="Solo pendientes" density="compact" hide-details color="warning" @update:modelValue="cargar" />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Cards resumen -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Proveedores con deuda</p>
            <p class="text-h5 font-weight-bold text-warning mb-0">{{ totales.total_proveedores || 0 }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Total neto (facturas − NCs)</p>
            <p class="text-h5 font-weight-bold mb-0">{{ fmt(totales.total_monto) }}</p>
            <p v-if="totales.total_ncs > 0" class="text-caption text-success mb-0">
              {{ totales.total_ncs }} NC{{ totales.total_ncs > 1 ? 's' : '' }} incluidas
            </p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Total pagado</p>
            <p class="text-h5 font-weight-bold text-success mb-0">{{ fmt(totales.total_pagado) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Total pendiente</p>
            <p class="text-h5 font-weight-bold text-error mb-0">{{ fmt(totales.total_pendiente) }}</p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla proveedores -->
    <VCard>
      <VDataTable
        :headers="headers"
        :items="proveedores"
        :loading="loading"
        item-value="rut_emisor"
        density="compact"
        :expanded="expanded"
        show-expand
        @update:expanded="onExpand"
      >
        <template #item.nombre_emisor="{ item }">
          <div class="d-flex align-center gap-2">
            <div>
              <span class="font-weight-medium">{{ item.nombre_emisor }}</span>
              <div class="text-caption text-medium-emphasis">{{ item.rut_emisor }}</div>
            </div>
            <!-- Badge "Por revisar" por proveedor -->
            <VChip v-if="item.facturas_por_revisar > 0" size="x-small" color="warning" variant="flat">
              <VIcon start size="10">mdi-alert</VIcon>{{ item.facturas_por_revisar }}
            </VChip>
          </div>
        </template>

        <template #item.cantidad_docs="{ item }">
          <div class="d-flex align-center gap-1">
            <VChip size="x-small" variant="tonal" color="secondary">{{ item.cantidad_docs }}</VChip>
            <VChip v-if="item.cantidad_ncs > 0" size="x-small" variant="tonal" color="success">
              {{ item.cantidad_ncs }} NC
            </VChip>
          </div>
        </template>

        <template #item.total_neto="{ item }">{{ fmt(item.total_neto) }}</template>
        <template #item.total_pagado="{ item }">
          <span class="text-success">{{ fmt(item.total_pagado) }}</span>
        </template>
        <template #item.total_pendiente="{ item }">
          <span class="font-weight-bold" :class="item.total_pendiente > 0 ? 'text-error' : 'text-success'">
            {{ fmt(item.total_pendiente) }}
          </span>
        </template>

        <template #item.progreso="{ item }">
          <div style="min-width: 100px">
            <VProgressLinear
              :model-value="item.total_neto > 0 ? (item.total_pagado / item.total_neto) * 100 : 0"
              color="success" bg-color="error" height="6" rounded
            />
            <div class="text-caption text-center mt-1">
              {{ item.total_neto > 0 ? Math.round((item.total_pagado / item.total_neto) * 100) : 0 }}%
            </div>
          </div>
        </template>

        <!-- Detalle expandido -->
        <template #expanded-row="{ item }">
          <tr>
            <td :colspan="headers.length + 1" class="pa-0">
              <div class="pa-4 bg-surface">
                <p class="text-body-2 font-weight-medium mb-3">
                  Documentos de {{ item.nombre_emisor }}
                </p>
                <div v-if="loadingFacturas[item.rut_emisor]" class="text-center py-4">
                  <VProgressCircular indeterminate size="24" />
                </div>
                <VTable v-else-if="facturasProveedor[item.rut_emisor]?.length" density="compact">
                  <thead>
                    <tr>
                      <th>Folio</th>
                      <th>Tipo</th>
                      <th>Fecha</th>
                      <th class="text-end">Total</th>
                      <th class="text-end">Pagado/Usado</th>
                      <th class="text-end">Pendiente</th>
                      <th>Categoría</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="f in facturasProveedor[item.rut_emisor]" :key="f.id">
                      <tr :class="f.es_nc ? 'nc-row' : ''">
                        <!-- Folio -->
                        <td class="font-weight-medium">
                          <span v-if="f.es_nc" class="text-success">NC #{{ f.folio }}</span>
                          <span v-else>{{ f.folio }}</span>
                        </td>
                        <!-- Tipo DTE -->
                        <td>
                          <VChip
                            size="x-small" variant="tonal"
                            :color="f.es_nc ? 'success' : 'info'"
                          >
                            DTE {{ f.tipo_dte }}{{ f.es_nc ? ' · NC' : '' }}
                          </VChip>
                        </td>
                        <!-- Fecha -->
                        <td class="text-caption">{{ fmtFecha(f.fecha_emision) }}</td>
                        <!-- Total (NCs en verde) -->
                        <td class="text-end" :class="f.es_nc ? 'text-success' : ''">
                          {{ f.es_nc ? '-' : '' }}{{ fmt(f.total) }}
                        </td>
                        <!-- Pagado/Usado -->
                        <td class="text-end text-success">{{ fmt(f.monto_pagado) }}</td>
                        <!-- Pendiente -->
                        <td class="text-end font-weight-bold" :class="f.pendiente > 0 ? 'text-error' : f.pendiente < 0 ? 'text-success' : 'text-medium-emphasis'">
                          {{ fmt(f.pendiente) }}
                        </td>
                        <!-- Categoría editable -->
                        <td>
                          <div v-if="!f.es_nc" style="min-width:160px">
                            <VSelect
                              :model-value="f.categoria"
                              :items="categoriasDisponibles"
                              density="compact"
                              variant="plain"
                              hide-details
                              placeholder="Sin categoría"
                              style="font-size:12px"
                              @update:model-value="(val) => abrirCambiarCategoria(f, item, val)"
                            />
                          </div>
                        </td>
                        <!-- Estado -->
                        <td>
                          <div class="d-flex flex-column gap-1">
                            <!-- Estado pago -->
                            <VChip
                              v-if="!f.es_nc"
                              size="x-small"
                              :color="f.pendiente <= 0 ? 'success' : 'warning'"
                              variant="tonal"
                            >
                              {{ f.pendiente <= 0 ? 'Pagada' : 'Pendiente' }}
                            </VChip>
                            <!-- Estado NC -->
                            <VChip
                              v-if="f.es_nc"
                              size="x-small"
                              :color="f.pendiente == 0 ? 'success' : (f.nc_posicion_factura_id ? 'primary' : 'secondary')"
                              variant="tonal"
                            >
                              {{ f.pendiente == 0 ? 'Aplicada' : (f.nc_posicion_factura_id ? 'Vinculada' : 'Sin vincular') }}
                            </VChip>
                            <!-- Estado revisión -->
                            <VChip
                              v-if="f.nc_revision_estado"
                              size="x-small"
                              :color="ncRevisionColor(f.nc_revision_estado)"
                              variant="flat"
                            >
                              <VIcon start size="10">{{ ncRevisionIcon(f.nc_revision_estado) }}</VIcon>
                              {{ ncRevisionLabel(f.nc_revision_estado) }}
                            </VChip>
                          </div>
                        </td>
                        <!-- Acciones -->
                        <td>
                          <div class="d-flex align-center flex-wrap" style="gap:4px">
                            <!-- Factura normal: rayo + conciliar -->
                            <template v-if="!f.es_nc">
                              <VTooltip v-if="sugerenciasPorCompra[f.id] && f.pendiente > 0" location="bottom" max-width="260">
                                <template #activator="{ props: tp }">
                                  <VBtn v-bind="tp" size="x-small" icon variant="flat" color="warning"
                                    style="border-radius:4px;min-width:26px;height:26px"
                                    :loading="loadingRayoCompra[f.id]"
                                    @click="conciliarRayoCompra(f, item)">
                                    <VIcon size="15">mdi-lightning-bolt</VIcon>
                                  </VBtn>
                                </template>
                                <div style="font-size:11px">
                                  <div class="font-weight-bold mb-1">⚡ Conciliar directamente</div>
                                  <div>{{ sugerenciasPorCompra[f.id].movimiento.descripcion }}</div>
                                  <div class="font-weight-bold mt-1">{{ fmt(sugerenciasPorCompra[f.id].monto_sugerido) }}</div>
                                </div>
                              </VTooltip>
                              <VBtn v-if="f.pendiente > 0" size="x-small" variant="tonal" color="primary" @click="abrirConciliar(f, item)">
                                <VIcon size="12" class="mr-1">mdi-link-variant</VIcon>Conciliar
                              </VBtn>
                              <VBtn v-else size="x-small" variant="text" color="success" @click="abrirConciliar(f, item)">
                                <VIcon size="13">mdi-eye-outline</VIcon>
                              </VBtn>
                              <!-- Cambiar estado revisión si tiene NC -->
                              <VMenu v-if="f.nc_revision_estado === 'requiere_revision'" location="bottom end">
                                <template #activator="{ props: mp }">
                                  <VBtn v-bind="mp" size="x-small" variant="tonal" color="warning" icon>
                                    <VIcon size="14">mdi-dots-vertical</VIcon>
                                  </VBtn>
                                </template>
                                <VList density="compact">
                                  <VListItem @click="cambiarEstadoFactura(f, 'reembolso_pendiente')">
                                    <VListItemTitle>Esperar reembolso bancario</VListItemTitle>
                                  </VListItem>
                                  <VListItem @click="abrirAplicarNC(f, item)">
                                    <VListItemTitle>Aplicar NC a factura</VListItemTitle>
                                  </VListItem>
                                  <VListItem @click="cambiarEstadoFactura(f, 'ignorado')">
                                    <VListItemTitle>Ignorar (sin acción)</VListItemTitle>
                                  </VListItem>
                                </VList>
                              </VMenu>
                            </template>

                            <!-- NC: vincular / aplicar -->
                            <template v-else>
                              <!-- Vincular solo si tiene saldo (pendiente < 0) o ya está vinculada formalmente (para poder desvincular) -->
                              <VBtn v-if="f.pendiente < 0 || f.nc_referencia_id" size="x-small" variant="tonal"
                                :color="f.nc_referencia_id ? 'secondary' : 'success'"
                                @click="abrirVincularNC(f, item)">
                                <VIcon size="12" class="mr-1">{{ f.nc_referencia_id ? 'mdi-link-off' : 'mdi-link-plus' }}</VIcon>
                                {{ f.nc_referencia_id ? 'Desvincular' : 'Vincular' }}
                              </VBtn>
                              <VBtn v-if="f.pendiente < 0" size="x-small" variant="tonal" color="primary" @click="abrirAplicarNC(f, item)">
                                <VIcon size="12" class="mr-1">mdi-transfer</VIcon>Aplicar
                              </VBtn>
                            </template>

                            <!-- PDF -->
                            <VBtn v-if="f.pdf_url" size="x-small" variant="text" icon :href="f.pdf_url" target="_blank">
                              <VIcon size="14">mdi-file-pdf-box</VIcon>
                            </VBtn>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </VTable>
                <p v-else class="text-body-2 text-medium-emphasis">Sin documentos pendientes.</p>
              </div>
            </td>
          </tr>
        </template>

        <template #bottom>
          <div class="pa-3 text-caption text-medium-emphasis">
            {{ proveedores.length }} proveedores
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- ── Modal: Conciliar factura con banco ─────────────────────────────── -->
    <VDialog v-model="dialogConciliar" max-width="1300" scrollable>
      <VCard v-if="facturaActiva">
        <VCardTitle class="d-flex align-center pa-4 pb-2">
          <span>Conciliar Factura #{{ facturaActiva.folio }}</span>
          <VSpacer />
          <VBtn icon variant="text" @click="dialogConciliar = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>
        <VCardText class="pa-0">
          <VRow no-gutters style="min-height: 500px">
            <VCol cols="12" md="8" class="border-e">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Movimientos Bancarios</p>
                <div v-if="asignados.length || ncsAplicadas.length" class="mb-4">
                  <p class="text-caption text-medium-emphasis mb-2">Asignados:</p>
                  <VTable density="compact">
                    <tbody>
                      <!-- Pagos bancarios -->
                      <tr v-for="a in asignados" :key="'b-' + a.pivot_id">
                        <td class="text-caption">{{ fmtFecha(a.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ a.descripcion }}</td>
                        <td class="text-end text-caption font-weight-bold text-success">{{ fmt(a.monto_asignado) }}</td>
                        <td>
                          <VBtn size="x-small" icon variant="text" color="error" :loading="loadingDesasignar[a.pivot_id]" @click="desasignar(a.pivot_id)">
                            <VIcon size="14">mdi-close</VIcon>
                          </VBtn>
                        </td>
                      </tr>
                      <!-- NCs aplicadas -->
                      <tr v-for="nc in ncsAplicadas" :key="'nc-' + nc.pivot_id" style="opacity:0.85">
                        <td class="text-caption">{{ fmtFecha(nc.fecha_contable) }}</td>
                        <td class="text-caption" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                          <VChip size="x-small" color="success" variant="tonal" class="mr-1">NC</VChip>
                          {{ nc.descripcion }}
                        </td>
                        <td class="text-end text-caption font-weight-bold text-success">{{ fmt(nc.monto_asignado) }}</td>
                        <td></td>
                      </tr>
                    </tbody>
                  </VTable>
                  <VDivider class="my-3" />
                </div>
                <VRow dense class="mb-3">
                  <VCol cols="8">
                    <VTextField v-model="buscarMov" placeholder="Buscar descripción..." density="compact" variant="outlined"
                      prepend-inner-icon="mdi-magnify" hide-details clearable @update:modelValue="cargarDisponibles" />
                  </VCol>
                  <VCol cols="4">
                    <VTextField v-model="buscarMontoMov" placeholder="Monto exacto" density="compact" variant="outlined"
                      prepend-inner-icon="mdi-currency-usd" hide-details clearable @update:modelValue="cargarDisponibles" />
                  </VCol>
                </VRow>
                <div v-if="loadingDisponibles" class="text-center py-6"><VProgressCircular indeterminate size="28" /></div>
                <VTable density="compact">
                  <thead>
                    <tr>
                      <th>Saldo</th><th>Monto</th><th>Fecha</th><th>Descripción</th><th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="mov in disponibles" :key="mov.id">
                      <td class="font-weight-bold text-warning">{{ fmt(mov.saldo_por_asignar) }}</td>
                      <td>{{ fmt(mov.monto) }}</td>
                      <td class="text-caption">{{ fmtFecha(mov.fecha_contable) }}</td>
                      <td class="text-caption" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ mov.descripcion }}</td>
                      <td>
                        <VBtn size="x-small" color="primary" variant="tonal" :loading="loadingAsignar[mov.id]"
                          :disabled="saldoPorPagar <= 0" @click="asignar(mov)">
                          Seleccionar
                        </VBtn>
                      </td>
                    </tr>
                    <tr v-if="!disponibles.length">
                      <td colspan="5" class="text-center text-caption text-medium-emphasis py-4">Sin movimientos disponibles</td>
                    </tr>
                  </tbody>
                </VTable>
              </div>
            </VCol>
            <VCol cols="12" md="4">
              <div class="pa-4">
                <p class="text-subtitle-2 font-weight-bold mb-3 text-primary">Documento</p>
                <VCard variant="outlined" class="pa-4">
                  <div class="d-flex justify-space-between mb-2">
                    <span class="text-caption text-medium-emphasis">{{ fmtFecha(facturaActiva.fecha_emision) }}</span>
                    <VChip size="x-small" color="info" variant="tonal">DTE {{ facturaActiva.tipo_dte }} · {{ facturaActiva.folio }}</VChip>
                  </div>
                  <p class="font-weight-bold mb-0">{{ proveedorActivo?.nombre_emisor }}</p>
                  <p class="text-caption text-medium-emphasis mb-3">{{ proveedorActivo?.rut_emisor }}</p>
                  <VDivider class="mb-3" />
                  <div class="d-flex justify-space-between">
                    <span class="text-body-2">Total factura</span>
                    <span class="font-weight-bold">{{ fmt(facturaActiva.total) }}</span>
                  </div>
                  <div class="d-flex justify-space-between mt-1">
                    <span class="text-body-2 text-success">Pagado (banco)</span>
                    <span class="text-success">{{ fmt(asignados.reduce((s,a) => s + a.monto_asignado, 0)) }}</span>
                  </div>
                  <div v-if="ncsAplicadas.length" class="d-flex justify-space-between mt-1">
                    <span class="text-body-2 text-success">Nota(s) de crédito</span>
                    <span class="text-success">{{ fmt(ncsAplicadas.reduce((s,a) => s + a.monto_asignado, 0)) }}</span>
                  </div>
                  <VDivider class="my-2" />
                  <div class="d-flex justify-space-between">
                    <span class="text-body-2 font-weight-bold" :class="saldoPorPagar > 0 ? 'text-error' : 'text-success'">Saldo por pagar</span>
                    <span class="font-weight-bold text-h6" :class="saldoPorPagar > 0 ? 'text-error' : 'text-success'">{{ fmt(saldoPorPagar) }}</span>
                  </div>
                  <VProgressLinear :model-value="facturaActiva.total > 0 ? ((facturaActiva.total - saldoPorPagar) / facturaActiva.total) * 100 : 0"
                    color="success" bg-color="error" height="8" rounded class="mt-3" />
                </VCard>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- ── Modal: Vincular NC a factura ──────────────────────────────────── -->
    <VDialog v-model="dialogVincular" max-width="700" scrollable>
      <VCard>
        <VCardTitle class="pa-4 pb-2 d-flex align-center">
          <VIcon color="success" class="mr-2">mdi-link-plus</VIcon>
          Vincular Nota de Crédito #{{ ncActiva?.folio }}
          <VSpacer />
          <VBtn icon variant="text" @click="dialogVincular = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Selecciona la factura original a la que hace referencia esta NC de
            <strong>{{ fmt(ncActiva?.total) }}</strong>.
          </p>
          <div v-if="loadingVincular" class="text-center py-6"><VProgressCircular indeterminate /></div>
          <VTable v-else density="compact">
            <thead>
              <tr><th>Folio</th><th>Fecha</th><th class="text-end">Total</th><th class="text-end">Pendiente</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="f in facturasParaVincular" :key="f.id">
                <td class="font-weight-medium">{{ f.folio }}</td>
                <td class="text-caption">{{ fmtFecha(f.fecha_emision) }}</td>
                <td class="text-end">{{ fmt(f.total) }}</td>
                <td class="text-end" :class="f.pendiente > 0 ? 'text-warning' : 'text-success'">{{ fmt(f.pendiente) }}</td>
                <td>
                  <VBtn size="x-small" color="success" variant="tonal" :loading="loadingVincularBtn[f.id]"
                    @click="vincularNC(f.id)">
                    Vincular
                  </VBtn>
                </td>
              </tr>
              <tr v-if="!facturasParaVincular.length">
                <td colspan="5" class="text-center text-caption text-medium-emphasis py-4">
                  Sin facturas de este proveedor disponibles
                </td>
              </tr>
            </tbody>
          </VTable>
          <div v-if="ncActiva?.nc_referencia_id" class="mt-4">
            <VAlert type="info" variant="tonal" density="compact">
              Esta NC ya está vinculada a la factura #{{ ncActiva.nc_referencia_id }}
            </VAlert>
            <VBtn color="error" variant="tonal" class="mt-2" size="small" :loading="loadingDesvincular" @click="desvincularNC">
              Quitar vínculo
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- ── Modal: Aplicar NC a factura (Escenario B — sin banco) ─────────── -->
    <VDialog v-model="dialogAplicar" max-width="650" scrollable>
      <VCard>
        <VCardTitle class="pa-4 pb-2 d-flex align-center">
          <VIcon color="primary" class="mr-2">mdi-transfer</VIcon>
          Aplicar Nota de Crédito
          <VSpacer />
          <VBtn icon variant="text" @click="dialogAplicar = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>
        <VCardText>
          <VAlert v-if="ncAplicar" type="info" variant="tonal" density="compact" class="mb-4">
            NC #{{ ncAplicar.folio }} · Saldo disponible: <strong>{{ fmt(ncAplicar.saldo_disponible) }}</strong>
          </VAlert>
          <VForm ref="formAplicar" @submit.prevent="aplicarNC">
            <VSelect
              v-model="aplicarForm.factura_id"
              :items="facturasParaAplicar"
              item-title="label"
              item-value="id"
              label="Aplicar sobre factura"
              density="compact"
              variant="outlined"
              class="mb-3"
              :rules="[v => !!v || 'Requerido']"
            />
            <VTextField
              v-model.number="aplicarForm.monto"
              label="Monto a aplicar"
              type="number"
              density="compact"
              variant="outlined"
              prefix="$"
              class="mb-3"
              :rules="[v => v > 0 || 'Debe ser mayor a 0']"
            />
            <VTextField
              v-model="aplicarForm.fecha"
              label="Fecha de aplicación"
              type="date"
              density="compact"
              variant="outlined"
              class="mb-3"
            />
            <VTextField
              v-model="aplicarForm.nota"
              label="Nota (opcional)"
              density="compact"
              variant="outlined"
              class="mb-4"
            />
            <VBtn type="submit" color="primary" variant="tonal" :loading="loadingAplicar">
              Aplicar NC
            </VBtn>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- ── Modal: Por revisar ─────────────────────────────────────────────── -->
    <VDialog v-model="dialogPorRevisar" max-width="900" scrollable>
      <VCard>
        <VCardTitle class="pa-4 pb-2 d-flex align-center">
          <VIcon color="warning" class="mr-2">mdi-alert-circle</VIcon>
          Facturas por revisar
          <VSpacer />
          <VBtn icon variant="text" @click="dialogPorRevisar = false"><VIcon>mdi-close</VIcon></VBtn>
        </VCardTitle>
        <VCardText>
          <VAlert type="warning" variant="tonal" density="compact" class="mb-4">
            Estas facturas ya fueron pagadas (o parcialmente pagadas) y luego recibieron una Nota de Crédito del proveedor. Revisa si corresponde un reembolso bancario o aplicar la NC contra otra factura.
          </VAlert>
          <div v-if="loadingPorRevisar" class="text-center py-6"><VProgressCircular indeterminate /></div>
          <VTable v-else density="compact">
            <thead>
              <tr>
                <th>Proveedor</th><th>Folio</th><th>Fecha</th>
                <th class="text-end">Total</th><th class="text-end">Pagado</th>
                <th>NC vinculada</th><th>Acción</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="f in facturasPorRevisar" :key="f.id">
                <td>
                  <div class="text-body-2 font-weight-medium">{{ f.nombre_emisor }}</div>
                  <div class="text-caption text-medium-emphasis">{{ f.rut_emisor }}</div>
                </td>
                <td class="font-weight-medium">{{ f.folio }}</td>
                <td class="text-caption">{{ fmtFecha(f.fecha_emision) }}</td>
                <td class="text-end">{{ fmt(f.total) }}</td>
                <td class="text-end text-success">{{ fmt(f.monto_pagado) }}</td>
                <td>
                  <div v-if="f.nc_folio" class="text-caption">
                    <VChip size="x-small" color="success" variant="tonal">NC #{{ f.nc_folio }}</VChip>
                    <div class="text-caption text-medium-emphasis mt-1">{{ fmt(f.nc_total) }}</div>
                  </div>
                  <span v-else class="text-caption text-medium-emphasis">—</span>
                </td>
                <td>
                  <div class="d-flex gap-1 flex-wrap">
                    <VBtn size="x-small" variant="tonal" color="info" @click="cambiarEstadoFactura(f, 'reembolso_pendiente')">
                      Reembolso
                    </VBtn>
                    <VBtn size="x-small" variant="tonal" color="secondary" @click="cambiarEstadoFactura(f, 'ignorado')">
                      Ignorar
                    </VBtn>
                  </div>
                </td>
              </tr>
              <tr v-if="!facturasPorRevisar.length">
                <td colspan="7" class="text-center text-caption text-medium-emphasis py-6">
                  No hay facturas por revisar
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- ── Dialog: Cambiar categoría ──────────────────────────────────────── -->
    <VDialog v-model="dialogCategoria" max-width="460" persistent>
      <VCard>
        <VCardTitle class="text-h6 pa-4">Cambiar categoría</VCardTitle>
        <VCardText class="pb-0">
          <p class="text-body-2 mb-3">
            <strong>{{ facturaCategoria?.folio }}</strong> ·
            {{ fmtFecha(facturaCategoria?.fecha_emision) }} ·
            {{ fmt(facturaCategoria?.total) }}
          </p>
          <VSelect
            v-model="nuevaCategoria"
            :items="categoriasDisponibles"
            label="Categoría"
            density="compact"
            variant="outlined"
          />
          <VCheckbox
            v-model="crearReglaCategoria"
            density="compact"
            hide-details
            class="mt-2"
            :label="`Crear regla para ${proveedorCategoria?.nombre_emisor} (${proveedorCategoria?.rut_emisor})`"
          />
          <p v-if="crearReglaCategoria" class="text-caption text-info mt-1 ml-8">
            Todas las facturas futuras de este proveedor se categorizarán automáticamente.
          </p>
        </VCardText>
        <VCardActions class="pa-4 pt-2">
          <VSpacer />
          <VBtn variant="text" @click="dialogCategoria = false">Cancelar</VBtn>
          <VBtn color="primary" variant="flat" :loading="loadingCategoria" @click="guardarCategoria">Guardar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog: Reglas de proveedores ──────────────────────────────────── -->
    <VDialog v-model="dialogReglas" max-width="800">
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between pa-4 pb-2">
          <span class="text-h6">Reglas de categorización</span>
          <div class="d-flex gap-2">
            <VBtn size="small" color="success" variant="flat" prepend-icon="mdi-plus" @click="abrirNuevaRegla">
              Nueva regla
            </VBtn>
            <VBtn size="small" color="secondary" variant="tonal" prepend-icon="mdi-play" :loading="loadingAplicarReglas" @click="aplicarTodasReglas">
              Aplicar a historial
            </VBtn>
          </div>
        </VCardTitle>
        <VCardText class="pa-0">
          <VTable density="compact">
            <thead>
              <tr>
                <th>RUT</th>
                <th>Proveedor</th>
                <th>Categoría</th>
                <th style="width:80px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="reglas.length === 0">
                <td colspan="4" class="text-center text-medium-emphasis py-4">Sin reglas</td>
              </tr>
              <template v-for="r in reglas" :key="r.id">
                <tr>
                  <td class="text-caption">{{ r.rut_emisor }}</td>
                  <td class="text-caption">{{ r.nombre_emisor }}</td>
                  <td>
                    <template v-if="editandoRegla?.id === r.id">
                      <VSelect
                        v-model="editandoRegla.categoria"
                        :items="categoriasDisponibles"
                        density="compact"
                        variant="outlined"
                        hide-details
                        style="min-width:200px"
                      />
                    </template>
                    <VChip v-else size="x-small" color="info" variant="tonal">{{ r.categoria }}</VChip>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <template v-if="editandoRegla?.id === r.id">
                        <VBtn icon size="x-small" color="success" variant="text" :loading="loadingGuardarRegla" @click="guardarRegla">
                          <VIcon size="16">mdi-check</VIcon>
                        </VBtn>
                        <VBtn icon size="x-small" variant="text" @click="editandoRegla = null">
                          <VIcon size="16">mdi-close</VIcon>
                        </VBtn>
                      </template>
                      <template v-else>
                        <VBtn icon size="x-small" variant="text" @click="editandoRegla = { ...r }">
                          <VIcon size="16">mdi-pencil</VIcon>
                        </VBtn>
                        <VBtn icon size="x-small" color="error" variant="text" @click="eliminarRegla(r.id)">
                          <VIcon size="16">mdi-delete</VIcon>
                        </VBtn>
                      </template>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </VTable>
        </VCardText>

        <!-- Nueva regla form -->
        <template v-if="mostrarFormNuevaRegla">
          <VDivider />
          <VCardText>
            <p class="text-body-2 font-weight-medium mb-3">Nueva regla</p>
            <VRow dense>
              <VCol cols="3">
                <VTextField v-model="nuevaReglaForm.rut_emisor" label="RUT" density="compact" variant="outlined" hide-details />
              </VCol>
              <VCol cols="5">
                <VTextField v-model="nuevaReglaForm.nombre_emisor" label="Proveedor" density="compact" variant="outlined" hide-details />
              </VCol>
              <VCol cols="4">
                <VSelect v-model="nuevaReglaForm.categoria" :items="categoriasDisponibles" label="Categoría" density="compact" variant="outlined" hide-details />
              </VCol>
            </VRow>
          </VCardText>
          <VCardActions class="px-4 pt-0 pb-4">
            <VSpacer />
            <VBtn variant="text" size="small" @click="mostrarFormNuevaRegla = false">Cancelar</VBtn>
            <VBtn color="success" variant="flat" size="small" :loading="loadingGuardarRegla" @click="crearNuevaRegla">Crear</VBtn>
          </VCardActions>
        </template>
        <template v-else>
          <VCardActions class="pa-4 pt-2">
            <VSpacer />
            <VBtn variant="text" @click="dialogReglas = false">Cerrar</VBtn>
          </VCardActions>
        </template>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/axiosInstance'

// ── Estado principal ─────────────────────────────────────────────────────────
const loading        = ref(false)
const proveedores    = ref([])
const totales        = ref({})
const expanded       = ref([])
const facturasProveedor  = ref({})
const loadingFacturas    = ref({})

// ── Sugerencias / Rayo ⚡ ───────────────────────────────────────────────────
const sugerenciasPorCompra = ref({})
const loadingRayoCompra    = ref({})

// ── Modal Conciliar ──────────────────────────────────────────────────────────
const dialogConciliar  = ref(false)
const facturaActiva    = ref(null)
const proveedorActivo  = ref(null)
const asignados        = ref([])
const ncsAplicadas     = ref([])
const disponibles      = ref([])
const saldoPorPagar    = ref(0)
const buscarMov        = ref('')
const buscarMontoMov   = ref('')
const loadingDisponibles = ref(false)
const loadingAsignar   = ref({})
const loadingDesasignar = ref({})

// ── Modal Vincular NC ────────────────────────────────────────────────────────
const dialogVincular      = ref(false)
const ncActiva            = ref(null)         // la NC que estamos vinculando
const proveedorNcActivo   = ref(null)
const facturasParaVincular = ref([])
const loadingVincular     = ref(false)
const loadingVincularBtn  = ref({})
const loadingDesvincular  = ref(false)
const loadingVincularNcs  = ref(false)

// ── Modal Aplicar NC (Escenario B) ───────────────────────────────────────────
const dialogAplicar     = ref(false)
const ncAplicar         = ref(null)           // NC con saldo disponible
const proveedorAplicar  = ref(null)
const facturasParaAplicar = ref([])
const loadingAplicar    = ref(false)
const formAplicar       = ref(null)
const aplicarForm       = ref({ factura_id: null, monto: 0, fecha: new Date().toISOString().slice(0, 10), nota: '' })

// ── Modal Por Revisar ────────────────────────────────────────────────────────
const dialogPorRevisar   = ref(false)
const facturasPorRevisar = ref([])
const loadingPorRevisar  = ref(false)

// ── Categorías de proveedores ─────────────────────────────────────────────────
const dialogCategoria    = ref(false)
const facturaCategoria   = ref(null)
const proveedorCategoria = ref(null)
const nuevaCategoria     = ref(null)
const crearReglaCategoria = ref(false)
const loadingCategoria   = ref(false)

const dialogReglas         = ref(false)
const reglas               = ref([])
const loadingReglas        = ref(false)
const editandoRegla        = ref(null)
const loadingGuardarRegla  = ref(false)
const loadingAplicarReglas = ref(false)
const mostrarFormNuevaRegla = ref(false)
const nuevaReglaForm       = ref({ rut_emisor: '', nombre_emisor: '', categoria: '' })

const CATEGORIAS_FIJAS = [
  'Almuerzos',
  'Bencina',
  'Comisiones Pagadas',
  'Costos Directo por Venta Aluminio y Termopanel',
  'Costos Directo por Venta PVC',
  'Gastos de Investigación y Desarrollo',
  'Gastos Financieros',
  'Gastos Generales',
  'Luz',
  'Otros Costos Directos del Giro',
  'Otros Egresos Fuera de Explotación',
  'Otros Gastos de Administración y Venta',
  'Repuestos/Arreglos',
  'Seguros',
  'Sueldos Administrativos',
  'Transporte/Encomiendas',
]
const categoriasDisponibles = ref([...CATEGORIAS_FIJAS])

// ── Filtros ──────────────────────────────────────────────────────────────────
const hoy        = new Date().toISOString().slice(0, 10)
const haceUnAño  = new Date(new Date().getFullYear() - 1, 0, 1).toISOString().slice(0, 10)
const inicioAño  = new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10)
const filtros    = ref({ desde: inicioAño, hasta: hoy, buscar: '', monto: '', solo_pendientes: true })

const headers = [
  { title: 'Proveedor',   key: 'nombre_emisor',  sortable: true },
  { title: 'Documentos',  key: 'cantidad_docs',   align: 'center', sortable: true },
  { title: 'Neto',        key: 'total_neto',      align: 'end',    sortable: true },
  { title: 'Pagado',      key: 'total_pagado',    align: 'end',    sortable: true },
  { title: 'Pendiente',   key: 'total_pendiente', align: 'end',    sortable: true },
  { title: 'Avance',      key: 'progreso',        align: 'center', sortable: false },
]

// ── Helpers ──────────────────────────────────────────────────────────────────
const fmt = (v) =>
  new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v || 0)

const fmtFecha = (f) => {
  if (!f) return '—'
  return new Date(f + 'T12:00:00').toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const ncRevisionColor = (e) => ({ requiere_revision: 'warning', reembolso_pendiente: 'info', aplicado: 'success', ignorado: 'secondary' }[e] ?? 'secondary')
const ncRevisionIcon  = (e) => ({ requiere_revision: 'mdi-alert', reembolso_pendiente: 'mdi-bank-outline', aplicado: 'mdi-check', ignorado: 'mdi-eye-off' }[e] ?? 'mdi-help')
const ncRevisionLabel = (e) => ({ requiere_revision: 'Revisar NC', reembolso_pendiente: 'Reembolso', aplicado: 'NC aplicada', ignorado: 'Ignorado' }[e] ?? e)

// ── Carga principal ──────────────────────────────────────────────────────────
async function cargar() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/cuentas-por-pagar', {
      params: {
        desde: filtros.value.desde,
        hasta: filtros.value.hasta,
        buscar: filtros.value.buscar || undefined,
        monto: filtros.value.monto   || undefined,
        solo_pendientes: filtros.value.solo_pendientes,
      }
    })
    proveedores.value      = data.proveedores
    totales.value          = data.totales
    facturasProveedor.value = {}
    expanded.value         = []
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

async function cargarFacturas(rut) {
  if (facturasProveedor.value[rut]) return
  loadingFacturas.value[rut] = true
  try {
    const { data } = await axios.get(`/api/cuentas-por-pagar/${encodeURIComponent(rut)}/facturas`, {
      params: { desde: filtros.value.desde, hasta: filtros.value.hasta }
    })
    facturasProveedor.value[rut] = intercalarNCs(data)
  } catch (e) { console.error(e) }
  finally { loadingFacturas.value[rut] = false }
}

// Reordena la lista para que NCs posicionables queden justo debajo de su factura.
// Usa nc_posicion_factura_id (= nc_referencia_id ?? factura donde fue aplicada).
function intercalarNCs(docs) {
  // NCs con posición conocida (vinculadas o aplicadas a alguna factura)
  const posicionables = docs.filter(d => d.es_nc && d.nc_posicion_factura_id)
  const sinPosicion   = docs.filter(d => d.es_nc && !d.nc_posicion_factura_id)

  const posicionablesIds = new Set(posicionables.map(nc => nc.id))
  // Base: todo excepto las NCs que vamos a re-insertar
  const result = docs.filter(d => !posicionablesIds.has(d.id))

  for (const nc of posicionables) {
    const idx = result.findIndex(d => d.id === nc.nc_posicion_factura_id)
    if (idx !== -1) {
      result.splice(idx + 1, 0, nc)
    } else {
      result.push(nc)
    }
  }

  return result
}

function onExpand(newExpanded) {
  expanded.value = newExpanded
  newExpanded.forEach(rut => cargarFacturas(rut))
}

// ── Vincular NCs pendientes desde XML ────────────────────────────────────────
async function vincularNcsPendientes() {
  loadingVincularNcs.value = true
  try {
    const { data } = await axios.post('/api/compras/vincular-ncs')
    alert(`Vinculación completada: ${data.vinculadas} NCs vinculadas de ${data.total} procesadas`)
    facturasProveedor.value = {}
    await cargar()
  } catch (e) {
    console.error(e)
    alert('Error al vincular NCs')
  } finally {
    loadingVincularNcs.value = false
  }
}

// ── Por Revisar ──────────────────────────────────────────────────────────────
async function abrirPorRevisar() {
  dialogPorRevisar.value  = true
  loadingPorRevisar.value = true
  try {
    const { data } = await axios.get('/api/cuentas-por-pagar/por-revisar')
    facturasPorRevisar.value = data.facturas
  } catch (e) { console.error(e) }
  finally { loadingPorRevisar.value = false }
}

// ── Modal Conciliar ──────────────────────────────────────────────────────────
async function abrirConciliar(factura, proveedor) {
  facturaActiva.value  = factura
  proveedorActivo.value = proveedor
  buscarMov.value      = ''
  buscarMontoMov.value = ''
  asignados.value      = []
  ncsAplicadas.value   = []
  disponibles.value    = []
  dialogConciliar.value = true
  await cargarEstadoConciliar()
}

async function cargarEstadoConciliar() {
  if (!facturaActiva.value) return
  try {
    const { data } = await axios.get(`/api/compras/${facturaActiva.value.id}/movimientos`)
    asignados.value     = data.asignados
    ncsAplicadas.value  = data.ncs_aplicadas ?? []
    saldoPorPagar.value = data.saldo_por_pagar
  } catch (e) { console.error(e) }
  await cargarDisponibles()
}

async function cargarDisponibles() {
  if (!facturaActiva.value) return
  loadingDisponibles.value = true
  try {
    const { data } = await axios.get(`/api/compras/${facturaActiva.value.id}/movimientos-disponibles`, {
      params: { buscar: buscarMov.value || undefined, monto: buscarMontoMov.value || undefined }
    })
    disponibles.value = data.data ?? data
  } catch (e) { console.error(e) }
  finally { loadingDisponibles.value = false }
}

async function asignar(mov) {
  loadingAsignar.value[mov.id] = true
  try {
    const monto = Math.min(mov.saldo_por_asignar, saldoPorPagar.value)
    await axios.post(`/api/compras/${facturaActiva.value.id}/movimientos`, { movimiento_id: mov.id, monto })
    await cargarEstadoConciliar()
    await refrescarProveedor(proveedorActivo.value?.rut_emisor)
  } catch (e) { console.error(e) }
  finally { loadingAsignar.value[mov.id] = false }
}

async function desasignar(pivotId) {
  loadingDesasignar.value[pivotId] = true
  try {
    await axios.delete(`/api/compras/${facturaActiva.value.id}/movimientos/${pivotId}`)
    await cargarEstadoConciliar()
    await refrescarProveedor(proveedorActivo.value?.rut_emisor)
  } catch (e) { console.error(e) }
  finally { loadingDesasignar.value[pivotId] = false }
}

// ── Vincular NC ──────────────────────────────────────────────────────────────
async function abrirVincularNC(nc, proveedor) {
  ncActiva.value          = nc
  proveedorNcActivo.value = proveedor
  dialogVincular.value    = true
  loadingVincular.value   = true
  try {
    // Cargar facturas DTE 33/34 de este proveedor
    const { data } = await axios.get(`/api/cuentas-por-pagar/${encodeURIComponent(proveedor.rut_emisor)}/facturas`)
    facturasParaVincular.value = data.filter(f => !f.es_nc)
  } catch (e) { console.error(e) }
  finally { loadingVincular.value = false }
}

async function vincularNC(facturaId) {
  loadingVincularBtn.value[facturaId] = true
  try {
    await axios.post(`/api/nc/compra/${ncActiva.value.id}/vincular`, { factura_id: facturaId })
    dialogVincular.value = false
    await refrescarProveedor(proveedorNcActivo.value?.rut_emisor)
  } catch (e) { console.error(e) }
  finally { loadingVincularBtn.value[facturaId] = false }
}

async function desvincularNC() {
  loadingDesvincular.value = true
  try {
    await axios.delete(`/api/nc/compra/${ncActiva.value.id}/vincular`)
    dialogVincular.value = false
    await refrescarProveedor(proveedorNcActivo.value?.rut_emisor)
  } catch (e) { console.error(e) }
  finally { loadingDesvincular.value = false }
}

// ── Aplicar NC a futura factura ───────────────────────────────────────────────
async function abrirAplicarNC(ncOFactura, proveedor) {
  // ncOFactura puede ser la NC (es_nc=1) o la factura con nc_revision_estado
  proveedorAplicar.value = proveedor
  dialogAplicar.value    = true
  loadingAplicar.value   = false
  aplicarForm.value      = { factura_id: null, monto: 0, fecha: new Date().toISOString().slice(0, 10), nota: '' }

  try {
    const [ncsRes, facRes] = await Promise.all([
      axios.get(`/api/cuentas-por-pagar/${encodeURIComponent(proveedor.rut_emisor)}/ncs`),
      axios.get(`/api/cuentas-por-pagar/${encodeURIComponent(proveedor.rut_emisor)}/facturas`),
    ])

    facturasParaAplicar.value = facRes.data
      .filter(f => !f.es_nc && f.pendiente > 0)
      .map(f => ({ ...f, label: `#${f.folio} · ${fmt(f.total)} (pendiente: ${fmt(f.pendiente)})` }))

    if (ncOFactura.es_nc) {
      // Buscar en NCs sin vincular primero; si ya está vinculada, construir desde la fila
      const ncDelEndpoint = ncsRes.data.find(n => n.id === ncOFactura.id)
      ncAplicar.value = ncDelEndpoint ?? {
        id: ncOFactura.id,
        folio: ncOFactura.folio,
        saldo_disponible: Math.abs(ncOFactura.pendiente),
      }
      // Pre-llenar monto con el saldo completo de la NC
      aplicarForm.value.monto = Math.abs(ncOFactura.pendiente)
      // Si ya tiene referencia, pre-seleccionar esa factura (si aún tiene saldo)
      if (ncOFactura.nc_referencia_id) {
        const facRef = facturasParaAplicar.value.find(f => f.id === ncOFactura.nc_referencia_id)
        if (facRef) aplicarForm.value.factura_id = ncOFactura.nc_referencia_id
      }
    } else {
      // Abierto desde factura con revisión: pre-seleccionar la NC vinculada si existe
      ncAplicar.value = ncsRes.data[0] || null
      aplicarForm.value.factura_id = ncOFactura.id
    }
  } catch (e) { console.error(e) }
}

async function aplicarNC() {
  if (!ncAplicar.value) return
  loadingAplicar.value = true
  try {
    await axios.post(`/api/nc/compra/${ncAplicar.value.id}/aplicar`, {
      factura_id: aplicarForm.value.factura_id,
      monto:      aplicarForm.value.monto,
      fecha:      aplicarForm.value.fecha,
      nota:       aplicarForm.value.nota,
    })
    dialogAplicar.value = false
    await refrescarProveedor(proveedorAplicar.value?.rut_emisor)
  } catch (e) {
    console.error(e)
    alert(e.response?.data?.message || 'Error al aplicar NC')
  } finally {
    loadingAplicar.value = false
  }
}

// ── Cambiar estado revisión ──────────────────────────────────────────────────
async function cambiarEstadoFactura(factura, estado) {
  try {
    await axios.patch(`/api/nc/compra/factura/${factura.id}/estado`, { estado })
    // Refrescar proveedor
    const rut = factura.rut_emisor || Object.keys(facturasProveedor.value).find(r =>
      facturasProveedor.value[r]?.some(f => f.id === factura.id)
    )
    if (rut) await refrescarProveedor(rut)
    // Si teníamos el diálogo por revisar abierto, recargarlo
    if (dialogPorRevisar.value) await abrirPorRevisar()
  } catch (e) { console.error(e) }
}

// ── Sugerencias / Rayo ⚡ ────────────────────────────────────────────────────
async function cargarSugerencias() {
  try {
    const { data } = await axios.get('/api/conciliacion/sugerencias')
    const mapa = {}
    for (const s of data) {
      if (s.monto_exacto && s.tipo_documento === 'compra') mapa[s.documento.id] = s
    }
    sugerenciasPorCompra.value = mapa
  } catch (e) { console.error('sugerencias CxP:', e) }
}

async function conciliarRayoCompra(factura, proveedor) {
  const sug = sugerenciasPorCompra.value[factura.id]
  if (!sug || loadingRayoCompra.value[factura.id]) return
  loadingRayoCompra.value[factura.id] = true
  try {
    await axios.post(`/api/compras/${factura.id}/movimientos`, {
      movimiento_id: sug.movimiento.id, monto: sug.monto_sugerido,
    })
    delete sugerenciasPorCompra.value[factura.id]
    await refrescarProveedor(proveedor?.rut_emisor)
  } catch (e) { console.error(e) }
  finally { delete loadingRayoCompra.value[factura.id] }
}

// ── Helpers de refresco ──────────────────────────────────────────────────────
async function refrescarProveedor(rut) {
  if (!rut) return
  delete facturasProveedor.value[rut]
  await cargarFacturas(rut)
  await cargar()
}

// ── Categorías ────────────────────────────────────────────────────────────────

function abrirCambiarCategoria(factura, proveedor, valorSeleccionado) {
  facturaCategoria.value   = factura
  proveedorCategoria.value = proveedor
  nuevaCategoria.value     = valorSeleccionado
  crearReglaCategoria.value = false
  dialogCategoria.value    = true
}

async function guardarCategoria() {
  if (!nuevaCategoria.value || !facturaCategoria.value) return
  loadingCategoria.value = true
  try {
    await axios.patch(`/api/compras/${facturaCategoria.value.id}/categoria`, {
      categoria:    nuevaCategoria.value,
      crear_regla:  crearReglaCategoria.value,
    })
    facturaCategoria.value.categoria = nuevaCategoria.value
    // Actualizar en caché de facturas
    const rut = proveedorCategoria.value?.rut_emisor
    if (rut && facturasProveedor.value[rut]) {
      const f = facturasProveedor.value[rut].find(x => x.id === facturaCategoria.value.id)
      if (f) f.categoria = nuevaCategoria.value
    }
    dialogCategoria.value = false
  } catch (e) {
    console.error(e)
  } finally {
    loadingCategoria.value = false
  }
}

// ── Reglas ────────────────────────────────────────────────────────────────────

async function abrirReglas() {
  dialogReglas.value = true
  mostrarFormNuevaRegla.value = false
  editandoRegla.value = null
  loadingReglas.value = true
  try {
    const { data } = await axios.get('/api/reglas-proveedor')
    reglas.value = data
  } catch (e) { console.error(e) }
  finally { loadingReglas.value = false }
}

function abrirNuevaRegla() {
  nuevaReglaForm.value = { rut_emisor: '', nombre_emisor: '', categoria: '' }
  mostrarFormNuevaRegla.value = true
}

async function crearNuevaRegla() {
  if (!nuevaReglaForm.value.rut_emisor || !nuevaReglaForm.value.categoria) return
  loadingGuardarRegla.value = true
  try {
    const { data } = await axios.post('/api/reglas-proveedor', nuevaReglaForm.value)
    reglas.value.push(data)
    mostrarFormNuevaRegla.value = false
  } catch (e) { console.error(e) }
  finally { loadingGuardarRegla.value = false }
}

async function guardarRegla() {
  if (!editandoRegla.value) return
  loadingGuardarRegla.value = true
  try {
    const { data } = await axios.put(`/api/reglas-proveedor/${editandoRegla.value.id}`, editandoRegla.value)
    const idx = reglas.value.findIndex(r => r.id === data.id)
    if (idx !== -1) reglas.value[idx] = data
    editandoRegla.value = null
  } catch (e) { console.error(e) }
  finally { loadingGuardarRegla.value = false }
}

async function eliminarRegla(id) {
  if (!confirm('¿Eliminar esta regla?')) return
  await axios.delete(`/api/reglas-proveedor/${id}`)
  reglas.value = reglas.value.filter(r => r.id !== id)
}

async function aplicarTodasReglas() {
  loadingAplicarReglas.value = true
  try {
    const { data } = await axios.post('/api/reglas-proveedor/aplicar')
    alert(`${data.actualizadas} compras actualizadas`)
    // Limpiar caché de facturas para que recarguen con nueva categoría
    facturasProveedor.value = {}
    expanded.value = []
  } catch (e) { console.error(e) }
  finally { loadingAplicarReglas.value = false }
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
</style>
