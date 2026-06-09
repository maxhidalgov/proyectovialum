<template>
  <div>
    <!-- Header -->
    <VRow class="mb-5" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Transbank</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Conciliación de pagos con tarjeta · Banco de Chile</p>
      </VCol>
      <VCol cols="auto" class="d-flex gap-2 align-center flex-wrap">
        <VSelect
          v-model="mesSel"
          :items="mesesOpts"
          density="compact"
          variant="outlined"
          hide-details
          style="min-width:130px"
          @update:modelValue="onPeriodoChange"
        />
        <VSelect
          v-model="anioSel"
          :items="aniosOpts"
          density="compact"
          variant="outlined"
          hide-details
          style="min-width:95px"
          @update:modelValue="onPeriodoChange"
        />
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="mdi-link-variant"
          :loading="loadingMatch"
          @click="autoMatch"
        >Auto-conciliar</VBtn>
        <VBtn color="primary" prepend-icon="mdi-upload" @click="dialogSubir = true">
          Subir .dat
        </VBtn>
        <VBtn variant="tonal" color="info" prepend-icon="mdi-file-import-outline" @click="dialogChipaxCsv = true">
          Importar Chipax CSV
        </VBtn>
      </VCol>
    </VRow>

    <!-- Tarjetas resumen -->
    <VRow class="mb-6">
      <VCol cols="6" md="3">
        <VCard variant="tonal" color="success">
          <VCardText class="pa-4">
            <p class="text-caption text-medium-emphasis mb-1">Ventas brutas</p>
            <p class="text-h5 font-weight-bold mb-0">{{ fmt(resumenPeriodo?.total_ventas) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6" md="3">
        <VCard variant="tonal" color="error">
          <VCardText class="pa-4">
            <p class="text-caption text-medium-emphasis mb-1">Comisiones</p>
            <p class="text-h5 font-weight-bold mb-0">{{ fmt(resumenPeriodo?.total_costos) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6" md="3">
        <VCard variant="tonal" color="primary">
          <VCardText class="pa-4">
            <p class="text-caption text-medium-emphasis mb-1">Total abonado</p>
            <p class="text-h5 font-weight-bold mb-0">{{ fmt(resumenPeriodo?.total_abono) }}</p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6" md="3">
        <VCard variant="tonal" :color="depositosConciliados === depositos.length && depositos.length > 0 ? 'success' : 'warning'">
          <VCardText class="pa-4">
            <p class="text-caption text-medium-emphasis mb-1">Depósitos OK</p>
            <p class="text-h5 font-weight-bold mb-0">
              {{ depositosConciliados }}<span class="text-h6 font-weight-regular opacity-70">/{{ depositos.length }}</span>
            </p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Archivos cargados -->
    <p class="text-overline text-medium-emphasis mb-2">Archivos .dat del periodo</p>
    <VRow class="mb-6">
      <VCol v-for="tipo in tipos" :key="tipo.key" cols="12" md="4">
        <VCard :variant="archivoTipo(tipo.key) ? 'elevated' : 'outlined'" class="h-100">
          <VCardText class="pa-4 d-flex align-center gap-3">
            <VIcon size="32" :color="tipo.color">{{ tipo.icon }}</VIcon>
            <div class="flex-grow-1 min-w-0">
              <p class="text-body-1 font-weight-semibold mb-0">{{ tipo.label }}</p>
              <template v-if="archivoTipo(tipo.key)">
                <p class="text-caption text-medium-emphasis mb-0 text-truncate">
                  {{ archivoTipo(tipo.key).nombre_archivo }}
                </p>
                <p class="text-caption text-medium-emphasis mb-0">
                  {{ fmt(archivoTipo(tipo.key).total_ventas) }} ventas &middot;
                  <span class="font-weight-medium">{{ fmt(archivoTipo(tipo.key).total_abono) }} abonado</span>
                </p>
              </template>
              <p v-else class="text-caption text-disabled mb-0">No cargado aún</p>
            </div>
            <VBtn
              v-if="archivoTipo(tipo.key)"
              icon="mdi-delete-outline"
              variant="text"
              color="error"
              size="small"
              density="compact"
              :loading="eliminando === archivoTipo(tipo.key).id"
              @click="eliminar(archivoTipo(tipo.key))"
            />
            <VBtn
              v-else
              size="small"
              variant="tonal"
              :color="tipo.color"
              @click="abrirSubirTipo(tipo.key)"
            >
              <VIcon size="16" start>mdi-upload</VIcon>Subir
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabs: Conciliación bancaria / Resumen SII -->
    <VTabs v-model="tab" class="mb-4">
      <VTab value="banco">
        <VIcon start>mdi-bank-outline</VIcon>
        Conciliación bancaria
        <VChip
          :color="depositosConciliados === depositos.length && depositos.length > 0 ? 'success' : 'warning'"
          size="x-small"
          class="ml-2"
        >{{ depositosConciliados }}/{{ depositos.length }}</VChip>
      </VTab>
      <VTab value="sii">
        <VIcon start>mdi-calendar-month-outline</VIcon>
        Resumen por periodo SII
      </VTab>
      <VTab value="docs">
        <VIcon start>mdi-file-document-check-outline</VIcon>
        Documentos
        <VChip
          v-if="facturasPendientes > 0"
          color="warning"
          size="x-small"
          class="ml-2"
        >{{ facturasPendientes }} sin vincular</VChip>
      </VTab>
    </VTabs>

    <!-- ──────────────────────────────────────────────────── -->
    <!-- Tab: Conciliación bancaria                          -->
    <!-- ──────────────────────────────────────────────────── -->
    <div v-if="tab === 'banco'">
      <p class="text-caption text-medium-emphasis mb-3">
        Cada fila es una transferencia Transbank → banco (suma crédito + débito + prepago de esa fecha).
      </p>

      <div v-if="loadingDepositos" class="d-flex justify-center py-10">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <VCard v-else-if="depositos.length === 0">
        <VCardText class="text-center py-10">
          <VIcon size="52" color="medium-emphasis" class="mb-3">mdi-bank-off-outline</VIcon>
          <p class="text-body-1 text-medium-emphasis mb-0">
            Sin depósitos para {{ periodoLabel }}.<br>Sube los archivos .dat para comenzar.
          </p>
        </VCardText>
      </VCard>

      <VCard v-else>
        <VTable density="comfortable">
          <thead>
            <tr>
              <th style="min-width:130px">Fecha abono</th>
              <th class="text-right" style="min-width:110px">Crédito</th>
              <th class="text-right" style="min-width:110px">Débito</th>
              <th class="text-right" style="min-width:110px">Prepago</th>
              <th class="text-right" style="min-width:120px">
                <strong>Total depósito</strong>
              </th>
              <th style="min-width:180px">Movimiento bancario</th>
              <th style="min-width:90px" class="text-center">Acción</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="dep in depositos" :key="dep.fecha_abono">
              <!-- Fila principal -->
              <tr
                class="dep-row"
                :class="dep.movimiento_bancario_id ? 'conciliado' : 'pendiente'"
                @click="toggleDetalle(dep.fecha_abono)"
              >
                <td>
                  <div class="d-flex align-center gap-2">
                    <VIcon
                      size="16"
                      :color="dep.movimiento_bancario_id ? 'success' : 'warning'"
                    >
                      {{ dep.movimiento_bancario_id ? 'mdi-check-circle' : 'mdi-clock-outline' }}
                    </VIcon>
                    <span class="font-weight-medium">{{ formatFecha(dep.fecha_abono) }}</span>
                    <VIcon
                      size="14"
                      color="medium-emphasis"
                      class="ml-auto"
                    >{{ detalleExpandido === dep.fecha_abono ? 'mdi-chevron-up' : 'mdi-chevron-down' }}</VIcon>
                  </div>
                </td>
                <td class="text-right">
                  <span v-if="dep.credito_neto > 0">{{ fmt(dep.credito_neto) }}</span>
                  <span v-else class="text-disabled">—</span>
                </td>
                <td class="text-right">
                  <span v-if="dep.debito_neto > 0">{{ fmt(dep.debito_neto) }}</span>
                  <span v-else class="text-disabled">—</span>
                </td>
                <td class="text-right">
                  <span v-if="dep.prepago_neto > 0">{{ fmt(dep.prepago_neto) }}</span>
                  <span v-else class="text-disabled">—</span>
                </td>
                <td class="text-right">
                  <strong>{{ fmt(dep.total_neto) }}</strong>
                </td>
                <td>
                  <div v-if="dep.movimiento">
                    <p class="text-body-2 mb-0 text-truncate" style="max-width:200px">
                      {{ dep.movimiento.descripcion }}
                    </p>
                    <p class="text-caption text-medium-emphasis mb-0">
                      {{ fmt(dep.movimiento.monto) }}
                      <span v-if="Math.abs(dep.movimiento.monto - dep.total_neto) > 10" class="text-warning ml-1">
                        (Δ {{ fmt(dep.movimiento.monto - dep.total_neto) }})
                      </span>
                    </p>
                  </div>
                  <span v-else class="text-caption text-disabled">Sin asignar</span>
                </td>
                <td class="text-center" @click.stop>
                  <VBtn
                    v-if="!dep.movimiento_bancario_id"
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    @click="abrirMatchManual(dep)"
                  >Asignar</VBtn>
                  <VBtn
                    v-else
                    size="x-small"
                    variant="text"
                    color="warning"
                    @click="desasignarDeposito(dep)"
                  >Quitar</VBtn>
                </td>
              </tr>

              <!-- Fila expandida: detalle de transacciones -->
              <tr v-if="detalleExpandido === dep.fecha_abono">
                <td colspan="7" class="pa-0">
                  <div class="pa-4" style="background: rgba(var(--v-theme-surface-variant), 0.4)">
                    <div v-if="loadingDetalle === dep.fecha_abono" class="d-flex justify-center py-4">
                      <VProgressCircular indeterminate size="22" />
                    </div>
                    <template v-else>
                      <div
                        v-for="t in tipos"
                        :key="t.key"
                        class="mb-3"
                      >
                        <div v-if="(detalleAbonos[dep.fecha_abono]?.[t.key] ?? []).length > 0">
                          <p class="text-caption font-weight-bold mb-1" :class="`text-${t.color}`">
                            {{ t.label }}
                            ({{ detalleAbonos[dep.fecha_abono][t.key].length }} transacciones)
                          </p>
                          <VTable density="compact" class="rounded border">
                            <thead>
                              <tr>
                                <th>Hora</th>
                                <th>Tarjeta</th>
                                <th class="text-right">Monto venta</th>
                                <th class="text-right">Comisión</th>
                                <th class="text-right">Neto abono</th>
                                <th>Documento</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="tx in detalleAbonos[dep.fecha_abono][t.key]" :key="tx.id">
                                <td class="text-caption">{{ formatHora(tx.fecha_movimiento) }}</td>
                                <td class="text-caption">{{ tx.tipo_tarjeta ?? '—' }}</td>
                                <td class="text-right">
                                  {{ tx.tipo === 'Venta' ? fmt(tx.monto_original) : '—' }}
                                </td>
                                <td class="text-right text-caption text-error">
                                  {{ tx.tipo === 'Venta'
                                      ? fmt(tx.monto_comision + tx.iva_comision)
                                      : 'Servicio: ' + fmt(tx.monto_servicio + tx.iva_servicio) }}
                                </td>
                                <td class="text-right font-weight-medium">
                                  {{ tx.tipo === 'Venta'
                                      ? fmt(tx.total_abono)
                                      : '−' + fmt(tx.monto_servicio + tx.iva_servicio) }}
                                </td>
                                <td class="text-caption">
                                  <VChip v-if="tx.tipo_documento && tx.tipo_documento !== 'N/A'" size="x-small" label>
                                    {{ tx.tipo_documento }}
                                  </VChip>
                                  <span v-else class="text-disabled">—</span>
                                </td>
                              </tr>
                            </tbody>
                          </VTable>
                        </div>
                      </div>
                    </template>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
          <tfoot>
            <tr style="background: rgba(var(--v-theme-on-surface), 0.05)">
              <td class="font-weight-bold">Total periodo</td>
              <td class="text-right font-weight-bold">{{ fmt(sum('credito_neto')) }}</td>
              <td class="text-right font-weight-bold">{{ fmt(sum('debito_neto')) }}</td>
              <td class="text-right font-weight-bold">{{ fmt(sum('prepago_neto')) }}</td>
              <td class="text-right font-weight-bold text-primary">{{ fmt(sum('total_neto')) }}</td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </VTable>
      </VCard>
    </div>

    <!-- ──────────────────────────────────────────────────── -->
    <!-- Tab: Resumen SII                                    -->
    <!-- ──────────────────────────────────────────────────── -->
    <div v-if="tab === 'sii'">
      <p class="text-caption text-medium-emphasis mb-3">
        Agrupa por <strong>fecha de venta</strong> (periodo SII), no de abono.
        Ventas del 31/03 que depositan el 01/04 aparecen en <em>marzo</em>.
      </p>

      <div v-if="loadingSii" class="d-flex justify-center py-10">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <VCard v-else-if="siiDetalle.length === 0">
        <VCardText class="text-center py-10 text-medium-emphasis">
          Sin datos de periodo SII. Sube los archivos .dat primero.
        </VCardText>
      </VCard>

      <template v-else>
        <!-- Agrupar por sii_periodo -->
        <VCard v-for="(grupo, siiPer) in siiAgrupado" :key="siiPer" class="mb-4">
          <VCardTitle class="text-h6 pa-4 pb-2">
            Periodo {{ siiPer }}
            <!-- Solo mostrar cuando el periodo SII es ANTERIOR al periodo del archivo -->
            <VChip
              v-if="siiPer < periodo"
              size="small"
              color="warning"
              class="ml-2"
            >Ventas del periodo anterior</VChip>
          </VCardTitle>
          <VTable density="comfortable">
            <thead>
              <tr>
                <th>Tipo tarjeta</th>
                <th class="text-right">N° transacciones</th>
                <th class="text-right">Total ventas</th>
                <th class="text-right">Comisiones</th>
                <th class="text-right">Cargos servicio</th>
                <th class="text-right"><strong>Neto abonado</strong></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in grupo" :key="row.tipo_tarjeta">
                <td>
                  <VChip
                    :color="tipoColor(row.tipo_tarjeta)"
                    size="small"
                    class="text-capitalize"
                  >{{ row.tipo_tarjeta }}</VChip>
                </td>
                <td class="text-right">{{ n(row.cantidad) }}</td>
                <td class="text-right">{{ fmt(row.total_ventas) }}</td>
                <td class="text-right text-error">{{ fmt(row.total_comision) }}</td>
                <td class="text-right text-error">
                  {{ siiServicios[row.tipo_tarjeta] ? fmt(siiServicios[row.tipo_tarjeta].total_servicio) : '—' }}
                </td>
                <td class="text-right font-weight-bold">{{ fmt(row.total_abono_neto) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr style="background: rgba(var(--v-theme-on-surface), 0.05)">
                <td class="font-weight-bold">Subtotal {{ siiPer }}</td>
                <td class="text-right font-weight-bold">{{ n(grupo.reduce((s, r) => s + n(r.cantidad), 0)) }}</td>
                <td class="text-right font-weight-bold">{{ fmt(grupo.reduce((s, r) => s + n(r.total_ventas), 0)) }}</td>
                <td class="text-right font-weight-bold text-error">{{ fmt(grupo.reduce((s, r) => s + n(r.total_comision), 0)) }}</td>
                <td></td>
                <td class="text-right font-weight-bold text-primary">{{ fmt(grupo.reduce((s, r) => s + n(r.total_abono_neto), 0)) }}</td>
              </tr>
            </tfoot>
          </VTable>
        </VCard>
      </template>
    </div>

    <!-- ──────────────────────────────────────────────────── -->
    <!-- Tab: Documentos                                     -->
    <!-- ──────────────────────────────────────────────────── -->
    <div v-if="tab === 'docs'">

      <!-- Resumen inteligente post-vinculación -->
      <VRow class="mb-4" dense>
        <VCol cols="6" md="3">
          <VCard variant="tonal" color="primary">
            <VCardText class="pa-3">
              <p class="text-caption text-medium-emphasis mb-1">Total transacciones</p>
              <p class="text-h6 font-weight-bold mb-0">{{ txFacturas.length }}</p>
              <p class="text-caption mb-0">{{ fmt(resumenDocs.totalMonto) }}</p>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="6" md="3">
          <VCard variant="tonal" color="success">
            <VCardText class="pa-3">
              <p class="text-caption text-medium-emphasis mb-1">Boletas con tarjeta</p>
              <p class="text-h6 font-weight-bold mb-0">{{ resumenDocs.boletas.count }}</p>
              <p class="text-caption mb-0">{{ fmt(resumenDocs.boletas.monto) }}</p>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="6" md="3">
          <VCard variant="tonal" color="info">
            <VCardText class="pa-3">
              <p class="text-caption text-medium-emphasis mb-1">Facturas vinculadas</p>
              <p class="text-h6 font-weight-bold mb-0">{{ resumenDocs.facturas.count }}</p>
              <p class="text-caption mb-0">{{ fmt(resumenDocs.facturas.monto) }}</p>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="6" md="3">
          <VCard variant="tonal" :color="resumenDocs.sinVincular.count > 0 ? 'warning' : 'success'">
            <VCardText class="pa-3">
              <p class="text-caption text-medium-emphasis mb-1">Sin vincular</p>
              <p class="text-h6 font-weight-bold mb-0">{{ resumenDocs.sinVincular.count }}</p>
              <p class="text-caption mb-0">{{ fmt(resumenDocs.sinVincular.monto) }}</p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Cabecera tabla -->
      <div class="d-flex align-center mb-3 gap-2 flex-wrap">
        <!-- Filtros -->
        <VChipGroup v-model="filtroDoc" mandatory selected-class="text-primary">
          <VChip value="todos" variant="tonal" size="small">
            Todos <span class="ml-1 font-weight-bold">{{ txFacturas.length }}</span>
          </VChip>
          <VChip value="sin_vincular" variant="tonal" color="warning" size="small">
            Sin vincular <span class="ml-1 font-weight-bold">{{ resumenDocs.sinVincular.count }}</span>
          </VChip>
          <VChip value="vinculados" variant="tonal" color="success" size="small">
            Vinculados <span class="ml-1 font-weight-bold">{{ txFacturas.length - resumenDocs.sinVincular.count }}</span>
          </VChip>
        </VChipGroup>

        <VSpacer />

        <VBtn
          variant="tonal"
          color="primary"
          size="small"
          prepend-icon="mdi-link-variant-plus"
          :loading="loadingAutoLink"
          @click="autoLinkFacturas"
        >Auto-vincular</VBtn>
      </div>

      <div v-if="loadingDocs" class="d-flex justify-center py-10">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <VCard v-else-if="txFacturas.length === 0">
        <VCardText class="text-center py-8 text-medium-emphasis">
          Sin transacciones de venta para este periodo.
        </VCardText>
      </VCard>

      <VCard v-else>
        <VCardText v-if="txFacturasFiltradas.length === 0" class="text-center py-8 text-medium-emphasis">
          No hay transacciones {{ filtroDoc === 'sin_vincular' ? 'sin vincular' : 'vinculadas' }} en este periodo.
        </VCardText>
        <VTable v-else density="comfortable">
          <thead>
            <tr>
              <th style="min-width:110px">Fecha venta</th>
              <th style="min-width:60px">Hora</th>
              <th class="text-right" style="min-width:110px">Monto</th>
              <th style="min-width:90px">Tarjeta</th>
              <th style="min-width:90px">Nro boleta</th>
              <th style="min-width:200px">Documento Bsale</th>
              <th class="text-center" style="min-width:90px">Acción</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="tx in txFacturasFiltradas"
              :key="tx.id"
              :class="tx.documento_id ? 'doc-linked' : ''"
            >
              <td>{{ formatFecha(tx.fecha_movimiento?.slice(0, 10)) }}</td>
              <td class="text-caption text-medium-emphasis">{{ formatHora(tx.fecha_movimiento) }}</td>
              <td class="text-right font-weight-medium">{{ fmt(tx.monto_original) }}</td>
              <td>
                <VChip :color="tipoColor(tx.medio_pago)" size="x-small" label>
                  {{ tx.tipo_tarjeta ?? tx.medio_pago }}
                </VChip>
              </td>
              <td class="text-caption text-medium-emphasis">{{ tx.nro_voucher ?? '—' }}</td>
              <td>
                <div v-if="tx.documento_id">
                  <div class="d-flex align-center gap-1 mb-0">
                    <VChip
                      size="x-small"
                      :color="tx.doc_tipo_bsale === 1 ? 'success' : 'info'"
                      label
                    >{{ tx.doc_tipo_bsale === 1 ? 'Boleta' : 'Factura' }}</VChip>
                    <span class="text-body-2 font-weight-medium">N° {{ tx.doc_numero }}</span>
                  </div>
                  <p class="text-caption text-medium-emphasis mb-0">
                    {{ tx.doc_cliente }} · {{ fmt(tx.doc_monto) }}
                  </p>
                </div>
                <VChip v-else size="x-small" color="warning" label>Sin vincular</VChip>
              </td>
              <td class="text-center" @click.stop>
                <VBtn
                  v-if="!tx.documento_id"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  @click="abrirLinkFactura(tx)"
                >Vincular</VBtn>
                <VBtn
                  v-else
                  size="x-small"
                  variant="text"
                  color="warning"
                  :loading="desvinculando === tx.id"
                  @click="desasociarFactura(tx)"
                >Quitar</VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>
    </div>

    <!-- ──────────────────────────────────────────────────── -->
    <!-- Dialog Subir archivo                                -->
    <!-- ──────────────────────────────────────────────────── -->
    <VDialog v-model="dialogSubir" max-width="480" persistent>
      <VCard>
        <VCardTitle class="d-flex align-center pa-4">
          Subir archivo Transbank
          <VSpacer />
          <VBtn icon="mdi-close" variant="text" size="small" @click="dialogSubir = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <VTextField
            v-model="subirPeriodo"
            type="month"
            label="Periodo"
            density="compact"
            variant="outlined"
            class="mb-3"
          />
          <VSelect
            v-model="subirTipo"
            :items="tipos.map(t => ({ title: t.label, value: t.key }))"
            label="Tipo de tarjeta"
            density="compact"
            variant="outlined"
            class="mb-3"
          />
          <VFileInput
            v-model="subirArchivo"
            label="Archivo .dat de Transbank"
            accept=".dat,.txt"
            density="compact"
            variant="outlined"
            prepend-icon=""
            prepend-inner-icon="mdi-file-upload-outline"
            @update:modelValue="onArchivoSeleccionado"
          />
          <!-- Pokayoke: periodo detectado del nombre del archivo -->
          <VAlert
            v-if="periodoDetectado && periodoDetectado !== subirPeriodo"
            type="warning"
            density="compact"
            variant="tonal"
            class="mt-2"
          >
            El archivo parece ser del periodo <strong>{{ periodoDetectado }}</strong>
            pero tienes seleccionado <strong>{{ subirPeriodo }}</strong>.
            <VBtn size="x-small" variant="text" @click="subirPeriodo = periodoDetectado">
              Usar {{ periodoDetectado }}
            </VBtn>
          </VAlert>
          <VAlert
            v-else-if="periodoDetectado && periodoDetectado === subirPeriodo"
            type="success"
            density="compact"
            variant="tonal"
            class="mt-2"
          >
            Periodo detectado del archivo: <strong>{{ periodoDetectado }}</strong> ✓
          </VAlert>
          <VAlert v-if="errorSubir" type="error" density="compact" class="mt-2" variant="tonal">
            {{ errorSubir }}
          </VAlert>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="text" @click="dialogSubir = false">Cancelar</VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :loading="subirLoading"
            :disabled="!subirArchivo || !subirTipo"
            @click="subirArchivos"
          >Subir archivo</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ──────────────────────────────────────────────────── -->
    <!-- Dialog Match Manual                                 -->
    <!-- ──────────────────────────────────────────────────── -->
    <VDialog v-model="dialogMatch" max-width="680">
      <VCard>
        <VCardTitle class="d-flex align-center pa-4">
          Asignar movimiento bancario
          <VSpacer />
          <VBtn icon="mdi-close" variant="text" size="small" @click="dialogMatch = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4" v-if="depositoSeleccionado">
          <VAlert type="info" variant="tonal" density="compact" class="mb-4">
            <strong>Depósito {{ formatFecha(depositoSeleccionado.fecha_abono) }}</strong>
            · Total <strong>{{ fmt(depositoSeleccionado.total_neto) }}</strong>
            <div class="text-caption mt-1 opacity-80">
              Crédito {{ fmt(depositoSeleccionado.credito_neto) }}
              + Débito {{ fmt(depositoSeleccionado.debito_neto) }}
              + Prepago {{ fmt(depositoSeleccionado.prepago_neto) }}
            </div>
          </VAlert>

          <p class="text-body-2 font-weight-medium mb-2">
            Movimientos bancarios tipo crédito (±5 días):
          </p>

          <div v-if="loadingMovimientos" class="d-flex justify-center py-4">
            <VProgressCircular indeterminate size="24" />
          </div>

          <VTable v-else density="compact">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Descripción</th>
                <th class="text-right">Monto</th>
                <th class="text-right">Diferencia</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="movimientosDisponibles.length === 0">
                <td colspan="5" class="text-center py-4 text-disabled">
                  No hay movimientos tipo crédito en ese rango de fechas
                </td>
              </tr>
              <tr
                v-for="mv in movimientosDisponibles"
                :key="mv.id"
                :class="{ 'match-ideal': Math.abs(mv.monto - depositoSeleccionado.total_neto) < 500 }"
              >
                <td class="text-caption">{{ formatFecha(mv.fecha_contable) }}</td>
                <td class="text-body-2">{{ mv.descripcion }}</td>
                <td class="text-right font-weight-bold">{{ fmt(mv.monto) }}</td>
                <td
                  class="text-right text-caption"
                  :class="Math.abs(mv.monto - depositoSeleccionado.total_neto) < 500 ? 'text-success' : 'text-warning'"
                >
                  {{ (mv.monto - depositoSeleccionado.total_neto) >= 0 ? '+' : '' }}{{ fmt(mv.monto - depositoSeleccionado.total_neto) }}
                </td>
                <td>
                  <VBtn
                    size="x-small"
                    color="primary"
                    :loading="matchLoading === mv.id"
                    @click="confirmarMatch(mv)"
                  >Asignar</VBtn>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- ──────────────────────────────────────────────────── -->
    <!-- Dialog Vincular Factura                             -->
    <!-- ──────────────────────────────────────────────────── -->
    <VDialog v-model="dialogLinkFactura" max-width="720">
      <VCard>
        <VCardTitle class="d-flex align-center pa-4">
          Vincular transacción a documento Bsale
          <VSpacer />
          <VBtn icon="mdi-close" variant="text" size="small" @click="dialogLinkFactura = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <VAlert v-if="txSeleccionada" type="info" variant="tonal" density="compact" class="mb-4">
            <strong>{{ fmt(txSeleccionada.monto_original) }}</strong>
            · {{ formatFecha(txSeleccionada.fecha_movimiento?.slice(0, 10)) }}
            · {{ txSeleccionada.tipo_tarjeta ?? txSeleccionada.medio_pago }}
            <span v-if="txSeleccionada.nro_voucher" class="ml-2">
              · Nro boleta <strong>{{ txSeleccionada.nro_voucher }}</strong>
            </span>
          </VAlert>

          <VTextField
            v-model="busquedaFactura"
            placeholder="Buscar por número de documento o cliente…"
            prepend-inner-icon="mdi-magnify"
            density="compact"
            variant="outlined"
            clearable
            class="mb-3"
            @update:modelValue="buscarFacturas"
          />

          <div v-if="loadingFacturas" class="d-flex justify-center py-6">
            <VProgressCircular indeterminate size="24" />
          </div>

          <VTable v-else density="compact">
            <thead>
              <tr>
                <th>N° documento</th>
                <th>Cliente</th>
                <th>Fecha emisión</th>
                <th class="text-right">Monto</th>
                <th>Comprobante</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="facturasDisponibles.length === 0">
                <td colspan="6" class="text-center py-4 text-disabled">
                  No hay documentos disponibles para vincular
                </td>
              </tr>
              <tr
                v-for="doc in facturasDisponibles"
                :key="doc.id"
                :class="{
                  'match-ideal': doc.nro_comprobante_transbank && doc.nro_comprobante_transbank === txSeleccionada?.nro_voucher
                    || (!doc.nro_comprobante_transbank && Math.abs(n(doc.monto) - n(txSeleccionada?.monto_original)) < 10)
                }"
              >
                <td class="font-weight-medium">{{ doc.numero_documento_bsale }}</td>
                <td class="text-body-2">{{ doc.bsale_cliente_nombre ?? '—' }}</td>
                <td class="text-caption">{{ formatFecha(doc.fecha_emision) }}</td>
                <td class="text-right font-weight-medium">{{ fmt(doc.monto) }}</td>
                <td class="text-caption text-medium-emphasis">{{ doc.nro_comprobante_transbank ?? '—' }}</td>
                <td>
                  <VBtn
                    size="x-small"
                    color="primary"
                    :loading="vinculando === doc.id"
                    @click="confirmarLinkFactura(doc)"
                  >Vincular</VBtn>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- ──────────────────────────────────────────────────── -->
    <!-- Dialog Importar CSV Chipax                          -->
    <!-- ──────────────────────────────────────────────────── -->
    <VDialog v-model="dialogChipaxCsv" max-width="560" persistent>
      <VCard>
        <VCardTitle class="d-flex align-center pa-4">
          Importar conciliación Chipax CSV
          <VSpacer />
          <VBtn icon="mdi-close" variant="text" size="small" @click="cerrarChipaxCsv" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <VAlert type="info" variant="tonal" density="compact" class="mb-4">
            Exporta desde Chipax: <strong>Conciliación → Conciliación avanzada → Exportar CSV</strong>.
            El archivo vincula los abonos Transbank con sus facturas en nuestro sistema.
          </VAlert>

          <VFileInput
            v-model="chipaxCsvArchivo"
            label="Archivo conciliacion_avanzada_*.xlsx"
            accept=".xlsx,.xls,.csv"
            density="compact"
            variant="outlined"
            prepend-icon=""
            prepend-inner-icon="mdi-file-excel-outline"
            @update:modelValue="onChipaxArchivoChange"
          />
          <p v-if="chipaxRowCount" class="text-caption text-medium-emphasis mt-1">
            {{ chipaxRowCount }} filas detectadas en el archivo
          </p>

          <!-- Resultado -->
          <template v-if="chipaxCsvResult">
            <VAlert
              :type="chipaxCsvResult.ok ? 'success' : 'error'"
              variant="tonal"
              density="compact"
              class="mt-3"
            >
              <div class="font-weight-semibold mb-1">
                {{ chipaxCsvResult.dry_run ? '[DRY RUN] ' : '' }}Resultado:
              </div>
              <div class="text-body-2">
                Movimientos bancarios: <strong>{{ chipaxCsvResult.stats.movimientos_procesados }}</strong> |
                Facturas vinculadas: <strong>{{ chipaxCsvResult.stats.facturas_vinculadas }}</strong> |
                Ya existían: <strong>{{ chipaxCsvResult.stats.ya_existian }}</strong> |
                Conciliados: <strong>{{ chipaxCsvResult.stats.conciliados }}</strong>
              </div>
              <div v-if="chipaxCsvResult.stats.mov_no_encontrado > 0 || chipaxCsvResult.stats.factura_no_encontrada > 0" class="text-body-2 mt-1 text-warning">
                Sin movimiento: {{ chipaxCsvResult.stats.mov_no_encontrado }} |
                Sin factura: {{ chipaxCsvResult.stats.factura_no_encontrada }}
              </div>
            </VAlert>

            <!-- Log detallado (colapsable) -->
            <VExpansionPanels v-if="chipaxCsvResult.stats.log?.length" class="mt-2" variant="accordion">
              <VExpansionPanel>
                <VExpansionPanelTitle class="text-caption">
                  Ver log detallado ({{ chipaxCsvResult.stats.log.length }} entradas)
                </VExpansionPanelTitle>
                <VExpansionPanelText>
                  <pre class="text-caption" style="max-height:200px;overflow:auto;white-space:pre-wrap">{{ chipaxCsvResult.stats.log.join('\n') }}</pre>
                </VExpansionPanelText>
              </VExpansionPanel>
            </VExpansionPanels>
          </template>

          <VAlert v-if="chipaxCsvError" type="error" variant="tonal" density="compact" class="mt-3">
            {{ chipaxCsvError }}
          </VAlert>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4">
          <VBtn variant="tonal" color="secondary" :loading="chipaxCsvLoading" :disabled="!chipaxCsvArchivo" @click="ejecutarChipaxCsv(true)">
            Vista previa (dry-run)
          </VBtn>
          <VSpacer />
          <VBtn variant="text" @click="cerrarChipaxCsv">Cerrar</VBtn>
          <VBtn
            color="info"
            variant="elevated"
            :loading="chipaxCsvLoading"
            :disabled="!chipaxCsvArchivo"
            @click="ejecutarChipaxCsv(false)"
          >Importar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar -->
    <VSnackbar v-model="snack.show" :color="snack.color" timeout="3500" location="bottom right">
      {{ snack.msg }}
    </VSnackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/axiosInstance'
import * as XLSX from 'xlsx'

// ── Config tipos ───────────────────────────────────────────────────────────────

const tipos = [
  { key: 'credito', label: 'Crédito',  color: 'primary',   icon: 'mdi-credit-card-outline' },
  { key: 'debito',  label: 'Débito',   color: 'info',      icon: 'mdi-credit-card-chip-outline' },
  { key: 'prepago', label: 'Prepago',  color: 'secondary', icon: 'mdi-credit-card-fast-outline' },
]

// ── Estado ─────────────────────────────────────────────────────────────────────

const _hoy    = new Date()
const mesSel  = ref(_hoy.getMonth() + 1)          // 1-12
const anioSel = ref(_hoy.getFullYear())
const periodo = computed(() =>
  `${anioSel.value}-${String(mesSel.value).padStart(2, '0')}`
)

const mesesOpts = [
  { title: 'Enero',      value: 1  },
  { title: 'Febrero',    value: 2  },
  { title: 'Marzo',      value: 3  },
  { title: 'Abril',      value: 4  },
  { title: 'Mayo',       value: 5  },
  { title: 'Junio',      value: 6  },
  { title: 'Julio',      value: 7  },
  { title: 'Agosto',     value: 8  },
  { title: 'Septiembre', value: 9  },
  { title: 'Octubre',    value: 10 },
  { title: 'Noviembre',  value: 11 },
  { title: 'Diciembre',  value: 12 },
]

const aniosOpts = Array.from({ length: 5 }, (_, i) => _hoy.getFullYear() - 2 + i)

function onPeriodoChange() {
  cargar()
}

const tab = ref('banco')

const archivos       = ref([])
const depositos      = ref([])
const resumenPeriodos = ref([])
const siiDetalle     = ref([])
const siiServicios   = ref({})

const loadingArchivos  = ref(false)
const loadingDepositos = ref(false)
const loadingMatch     = ref(false)
const loadingSii       = ref(false)
const eliminando       = ref(null)

const detalleExpandido = ref(null)
const detalleAbonos    = ref({})
const loadingDetalle   = ref(null)

const dialogSubir      = ref(false)
const subirPeriodo     = ref(new Date().toISOString().slice(0, 7))
const subirTipo        = ref('credito')
const subirArchivo     = ref(null)
const subirLoading     = ref(false)
const errorSubir       = ref('')
const periodoDetectado = ref(null)

const dialogMatch            = ref(false)
const depositoSeleccionado   = ref(null)
const movimientosDisponibles = ref([])
const loadingMovimientos     = ref(false)
const matchLoading           = ref(null)

// ── Docs tab state ─────────────────────────────────────────────────────────────

const txFacturas         = ref([])
const txBoletas          = ref(null)
const loadingDocs        = ref(false)
const filtroDoc          = ref('todos')
const loadingAutoLink    = ref(false)
const dialogLinkFactura  = ref(false)
const txSeleccionada     = ref(null)
const facturasDisponibles = ref([])
const loadingFacturas    = ref(false)
const busquedaFactura    = ref('')
const vinculando         = ref(null)
const desvinculando      = ref(null)

const snack = ref({ show: false, msg: '', color: 'success' })

// ── Chipax CSV import ──────────────────────────────────────────────────────────
const dialogChipaxCsv  = ref(false)
const chipaxCsvArchivo = ref(null)
const chipaxCsvLoading = ref(false)
const chipaxCsvResult  = ref(null)
const chipaxCsvError   = ref('')
const chipaxRowCount   = ref(0)
let   chipaxParsedRows = []

// ── Computed ───────────────────────────────────────────────────────────────────

const resumenPeriodo = computed(() =>
  resumenPeriodos.value.find(r => r.periodo === periodo.value) ?? null
)

const depositosConciliados = computed(() =>
  depositos.value.filter(d => d.movimiento_bancario_id).length
)

const periodoLabel = computed(() => {
  const [y, m] = periodo.value.split('-')
  return new Date(+y, +m - 1).toLocaleString('es-CL', { month: 'long', year: 'numeric' })
})

const facturasPendientes = computed(() =>
  txFacturas.value.filter(tx => !tx.documento_id).length
)

const txFacturasFiltradas = computed(() => {
  if (filtroDoc.value === 'sin_vincular') return txFacturas.value.filter(tx => !tx.documento_id)
  if (filtroDoc.value === 'vinculados')   return txFacturas.value.filter(tx =>  tx.documento_id)
  return txFacturas.value
})

// Resumen docs: boletas (tipo_bsale=1) vs facturas vs sin vincular
const resumenDocs = computed(() => {
  const boletas     = { count: 0, monto: 0 }
  const facturas    = { count: 0, monto: 0 }
  const sinVincular = { count: 0, monto: 0 }
  let totalMonto    = 0

  for (const tx of txFacturas.value) {
    const m = n(tx.monto_original)
    totalMonto += m
    if (!tx.documento_id) {
      sinVincular.count++
      sinVincular.monto += m
    } else if (tx.doc_tipo_bsale === 1) {
      boletas.count++
      boletas.monto += m
    } else {
      facturas.count++
      facturas.monto += m
    }
  }
  return { totalMonto, boletas, facturas, sinVincular }
})

// Agrupar detalle SII por sii_periodo
const siiAgrupado = computed(() => {
  const grupos = {}
  for (const row of siiDetalle.value) {
    if (!grupos[row.sii_periodo]) grupos[row.sii_periodo] = []
    grupos[row.sii_periodo].push(row)
  }
  return grupos
})

function archivoTipo(tipo) {
  return archivos.value.find(a => a.tipo === tipo) ?? null
}

function tipoColor(tipo) {
  return tipos.find(t => t.key === tipo)?.color ?? 'default'
}

function sum(campo) {
  return depositos.value.reduce((s, d) => s + (Number(d[campo]) || 0), 0)
}

// ── Helpers ────────────────────────────────────────────────────────────────────

// n() convierte strings de la API a número (evita concatenación en reduce)
function n(v) { return Number(v) || 0 }

function fmt(v) {
  if (v == null) return '—'
  const num = n(v)
  if (isNaN(num)) return '—'
  return '$' + num.toLocaleString('es-CL')
}

function formatFecha(s) {
  if (!s) return '—'
  const parts = String(s).split('-')
  if (parts.length < 3) return s
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

function formatHora(s) {
  if (!s) return '—'
  return String(s).replace('T', ' ').slice(11, 16)
}

function toast(msg, color = 'success') {
  snack.value = { show: true, msg, color }
}

// ── Carga ──────────────────────────────────────────────────────────────────────

async function cargar() {
  detalleExpandido.value = null
  detalleAbonos.value    = {}
  await Promise.all([cargarArchivos(), cargarDepositos(), cargarSii(), cargarDocumentos()])
}

async function cargarArchivos() {
  loadingArchivos.value = true
  try {
    const { data } = await axios.get('/api/transbank', { params: { periodo: periodo.value } })
    archivos.value        = data.archivos
    resumenPeriodos.value = data.resumen_periodos
  } finally {
    loadingArchivos.value = false
  }
}

async function cargarDepositos() {
  loadingDepositos.value = true
  try {
    const { data } = await axios.get('/api/transbank/depositos', { params: { periodo: periodo.value } })
    depositos.value = data
  } finally {
    loadingDepositos.value = false
  }
}

async function cargarSii() {
  loadingSii.value = true
  try {
    const { data } = await axios.get('/api/transbank/resumen-sii', { params: { periodo: periodo.value } })
    siiDetalle.value   = data.detalle
    siiServicios.value = data.servicios
  } finally {
    loadingSii.value = false
  }
}

onMounted(cargar)

// ── Detalle transacciones ─────────────────────────────────────────────────────

async function toggleDetalle(fecha) {
  if (detalleExpandido.value === fecha) {
    detalleExpandido.value = null
    return
  }
  detalleExpandido.value = fecha
  if (detalleAbonos.value[fecha]) return

  loadingDetalle.value = fecha
  try {
    const result = { credito: [], debito: [], prepago: [] }
    for (const archivo of archivos.value) {
      const { data } = await axios.get(`/api/transbank/${archivo.id}/abonos`)
      const abono = data.find(a => a.fecha_abono === fecha)
      if (abono?.transacciones?.length) {
        result[archivo.tipo] = abono.transacciones
      }
    }
    detalleAbonos.value[fecha] = result
  } finally {
    loadingDetalle.value = null
  }
}

// ── Subir ──────────────────────────────────────────────────────────────────────

function abrirSubirTipo(tipo) {
  subirTipo.value        = tipo
  subirPeriodo.value     = periodo.value
  subirArchivo.value     = null
  errorSubir.value       = ''
  periodoDetectado.value = null
  dialogSubir.value      = true
}

// Pokayoke: detectar periodo desde el nombre del .dat
// Formato: extraccion-masiva-credito-pesos-1-al-{dia}-{mes}-{año}(...)
function onArchivoSeleccionado(file) {
  periodoDetectado.value = null
  if (!file) return
  const nombre = file.name ?? ''
  const match  = nombre.match(/al-\d+-(\d+)-(\d{4})/i)
  if (match) {
    const mes  = String(match[1]).padStart(2, '0')
    const anio = match[2]
    periodoDetectado.value = `${anio}-${mes}`
    // Auto-completar si el picker aún tiene el default o el periodo actual
    if (!subirPeriodo.value || subirPeriodo.value === periodo.value) {
      subirPeriodo.value = periodoDetectado.value
    }
  }
}

async function subirArchivos() {
  errorSubir.value   = ''
  subirLoading.value = true
  const fd = new FormData()
  fd.append('archivo', subirArchivo.value)
  fd.append('tipo',    subirTipo.value)
  fd.append('periodo', subirPeriodo.value)
  try {
    await axios.post('/api/transbank/subir', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    dialogSubir.value      = false
    subirArchivo.value     = null
    periodoDetectado.value = null
    const [y, m]  = subirPeriodo.value.split('-')
    anioSel.value = parseInt(y)
    mesSel.value  = parseInt(m)
    await cargar()
    toast('Archivo subido y procesado correctamente')
  } catch (e) {
    errorSubir.value = e.response?.data?.error ?? 'Error al subir el archivo'
  } finally {
    subirLoading.value = false
  }
}

// ── Eliminar ───────────────────────────────────────────────────────────────────

async function eliminar(archivo) {
  if (!confirm(`¿Eliminar "${archivo.nombre_archivo}"?\nSe borrarán todos los abonos asociados.`)) return
  eliminando.value = archivo.id
  try {
    await axios.delete(`/api/transbank/${archivo.id}`)
    await cargar()
    toast('Archivo eliminado')
  } catch {
    toast('Error al eliminar', 'error')
  } finally {
    eliminando.value = null
  }
}

// ── Auto-match ─────────────────────────────────────────────────────────────────

async function autoMatch() {
  loadingMatch.value = true
  try {
    const { data } = await axios.post('/api/transbank/auto-match', { periodo: periodo.value })
    await cargar()
    toast(`Auto-conciliación: ${data.matched} de ${data.revisados} depósito(s) conciliado(s)`)
  } catch {
    toast('Error en auto-conciliación', 'error')
  } finally {
    loadingMatch.value = false
  }
}

// ── Match manual ───────────────────────────────────────────────────────────────

async function abrirMatchManual(deposito) {
  depositoSeleccionado.value   = deposito
  movimientosDisponibles.value = []
  dialogMatch.value            = true
  loadingMovimientos.value     = true
  try {
    const { data } = await axios.get('/api/movimientos-bancarios', {
      params: {
        tipo:  'C',
        desde: offsetDias(deposito.fecha_abono, -5),
        hasta: offsetDias(deposito.fecha_abono, +3),
      },
    })
    movimientosDisponibles.value = (data.data ?? data).filter(m => Number(m.monto) > 0)
  } catch {
    movimientosDisponibles.value = []
  } finally {
    loadingMovimientos.value = false
  }
}

function offsetDias(fecha, dias) {
  const d = new Date(fecha)
  d.setDate(d.getDate() + dias)
  return d.toISOString().slice(0, 10)
}

async function confirmarMatch(movimiento) {
  matchLoading.value = movimiento.id
  try {
    await axios.post('/api/transbank/deposito/match', {
      fecha_abono:            depositoSeleccionado.value.fecha_abono,
      periodo:                periodo.value,
      movimiento_bancario_id: movimiento.id,
    })
    dialogMatch.value = false
    await cargar()
    toast('Depósito conciliado correctamente')
  } catch {
    toast('Error asignando movimiento', 'error')
  } finally {
    matchLoading.value = null
  }
}

async function desasignarDeposito(deposito) {
  try {
    await axios.post('/api/transbank/deposito/match', {
      fecha_abono:            deposito.fecha_abono,
      periodo:                periodo.value,
      movimiento_bancario_id: null,
    })
    await cargar()
    toast('Asignación removida')
  } catch {
    toast('Error', 'error')
  }
}

// ── Documentos tab ─────────────────────────────────────────────────────────────

async function cargarDocumentos() {
  loadingDocs.value = true
  filtroDoc.value   = 'todos'
  try {
    const { data } = await axios.get('/api/transbank/documentos', { params: { periodo: periodo.value } })
    txFacturas.value = data.facturas
    txBoletas.value  = data.boletas
  } finally {
    loadingDocs.value = false
  }
}

async function autoLinkFacturas() {
  loadingAutoLink.value = true
  try {
    const { data } = await axios.post('/api/transbank/auto-link', null, {
      params: { periodo: periodo.value },
    })
    await cargarDocumentos()
    toast(`Auto-vinculación: ${data.linked} de ${data.revisadas} transacción(es) vinculada(s)`)
  } catch {
    toast('Error en auto-vinculación', 'error')
  } finally {
    loadingAutoLink.value = false
  }
}

function abrirLinkFactura(tx) {
  txSeleccionada.value     = tx
  facturasDisponibles.value = []
  busquedaFactura.value    = ''
  dialogLinkFactura.value  = true
  buscarFacturas()
}

async function buscarFacturas() {
  loadingFacturas.value = true
  try {
    const { data } = await axios.get('/api/transbank/facturas-disponibles', {
      params: {
        q:       busquedaFactura.value,
        monto:   txSeleccionada.value?.monto_original,
        periodo: periodo.value,
      },
    })
    facturasDisponibles.value = data
  } finally {
    loadingFacturas.value = false
  }
}

async function confirmarLinkFactura(doc) {
  vinculando.value = doc.id
  try {
    await axios.post(`/api/transbank/transaccion/${txSeleccionada.value.id}/link`, {
      documento_id: doc.id,
    })
    dialogLinkFactura.value = false
    await cargarDocumentos()
    toast('Transacción vinculada correctamente')
  } catch {
    toast('Error al vincular', 'error')
  } finally {
    vinculando.value = null
  }
}

async function desasociarFactura(tx) {
  desvinculando.value = tx.id
  try {
    await axios.delete(`/api/transbank/transaccion/${tx.id}/link`)
    await cargarDocumentos()
    toast('Vinculación removida')
  } catch {
    toast('Error', 'error')
  } finally {
    desvinculando.value = null
  }
}

// ── Chipax CSV import ──────────────────────────────────────────────────────────

function cerrarChipaxCsv() {
  dialogChipaxCsv.value  = false
  chipaxCsvArchivo.value = null
  chipaxCsvResult.value  = null
  chipaxCsvError.value   = ''
  chipaxRowCount.value   = 0
  chipaxParsedRows       = []
}

async function onChipaxArchivoChange(file) {
  chipaxRowCount.value = 0
  chipaxParsedRows     = []
  chipaxCsvResult.value = null
  if (!file) return
  try {
    chipaxParsedRows   = await parseXlsxToRows(file)
    chipaxRowCount.value = chipaxParsedRows.length - 1 // minus header
  } catch {
    chipaxCsvError.value = 'No se pudo leer el archivo. Asegúrate de que sea .xlsx o .csv de Chipax.'
  }
}

function parseXlsxToRows(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = (e) => {
      try {
        const data     = new Uint8Array(e.target.result)
        const workbook = XLSX.read(data, { type: 'array' })
        const sheet    = workbook.Sheets[workbook.SheetNames[0]]
        // header:1 → array of arrays; defval:'' → empty cells as empty string
        const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' })
        resolve(rows)
      } catch (err) {
        reject(err)
      }
    }
    reader.onerror = reject
    reader.readAsArrayBuffer(file)
  })
}

async function ejecutarChipaxCsv(dryRun) {
  chipaxCsvError.value  = ''
  chipaxCsvResult.value = null

  if (!chipaxParsedRows.length) {
    chipaxCsvError.value = 'El archivo no pudo ser leído. Selecciónalo de nuevo.'
    return
  }

  chipaxCsvLoading.value = true
  try {
    const { data } = await axios.post('/api/transbank/chipax-csv', {
      rows:    chipaxParsedRows,
      dry_run: dryRun ? '1' : '0',
    })
    chipaxCsvResult.value = data
    if (!dryRun && data.ok) {
      toast(`Importación OK: ${data.stats.facturas_vinculadas} facturas vinculadas, ${data.stats.conciliados} movimientos conciliados`)
      await cargar()
    }
  } catch (e) {
    chipaxCsvError.value = e.response?.data?.message ?? 'Error al procesar el archivo'
  } finally {
    chipaxCsvLoading.value = false
  }
}
</script>

<style scoped>
.dep-row { cursor: pointer; transition: background 0.15s; }
.dep-row:hover { background: rgba(var(--v-theme-on-surface), 0.04); }
.dep-row.conciliado td:first-child { border-left: 3px solid rgb(var(--v-theme-success)); }
.dep-row.pendiente  td:first-child { border-left: 3px solid rgb(var(--v-theme-warning)); }
.match-ideal { background: rgba(var(--v-theme-success), 0.08); }
.min-w-0 { min-width: 0; }
.doc-linked td:first-child { border-left: 3px solid rgb(var(--v-theme-success)); }
</style>
