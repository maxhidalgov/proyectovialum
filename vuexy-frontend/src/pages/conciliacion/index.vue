<template>
  <div>
    <!-- Header -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h4 class="text-h5 font-weight-bold">Conciliación Bancaria</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Banco de Chile — Cuenta {{ cuenta }}</p>
      </VCol>
      <VCol cols="auto" class="d-flex gap-2">
        <VBtn
          variant="tonal"
          color="info"
          prepend-icon="mdi-refresh"
          :loading="loadingSaldo"
          @click="cargarSaldo"
        >Saldo</VBtn>
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="mdi-link-variant"
          :loading="loadingMatch"
          @click="autoConcilar"
        >Auto-conciliar</VBtn>
        <VBtn
          color="primary"
          prepend-icon="mdi-cloud-download"
          :loading="loadingImport"
          @click="dialogImportar = true"
        >Importar BCH</VBtn>
        <VBtn
          color="success"
          prepend-icon="mdi-file-upload"
          :loading="loadingCartola"
          @click="dialogCartola = true"
        >Importar Cartola</VBtn>
      </VCol>
    </VRow>

    <!-- Cards resumen -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Saldo Disponible</p>
            <p class="text-h5 font-weight-bold text-primary mb-0">
              {{ saldo !== null ? formatMonto(saldo) : '—' }}
            </p>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Ingresos del período</p>
            <p class="text-h5 font-weight-bold text-success mb-1">
              {{ formatMonto(totales.total_creditos || 0) }}
            </p>
            <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-1">
              <span>{{ totales.conciliados_creditos || 0 }}/{{ totales.total_creditos_count || 0 }} conciliados</span>
              <span class="font-weight-medium" :class="pctCreditos === 100 ? 'text-success' : ''">{{ pctCreditos }}%</span>
            </div>
            <VProgressLinear :model-value="pctCreditos" color="success" bg-color="rgba(40,199,111,0.15)" rounded height="5" />
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Egresos del período</p>
            <p class="text-h5 font-weight-bold text-error mb-1">
              {{ formatMonto(totales.total_debitos || 0) }}
            </p>
            <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-1">
              <span>{{ totales.conciliados_debitos || 0 }}/{{ totales.total_debitos_count || 0 }} conciliados</span>
              <span class="font-weight-medium" :class="pctDebitos === 100 ? 'text-success' : ''">{{ pctDebitos }}%</span>
            </div>
            <VProgressLinear :model-value="pctDebitos" color="error" bg-color="rgba(234,84,85,0.15)" rounded height="5" />
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" lg="3">
        <VCard>
          <VCardText>
            <p class="text-body-2 text-medium-emphasis mb-1">Progreso conciliación</p>
            <p class="text-h5 font-weight-bold mb-1" :class="pctTotal === 100 ? 'text-success' : 'text-warning'">
              {{ pctTotal }}%
            </p>
            <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-1">
              <span>{{ (totales.total_movimientos || 0) - (totales.pendientes || 0) }} de {{ totales.total_movimientos || 0 }} movimientos</span>
              <span>{{ totales.pendientes || 0 }} pendientes</span>
            </div>
            <VProgressLinear :model-value="pctTotal" :color="pctTotal === 100 ? 'success' : 'warning'" bg-color="rgba(255,159,67,0.15)" rounded height="5" />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabs -->
    <VCard>
      <VTabs v-model="tab" color="primary">
        <VTab value="movimientos">Movimientos</VTab>
        <VTab value="flujo">Flujo de Caja</VTab>
        <VTab value="reglas">Reglas</VTab>
      </VTabs>
      <VDivider />

      <!-- Tab Movimientos -->
      <VWindow v-model="tab">
        <VWindowItem value="movimientos">
          <!-- Filtros -->
          <VCardText>
            <VRow dense>
              <VCol cols="12" sm="6" md="2">
                <VTextField
                  v-model="filtros.desde"
                  label="Desde"
                  type="date"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @change="cargarMovimientos"
                />
              </VCol>
              <VCol cols="12" sm="6" md="2">
                <VTextField
                  v-model="filtros.hasta"
                  label="Hasta"
                  type="date"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @change="cargarMovimientos"
                />
              </VCol>
              <VCol cols="6" md="2">
                <VSelect
                  v-model="filtros.tipo"
                  label="Tipo"
                  density="compact"
                  variant="outlined"
                  hide-details
                  :items="[{ title: 'Todos', value: '' }, { title: 'Crédito', value: 'C' }, { title: 'Débito', value: 'D' }]"
                  item-title="title"
                  item-value="value"
                  @update:modelValue="cargarMovimientos"
                />
              </VCol>
              <VCol cols="6" md="2">
                <VSelect
                  v-model="filtros.conciliado"
                  label="Estado"
                  density="compact"
                  variant="outlined"
                  hide-details
                  :items="[{ title: 'Todos', value: '' }, { title: 'Pendientes', value: 'false' }, { title: 'Conciliados', value: 'true' }]"
                  item-title="title"
                  item-value="value"
                  @update:modelValue="cargarMovimientos"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="filtros.buscar"
                  label="Buscar descripción"
                  density="compact"
                  variant="outlined"
                  hide-details
                  prepend-inner-icon="mdi-magnify"
                  clearable
                  @update:modelValue="debounceBuscar"
                />
              </VCol>
            </VRow>
          </VCardText>

          <!-- Barra de acción cuando hay seleccionados -->
          <div v-if="seleccionados.length > 0" class="mx-4 mb-2">
            <VAlert color="primary" variant="tonal" density="compact">
              <div class="d-flex align-center justify-space-between w-100 flex-wrap gap-2">
                <!-- Resumen de selección -->
                <span class="text-body-2 d-flex align-center gap-2 flex-wrap">
                  <template v-if="seleccionadosDebitos.length">
                    <VChip size="x-small" color="error" variant="flat">{{ seleccionadosDebitos.length }} egresos</VChip>
                    <strong>{{ formatMonto(sumaSeleccionados) }}</strong>
                  </template>
                  <template v-if="seleccionadosDebitos.length && seleccionadosCreditos.length">
                    <span class="text-medium-emphasis">·</span>
                  </template>
                  <template v-if="seleccionadosCreditos.length">
                    <VChip size="x-small" color="success" variant="flat">{{ seleccionadosCreditos.length }} ingresos</VChip>
                    <strong>{{ formatMonto(sumaSeleccionadosCreditos) }}</strong>
                  </template>
                </span>
                <!-- Acciones -->
                <div class="d-flex gap-2 flex-wrap">
                  <!-- Acciones para egresos -->
                  <template v-if="seleccionadosDebitos.length">
                    <VBtn size="small" color="primary" variant="flat" prepend-icon="mdi-plus-circle-outline"
                      @click="abrirCrearGastoMultiple">Crear gasto</VBtn>
                    <VBtn size="small" color="purple" variant="flat" prepend-icon="mdi-account-cash"
                      @click="abrirCrearSueldoMultiple">Crear sueldo</VBtn>
                  </template>
                  <!-- Acciones para ingresos -->
                  <template v-if="seleccionadosCreditos.length">
                    <VBtn size="small" color="success" variant="flat" prepend-icon="mdi-receipt-text-plus"
                      @click="abrirRegistrarIngresos">Registrar ingresos s/doc</VBtn>
                    <VBtn size="small" color="success" variant="tonal" prepend-icon="mdi-link-variant"
                      @click="abrirVincularVentaMultiple">Vincular a venta Bsale</VBtn>
                  </template>
                  <VBtn size="small" variant="text" @click="seleccionados = []">Cancelar</VBtn>
                </div>
              </div>
            </VAlert>
          </div>

          <!-- Tabla -->
          <VDataTable
            v-model="seleccionados"
            show-select
            :headers="headers"
            :items="movimientos"
            :loading="loadingTable"
            item-value="id"
            density="compact"
            class="text-no-wrap"
          >
            <!-- Fecha -->
            <template #item.fecha_contable="{ item }">
              {{ formatFecha(item.fecha_contable) }}
            </template>

            <!-- Descripción + glosa -->
            <template #item.descripcion="{ item }">
              <div>
                <span>{{ item.descripcion }}</span>
                <div v-if="item.glosa" class="text-caption text-medium-emphasis mt-n1">
                  <VIcon size="11" class="mr-1">mdi-comment-text-outline</VIcon>{{ item.glosa }}
                </div>
              </div>
            </template>

            <!-- Monto con color -->
            <template #item.monto="{ item }">
              <span :class="item.tipo === 'C' ? 'text-success' : 'text-error'" class="font-weight-medium">
                {{ item.tipo === 'C' ? '+' : '-' }}{{ formatMonto(item.monto) }}
              </span>
            </template>

            <!-- Tipo chip -->
            <template #item.tipo="{ item }">
              <VChip
                size="x-small"
                :color="item.tipo === 'C' ? 'success' : 'error'"
                variant="tonal"
              >{{ item.tipo === 'C' ? 'Crédito' : 'Débito' }}</VChip>
            </template>

            <!-- Categoría editable -->
            <template #item.categoria="{ item }">
              <VSelect
                :model-value="item.categoria"
                :items="categorias"
                density="compact"
                variant="plain"
                hide-details
                placeholder="Sin categoría"
                style="min-width: 140px"
                @update:modelValue="(v) => actualizarMov(item, { categoria: v })"
              />
            </template>

            <!-- Estado conciliación: saldo por asignar -->
            <template #item.saldo_por_asignar="{ item }">
              <div v-if="item.tipo === 'D'" class="text-end" style="cursor: pointer" @click="abrirConciliar(item)">
                <VChip v-if="item.conciliado" size="x-small" color="success" variant="tonal">
                  <VIcon start size="11">mdi-check</VIcon> Conciliado
                </VChip>
                <span v-else-if="item.saldo_por_asignar > 0" class="text-caption text-warning font-weight-bold">
                  {{ formatMonto(item.saldo_por_asignar) }} por conciliar
                </span>
                <VChip v-else size="x-small" color="success" variant="tonal">
                  <VIcon start size="11">mdi-check</VIcon> Conciliado
                </VChip>
              </div>
              <div v-else-if="item.tipo === 'C'" class="text-end" style="cursor: pointer" @click="abrirConciliar(item)">
                <VChip v-if="item.conciliado" size="x-small" color="success" variant="tonal">
                  <VIcon start size="11">mdi-check</VIcon> Conciliado
                </VChip>
                <span v-else class="text-caption text-warning font-weight-bold">Por conciliar</span>
              </div>
              <span v-else class="text-caption text-medium-emphasis">—</span>
            </template>

            <!-- Conciliado toggle -->
            <template #item.conciliado="{ item }">
              <VSwitch
                :model-value="item.conciliado"
                density="compact"
                hide-details
                color="success"
                @update:modelValue="(v) => actualizarMov(item, { conciliado: v })"
              />
            </template>

            <!-- Acciones -->
            <template #item.actions="{ item }">
              <!-- Débito: vincular a compras / gastos / sueldos -->
              <div v-if="item.tipo === 'D'" class="d-flex">
                <VBtn
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  style="border-radius: 4px 0 0 4px"
                  @click="abrirConciliar(item)"
                >
                  <VIcon size="14" class="mr-1">mdi-link-variant</VIcon>Conciliar
                </VBtn>
                <VMenu location="bottom end">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      size="x-small"
                      variant="tonal"
                      color="primary"
                      style="border-radius: 0 4px 4px 0; border-left: 1px solid rgba(255,255,255,0.3); min-width: 20px; padding: 0 4px"
                    >
                      <VIcon size="12">mdi-chevron-down</VIcon>
                    </VBtn>
                  </template>
                  <VList density="compact" min-width="220">
                    <VListItem
                      prepend-icon="mdi-link-variant"
                      title="Vincular documento existente"
                      @click="abrirConciliar(item)"
                    />
                    <VDivider />
                    <VListItem
                      prepend-icon="mdi-plus-circle-outline"
                      title="Crear gasto general"
                      @click="abrirCrearGasto(item)"
                    />
                    <VListItem
                      prepend-icon="mdi-account-cash"
                      title="Crear sueldo"
                      @click="abrirCrearSueldo(item)"
                    />
                  </VList>
                </VMenu>
              </div>
              <!-- Crédito: vincular a ventas (facturas emitidas) -->
              <div v-else-if="item.tipo === 'C'" class="d-flex">
                <VBtn
                  size="x-small"
                  variant="tonal"
                  color="success"
                  style="border-radius: 4px 0 0 4px"
                  @click="abrirConciliar(item)"
                >
                  <VIcon size="14" class="mr-1">mdi-link-variant</VIcon>Conciliar
                </VBtn>
                <VMenu location="bottom end">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      size="x-small"
                      variant="tonal"
                      color="success"
                      style="border-radius: 0 4px 4px 0; border-left: 1px solid rgba(255,255,255,0.3); min-width: 20px; padding: 0 4px"
                    >
                      <VIcon size="12">mdi-chevron-down</VIcon>
                    </VBtn>
                  </template>
                  <VList density="compact" min-width="220">
                    <VListItem
                      prepend-icon="mdi-link-variant"
                      title="Vincular a venta / factura emitida"
                      @click="abrirConciliar(item)"
                    />
                    <VDivider />
                    <VListItem
                      prepend-icon="mdi-check-circle-outline"
                      title="Marcar conciliado directamente"
                      @click="marcarConciliadoDirecto(item)"
                    />
                  </VList>
                </VMenu>
              </div>
            </template>

          </VDataTable>
        </VWindowItem>

        <!-- Tab Flujo de Caja -->
        <VWindowItem value="flujo">
          <VCardText>
            <VRow dense class="mb-4">
              <VCol cols="12" sm="4" md="2">
                <VTextField
                  v-model="filtroFlujo.desde"
                  label="Desde"
                  type="date"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @change="cargarFlujo"
                />
              </VCol>
              <VCol cols="12" sm="4" md="2">
                <VTextField
                  v-model="filtroFlujo.hasta"
                  label="Hasta"
                  type="date"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @change="cargarFlujo"
                />
              </VCol>
            </VRow>
            <VueApexCharts
              v-if="flujoCajaData.length"
              type="bar"
              height="320"
              :options="chartOptions"
              :series="chartSeries"
            />
            <p v-else class="text-center text-medium-emphasis py-8">Sin datos para el período</p>
          </VCardText>
        </VWindowItem>
        <!-- Tab Reglas -->
        <VWindowItem value="reglas">
          <VCardText>
            <VRow class="mb-3" align="center">
              <VCol>
                <p class="text-body-2 text-medium-emphasis mb-0">
                  Define patrones de texto para categorizar movimientos automáticamente al importar.
                </p>
              </VCol>
              <VCol cols="auto" class="d-flex gap-2">
                <VBtn variant="tonal" color="warning" size="small" :loading="loadingAplicar" @click="aplicarReglas">
                  Aplicar a existentes
                </VBtn>
                <VBtn color="primary" size="small" prepend-icon="mdi-plus" @click="abrirNuevaRegla">
                  Nueva regla
                </VBtn>
              </VCol>
            </VRow>

            <VDataTable
              :headers="headersReglas"
              :items="reglas"
              :loading="loadingReglas"
              density="compact"
              item-value="id"
            >
              <template #item.tipo="{ item }">
                <VChip size="x-small" :color="item.tipo === 'C' ? 'success' : item.tipo === 'D' ? 'error' : 'info'" variant="tonal">
                  {{ item.tipo === 'C' ? 'Crédito' : item.tipo === 'D' ? 'Débito' : 'Ambos' }}
                </VChip>
              </template>
              <template #item.activa="{ item }">
                <VSwitch
                  :model-value="item.activa"
                  density="compact"
                  hide-details
                  color="success"
                  @update:modelValue="(v) => actualizarRegla(item, { activa: v })"
                />
              </template>
              <template #item.actions="{ item }">
                <div class="d-flex gap-1">
                  <VBtn size="x-small" variant="tonal" color="primary" icon @click="editarRegla(item)">
                    <VIcon size="14">mdi-pencil</VIcon>
                  </VBtn>
                  <VBtn size="x-small" variant="tonal" color="error" icon @click="eliminarRegla(item.id)">
                    <VIcon size="14">mdi-delete</VIcon>
                  </VBtn>
                </div>
              </template>
            </VDataTable>
          </VCardText>
        </VWindowItem>
      </VWindow>
    </VCard>

    <!-- Dialog Nueva/Editar Regla -->
    <VDialog v-model="dialogRegla" max-width="480">
      <VCard :title="reglaEditando?.id ? 'Editar Regla' : 'Nueva Regla'">
        <VCardText>
          <VRow dense>
            <VCol cols="12">
              <VTextField v-model="reglaForm.nombre" label="Nombre" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="reglaForm.patron"
                label="Patrón (texto a buscar en descripción)"
                density="compact"
                variant="outlined"
                hide-details
                hint="Ej: TRANSF, SUELDO, ARRIENDO — no distingue mayúsculas"
                persistent-hint
              />
            </VCol>
            <VCol cols="12" sm="8">
              <VSelect
                v-model="reglaForm.categoria"
                label="Categoría"
                density="compact"
                variant="outlined"
                hide-details
                :items="categorias"
                :return-object="false"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VSelect
                v-model="reglaForm.tipo"
                label="Tipo"
                density="compact"
                variant="outlined"
                hide-details
                :items="[{ title: 'Ambos', value: 'A' }, { title: 'Crédito', value: 'C' }, { title: 'Débito', value: 'D' }]"
                item-title="title"
                item-value="value"
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model.number="reglaForm.prioridad"
                label="Prioridad (1 = primero)"
                type="number"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>
            <VCol cols="6" class="d-flex align-center">
              <VSwitch v-model="reglaForm.activa" label="Activa" color="success" hide-details density="compact" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogRegla = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="savingRegla" @click="guardarRegla">Guardar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Importar -->
    <VDialog v-model="dialogImportar" max-width="420">
      <VCard title="Importar Movimientos">
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Descarga los movimientos desde Banco de Chile para el rango seleccionado.
          </p>
          <VRow dense>
            <VCol cols="6">
              <VTextField
                v-model="importForm.desde"
                label="Desde"
                type="date"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model="importForm.hasta"
                label="Hasta"
                type="date"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>
          </VRow>
          <VAlert
            v-if="importResult"
            class="mt-4"
            :color="importResult.error ? 'error' : 'success'"
            variant="tonal"
          >
            <span v-if="importResult.error">{{ importResult.error }}</span>
            <span v-else>
              {{ importResult.nuevos }} nuevos · {{ importResult.duplicados }} duplicados
              ({{ importResult.total }} total)
              <span v-if="importResult.errores?.length" class="d-block text-caption mt-1">
                Errores ({{ importResult.errores.length }}): {{ importResult.errores[0] }}
              </span>
            </span>
          </VAlert>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogImportar = false">Cerrar</VBtn>
          <VBtn color="primary" :loading="loadingImport" @click="importar">Importar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Importar Cartola CSV -->
    <VDialog v-model="dialogCartola" max-width="440">
      <VCard title="Importar Cartola CSV">
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Sube el archivo CSV exportado desde el sitio web de Banco de Chile
            (Cartola → Exportar → CSV).
          </p>
          <VFileInput
            v-model="cartolaArchivo"
            label="Archivo cartola (.csv)"
            accept=".csv,.txt"
            prepend-icon="mdi-file-delimited"
            variant="outlined"
            density="compact"
            hide-details
          />
          <VAlert
            v-if="cartolaResult"
            class="mt-4"
            :color="cartolaResult.error ? 'error' : 'success'"
            variant="tonal"
          >
            <span v-if="cartolaResult.error">{{ cartolaResult.error }}</span>
            <span v-else>
              {{ cartolaResult.nuevos }} nuevos · {{ cartolaResult.duplicados }} duplicados
              ({{ cartolaResult.total }} total)
              <span v-if="cartolaResult.errores?.length" class="d-block text-caption mt-1">
                Errores ({{ cartolaResult.errores.length }}): {{ cartolaResult.errores[0] }}
              </span>
            </span>
          </VAlert>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogCartola = false">Cerrar</VBtn>
          <VBtn color="success" :loading="loadingCartola" :disabled="!cartolaArchivo" @click="importarCartola">Importar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Modal Conciliar Movimiento ↔ Facturas ───────────────────────────── -->
    <VDialog v-model="dialogConciliar" max-width="1300" scrollable>
      <VCard>
        <VCardTitle class="pa-4 d-flex align-center justify-space-between" style="background: rgb(var(--v-theme-primary)); color: white">
          <span>Conciliar Movimiento</span>
          <VBtn icon variant="text" color="white" @click="dialogConciliar = false; cargarMovimientos()">
            <VIcon>mdi-close</VIcon>
          </VBtn>
        </VCardTitle>

        <VCardText class="pa-4">
          <VRow>
            <!-- Panel izq: movimiento + facturas asignadas -->
            <VCol cols="12" md="4">
              <p class="text-overline text-medium-emphasis mb-2">Movimiento Bancario</p>
              <VCard variant="outlined" class="mb-4">
                <VCardText class="pa-3">
                  <div class="d-flex justify-space-between align-start mb-1">
                    <span class="text-body-2 font-weight-bold" style="max-width:220px">{{ movConciliando?.descripcion }}</span>
                    <VChip size="x-small" :color="movConciliando?.tipo === 'C' ? 'success' : 'error'" variant="tonal" class="ml-1">
                      {{ movConciliando?.tipo === 'C' ? 'Crédito' : 'Débito' }}
                    </VChip>
                  </div>
                  <div class="text-caption text-medium-emphasis">{{ movConciliando?.fecha_contable }}</div>
                  <div v-if="movConciliando?.glosa" class="text-caption text-medium-emphasis mt-1">
                    <VIcon size="11" class="mr-1">mdi-comment-text-outline</VIcon>{{ movConciliando.glosa }}
                  </div>
                  <VDivider class="my-2" />
                  <div class="d-flex justify-space-between">
                    <span class="text-caption">Monto total</span>
                    <span class="font-weight-bold">{{ formatMonto(movConciliando?.monto) }}</span>
                  </div>
                  <div class="d-flex justify-space-between mt-1">
                    <span class="text-caption">Saldo por asignar</span>
                    <span :class="concSaldoPorAsignar > 0 ? 'text-warning font-weight-bold' : 'text-success font-weight-bold'">
                      {{ formatMonto(concSaldoPorAsignar) }}
                    </span>
                  </div>
                  <VProgressLinear
                    :model-value="movConciliando?.monto ? ((movConciliando.monto - concSaldoPorAsignar) / movConciliando.monto) * 100 : 0"
                    color="success"
                    rounded
                    height="6"
                    class="mt-2"
                  />
                </VCardText>
              </VCard>

              <p class="text-overline text-medium-emphasis mb-2">Documentos asignados</p>

              <!-- ── CRÉDITO: ventas vinculadas + ingresos manuales ── -->
              <template v-if="movConciliando?.tipo === 'C'">
                <p v-if="!concVentasAsignadas.length && !concIngresosAsignados.length" class="text-caption text-medium-emphasis mb-4">
                  Ningún documento asignado aún
                </p>
                <!-- ventas Bsale asignadas -->
                <VCard v-for="a in concVentasAsignadas" :key="'v'+a.pivot_id" variant="tonal" color="success" class="mb-2">
                  <VCardText class="pa-2 d-flex align-center justify-space-between">
                    <div>
                      <VChip size="x-small" color="success" variant="flat" class="mb-1">{{ a.tipo_doc || 'Venta' }}</VChip>
                      <div class="text-caption font-weight-bold">{{ a.nombre_receptor }}</div>
                      <div class="text-caption text-medium-emphasis">F. {{ a.folio }} · {{ formatFecha(a.fecha_emision) }}</div>
                      <div class="text-caption">Asignado: <strong>{{ formatMonto(a.monto_asignado) }}</strong></div>
                    </div>
                    <VBtn icon size="x-small" variant="text" color="error" :loading="loadingDesasignar === 'v'+a.pivot_id" @click="desasignarVenta(a.pivot_id)">
                      <VIcon size="16">mdi-close-circle</VIcon>
                    </VBtn>
                  </VCardText>
                </VCard>
                <!-- ingresos manuales asignados -->
                <VCard v-for="a in concIngresosAsignados" :key="'i'+a.pivot_id" variant="tonal" color="teal" class="mb-2">
                  <VCardText class="pa-2 d-flex align-center justify-space-between">
                    <div>
                      <VChip size="x-small" color="teal" variant="flat" class="mb-1">Sin doc SII</VChip>
                      <div class="text-caption font-weight-bold">{{ a.descripcion }}</div>
                      <div class="text-caption text-medium-emphasis">{{ a.fecha }} · {{ a.categoria }}</div>
                      <div class="text-caption">Asignado: <strong>{{ formatMonto(a.monto_asignado) }}</strong></div>
                    </div>
                    <VBtn icon size="x-small" variant="text" color="error" :loading="loadingDesasignar === 'i'+a.pivot_id" @click="desasignarIngreso(a.pivot_id)">
                      <VIcon size="16">mdi-close-circle</VIcon>
                    </VBtn>
                  </VCardText>
                </VCard>
              </template>

              <!-- ── DÉBITO: facturas / sueldos / gastos vinculados ── -->
              <template v-else>
                <p v-if="!concAsignadas.length && !concGastosAsignados.length && !concSueldosAsignados.length" class="text-caption text-medium-emphasis mb-4">
                  Ningún documento asignado aún
                </p>
                <!-- facturas asignadas -->
                <VCard v-for="a in concAsignadas" :key="'f'+a.pivot_id" variant="tonal" color="success" class="mb-2">
                  <VCardText class="pa-2 d-flex align-center justify-space-between">
                    <div>
                      <VChip size="x-small" color="primary" variant="flat" class="mb-1">Factura</VChip>
                      <div class="text-caption font-weight-bold">{{ a.nombre_emisor }}</div>
                      <div class="text-caption text-medium-emphasis">F. {{ a.folio }} · {{ formatFecha(a.fecha_emision) }}</div>
                      <div class="text-caption">Asignado: <strong>{{ formatMonto(a.monto_asignado) }}</strong></div>
                    </div>
                    <VBtn icon size="x-small" variant="text" color="error" :loading="loadingDesasignar === 'c'+a.pivot_id" @click="desasignarCompra(a.pivot_id)">
                      <VIcon size="16">mdi-close-circle</VIcon>
                    </VBtn>
                  </VCardText>
                </VCard>
                <!-- sueldos asignados -->
                <VCard v-for="a in concSueldosAsignados" :key="'s'+a.pago_id" variant="tonal" color="purple" class="mb-2">
                  <VCardText class="pa-2 d-flex align-center justify-space-between">
                    <div>
                      <VChip size="x-small" color="purple" variant="flat" class="mb-1">Sueldo</VChip>
                      <div class="text-caption font-weight-bold">{{ a.empleado_nombre }}</div>
                      <div class="text-caption text-medium-emphasis">{{ a.periodo?.slice(0,7) }} · {{ a.tipo }}</div>
                      <div class="text-caption">{{ formatMonto(a.monto) }}</div>
                    </div>
                    <VBtn icon size="x-small" variant="text" color="error" :loading="loadingDesasignar === 's'+a.pago_id" @click="desasignarSueldo(a.pago_id)">
                      <VIcon size="16">mdi-close-circle</VIcon>
                    </VBtn>
                  </VCardText>
                </VCard>
                <!-- gastos asignados -->
                <VCard v-for="a in concGastosAsignados" :key="'g'+a.pivot_id" variant="tonal" color="info" class="mb-2">
                  <VCardText class="pa-2 d-flex align-center justify-space-between">
                    <div>
                      <VChip size="x-small" color="info" variant="flat" class="mb-1">Gasto</VChip>
                      <div class="text-caption font-weight-bold">{{ a.descripcion }}</div>
                      <div class="text-caption text-medium-emphasis">{{ a.fecha }} · {{ a.categoria }}</div>
                      <div class="text-caption">Asignado: <strong>{{ formatMonto(a.monto_asignado) }}</strong></div>
                    </div>
                    <VBtn icon size="x-small" variant="text" color="error" :loading="loadingDesasignar === 'g'+a.pivot_id" @click="desasignarGasto(a.pivot_id)">
                      <VIcon size="16">mdi-close-circle</VIcon>
                    </VBtn>
                  </VCardText>
                </VCard>
              </template>
            </VCol>

            <!-- Panel der: tabs Facturas / Gastos -->
            <VCol cols="12" md="8">
              <div class="d-flex align-center justify-space-between mb-2">
                <p class="text-overline text-medium-emphasis mb-0">
                  {{ movConciliando?.tipo === 'C' ? 'Ventas / Facturas Emitidas' : 'Documentos de Respaldo' }}
                </p>
                <span v-if="movConciliando?.tipo === 'D'" class="text-caption text-medium-emphasis">Ordenados por monto más cercano</span>
              </div>

              <div v-if="loadingConciliar" class="text-center py-6">
                <VProgressCircular indeterminate color="primary" />
              </div>

              <!-- ══ CRÉDITO: tabs ventas + ingreso s/doc ══ -->
              <template v-else-if="movConciliando?.tipo === 'C'">
                <VTabs v-model="concTab" density="compact" class="mb-3">
                  <VTab value="ventas">
                    <VIcon size="16" class="mr-1">mdi-file-document-outline</VIcon>Ventas Bsale
                  </VTab>
                  <VTab value="ingreso_manual">
                    <VIcon size="16" class="mr-1">mdi-receipt-text-plus</VIcon>Ingreso sin doc SII
                  </VTab>
                </VTabs>

                <!-- Sub-tab: Ventas Bsale -->
                <div v-if="concTab === 'ventas'">
                  <VTextField
                    v-model="buscarVentaDisp"
                    placeholder="Buscar por cliente, RUT o folio..."
                    density="compact" variant="outlined" hide-details
                    prepend-inner-icon="mdi-magnify" clearable class="mb-3"
                    @update:modelValue="debounceBuscarVenta"
                  />
                  <div v-if="!concVentasDisponibles.length" class="text-caption text-medium-emphasis text-center py-4">
                    No hay ventas con saldo pendiente
                  </div>
                  <div v-else style="overflow-x: auto">
                    <VTable density="compact">
                      <thead>
                        <tr>
                          <th>Saldo</th><th>Total</th><th>Fecha</th><th>Folio</th><th>Cliente</th><th></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="v in concVentasDisponibles" :key="v.id">
                          <td class="font-weight-bold text-success">{{ formatMonto(v.saldo_por_cobrar) }}</td>
                          <td>{{ formatMonto(v.monto) }}</td>
                          <td class="text-caption">{{ formatFecha(v.fecha_emision) }}</td>
                          <td><VChip size="x-small" color="success" variant="tonal" class="mr-1">{{ v.tipo_doc }}</VChip>{{ v.folio }}</td>
                          <td class="text-caption">{{ v.nombre_receptor }}<br><span class="text-medium-emphasis">{{ v.rut_receptor }}</span></td>
                          <td>
                            <VBtn size="x-small" color="success" variant="tonal"
                              :loading="loadingAsignarVenta === v.id" :disabled="concSaldoPorAsignar <= 0"
                              @click="asignarVenta(v)">Seleccionar</VBtn>
                          </td>
                        </tr>
                      </tbody>
                    </VTable>
                  </div>
                </div>

                <!-- Sub-tab: Ingreso sin documento SII -->
                <div v-else-if="concTab === 'ingreso_manual'">
                  <VAlert color="teal" variant="tonal" density="compact" class="mb-4 text-caption">
                    <VIcon size="15" class="mr-1">mdi-information-outline</VIcon>
                    Este ingreso no tiene boleta ni factura del SII. Quedará registrado como
                    <strong>ingreso manual</strong> y se incluirá en el Estado de Resultados.
                  </VAlert>
                  <VRow dense>
                    <VCol cols="12">
                      <VTextField
                        v-model="ingresoManualForm.descripcion"
                        label="Descripción"
                        density="compact" variant="outlined"
                        hide-details="auto"
                        class="mb-3"
                      />
                    </VCol>
                    <VCol cols="12" sm="6">
                      <VSelect
                        v-model="ingresoManualForm.categoria"
                        :items="categoriasIngreso"
                        label="Categoría"
                        density="compact" variant="outlined"
                        hide-details="auto"
                        class="mb-3"
                      />
                    </VCol>
                    <VCol cols="12" sm="6">
                      <VTextField
                        :model-value="formatMonto(movConciliando?.monto)"
                        label="Monto (del movimiento)"
                        density="compact" variant="outlined"
                        hide-details readonly
                        class="mb-3"
                      />
                    </VCol>
                    <VCol cols="12">
                      <VTextarea
                        v-model="ingresoManualForm.notas"
                        label="Notas (opcional)"
                        density="compact" variant="outlined"
                        hide-details rows="2"
                        class="mb-3"
                      />
                    </VCol>
                  </VRow>
                  <VBtn
                    color="teal"
                    variant="flat"
                    prepend-icon="mdi-check"
                    :loading="savingIngresoManual"
                    :disabled="!ingresoManualForm.descripcion"
                    @click="crearIngresoManual"
                  >Registrar ingreso sin doc SII</VBtn>
                </div>
              </template>

              <!-- ══ DÉBITO: tabs facturas / gastos / sueldos ══ -->
              <template v-else>
                <VTabs v-model="concTab" density="compact" class="mb-3">
                  <VTab value="facturas">Facturas de compra</VTab>
                  <VTab value="gastos">Gastos generales</VTab>
                  <VTab value="sueldos">Sueldos</VTab>
                </VTabs>

                <!-- Tab Facturas -->
                <div v-if="concTab === 'facturas'">
                  <VTextField
                    v-model="buscarCompraDisp"
                    placeholder="Buscar por proveedor, RUT o folio..."
                    density="compact" variant="outlined" hide-details
                    prepend-inner-icon="mdi-magnify" clearable class="mb-3"
                    @update:modelValue="debounceBuscarDisp"
                  />
                  <div v-if="!concDisponibles.length" class="text-caption text-medium-emphasis text-center py-4">
                    No hay facturas con saldo pendiente
                  </div>
                  <div v-else style="overflow-x: auto">
                    <VTable density="compact">
                      <thead>
                        <tr>
                          <th>Saldo</th><th>Total</th><th>Fecha</th><th>Folio</th><th>Proveedor</th><th></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="c in concDisponibles" :key="c.id">
                          <td class="font-weight-bold text-primary">{{ formatMonto(c.saldo_por_pagar) }}</td>
                          <td>{{ formatMonto(c.total) }}</td>
                          <td class="text-caption">{{ formatFecha(c.fecha_emision) }}</td>
                          <td><VChip size="x-small" color="primary" variant="tonal" class="mr-1">{{ c.tipo_dte }}</VChip>{{ c.folio }}</td>
                          <td class="text-caption">{{ c.nombre_emisor }}<br><span class="text-medium-emphasis">{{ c.rut_emisor }}</span></td>
                          <td>
                            <VBtn size="x-small" color="primary" variant="tonal"
                              :loading="loadingAsignar === c.id" :disabled="concSaldoPorAsignar <= 0"
                              @click="asignarCompra(c)">Seleccionar</VBtn>
                          </td>
                        </tr>
                      </tbody>
                    </VTable>
                  </div>
                </div>

                <!-- Tab Sueldos -->
                <div v-else-if="concTab === 'sueldos'">
                  <VTextField
                    v-model="buscarSueldoDisp"
                    placeholder="Buscar por nombre o RUT..."
                    density="compact" variant="outlined" hide-details
                    prepend-inner-icon="mdi-magnify" clearable class="mb-3"
                    @update:modelValue="debounceBuscarSueldo"
                  />
                  <div v-if="!concSueldosDisponibles.length" class="text-caption text-medium-emphasis text-center py-4">
                    No hay sueldos pendientes de pago
                  </div>
                  <div v-else style="overflow-x: auto">
                    <VTable density="compact">
                      <thead>
                        <tr>
                          <th>Monto</th><th>Período</th><th>Tipo</th><th>Empleado</th><th>RUT</th><th></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="p in concSueldosDisponibles" :key="p.pago_id">
                          <td class="font-weight-bold text-primary">{{ formatMonto(p.monto) }}</td>
                          <td class="text-caption">{{ p.periodo?.slice(0,7) }}</td>
                          <td><VChip size="x-small" color="purple" variant="tonal">{{ p.tipo }}</VChip></td>
                          <td class="text-caption font-weight-bold">{{ p.empleado_nombre }}</td>
                          <td class="text-caption text-medium-emphasis">{{ p.empleado_rut }}</td>
                          <td>
                            <VBtn size="x-small" color="purple" variant="tonal"
                              :loading="loadingAsignarSueldo === p.pago_id"
                              :disabled="concSaldoPorAsignar <= 0"
                              @click="asignarSueldo(p)">Seleccionar</VBtn>
                          </td>
                        </tr>
                      </tbody>
                    </VTable>
                  </div>
                </div>

                <!-- Tab Gastos -->
                <div v-else>
                  <VTextField
                    v-model="buscarGastoDisp"
                    placeholder="Buscar descripción o proveedor..."
                    density="compact" variant="outlined" hide-details
                    prepend-inner-icon="mdi-magnify" clearable class="mb-3"
                    @update:modelValue="debounceBuscarGasto"
                  />
                  <div v-if="!concGastosDisponibles.length" class="text-caption text-medium-emphasis text-center py-4">
                    No hay gastos con saldo pendiente
                  </div>
                  <div v-else style="overflow-x: auto">
                    <VTable density="compact">
                      <thead>
                        <tr>
                          <th>Saldo</th><th>Monto</th><th>Fecha</th><th>Descripción</th><th>Categoría</th><th></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="g in concGastosDisponibles" :key="g.id">
                          <td class="font-weight-bold text-primary">{{ formatMonto(g.saldo_por_conciliar) }}</td>
                          <td>{{ formatMonto(g.monto) }}</td>
                          <td class="text-caption">{{ g.fecha }}</td>
                          <td class="text-caption">{{ g.descripcion }}<br><span class="text-medium-emphasis">{{ g.proveedor }}</span></td>
                          <td><VChip v-if="g.categoria" size="x-small" color="secondary" variant="tonal">{{ g.categoria }}</VChip></td>
                          <td>
                            <VBtn size="x-small" color="info" variant="tonal"
                              :loading="loadingAsignarGasto === g.id" :disabled="concSaldoPorAsignar <= 0"
                              @click="asignarGasto(g)">Seleccionar</VBtn>
                          </td>
                        </tr>
                      </tbody>
                    </VTable>
                  </div>
                </div>
              </template>
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn color="primary" @click="dialogConciliar = false; cargarMovimientos()">Listo</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog Crear Gasto Rápido ────────────────────────────────────────── -->
    <VDialog v-model="dialogCrearGasto" max-width="520">
      <VCard title="Crear Gasto General">
        <VCardText>
          <p class="text-caption text-medium-emphasis mb-4">
            <template v-if="movParaGastoLista.length === 1">
              Se creará el gasto y se vinculará al movimiento
              <strong>{{ movParaGastoLista[0]?.descripcion }}</strong>.
            </template>
            <template v-else>
              Se creará <strong>un solo gasto</strong> y se vinculará a los
              <strong>{{ movParaGastoLista.length }} egresos seleccionados</strong>
              (total {{ formatMonto(sumaSeleccionados) }}).
            </template>
          </p>
          <VRow dense>
            <VCol cols="6">
              <VTextField v-model="gastoRapidoForm.fecha" label="Fecha" type="date" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="6">
              <VTextField v-model.number="gastoRapidoForm.monto" label="Monto ($)" type="number" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="gastoRapidoForm.descripcion" label="Descripción" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12" sm="7">
              <VSelect
                v-model="gastoRapidoForm.categoria"
                label="Categoría"
                density="compact"
                variant="outlined"
                hide-details
                :items="categoriasGasto"
              />
            </VCol>
            <VCol cols="12" sm="5">
              <VTextField v-model="gastoRapidoForm.numero_documento" label="N° Doc (opcional)" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="gastoRapidoForm.proveedor" label="Proveedor (opcional)" density="compact" variant="outlined" hide-details />
            </VCol>
          </VRow>
          <VAlert v-if="gastoRapidoError" class="mt-3" color="error" variant="tonal" density="compact">
            {{ gastoRapidoError }}
          </VAlert>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogCrearGasto = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="savingGastoRapido" @click="guardarGastoRapido">Crear y vincular</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog Crear Sueldo Rápido ───────────────────────────────────────── -->
    <VDialog v-model="dialogCrearSueldo" max-width="520">
      <VCard title="Crear Sueldo">
        <VCardText>
          <p class="text-caption text-medium-emphasis mb-4">
            <template v-if="movParaSueldoLista.length === 1">
              Se creará el pago y se vinculará al movimiento
              <strong>{{ movParaSueldoLista[0]?.descripcion }}</strong>.
            </template>
            <template v-else>
              Se creará <strong>un pago por cada egreso seleccionado</strong>
              ({{ movParaSueldoLista.length }} pagos · total {{ formatMonto(sumaSeleccionados) }}).
            </template>
          </p>
          <VRow dense>
            <VCol cols="12">
              <VSelect
                v-model="sueldoRapidoForm.empleado_id"
                label="Empleado"
                density="compact"
                variant="outlined"
                hide-details
                :items="empleadosActivos"
                item-title="nombre"
                item-value="id"
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model="sueldoRapidoForm.periodo"
                label="Período"
                type="month"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>
            <VCol cols="6">
              <VSelect
                v-model="sueldoRapidoForm.tipo"
                label="Tipo"
                density="compact"
                variant="outlined"
                hide-details
                :items="['sueldo', 'bono', 'finiquito']"
              />
            </VCol>
            <VCol cols="12" v-if="movParaSueldoLista.length === 1">
              <VTextField
                v-model.number="sueldoRapidoForm.monto"
                label="Monto ($)"
                type="number"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>
            <VCol cols="12" v-else>
              <VTextField
                :model-value="formatMonto(sumaSeleccionados)"
                label="Monto total (suma de egresos seleccionados)"
                density="compact"
                variant="outlined"
                hide-details
                readonly
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="sueldoRapidoForm.notas"
                label="Notas (opcional)"
                density="compact"
                variant="outlined"
                hide-details
              />
            </VCol>
          </VRow>
          <VAlert v-if="sueldoRapidoError" class="mt-3" color="error" variant="tonal" density="compact">
            {{ sueldoRapidoError }}
          </VAlert>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogCrearSueldo = false">Cancelar</VBtn>
          <VBtn color="purple" :loading="savingSueldoRapido" :disabled="!sueldoRapidoForm.empleado_id" @click="guardarSueldoRapido">
            Crear y vincular
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog: Registrar múltiples ingresos sin documento SII ───────────── -->
    <VDialog v-model="dialogRegistrarIngresos" max-width="580">
      <VCard title="Registrar ingresos sin documento SII">
        <VCardText>
          <VAlert color="teal" variant="tonal" density="compact" class="mb-4 text-caption">
            <VIcon size="15" class="mr-1">mdi-information-outline</VIcon>
            Se creará un registro de ingreso manual por cada movimiento seleccionado.
            Quedarán disponibles en el <strong>Estado de Resultados</strong>.
          </VAlert>

          <!-- Lista de créditos que se van a registrar -->
          <p class="text-overline text-medium-emphasis mb-2">Ingresos a registrar</p>
          <div class="mb-4" style="max-height: 200px; overflow-y: auto">
            <VCard v-for="m in seleccionadosCreditos" :key="m.id" variant="outlined" class="mb-1">
              <VCardText class="pa-2 d-flex justify-space-between align-center">
                <div>
                  <div class="text-caption font-weight-medium">{{ m.descripcion }}</div>
                  <div class="text-caption text-medium-emphasis">{{ formatFecha(m.fecha_contable) }}</div>
                </div>
                <span class="text-body-2 font-weight-bold text-success">{{ formatMonto(m.monto) }}</span>
              </VCardText>
            </VCard>
          </div>

          <!-- Categoría compartida para todos -->
          <VSelect
            v-model="registrarIngresosCategoria"
            :items="categoriasIngreso"
            label="Categoría (se aplica a todos)"
            density="compact"
            variant="outlined"
            hide-details="auto"
          />

          <VAlert v-if="errorRegistrarIngresos" color="error" variant="tonal" density="compact" class="mt-3 text-caption">
            {{ errorRegistrarIngresos }}
          </VAlert>
        </VCardText>
        <VCardActions>
          <VCol class="text-caption text-medium-emphasis">
            Total: <strong>{{ formatMonto(sumaSeleccionadosCreditos) }}</strong>
            · {{ seleccionadosCreditos.length }} registros
          </VCol>
          <VSpacer />
          <VBtn variant="text" @click="dialogRegistrarIngresos = false">Cancelar</VBtn>
          <VBtn color="teal" :loading="savingRegistrarIngresos" @click="guardarRegistrarIngresos">
            Registrar todos
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ── Dialog: Vincular múltiples ingresos a una venta ──────────────────── -->
    <VDialog v-model="dialogVincularVentaMultiple" max-width="820" scrollable>
      <VCard>
        <VCardTitle class="pa-4 d-flex align-center justify-space-between" style="background: rgb(var(--v-theme-success)); color: white">
          <span>Vincular ingresos a una venta</span>
          <VBtn icon variant="text" color="white" @click="dialogVincularVentaMultiple = false">
            <VIcon>mdi-close</VIcon>
          </VBtn>
        </VCardTitle>
        <VCardText class="pa-4">
          <!-- Resumen de ingresos seleccionados -->
          <VAlert color="success" variant="tonal" density="compact" class="mb-4">
            <div class="d-flex align-center gap-3 flex-wrap">
              <VIcon color="success">mdi-bank-transfer-in</VIcon>
              <div>
                <strong>{{ seleccionadosCreditos.length }} ingresos seleccionados</strong>
                · Total: <strong>{{ formatMonto(sumaSeleccionadosCreditos) }}</strong>
              </div>
              <div class="d-flex gap-1 flex-wrap">
                <VChip v-for="m in seleccionadosCreditos" :key="m.id" size="x-small" color="success" variant="tonal">
                  {{ formatMonto(m.monto) }} · {{ formatFecha(m.fecha_contable) }}
                </VChip>
              </div>
            </div>
          </VAlert>

          <!-- Búsqueda de ventas -->
          <p class="text-overline text-medium-emphasis mb-2">Selecciona la venta a la que pertenecen estos ingresos</p>
          <VTextField
            v-model="buscarVentaMulti"
            placeholder="Buscar por cliente, RUT o folio..."
            density="compact" variant="outlined" hide-details
            prepend-inner-icon="mdi-magnify" clearable class="mb-3"
            @update:modelValue="debounceBuscarVentaMulti"
          />

          <div v-if="loadingVentasMulti" class="text-center py-6">
            <VProgressCircular indeterminate color="success" />
          </div>
          <div v-else-if="!ventasMultiDisponibles.length" class="text-caption text-medium-emphasis text-center py-4">
            No hay ventas con saldo pendiente
          </div>
          <div v-else style="overflow-x: auto">
            <VTable density="compact">
              <thead>
                <tr>
                  <th>Saldo</th><th>Total</th><th>Fecha</th><th>Folio</th><th>Cliente</th><th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="v in ventasMultiDisponibles" :key="v.id">
                  <td class="font-weight-bold text-success">{{ formatMonto(v.saldo_por_cobrar) }}</td>
                  <td>{{ formatMonto(v.monto) }}</td>
                  <td class="text-caption">{{ formatFecha(v.fecha_emision) }}</td>
                  <td><VChip size="x-small" color="success" variant="tonal" class="mr-1">{{ v.tipo_doc }}</VChip>{{ v.folio }}</td>
                  <td class="text-caption">{{ v.nombre_receptor }}<br><span class="text-medium-emphasis">{{ v.rut_receptor }}</span></td>
                  <td>
                    <VBtn size="x-small" color="success" variant="tonal"
                      :loading="linkingVentaMulti === v.id"
                      @click="confirmarVincularVentaMultiple(v)">
                      Vincular {{ seleccionadosCreditos.length }} ingresos
                    </VBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="text" @click="dialogVincularVentaMultiple = false">Cancelar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Link Compra -->
    <VDialog v-model="dialogLinkCompra" max-width="500">
      <VCard title="Vincular con Compra">
        <VCardText>
          <p class="text-body-2 mb-2">
            Movimiento: <strong>{{ movSeleccionado?.descripcion }}</strong>
            — {{ formatMonto(movSeleccionado?.monto) }}
          </p>
          <VTextField
            v-model="buscarCompra"
            label="Buscar compra por folio o proveedor"
            density="compact"
            variant="outlined"
            prepend-inner-icon="mdi-magnify"
          />
          <VList v-if="comprasSugeridas.length" density="compact" class="mt-2">
            <VListItem
              v-for="c in comprasSugeridas"
              :key="c.id"
              :subtitle="`${c.folio} — ${formatMonto(c.neto)}`"
              :title="c.proveedor_nombre || c.emisor"
              @click="vincularCompra(c)"
            />
          </VList>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogLinkCompra = false">Cancelar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from '@/axiosInstance'
import VueApexCharts from 'vue3-apexcharts'

const cuenta = computed(() => import.meta.env.VITE_BCH_CUENTA || '—')

// ── Empleados (para sueldo rápido) ───────────────────────────────────────────

const empleadosActivos = ref([])

async function cargarEmpleados() {
  try {
    const { data } = await axios.get('/api/empleados')
    empleadosActivos.value = data.filter(e => e.activo)
  } catch (e) { console.error(e) }
}

// ── Estado ──────────────────────────────────────────────────────────────────

const tab = ref('movimientos')
const movimientos = ref([])
const totales = ref({})
const saldo = ref(null)
const flujoCajaData = ref([])

// ── Progreso de conciliación ─────────────────────────────────────────────────
const pctCreditos = computed(() => {
  const total = totales.value.total_creditos_count || 0
  if (!total) return 0
  return Math.round(((totales.value.conciliados_creditos || 0) / total) * 100)
})
const pctDebitos = computed(() => {
  const total = totales.value.total_debitos_count || 0
  if (!total) return 0
  return Math.round(((totales.value.conciliados_debitos || 0) / total) * 100)
})
const pctTotal = computed(() => {
  const total = totales.value.total_movimientos || 0
  if (!total) return 0
  return Math.round(((total - (totales.value.pendientes || 0)) / total) * 100)
})

const loadingTable = ref(false)
const loadingSaldo = ref(false)
const loadingImport = ref(false)
const loadingCartola = ref(false)
const loadingMatch = ref(false)
const loadingReglas = ref(false)
const loadingAplicar = ref(false)
const savingRegla = ref(false)

const reglas = ref([])
const dialogRegla = ref(false)
const reglaEditando = ref(null)
const reglaFormVacio = () => ({ nombre: '', patron: '', categoria: '', tipo: 'A', prioridad: 100, activa: true })
const reglaForm = ref(reglaFormVacio())

const dialogImportar = ref(false)
const dialogCartola = ref(false)
const cartolaArchivo = ref(null)
const cartolaResult = ref(null)
const dialogLinkCompra = ref(false)
const movSeleccionado = ref(null)
const buscarCompra = ref('')
const comprasSugeridas = ref([])
const importResult = ref(null)

const hoy = new Date().toISOString().slice(0, 10)
const primerDiaMes = new Date(new Date().getFullYear() - 2, 0, 1).toISOString().slice(0, 10)

const filtros = ref({
  desde: primerDiaMes,
  hasta: hoy,
  tipo: '',
  conciliado: '',
  buscar: '',
})

const importForm = ref({ desde: primerDiaMes, hasta: hoy })

const filtroFlujo = ref({
  desde: new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
  hasta: hoy,
})

const categorias = [
  'Compra proveedor',
  'Sueldo',
  'Arriendo',
  'Servicios básicos',
  'Impuestos',
  'Ingreso cliente',
  'Transferencia interna',
  'Comisión bancaria',
  'Otro',
]

// ── Headers tabla ────────────────────────────────────────────────────────────

const headersReglas = [
  { title: 'Prioridad', key: 'prioridad', width: '80px' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Patrón', key: 'patron' },
  { title: 'Categoría', key: 'categoria' },
  { title: 'Tipo', key: 'tipo', align: 'center' },
  { title: 'Activa', key: 'activa', align: 'center' },
  { title: '', key: 'actions', sortable: false, width: '80px' },
]

const headers = [
  { title: 'Fecha', key: 'fecha_contable', sortable: true },
  { title: 'Descripción', key: 'descripcion', sortable: false },
  { title: 'N° Doc', key: 'numero_documento', sortable: false },
  { title: 'Monto', key: 'monto', align: 'end', sortable: true },
  { title: 'Tipo', key: 'tipo', align: 'center', sortable: false },
  { title: 'Categoría', key: 'categoria', sortable: false },
  { title: 'Estado Conciliación', key: 'saldo_por_asignar', align: 'end', sortable: false },
  { title: 'Conciliado', key: 'conciliado', align: 'center', sortable: false },
  { title: '', key: 'actions', sortable: false, width: '50px' },
]

// ── API calls ────────────────────────────────────────────────────────────────

async function cargarMovimientos() {
  loadingTable.value = true
  try {
    const params = { ...filtros.value }
    const { data } = await axios.get('/api/conciliacion/movimientos', { params })
    movimientos.value = data.movimientos?.data || []
    totales.value = data.totales || {}
  } catch (e) {
    console.error(e)
  } finally {
    loadingTable.value = false
  }
}

async function cargarSaldo() {
  loadingSaldo.value = true
  try {
    const { data } = await axios.get('/api/conciliacion/saldo')
    saldo.value = data.saldoDisponible ?? data.saldo ?? null
  } catch (e) {
    console.error(e)
  } finally {
    loadingSaldo.value = false
  }
}

async function importar() {
  loadingImport.value = true
  importResult.value = null
  try {
    const { data } = await axios.post('/api/conciliacion/importar', importForm.value)
    importResult.value = data
    // Ajustar filtro de tabla al rango importado para que aparezcan los movimientos
    filtros.value.desde = importForm.value.desde
    filtros.value.hasta = importForm.value.hasta
    await cargarMovimientos()
  } catch (e) {
    importResult.value = { error: e.response?.data?.error || 'Error al importar' }
  } finally {
    loadingImport.value = false
  }
}

async function importarCartola() {
  const file = cartolaArchivo.value
  if (!file) return
  loadingCartola.value = true
  cartolaResult.value = null
  try {
    const form = new FormData()
    form.append('archivo', file)
    const { data } = await axios.post('/api/conciliacion/importar-cartola', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    cartolaResult.value = data
    // Mostrar los movimientos recién importados
    const hoyStr = new Date().toISOString().slice(0, 10)
    const hace2años = new Date(new Date().getFullYear() - 2, 0, 1).toISOString().slice(0, 10)
    filtros.value.desde = hace2años
    filtros.value.hasta = hoyStr
    await cargarMovimientos()
  } catch (e) {
    cartolaResult.value = { error: e.response?.data?.message || 'Error al importar cartola' }
  } finally {
    loadingCartola.value = false
  }
}

async function autoConcilar() {
  loadingMatch.value = true
  try {
    const { data } = await axios.post('/api/conciliacion/auto-concilar')
    alert(`Conciliación automática: ${data.matches} movimientos vinculados`)
    await cargarMovimientos()
  } catch (e) {
    console.error(e)
  } finally {
    loadingMatch.value = false
  }
}

async function actualizarMov(item, changes) {
  try {
    const { data } = await axios.patch(`/api/conciliacion/movimientos/${item.id}`, changes)
    Object.assign(item, data)
  } catch (e) {
    console.error(e)
  }
}

async function cargarFlujo() {
  try {
    const { data } = await axios.get('/api/conciliacion/flujo-caja', { params: filtroFlujo.value })
    flujoCajaData.value = data
  } catch (e) {
    console.error(e)
  }
}

// ── Conciliar movimiento ↔ facturas ──────────────────────────────────────────

const dialogConciliar       = ref(false)
const movConciliando        = ref(null)
const concAsignadas         = ref([])
const concDisponibles       = ref([])
const concGastosAsignados   = ref([])
const concGastosDisponibles = ref([])
const concSueldosAsignados  = ref([])
const concSueldosDisponibles = ref([])
const concVentasAsignadas   = ref([])
const concVentasDisponibles = ref([])
const concIngresosAsignados = ref([])
const loadingConciliar      = ref(false)
const loadingAsignar        = ref(null)
const loadingAsignarGasto   = ref(null)
const loadingAsignarSueldo  = ref(null)
const loadingAsignarVenta   = ref(null)
const loadingDesasignar     = ref(null)
const savingIngresoManual   = ref(false)
const buscarCompraDisp      = ref('')
const buscarGastoDisp       = ref('')
const buscarSueldoDisp      = ref('')
const buscarVentaDisp       = ref('')
const concSaldoPorAsignar   = ref(0)
const concTab               = ref('facturas')

const categoriasIngreso = [
  'Ingreso por ventas', 'Servicios prestados', 'Honorarios recibidos',
  'Arriendo cobrado', 'Comisión cobrada', 'Transferencia recibida', 'Otro',
]
const ingresoManualForm = ref({ descripcion: '', categoria: 'Ingreso por ventas', notas: '' })

function abrirConciliar(mov) {
  movConciliando.value         = mov
  buscarCompraDisp.value       = ''
  buscarGastoDisp.value        = ''
  buscarSueldoDisp.value       = ''
  buscarVentaDisp.value        = ''
  concAsignadas.value          = []
  concDisponibles.value        = []
  concGastosAsignados.value    = []
  concGastosDisponibles.value  = []
  concSueldosAsignados.value   = []
  concSueldosDisponibles.value = []
  concVentasAsignadas.value    = []
  concVentasDisponibles.value  = []
  concIngresosAsignados.value  = []
  ingresoManualForm.value      = { descripcion: mov.descripcion ?? '', categoria: 'Ingreso por ventas', notas: '' }
  concTab.value                = mov.tipo === 'C' ? 'ventas' : 'facturas'
  dialogConciliar.value        = true
  cargarEstadoConciliar()
}

async function cargarEstadoConciliar() {
  if (!movConciliando.value) return
  loadingConciliar.value = true
  try {
    if (movConciliando.value.tipo === 'C') {
      // ── Crédito: cargar ventas asignadas + ingresos manuales asignados ──
      const [resVentas, resIngresos] = await Promise.all([
        axios.get(`/api/conciliacion/movimientos/${movConciliando.value.id}/ventas`),
        axios.get(`/api/conciliacion/movimientos/${movConciliando.value.id}/ingresos`),
      ])
      concVentasAsignadas.value   = resVentas.data.asignados
      concIngresosAsignados.value = resIngresos.data.asignados
      const totalAsignado = concVentasAsignadas.value.reduce((s, a) => s + parseFloat(a.monto_asignado), 0)
                          + concIngresosAsignados.value.reduce((s, a) => s + parseFloat(a.monto_asignado), 0)
      concSaldoPorAsignar.value = Math.max(0, (movConciliando.value?.monto ?? 0) - totalAsignado)
      await cargarVentasDisponibles()
    } else {
      // ── Débito: cargar compras / gastos / sueldos ──
      const [resCompras, resGastos, resSueldos] = await Promise.all([
        axios.get(`/api/conciliacion/movimientos/${movConciliando.value.id}/compras`),
        axios.get(`/api/conciliacion/movimientos/${movConciliando.value.id}/gastos`),
        axios.get(`/api/conciliacion/movimientos/${movConciliando.value.id}/sueldos`),
      ])
      concAsignadas.value        = resCompras.data.asignados
      concGastosAsignados.value  = resGastos.data.asignados
      concSueldosAsignados.value = resSueldos.data.asignados
      const totalAsignado = concAsignadas.value.reduce((s, a) => s + parseFloat(a.monto_asignado), 0)
                          + concGastosAsignados.value.reduce((s, a) => s + parseFloat(a.monto_asignado), 0)
                          + concSueldosAsignados.value.reduce((s, a) => s + parseFloat(a.monto), 0)
      concSaldoPorAsignar.value = Math.max(0, (movConciliando.value?.monto ?? 0) - totalAsignado)
      await Promise.all([cargarDisponibles(), cargarGastosDisponibles(), cargarSueldosDisponibles()])
    }
  } catch (e) {
    console.error(e)
  } finally {
    loadingConciliar.value = false
  }
}

async function cargarDisponibles() {
  if (!movConciliando.value) return
  try {
    const params = buscarCompraDisp.value ? { buscar: buscarCompraDisp.value } : {}
    const { data } = await axios.get(
      `/api/conciliacion/movimientos/${movConciliando.value.id}/compras-disponibles`, { params }
    )
    concDisponibles.value = data.data ?? data
  } catch (e) { console.error(e) }
}

async function cargarGastosDisponibles() {
  if (!movConciliando.value) return
  try {
    const params = buscarGastoDisp.value ? { buscar: buscarGastoDisp.value } : {}
    const { data } = await axios.get(
      `/api/conciliacion/movimientos/${movConciliando.value.id}/gastos-disponibles`, { params }
    )
    concGastosDisponibles.value = data.data ?? data
  } catch (e) { console.error(e) }
}

async function asignarCompra(compra) {
  loadingAsignar.value = compra.id
  try {
    await axios.post(`/api/conciliacion/movimientos/${movConciliando.value.id}/compras`, {
      compra_id: compra.id,
      monto: Math.min(concSaldoPorAsignar.value, compra.saldo_por_pagar),
    })
    await cargarEstadoConciliar()
  } catch (e) {
    console.error(e)
  } finally {
    loadingAsignar.value = null
  }
}

async function asignarGasto(gasto) {
  loadingAsignarGasto.value = gasto.id
  try {
    await axios.post(`/api/conciliacion/movimientos/${movConciliando.value.id}/gastos`, {
      gasto_id: gasto.id,
      monto: Math.min(concSaldoPorAsignar.value, gasto.saldo_por_conciliar),
    })
    await cargarEstadoConciliar()
  } catch (e) {
    console.error(e)
  } finally {
    loadingAsignarGasto.value = null
  }
}

async function desasignarCompra(pivotId) {
  loadingDesasignar.value = 'c' + pivotId
  try {
    await axios.delete(`/api/conciliacion/movimientos/${movConciliando.value.id}/compras/${pivotId}`)
    await cargarEstadoConciliar()
  } catch (e) {
    console.error(e)
  } finally {
    loadingDesasignar.value = null
  }
}

async function desasignarGasto(pivotId) {
  loadingDesasignar.value = 'g' + pivotId
  try {
    await axios.delete(`/api/conciliacion/movimientos/${movConciliando.value.id}/gastos/${pivotId}`)
    await cargarEstadoConciliar()
  } catch (e) {
    console.error(e)
  } finally {
    loadingDesasignar.value = null
  }
}

let buscarDispTimer = null
function debounceBuscarDisp() {
  clearTimeout(buscarDispTimer)
  buscarDispTimer = setTimeout(cargarDisponibles, 350)
}

let buscarGastoTimer = null
function debounceBuscarGasto() {
  clearTimeout(buscarGastoTimer)
  buscarGastoTimer = setTimeout(cargarGastosDisponibles, 350)
}

async function cargarSueldosDisponibles() {
  if (!movConciliando.value) return
  try {
    const params = buscarSueldoDisp.value ? { buscar: buscarSueldoDisp.value } : {}
    const { data } = await axios.get(
      `/api/conciliacion/movimientos/${movConciliando.value.id}/sueldos-disponibles`, { params }
    )
    concSueldosDisponibles.value = data.data ?? data
  } catch (e) { console.error(e) }
}

async function asignarSueldo(pago) {
  loadingAsignarSueldo.value = pago.pago_id
  try {
    await axios.post(`/api/conciliacion/movimientos/${movConciliando.value.id}/sueldos`, {
      pago_id: pago.pago_id,
    })
    await cargarEstadoConciliar()
  } catch (e) {
    console.error(e)
  } finally {
    loadingAsignarSueldo.value = null
  }
}

async function desasignarSueldo(pagoId) {
  loadingDesasignar.value = 's' + pagoId
  try {
    await axios.delete(`/api/conciliacion/movimientos/${movConciliando.value.id}/sueldos/${pagoId}`)
    await cargarEstadoConciliar()
  } catch (e) {
    console.error(e)
  } finally {
    loadingDesasignar.value = null
  }
}

let buscarSueldoTimer = null
function debounceBuscarSueldo() {
  clearTimeout(buscarSueldoTimer)
  buscarSueldoTimer = setTimeout(cargarSueldosDisponibles, 350)
}

// ── Ventas (para movimientos Crédito) ─────────────────────────────────────────

async function cargarVentasDisponibles() {
  if (!movConciliando.value) return
  try {
    const params = buscarVentaDisp.value ? { buscar: buscarVentaDisp.value } : {}
    const { data } = await axios.get(
      `/api/conciliacion/movimientos/${movConciliando.value.id}/ventas-disponibles`, { params }
    )
    concVentasDisponibles.value = data.data ?? data
  } catch (e) { console.error(e) }
}

async function asignarVenta(venta) {
  loadingAsignarVenta.value = venta.id
  try {
    await axios.post(`/api/conciliacion/movimientos/${movConciliando.value.id}/ventas`, {
      venta_id: venta.id,
      monto: Math.min(concSaldoPorAsignar.value, venta.saldo_por_cobrar),
    })
    await cargarEstadoConciliar()
  } catch (e) {
    console.error(e)
  } finally {
    loadingAsignarVenta.value = null
  }
}

async function desasignarVenta(pivotId) {
  loadingDesasignar.value = 'v' + pivotId
  try {
    await axios.delete(`/api/conciliacion/movimientos/${movConciliando.value.id}/ventas/${pivotId}`)
    await cargarEstadoConciliar()
  } catch (e) {
    console.error(e)
  } finally {
    loadingDesasignar.value = null
  }
}

let buscarVentaTimer = null
function debounceBuscarVenta() {
  clearTimeout(buscarVentaTimer)
  buscarVentaTimer = setTimeout(cargarVentasDisponibles, 350)
}

// ── Ingresos manuales (sin documento SII) ────────────────────────────────────

async function crearIngresoManual() {
  if (!movConciliando.value) return
  savingIngresoManual.value = true
  try {
    await axios.post(`/api/conciliacion/movimientos/${movConciliando.value.id}/ingresos`, {
      descripcion: ingresoManualForm.value.descripcion,
      categoria:   ingresoManualForm.value.categoria,
      notas:       ingresoManualForm.value.notas || null,
    })
    await cargarEstadoConciliar()
    // Volver al tab ventas después de registrar
    concTab.value = 'ventas'
  } catch (e) {
    console.error(e)
  } finally {
    savingIngresoManual.value = false
  }
}

async function desasignarIngreso(pivotId) {
  loadingDesasignar.value = 'i' + pivotId
  try {
    await axios.delete(`/api/conciliacion/movimientos/${movConciliando.value.id}/ingresos/${pivotId}`)
    await cargarEstadoConciliar()
  } catch (e) {
    console.error(e)
  } finally {
    loadingDesasignar.value = null
  }
}

async function marcarConciliadoDirecto(mov) {
  try {
    await axios.patch(`/api/conciliacion/movimientos/${mov.id}`, { conciliado: true })
    cargarMovimientos()
  } catch (e) {
    console.error(e)
  }
}

// ── Vincular múltiples ingresos a una venta ───────────────────────────────────

const dialogVincularVentaMultiple  = ref(false)
const ventasMultiDisponibles       = ref([])
const buscarVentaMulti             = ref('')
const loadingVentasMulti           = ref(false)
const linkingVentaMulti            = ref(null)
const loadingMarcarMultiple        = ref(false)
const dialogRegistrarIngresos      = ref(false)
const registrarIngresosCategoria   = ref('Ingreso por ventas')
const savingRegistrarIngresos      = ref(false)
const errorRegistrarIngresos       = ref(null)

async function abrirVincularVentaMultiple() {
  if (!seleccionadosCreditos.value.length) return
  buscarVentaMulti.value       = ''
  ventasMultiDisponibles.value = []
  dialogVincularVentaMultiple.value = true
  await cargarVentasMultiDisponibles()
}

async function cargarVentasMultiDisponibles() {
  loadingVentasMulti.value = true
  try {
    // Usamos el id del primer crédito seleccionado (el endpoint no filtra por él)
    const primerCredito = seleccionadosCreditos.value[0]
    const params = buscarVentaMulti.value ? { buscar: buscarVentaMulti.value } : {}
    const { data } = await axios.get(
      `/api/conciliacion/movimientos/${primerCredito.id}/ventas-disponibles`, { params }
    )
    ventasMultiDisponibles.value = data.data ?? data
  } catch (e) {
    console.error(e)
  } finally {
    loadingVentasMulti.value = false
  }
}

async function confirmarVincularVentaMultiple(venta) {
  linkingVentaMulti.value = venta.id
  try {
    // Vincular cada ingreso seleccionado a la venta con su monto completo
    for (const mov of seleccionadosCreditos.value) {
      await axios.post(`/api/conciliacion/movimientos/${mov.id}/ventas`, {
        venta_id: venta.id,
        monto:    parseFloat(mov.monto),
      })
    }
    dialogVincularVentaMultiple.value = false
    seleccionados.value = []
    await cargarMovimientos()
  } catch (e) {
    console.error(e)
  } finally {
    linkingVentaMulti.value = null
  }
}

let buscarVentaMultiTimer = null
function debounceBuscarVentaMulti() {
  clearTimeout(buscarVentaMultiTimer)
  buscarVentaMultiTimer = setTimeout(cargarVentasMultiDisponibles, 350)
}

function abrirRegistrarIngresos() {
  if (!seleccionadosCreditos.value.length) return
  registrarIngresosCategoria.value = 'Ingreso por ventas'
  errorRegistrarIngresos.value     = null
  dialogRegistrarIngresos.value    = true
}

async function guardarRegistrarIngresos() {
  savingRegistrarIngresos.value = true
  errorRegistrarIngresos.value  = null
  try {
    // Crea un ingreso_manual por cada crédito seleccionado
    for (const mov of seleccionadosCreditos.value) {
      await axios.post(`/api/conciliacion/movimientos/${mov.id}/ingresos`, {
        descripcion: mov.descripcion ?? '',
        categoria:   registrarIngresosCategoria.value,
      })
    }
    dialogRegistrarIngresos.value = false
    seleccionados.value = []
    await cargarMovimientos()
  } catch (e) {
    errorRegistrarIngresos.value = e.response?.data?.message || 'Error al registrar los ingresos'
  } finally {
    savingRegistrarIngresos.value = false
  }
}

// Mantener como opción alternativa: solo marcar flag sin crear ingreso_manual
async function marcarConciliadosMultiple() {
  if (!seleccionadosCreditos.value.length) return
  loadingMarcarMultiple.value = true
  try {
    await Promise.all(
      seleccionadosCreditos.value.map(m =>
        axios.patch(`/api/conciliacion/movimientos/${m.id}`, { conciliado: true })
      )
    )
    seleccionados.value = []
    await cargarMovimientos()
  } catch (e) {
    console.error(e)
  } finally {
    loadingMarcarMultiple.value = false
  }
}

// ── Selección múltiple ───────────────────────────────────────────────────────

const seleccionados = ref([])

const seleccionadosDebitos = computed(() =>
  movimientos.value.filter(m => seleccionados.value.includes(m.id) && m.tipo === 'D')
)
const seleccionadosCreditos = computed(() =>
  movimientos.value.filter(m => seleccionados.value.includes(m.id) && m.tipo === 'C')
)

const sumaSeleccionados = computed(() =>
  seleccionadosDebitos.value.reduce((s, m) => s + (m.saldo_por_asignar ?? m.monto), 0)
)
const sumaSeleccionadosCreditos = computed(() =>
  seleccionadosCreditos.value.reduce((s, m) => s + parseFloat(m.monto ?? 0), 0)
)

// Limpiar selección al recargar movimientos
watch(movimientos, () => { seleccionados.value = [] })

// ── Crear gasto rápido desde conciliación ────────────────────────────────────

const categoriasGasto = [
  'Arriendo', 'Servicios básicos', 'Comisión bancaria', 'Publicidad',
  'Mantención', 'Transporte y combustible', 'Seguros', 'Honorarios',
  'Gastos de oficina', 'Otro',
]

const dialogCrearGasto  = ref(false)
const movParaGastoLista = ref([])   // siempre array (1 o N movimientos)
const savingGastoRapido = ref(false)
const gastoRapidoError  = ref(null)
const gastoRapidoForm   = ref({})

function abrirCrearGasto(mov) {
  movParaGastoLista.value = [mov]
  gastoRapidoError.value  = null
  const saldo = mov.saldo_por_asignar ?? mov.monto
  gastoRapidoForm.value = {
    fecha:            mov.fecha_contable?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
    descripcion:      mov.descripcion ?? '',
    monto:            saldo,
    categoria:        '',
    proveedor:        '',
    numero_documento: '',
  }
  dialogCrearGasto.value = true
}

function abrirCrearGastoMultiple() {
  const movs = seleccionadosDebitos.value
  if (!movs.length) return
  movParaGastoLista.value = movs
  gastoRapidoError.value  = null
  gastoRapidoForm.value = {
    fecha:            movs[0].fecha_contable?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
    descripcion:      '',
    monto:            sumaSeleccionados.value,
    categoria:        '',
    proveedor:        '',
    numero_documento: '',
  }
  dialogCrearGasto.value = true
}

async function guardarGastoRapido() {
  savingGastoRapido.value = true
  gastoRapidoError.value  = null
  try {
    // 1. Crear el gasto por el monto total del form
    const { data: gasto } = await axios.post('/api/gastos', gastoRapidoForm.value)

    // 2. Vincular cada movimiento seleccionado asignando su saldo por completo
    for (const mov of movParaGastoLista.value) {
      const montoMov = mov.saldo_por_asignar ?? mov.monto
      await axios.post(`/api/conciliacion/movimientos/${mov.id}/gastos`, {
        gasto_id: gasto.id,
        monto:    montoMov,
      })
    }

    dialogCrearGasto.value = false
    seleccionados.value    = []
    await cargarMovimientos()
  } catch (e) {
    gastoRapidoError.value = e.response?.data?.message || e.response?.data?.error || 'Error al crear el gasto'
  } finally {
    savingGastoRapido.value = false
  }
}

// ── Crear sueldo rápido desde conciliación ───────────────────────────────────

const dialogCrearSueldo  = ref(false)
const movParaSueldoLista = ref([])
const savingSueldoRapido = ref(false)
const sueldoRapidoError  = ref(null)
const sueldoRapidoForm   = ref({})

function sueldoFormVacio(mov) {
  return {
    empleado_id: null,
    periodo:     mov?.fecha_contable?.slice(0, 7) ?? new Date().toISOString().slice(0, 7),
    tipo:        'sueldo',
    monto:       mov ? (mov.saldo_por_asignar ?? mov.monto) : 0,
    notas:       '',
  }
}

function abrirCrearSueldo(mov) {
  movParaSueldoLista.value = [mov]
  sueldoRapidoError.value  = null
  sueldoRapidoForm.value   = sueldoFormVacio(mov)
  dialogCrearSueldo.value  = true
}

function abrirCrearSueldoMultiple() {
  const movs = seleccionadosDebitos.value
  if (!movs.length) return
  movParaSueldoLista.value = movs
  sueldoRapidoError.value  = null
  sueldoRapidoForm.value   = sueldoFormVacio(movs[0])
  dialogCrearSueldo.value  = true
}

async function guardarSueldoRapido() {
  if (!sueldoRapidoForm.value.empleado_id) return
  savingSueldoRapido.value = true
  sueldoRapidoError.value  = null
  try {
    const esSingle = movParaSueldoLista.value.length === 1

    for (const mov of movParaSueldoLista.value) {
      const montoMov = esSingle
        ? sueldoRapidoForm.value.monto
        : (mov.saldo_por_asignar ?? mov.monto)

      // 1. Crear pago en empleados
      const { data: pago } = await axios.post(
        `/api/empleados/${sueldoRapidoForm.value.empleado_id}/pagos`,
        {
          periodo:    sueldoRapidoForm.value.periodo + '-01',
          monto:      montoMov,
          tipo:       sueldoRapidoForm.value.tipo,
          pagado:     true,
          fecha_pago: mov.fecha_contable?.slice(0, 10),
          notas:      sueldoRapidoForm.value.notas || null,
        }
      )

      // 2. Vincular pago al movimiento bancario
      await axios.post(`/api/conciliacion/movimientos/${mov.id}/sueldos`, {
        pago_id: pago.id,
      })
    }

    dialogCrearSueldo.value = false
    seleccionados.value     = []
    await cargarMovimientos()
  } catch (e) {
    sueldoRapidoError.value = e.response?.data?.message || e.response?.data?.error || 'Error al crear el sueldo'
  } finally {
    savingSueldoRapido.value = false
  }
}

// ── Vincular compra (legacy) ──────────────────────────────────────────────────

function abrirLinkCompra(mov) {
  movSeleccionado.value = mov
  buscarCompra.value = ''
  comprasSugeridas.value = []
  dialogLinkCompra.value = true
}

async function vincularCompra(compra) {
  await actualizarMov(movSeleccionado.value, { compra_id: compra.id, conciliado: true })
  dialogLinkCompra.value = false
  await cargarMovimientos()
}

let buscarTimer = null
function debounceBuscar() {
  clearTimeout(buscarTimer)
  buscarTimer = setTimeout(cargarMovimientos, 350)
}

// ── Chart flujo de caja ──────────────────────────────────────────────────────

const chartSeries = computed(() => [
  { name: 'Ingresos', data: flujoCajaData.value.map(r => parseFloat(r.ingresos || 0)) },
  { name: 'Egresos',  data: flujoCajaData.value.map(r => parseFloat(r.egresos || 0)) },
])

const chartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, foreColor: '#a8aaae' },
  colors: ['#28c76f', '#ea5455'],
  xaxis: {
    categories: flujoCajaData.value.map(r => r.mes),
    labels: { style: { colors: '#a8aaae' } },
  },
  yaxis: { labels: { formatter: v => '$' + Math.round(v).toLocaleString('es-CL') } },
  tooltip: { theme: 'dark', y: { formatter: v => '$' + Math.round(v).toLocaleString('es-CL') } },
  grid: { borderColor: 'rgba(255,255,255,0.07)' },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
  dataLabels: { enabled: false },
  legend: { labels: { colors: '#a8aaae' } },
}))

// ── Helpers ──────────────────────────────────────────────────────────────────

function formatMonto(v) {
  return '$' + parseFloat(v || 0).toLocaleString('es-CL', { minimumFractionDigits: 0 })
}

function formatFecha(str) {
  if (!str) return ''
  return str.slice(0, 10)
}

// ── Reglas ───────────────────────────────────────────────────────────────────

async function cargarReglas() {
  loadingReglas.value = true
  try {
    const { data } = await axios.get('/api/conciliacion/reglas')
    reglas.value = data
  } finally {
    loadingReglas.value = false
  }
}

function abrirNuevaRegla() {
  reglaEditando.value = null
  reglaForm.value = reglaFormVacio()
  dialogRegla.value = true
}

function editarRegla(regla) {
  reglaEditando.value = regla
  reglaForm.value = { ...regla }
  dialogRegla.value = true
}

async function guardarRegla() {
  savingRegla.value = true
  try {
    if (reglaEditando.value?.id) {
      const { data } = await axios.put(`/api/conciliacion/reglas/${reglaEditando.value.id}`, reglaForm.value)
      const idx = reglas.value.findIndex(r => r.id === data.id)
      if (idx >= 0) reglas.value[idx] = data
    } else {
      const { data } = await axios.post('/api/conciliacion/reglas', reglaForm.value)
      reglas.value.push(data)
      reglas.value.sort((a, b) => a.prioridad - b.prioridad)
    }
    dialogRegla.value = false
  } catch (e) {
    console.error(e)
  } finally {
    savingRegla.value = false
  }
}

async function actualizarRegla(regla, changes) {
  try {
    const { data } = await axios.put(`/api/conciliacion/reglas/${regla.id}`, changes)
    Object.assign(regla, data)
  } catch (e) {
    console.error(e)
  }
}

async function eliminarRegla(id) {
  if (!confirm('¿Eliminar esta regla?')) return
  await axios.delete(`/api/conciliacion/reglas/${id}`)
  reglas.value = reglas.value.filter(r => r.id !== id)
}

async function aplicarReglas() {
  loadingAplicar.value = true
  try {
    const { data } = await axios.post('/api/conciliacion/reglas/aplicar')
    alert(`Reglas aplicadas: ${data.aplicados} movimientos categorizados de ${data.total}`)
    await cargarMovimientos()
  } finally {
    loadingAplicar.value = false
  }
}

// ── Init ─────────────────────────────────────────────────────────────────────

onMounted(async () => {
  await cargarMovimientos()
  await cargarFlujo()
  await cargarReglas()
  cargarSaldo()
  cargarEmpleados()
})
</script>
