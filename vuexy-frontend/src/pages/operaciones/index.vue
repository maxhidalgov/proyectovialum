<template>
  <v-container fluid class="pa-4">

    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div>
        <h2 class="text-h5 font-weight-bold">Panel de Operaciones</h2>
        <p class="text-caption text-grey mt-1">Cotizaciones aprobadas y seguimiento de producción</p>
      </div>
      <div class="d-flex align-center" style="gap:12px">
        <v-btn color="primary" variant="flat" size="small" class="text-none" prepend-icon="mdi-plus" @click="abrirCrearManual">
          Agregar proyecto
        </v-btn>
        <v-btn-toggle v-model="vista" mandatory color="primary" variant="outlined" divided rounded="lg">
          <v-btn value="tablero" size="small" class="text-none px-3">
            <v-icon start size="18">mdi-view-dashboard-variant</v-icon>Tablero
          </v-btn>
          <v-btn value="tabla" size="small" class="text-none px-3">
            <v-icon start size="18">mdi-table</v-icon>Tabla
          </v-btn>
          <v-btn value="kanban" size="small" class="text-none px-3">
            <v-icon start size="18">mdi-view-column</v-icon>Kanban
          </v-btn>
        </v-btn-toggle>
      </div>
    </div>

    <v-progress-linear v-if="cargando" indeterminate color="primary" class="mb-2" />

    <!-- Stat cards -->
    <v-row class="mb-4" dense>
      <v-col cols="6" sm="4" md="2" v-for="card in statCards" :key="card.label">
        <v-card variant="tonal" :color="card.color" class="pa-3 text-center">
          <v-icon :color="card.color" size="22" class="mb-1">{{ card.icon }}</v-icon>
          <div class="text-h6 font-weight-bold">{{ card.valor }}</div>
          <div class="text-caption text-medium-emphasis">{{ card.label }}</div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filtros -->
    <v-card class="mb-3 pa-3" variant="outlined">
      <v-row dense align="center">
        <v-col cols="12" sm="3">
          <v-text-field
            v-model="filtros.busqueda"
            label="Buscar cliente..."
            prepend-inner-icon="mdi-magnify"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="2">
          <v-select
            v-model="filtros.estado"
            :items="['Aprobada','En Producción','Entregada','Facturada']"
            label="Estado"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="2">
          <v-select
            v-model="filtros.estadoProd"
            :items="['Sin asignar', ...estadosProduccion]"
            label="Estado producción"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="2">
          <v-select
            v-model="filtros.vendedor"
            :items="vendedoresUnicos"
            label="Vendedor"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="2">
          <v-select
            v-model="filtros.saldo"
            :items="[{ title: 'Con saldo pendiente', value: 'pendiente' }, { title: 'Sin saldo', value: 'pagado' }]"
            label="Saldo"
            density="compact"
            variant="outlined"
            hide-details
            clearable
          />
        </v-col>
        <v-col cols="6" sm="1" class="d-flex align-center">
          <v-btn
            v-if="filtros.busqueda || filtros.estado || filtros.estadoProd || filtros.vendedor || filtros.saldo"
            size="small"
            variant="text"
            color="grey"
            @click="limpiarFiltros"
          >
            Limpiar
          </v-btn>
        </v-col>
      </v-row>

      <!-- Alertas resumen + toggle terminados -->
      <v-row dense class="mt-2">
        <v-col class="d-flex align-center flex-wrap" style="gap:8px">
          <v-chip v-if="alertas.vencidas > 0" color="error" size="small" prepend-icon="mdi-calendar-alert">
            {{ alertas.vencidas }} entrega{{ alertas.vencidas > 1 ? 's' : '' }} vencida{{ alertas.vencidas > 1 ? 's' : '' }}
          </v-chip>
          <v-chip v-if="alertas.sinMover > 0" color="warning" size="small" prepend-icon="mdi-clock-alert">
            {{ alertas.sinMover }} sin mover hace +{{ DIAS_ALERTA }} días
          </v-chip>
          <v-spacer />
          <v-chip
            size="small"
            :color="verTerminados ? 'success' : undefined"
            :variant="verTerminados ? 'flat' : 'outlined'"
            class="cursor-pointer"
            @click="verTerminados = !verTerminados"
          >
            <v-icon start size="14">{{ verTerminados ? 'mdi-eye' : 'mdi-eye-off-outline' }}</v-icon>
            {{ verTerminados ? 'Ocultar terminados' : `Ver terminados (${terminadosCount})` }}
          </v-chip>
        </v-col>
      </v-row>
    </v-card>

    <!-- ── VISTA TABLA ─────────────────────────────────────────── -->
    <!-- ── VISTA TABLERO (agrupada por material, estilo Monday) ──────── -->
    <template v-if="vista === 'tablero'">
      <div v-if="!gruposTablero.length && !cargando" class="text-center text-medium-emphasis py-10">
        No hay proyectos que coincidan con los filtros.
      </div>

      <div v-for="g in gruposTablero" :key="g.material" class="mb-5">
        <!-- Encabezado del grupo -->
        <div class="tablero-group-header" :style="{ '--g-color': g.color }">
          <v-icon :color="g.color" size="18">mdi-shape</v-icon>
          <span class="font-weight-bold text-body-1">{{ g.material }}</span>
          <v-chip size="x-small" :color="g.color" variant="flat" class="ml-1">{{ g.items.length }}</v-chip>
          <v-spacer />
          <span class="text-caption text-medium-emphasis d-none d-sm-inline">
            Total <strong>{{ fmt(g.totalBruto) }}</strong> ·
            Abono <strong class="text-success">{{ fmt(g.totalAbono) }}</strong> ·
            Deuda <strong :class="g.totalDeuda > 0 ? 'text-warning' : 'text-success'">{{ fmt(g.totalDeuda) }}</strong>
          </span>
        </div>

        <v-card variant="outlined" class="tablero-card">
          <div style="overflow-x:auto">
            <table class="tablero-table">
              <thead>
                <tr>
                  <th style="min-width:150px">Elemento</th>
                  <th>Tipo</th>
                  <th style="min-width:110px">EETT / Color</th>
                  <th style="min-width:150px">Estado</th>
                  <th class="text-center">Cant</th>
                  <th class="text-right">M²</th>
                  <th class="text-center">Pedido</th>
                  <th class="text-center">Postv.</th>
                  <th class="text-center" style="min-width:70px">Inicio</th>
                  <th class="text-right">Total</th>
                  <th class="text-right">Abono</th>
                  <th class="text-right">Deuda</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in g.items" :key="item.id" :class="{ 'row-vencida': estaVencida(item) }">
                  <!-- Elemento (cliente / nombre manual) -->
                  <td>
                    <div class="d-flex align-center gap-1">
                      <template v-if="item.es_manual">
                        <v-tooltip text="Proyecto manual" location="top">
                          <template #activator="{ props }">
                            <v-icon v-bind="props" size="13" color="primary">mdi-hand-back-right-outline</v-icon>
                          </template>
                        </v-tooltip>
                        <v-text-field :model-value="item.cliente" density="compact" variant="plain" hide-details
                          style="min-width:120px" class="font-weight-medium"
                          @blur="e => e.target.value !== item.cliente && updateCampo(item, 'nombre_manual', e.target.value)" />
                        <v-btn icon size="x-small" variant="text" color="info" title="Vincular a cotización" @click="abrirVincular(item)">
                          <v-icon size="14">mdi-link-variant</v-icon>
                        </v-btn>
                        <v-btn icon size="x-small" variant="text" color="error" title="Borrar proyecto" @click="borrarManual(item)">
                          <v-icon size="14">mdi-delete-outline</v-icon>
                        </v-btn>
                      </template>
                      <template v-else>
                        <span class="font-weight-medium">{{ item.cliente }}</span>
                        <v-icon v-if="estaVencida(item)" color="error" size="13" title="Entrega vencida">mdi-calendar-alert</v-icon>
                        <v-icon v-if="sinMoverMucho(item)" color="warning" size="13" title="Sin avanzar hace días">mdi-clock-alert</v-icon>
                      </template>
                    </div>
                  </td>
                  <!-- Tipo / material -->
                  <td>
                    <v-menu>
                      <template #activator="{ props }">
                        <v-chip v-bind="props" :color="colorMaterial(item.material)" size="small" variant="tonal" class="cursor-pointer text-no-wrap">
                          {{ item.material }}<v-icon end size="14">mdi-menu-down</v-icon>
                        </v-chip>
                      </template>
                      <v-list density="compact">
                        <v-list-item v-for="m in materiales" :key="m" @click="updateCampo(item, 'material', m)">
                          <template #prepend><v-icon :color="colorMaterial(m)" size="12">mdi-circle</v-icon></template>
                          <v-list-item-title>{{ m }}</v-list-item-title>
                        </v-list-item>
                      </v-list>
                    </v-menu>
                  </td>
                  <!-- EETT / Color -->
                  <td>
                    <v-text-field
                      :model-value="item.eett" density="compact" variant="plain" hide-details
                      placeholder="—" style="min-width:130px"
                      @blur="e => e.target.value !== (item.eett ?? '') && updateCampo(item, 'eett', e.target.value || null)"
                    />
                  </td>
                  <!-- Estado producción -->
                  <td>
                    <div class="d-flex align-center gap-1">
                      <v-menu>
                        <template #activator="{ props }">
                          <v-chip v-bind="props" :color="colorEstadoProd(item.estado_produccion)" size="small"
                            :variant="item.estado_produccion ? 'tonal' : 'outlined'" class="cursor-pointer text-no-wrap">
                            {{ item.estado_produccion || 'Sin estado' }}<v-icon end size="14">mdi-menu-down</v-icon>
                          </v-chip>
                        </template>
                        <v-list density="compact">
                          <v-list-item v-for="e in estadosProduccion" :key="e" @click="updateCampo(item, 'estado_produccion', e)">
                            <template #prepend><v-icon :color="colorEstadoProd(e)" size="12">mdi-circle</v-icon></template>
                            <v-list-item-title>{{ e }}</v-list-item-title>
                          </v-list-item>
                          <v-divider />
                          <v-list-item @click="updateCampo(item, 'estado_produccion', null)">
                            <v-list-item-title class="text-medium-emphasis">Quitar</v-list-item-title>
                          </v-list-item>
                        </v-list>
                      </v-menu>
                      <v-tooltip v-if="sinMoverMucho(item)" :text="`${diasEnEstado(item)} días en este estado`" location="top">
                        <template #activator="{ props }">
                          <v-chip v-bind="props" color="warning" size="x-small" variant="tonal">{{ diasEnEstado(item) }}d</v-chip>
                        </template>
                      </v-tooltip>
                    </div>
                  </td>
                  <!-- Cant / M2 -->
                  <td class="text-center">
                    <v-text-field v-if="item.es_manual" :model-value="item.cant_ventanas" type="number" min="0"
                      density="compact" variant="plain" hide-details style="max-width:56px"
                      @blur="e => updateCampo(item, 'cant_manual', Number(e.target.value) || 0)" />
                    <v-chip v-else size="x-small" color="blue" variant="tonal">{{ item.cant_ventanas }}</v-chip>
                  </td>
                  <td class="text-right text-caption">
                    <v-text-field v-if="item.es_manual" :model-value="item.m2" type="number" min="0"
                      density="compact" variant="plain" hide-details reverse style="max-width:70px"
                      @blur="e => updateCampo(item, 'm2_manual', Number(e.target.value) || 0)" />
                    <span v-else>{{ item.m2 }}</span>
                  </td>
                  <!-- Pedido proveedor -->
                  <td class="text-center">
                    <v-chip
                      size="small" variant="tonal"
                      :color="item.pedido_proveedor ? 'success' : 'grey'"
                      :prepend-icon="item.pedido_proveedor ? 'mdi-check' : 'mdi-clock-outline'"
                      class="cursor-pointer"
                      @click="updateCampo(item, 'pedido_proveedor', !item.pedido_proveedor)"
                    >{{ item.pedido_proveedor ? 'Listo' : 'Pendiente' }}</v-chip>
                  </td>
                  <!-- Postventa -->
                  <td class="text-center">
                    <v-chip
                      size="small" variant="tonal"
                      :color="item.postventa ? 'deep-purple' : 'grey'"
                      class="cursor-pointer"
                      @click="updateCampo(item, 'postventa', !item.postventa)"
                    >{{ item.postventa ? 'Sí' : 'No' }}</v-chip>
                  </td>
                  <!-- Inicio -->
                  <td class="text-center">
                    <span class="text-caption text-medium-emphasis">{{ fmtFechaCorta(item.fecha) }}</span>
                  </td>
                  <!-- Montos -->
                  <td class="text-right text-caption">
                    <v-text-field v-if="item.es_manual" :model-value="item.total" type="number" min="0" prefix="$"
                      density="compact" variant="plain" hide-details reverse style="max-width:120px"
                      @blur="e => updateCampo(item, 'total', Number(e.target.value) || 0)" />
                    <span v-else>{{ fmt(item.total) }}</span>
                  </td>
                  <td class="text-right">
                    <div class="text-caption cursor-pointer d-inline-flex align-center"
                      :class="item.total_abonado > 0 ? 'text-success' : 'text-medium-emphasis'"
                      title="Ver / editar abonos" @click="abrirAbonos(item)">
                      {{ fmt(item.total_abonado) }}
                      <v-icon size="12" class="ml-1">mdi-format-list-bulleted</v-icon>
                    </div>
                    <v-tooltip v-if="!item.es_manual && item.falta_conciliar > 0" location="top"
                      :text="`Pagado al emitir (${fmt(item.falta_conciliar)}) pero aún sin conciliar en el banco. Al conciliar se pone en verde.`">
                      <template #activator="{ props }">
                        <div>
                          <v-chip v-bind="props" size="x-small" color="warning" variant="tonal" class="cursor-pointer mt-1" to="/facturacion">
                            <v-icon start size="11">mdi-alert-circle-outline</v-icon>Falta conciliar
                          </v-chip>
                        </div>
                      </template>
                    </v-tooltip>
                  </td>
                  <td class="text-right">
                    <v-chip size="small" :color="item.saldo <= 0 ? 'success' : 'warning'" variant="tonal">{{ fmt(item.saldo) }}</v-chip>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="tablero-subtotal">
                  <td colspan="9" class="text-right">Subtotal {{ g.material }}</td>
                  <td class="text-right">{{ fmt(g.totalBruto) }}</td>
                  <td class="text-right text-success">{{ fmt(g.totalAbono) }}</td>
                  <td class="text-right" :class="g.totalDeuda > 0 ? 'text-warning' : 'text-success'">{{ fmt(g.totalDeuda) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </v-card>
      </div>
    </template>

    <template v-if="vista === 'tabla'">
      <v-card>
        <v-data-table
          :headers="headers"
          :items="cotizacionesFiltradas"
          :items-per-page="25"
          density="compact"
          class="operaciones-table"
          :row-props="rowProps"
        >
          <!-- Cliente -->
          <template #item.cliente="{ item }">
            <div class="d-flex align-center gap-1">
              <span class="text-body-2 font-weight-medium">{{ item.cliente }}</span>
              <v-tooltip v-if="estaVencida(item)" text="Entrega vencida" location="top">
                <template #activator="{ props }">
                  <v-icon v-bind="props" color="error" size="14">mdi-calendar-alert</v-icon>
                </template>
              </v-tooltip>
              <v-tooltip v-if="sinMoverMucho(item)" text="Sin cambio de estado hace mucho" location="top">
                <template #activator="{ props }">
                  <v-icon v-bind="props" color="warning" size="14">mdi-clock-alert</v-icon>
                </template>
              </v-tooltip>
            </div>
          </template>

          <!-- Estado cotización -->
          <template #item.estado="{ item }">
            <v-chip size="small" :color="colorEstadoCot(item.estado)" variant="tonal">
              {{ item.estado }}
            </v-chip>
          </template>

          <!-- Total -->
          <template #item.total="{ item }">
            <span class="text-body-2">{{ fmt(item.total) }}</span>
          </template>

          <!-- Abonado -->
          <template #item.total_abonado="{ item }">
            <span :class="item.saldo <= 0 ? 'text-success' : 'text-warning'" class="text-body-2">
              {{ fmt(item.total_abonado) }}
            </span>
          </template>

          <!-- Saldo -->
          <template #item.saldo="{ item }">
            <v-chip size="small" :color="item.saldo <= 0 ? 'success' : 'warning'" variant="tonal">
              {{ fmt(item.saldo) }}
            </v-chip>
          </template>

          <!-- Pedido proveedor -->
          <template #item.pedido_proveedor="{ item }">
            <v-checkbox-btn
              :model-value="item.pedido_proveedor"
              color="primary"
              @update:model-value="val => updateCampo(item, 'pedido_proveedor', val)"
            />
          </template>

          <!-- Estado producción -->
          <template #item.estado_produccion="{ item }">
            <div class="d-flex align-center gap-1">
              <v-select
                :model-value="item.estado_produccion"
                :items="estadosProduccion"
                density="compact"
                variant="plain"
                hide-details
                clearable
                style="min-width:180px"
                @update:model-value="val => updateCampo(item, 'estado_produccion', val)"
              >
                <template #selection="{ item: sel }">
                  <v-chip :color="colorEstadoProd(sel.value)" size="small" variant="flat">
                    {{ sel.value || '—' }}
                  </v-chip>
                </template>
              </v-select>
              <v-tooltip v-if="sinMoverMucho(item)" :text="`${diasEnEstado(item)} días en este estado`" location="top">
                <template #activator="{ props }">
                  <v-chip v-bind="props" color="warning" size="x-small" variant="tonal">
                    {{ diasEnEstado(item) }}d
                  </v-chip>
                </template>
              </v-tooltip>
            </div>
          </template>

          <!-- Fecha entrega -->
          <template #item.fecha_entrega="{ item }">
            <div class="d-flex flex-column">
              <input
                type="date"
                :value="item.fecha_entrega ?? ''"
                :class="['date-input', estaVencida(item) ? 'date-vencida' : '']"
                @change="e => updateCampo(item, 'fecha_entrega', e.target.value || null)"
              />
              <button
                v-if="!item.fecha_entrega && item.entrega_sugerida"
                type="button"
                class="sugerida-link"
                title="Usar fecha sugerida por el sistema"
                @click="updateCampo(item, 'fecha_entrega', item.entrega_sugerida)"
              >
                <v-icon size="11">mdi-lightbulb-on-outline</v-icon>
                Sugerida: {{ fmtFechaCorta(item.entrega_sugerida) }}
              </button>
            </div>
          </template>

          <!-- Tiempos (T0 = medición) -->
          <template #item.tiempos="{ item }">
            <div class="d-flex align-center gap-1">
              <div v-if="item.medido_en">
                <v-chip
                  size="x-small"
                  :color="item.instalado_en ? 'success' : 'indigo'"
                  variant="tonal"
                  prepend-icon="mdi-timer-outline"
                >
                  {{ item.dias_produccion }} d
                </v-chip>
                <div class="text-caption text-grey mt-1">
                  Medido: {{ item.medido_en }}{{ item.instalado_en ? '' : ' · en curso' }}
                </div>
              </div>
              <span v-else class="text-caption text-grey">Sin medir</span>
              <v-tooltip text="Ver línea de tiempo" location="top">
                <template #activator="{ props }">
                  <v-btn v-bind="props" size="x-small" variant="text" icon="mdi-timeline-clock-outline" @click="abrirTimeline(item)" />
                </template>
              </v-tooltip>
            </div>
          </template>

          <!-- Ventanas / M² -->
          <template #item.cant_ventanas="{ item }">
            <v-chip size="small" color="blue" variant="tonal">{{ item.cant_ventanas }}</v-chip>
          </template>
          <template #item.m2="{ item }">
            <span class="text-body-2">{{ item.m2 }} m²</span>
          </template>

          <!-- Notas -->
          <template #item.notas_operaciones="{ item }">
            <v-text-field
              :model-value="item.notas_operaciones"
              density="compact"
              variant="plain"
              hide-details
              placeholder="—"
              style="min-width:140px"
              @blur="e => updateCampo(item, 'notas_operaciones', e.target.value || null)"
            />
          </template>
        </v-data-table>
      </v-card>
    </template>

    <!-- ── VISTA KANBAN ────────────────────────────────────────── -->
    <template v-if="vista === 'kanban'">
      <div class="kanban-board">
        <div v-for="col in columnasKanban" :key="col.estado ?? 'sin'" class="kanban-col">
          <div class="kanban-col-header" :style="{ borderColor: col.color }">
            <v-icon :color="col.color" size="16" class="mr-1">{{ col.icon }}</v-icon>
            <span class="text-caption font-weight-bold text-uppercase">{{ col.label ?? col.estado }}</span>
            <v-chip size="x-small" class="ml-auto" :color="col.color" variant="tonal">
              {{ tarjetasPorEstado(col.estado).length }}
            </v-chip>
          </div>

          <div class="kanban-col-body">
            <v-card
              v-for="item in tarjetasPorEstado(col.estado)"
              :key="item.id"
              class="kanban-card mb-2"
              variant="outlined"
              :class="{ 'kanban-card--vencida': estaVencida(item), 'kanban-card--alerta': sinMoverMucho(item) }"
            >
              <v-card-text class="pa-3">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-body-2 font-weight-bold">{{ item.cliente }}</span>
                  <div class="d-flex align-center gap-1">
                    <v-icon v-if="estaVencida(item)" color="error" size="14">mdi-calendar-alert</v-icon>
                    <v-icon v-if="sinMoverMucho(item)" color="warning" size="14">mdi-clock-alert</v-icon>
                    <span class="text-caption text-grey">#{{ item.id }}</span>
                  </div>
                </div>
                <div class="text-caption text-grey mb-1">{{ item.fecha }}</div>
                <div v-if="item.fecha_entrega" class="text-caption mb-2" :class="estaVencida(item) ? 'text-error' : 'text-grey'">
                  <v-icon size="12">mdi-calendar</v-icon> Entrega: {{ item.fecha_entrega }}
                </div>
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="text-caption">Total</span>
                  <span class="text-body-2 font-weight-medium">{{ fmt(item.total) }}</span>
                </div>
                <div class="d-flex justify-space-between align-center mb-1">
                  <span class="text-caption">Saldo</span>
                  <v-chip size="x-small" :color="item.saldo <= 0 ? 'success' : 'warning'" variant="tonal">
                    {{ fmt(item.saldo) }}
                  </v-chip>
                </div>
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-caption">{{ item.cant_ventanas }} ventanas</span>
                  <span class="text-caption">{{ item.m2 }} m²</span>
                </div>
                <div class="d-flex gap-1 flex-wrap mb-2">
                  <v-chip v-if="item.pedido_proveedor" size="x-small" color="blue" variant="tonal">
                    <v-icon start size="10">mdi-check</v-icon>Pedido OK
                  </v-chip>
                  <v-chip v-if="sinMoverMucho(item)" size="x-small" color="warning" variant="tonal">
                    {{ diasEnEstado(item) }}d sin mover
                  </v-chip>
                </div>
                <v-select
                  :model-value="item.estado_produccion"
                  :items="estadosProduccion"
                  density="compact"
                  variant="outlined"
                  hide-details
                  clearable
                  class="mt-1"
                  label="Mover a..."
                  style="font-size:12px"
                  @update:model-value="val => updateCampo(item, 'estado_produccion', val)"
                />
              </v-card-text>
            </v-card>

            <div v-if="!tarjetasPorEstado(col.estado).length" class="text-center text-caption text-grey pa-4">
              Sin cotizaciones
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Agregar proyecto manual -->
    <v-dialog v-model="dialogManual.show" max-width="560">
      <v-card>
        <v-card-title class="d-flex align-center gap-2 pa-4">
          <v-icon color="primary">mdi-plus-box</v-icon> Agregar proyecto
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <v-text-field v-model="dialogManual.nombre_manual" label="Nombre / cliente" variant="outlined" density="compact" class="mb-3" hide-details autofocus />
          <v-row dense>
            <v-col cols="6">
              <v-select v-model="dialogManual.material" :items="materiales" label="Tipo / material" variant="outlined" density="compact" hide-details />
            </v-col>
            <v-col cols="6">
              <v-select v-model="dialogManual.estado_produccion" :items="estadosProduccion" label="Estado (opcional)" variant="outlined" density="compact" clearable hide-details />
            </v-col>
          </v-row>
          <v-text-field v-model="dialogManual.eett" label="EETT / Color (opcional)" variant="outlined" density="compact" class="mt-3" hide-details />
          <v-row dense class="mt-1">
            <v-col cols="6"><v-text-field v-model.number="dialogManual.cant_manual" type="number" min="0" label="Cantidad" variant="outlined" density="compact" hide-details /></v-col>
            <v-col cols="6"><v-text-field v-model.number="dialogManual.m2_manual" type="number" min="0" label="M²" variant="outlined" density="compact" hide-details /></v-col>
          </v-row>
          <v-row dense class="mt-1">
            <v-col cols="6"><v-text-field v-model.number="dialogManual.total" type="number" min="0" label="Total (bruto)" prefix="$" variant="outlined" density="compact" hide-details /></v-col>
            <v-col cols="6"><v-text-field v-model.number="dialogManual.abono_manual" type="number" min="0" label="Abono" prefix="$" variant="outlined" density="compact" hide-details /></v-col>
          </v-row>
          <v-text-field v-model="dialogManual.fecha" type="date" label="Inicio" variant="outlined" density="compact" class="mt-3" hide-details />
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-3">
          <v-spacer />
          <v-btn variant="text" @click="dialogManual.show = false">Cancelar</v-btn>
          <v-btn color="primary" variant="flat" :loading="dialogManual.loading" :disabled="!dialogManual.nombre_manual" @click="crearManual">Crear</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Detalle de abonos -->
    <v-dialog v-model="dialogAbonos.show" max-width="520">
      <v-card v-if="dialogAbonos.item">
        <v-card-title class="d-flex align-center gap-2 pa-4">
          <v-icon color="success">mdi-cash-multiple</v-icon>
          Abonos — {{ dialogAbonos.item.cliente }}
          <v-spacer />
          <span class="text-body-2 font-weight-bold text-success">{{ fmt(sumaAbonos) }}</span>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <div v-if="dialogAbonos.loading" class="text-center py-4"><v-progress-circular indeterminate size="24" /></div>
          <template v-else>
            <div v-if="!dialogAbonos.abonos.length" class="text-center text-medium-emphasis py-3">Sin abonos registrados.</div>
            <v-table v-else density="compact" class="mb-2">
              <tbody>
                <tr v-for="(a, i) in dialogAbonos.abonos" :key="a.id ?? i">
                  <td class="text-no-wrap">{{ fmtFechaCorta(a.fecha) }}</td>
                  <td><v-chip size="x-small" variant="tonal" :color="a.fuente === 'Tarjeta / Transbank' ? 'primary' : a.fuente === 'Transferencia' ? 'info' : 'secondary'">{{ a.fuente }}</v-chip></td>
                  <td class="text-right font-weight-medium">{{ fmt(a.monto) }}</td>
                  <td class="text-right" style="width:36px">
                    <v-btn v-if="a.editable" icon size="x-small" variant="text" color="error" @click="borrarAbono(a)"><v-icon size="14">mdi-close</v-icon></v-btn>
                  </td>
                </tr>
              </tbody>
            </v-table>

            <!-- Agregar abono (solo manuales) -->
            <template v-if="dialogAbonos.item.es_manual">
              <v-divider class="my-2" />
              <div class="text-caption font-weight-bold mb-2">Agregar abono</div>
              <div class="d-flex align-center flex-wrap" style="gap:8px">
                <v-text-field v-model="nuevoAbono.fecha" type="date" density="compact" variant="outlined" hide-details style="max-width:150px" />
                <v-text-field v-model.number="nuevoAbono.monto" type="number" min="0" prefix="$" placeholder="Monto" density="compact" variant="outlined" hide-details style="max-width:130px" />
                <v-text-field v-model="nuevoAbono.nota" placeholder="Nota (opcional)" density="compact" variant="outlined" hide-details style="min-width:120px;flex:1" />
                <v-btn color="primary" size="small" :disabled="!nuevoAbono.monto" :loading="dialogAbonos.saving" @click="agregarAbono">Agregar</v-btn>
              </div>
            </template>
            <div v-else class="text-caption text-medium-emphasis mt-1">
              Abonos automáticos desde las facturas/conciliación (solo lectura).
            </div>
          </template>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-3">
          <v-spacer />
          <v-btn variant="text" @click="dialogAbonos.show = false">Cerrar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Vincular manual a cotización -->
    <v-dialog v-model="dialogVincular.show" max-width="560">
      <v-card v-if="dialogVincular.item">
        <v-card-title class="d-flex align-center gap-2 pa-4">
          <v-icon color="info">mdi-link-variant</v-icon> Vincular a cotización
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <p class="text-body-2 text-medium-emphasis mb-3">
            El seguimiento de <strong>{{ dialogVincular.item.cliente }}</strong> se pasa a la cotización que elijas, y este proyecto manual se elimina (para no duplicar).
          </p>
          <v-text-field v-model="dialogVincular.buscar" label="Buscar cotización (cliente o #)" density="compact" variant="outlined"
            prepend-inner-icon="mdi-magnify" hide-details clearable class="mb-3" @update:model-value="buscarCotizaciones" />
          <div v-if="dialogVincular.loading" class="text-center py-3"><v-progress-circular indeterminate size="22" /></div>
          <v-table v-else-if="dialogVincular.resultados.length" density="compact">
            <tbody>
              <tr v-for="r in dialogVincular.resultados" :key="r.id" class="cursor-pointer" @click="confirmarVincular(r)">
                <td>#{{ r.id }}</td>
                <td>{{ r.cliente }}</td>
                <td class="text-right">{{ fmt(r.total) }}</td>
                <td><v-btn size="x-small" color="info" variant="tonal">Vincular</v-btn></td>
              </tr>
            </tbody>
          </v-table>
          <div v-else class="text-center text-medium-emphasis py-3 text-caption">Escribe para buscar una cotización.</div>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-3">
          <v-spacer />
          <v-btn variant="text" @click="dialogVincular.show = false">Cancelar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Línea de tiempo -->
    <v-dialog v-model="timeline.show" max-width="540">
      <v-card v-if="timeline.item">
        <v-card-title class="d-flex align-center gap-2 pa-4">
          <v-icon color="indigo">mdi-timeline-clock-outline</v-icon>
          <div>
            <div class="text-body-1 font-weight-bold">{{ timeline.item.cliente }}</div>
            <div class="text-caption text-grey">
              Línea de tiempo · T0 = medición
              <template v-if="timeline.item.medido_en">
                · {{ timeline.item.dias_produccion }} días {{ timeline.item.instalado_en ? 'de producción' : 'en curso' }}
              </template>
            </div>
          </div>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <v-timeline v-if="timeline.item.timeline?.length" density="compact" side="end" truncate-line="both">
            <v-timeline-item
              v-for="h in timeline.item.timeline"
              :key="h.id"
              :dot-color="h.tipo === 'produccion' ? colorEstadoProd(h.estado) : 'teal'"
              size="x-small"
            >
              <div class="d-flex justify-space-between align-center gap-3">
                <div>
                  <div class="text-body-2 font-weight-medium">{{ h.estado || '—' }}</div>
                  <div class="text-caption text-grey">
                    {{ h.tipo === 'produccion' ? 'Producción' : 'Comercial' }}
                    <span v-if="h.estado === 'Lista para Corte'" class="text-indigo font-weight-bold">· medición (T0)</span>
                  </div>
                </div>
                <div class="d-flex align-center gap-1">
                  <input
                    v-if="h.tipo === 'produccion'"
                    type="date"
                    :value="(h.fecha || '').slice(0, 10)"
                    class="date-input"
                    @change="e => guardarFechaHito(h, e.target.value)"
                  />
                  <span v-else class="text-caption text-grey">{{ (h.fecha || '').slice(0, 10) }}</span>
                  <v-btn
                    v-if="h.tipo === 'produccion'"
                    size="x-small"
                    variant="text"
                    color="error"
                    icon="mdi-delete-outline"
                    @click="borrarHito(h)"
                  />
                </div>
              </div>
            </v-timeline-item>
          </v-timeline>
          <div v-else class="text-center text-caption text-grey py-6">
            Sin historial aún. Se registrará automáticamente con cada cambio de estado.
          </div>

          <!-- Agregar hito con fecha real (poner al día trabajos en curso) -->
          <v-divider class="my-3" />
          <div class="text-caption font-weight-bold mb-2">Agregar hito con fecha real</div>
          <div class="d-flex gap-2 align-center">
            <v-select
              v-model="nuevoHito.estado"
              :items="estadosProduccion"
              label="Estado"
              density="compact"
              variant="outlined"
              hide-details
              style="max-width:220px"
            />
            <input v-model="nuevoHito.fecha" type="date" class="date-input date-input--box" />
            <v-btn
              size="small"
              color="indigo"
              variant="flat"
              prepend-icon="mdi-plus"
              :disabled="!nuevoHito.estado || !nuevoHito.fecha"
              @click="agregarHito"
            >
              Agregar
            </v-btn>
          </div>
          <p class="text-caption text-grey mt-3">
            Para trabajos ya en curso: agrega el hito <strong>"Lista para Corte"</strong> con la fecha real de medición (T0) y los demás hitos que correspondan.
          </p>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-3">
          <v-spacer />
          <v-btn variant="text" @click="timeline.show = false">Cerrar</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snack.show" :color="snack.color" timeout="3000" location="top">
      {{ snack.msg }}
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/axiosInstance'

const DIAS_ALERTA = 7   // días sin cambio de estado para mostrar alerta

const vista        = ref('tablero')
const cargando     = ref(false)
const cotizaciones = ref([])
const stats        = ref({})

// ── Filtros ──────────────────────────────────────────────────────
const filtros = ref({ busqueda: '', estado: null, estadoProd: null, vendedor: null, saldo: null })

// Ver los proyectos terminados (instalados + pagados + sin postventa). Por defecto ocultos.
const verTerminados = ref(false)

// Un proyecto está "terminado" (sale del tablero activo) si: Instalada + saldo≤0 + sin postventa.
// Si está instalado pero aún debe, o tiene postventa pendiente, sigue siendo activo.
function esTerminado(c) {
  return c.estado_produccion === 'Instalada' && Number(c.saldo) <= 0 && !c.postventa
}
const terminadosCount = computed(() => cotizaciones.value.filter(esTerminado).length)

const vendedoresUnicos = computed(() =>
  [...new Set(cotizaciones.value.map(c => c.vendedor).filter(Boolean))]
)

function limpiarFiltros() {
  filtros.value = { busqueda: '', estado: null, estadoProd: null, vendedor: null, saldo: null }
}

const cotizacionesFiltradas = computed(() => {
  return cotizaciones.value.filter(c => {
    if (!verTerminados.value && esTerminado(c)) return false
    if (filtros.value.busqueda && !c.cliente?.toLowerCase().includes(filtros.value.busqueda.toLowerCase())) return false
    if (filtros.value.estado && c.estado !== filtros.value.estado) return false
    if (filtros.value.estadoProd) {
      if (filtros.value.estadoProd === 'Sin asignar' && c.estado_produccion) return false
      if (filtros.value.estadoProd !== 'Sin asignar' && c.estado_produccion !== filtros.value.estadoProd) return false
    }
    if (filtros.value.vendedor && c.vendedor !== filtros.value.vendedor) return false
    if (filtros.value.saldo === 'pendiente' && c.saldo <= 0) return false
    if (filtros.value.saldo === 'pagado' && c.saldo > 0) return false
    return true
  })
})

// ── Alertas ──────────────────────────────────────────────────────
const hoy = new Date()
hoy.setHours(0, 0, 0, 0)

function estaVencida(item) {
  if (!item.fecha_entrega) return false
  // Una obra ya instalada/entregada no cuenta como vencida
  if (item.estado_produccion === 'Instalada' || item.instalado_en) return false
  return new Date(item.fecha_entrega) < hoy
}

function diasEnEstado(item) {
  // Días reales en el estado de producción actual (según último cambio registrado)
  return item.dias_en_estado ?? 0
}

function sinMoverMucho(item) {
  // Alerta si tiene estado_produccion asignado y lleva más de DIAS_ALERTA sin cambiarlo
  if (!item.estado_produccion || item.dias_en_estado == null) return false
  return item.dias_en_estado > DIAS_ALERTA
}

const alertas = computed(() => ({
  vencidas: cotizacionesFiltradas.value.filter(estaVencida).length,
  sinMover: cotizacionesFiltradas.value.filter(sinMoverMucho).length,
}))

function rowProps({ item }) {
  if (estaVencida(item)) return { class: 'row-vencida' }
  if (sinMoverMucho(item)) return { class: 'row-alerta' }
  return {}
}

// ── Stat cards ───────────────────────────────────────────────────
// Cuentan sobre lo VISIBLE (respeta el filtro y "ver terminados") para que los
// números cuadren con las filas del tablero. Días prod prom viene del backend.
const statCards = computed(() => {
  const l = cotizacionesFiltradas.value
  const sum = (k) => l.reduce((s, i) => s + Number(i[k] || 0), 0)
  return [
    { label: 'Proyectos',      valor: l.length,                                  color: 'primary', icon: 'mdi-file-multiple'  },
    { label: 'Ventanas',       valor: l.reduce((s, i) => s + Number(i.cant_ventanas || 0), 0), color: 'blue', icon: 'mdi-window-open' },
    { label: 'M²',             valor: `${Math.round(sum('m2') * 100) / 100} m²`, color: 'teal',    icon: 'mdi-ruler-square'   },
    { label: 'Total a cobrar', valor: fmt(sum('total')),                         color: 'green',   icon: 'mdi-currency-usd'   },
    { label: 'Abonado',        valor: fmt(sum('total_abonado')),                 color: 'success', icon: 'mdi-cash-check'     },
    { label: 'Saldo pendiente',valor: fmt(sum('saldo')),                         color: 'warning', icon: 'mdi-cash-clock'     },
    { label: 'Días prod. prom',valor: stats.value.dias_produccion_prom != null ? `${stats.value.dias_produccion_prom} d` : '—', color: 'indigo', icon: 'mdi-timer-outline' },
  ]
})

// ── Tabla ────────────────────────────────────────────────────────
const headers = [
  { title: '#',            value: 'id',                width: 48  },
  { title: 'Cliente',      value: 'cliente',           width: 150 },
  { title: 'Vendedor',     value: 'vendedor',          width: 100 },
  { title: 'Estado',       value: 'estado',            width: 100 },
  { title: 'Total',        value: 'total',             width: 100 },
  { title: 'Abonado',      value: 'total_abonado',     width: 100 },
  { title: 'Saldo',        value: 'saldo',             width: 95  },
  { title: 'Pedido Prov.', value: 'pedido_proveedor',  width: 70  },
  { title: 'Estado Prod.', value: 'estado_produccion', width: 180 },
  { title: 'Entrega',      value: 'fecha_entrega',     width: 130 },
  { title: 'Tiempos',      value: 'tiempos',           width: 130, sortable: false },
  { title: 'Ventanas',     value: 'cant_ventanas',     width: 75  },
  { title: 'M²',           value: 'm2',                width: 65  },
  { title: 'Notas',        value: 'notas_operaciones', width: 150 },
]

const estadosProduccion = [
  'En Espera de Medidas',
  'Lista para Corte',
  'En Fabricación',
  'Fabricadas OK',
  'Instalada',
]

// ── Tablero (agrupado por material) ──────────────────────────────
const materiales  = ['PVC', 'Aluminio', 'Otros']
const estadosObra = ['Obra No Emitida', 'Obra Emitida', 'En Ejecución', 'Terminada']

function colorMaterial(m) {
  return { 'PVC': 'indigo', 'Aluminio': 'blue-grey', 'Otros': 'brown' }[m] ?? 'grey'
}
function colorEstadoObra(e) {
  return {
    'Obra No Emitida': 'grey',
    'Obra Emitida':    'blue',
    'En Ejecución':    'orange',
    'Terminada':       'green',
  }[e] ?? 'grey'
}

// Agrupa las cotizaciones filtradas por material, en el orden PVC → Aluminio → Otros
const gruposTablero = computed(() => {
  const orden = ['PVC', 'Aluminio', 'Otros']
  const porMat = {}
  for (const c of cotizacionesFiltradas.value) {
    const m = materiales.includes(c.material) ? c.material : 'Otros'
    ;(porMat[m] ??= []).push(c)
  }
  return orden
    .filter(m => porMat[m]?.length)
    .map(m => {
      const items = porMat[m]
      return {
        material:    m,
        color:       colorMaterial(m),
        items,
        totalBruto:  items.reduce((s, i) => s + Number(i.total || 0), 0),
        totalAbono:  items.reduce((s, i) => s + Number(i.total_abonado || 0), 0),
        totalDeuda:  items.reduce((s, i) => s + Number(i.saldo || 0), 0),
      }
    })
})

const columnasKanban = [
  { estado: null,                   label: 'Sin asignar',       color: 'grey',   icon: 'mdi-help-circle-outline' },
  { estado: 'En Espera de Medidas', color: 'grey',              icon: 'mdi-clock-outline'  },
  { estado: 'Lista para Corte',     color: 'blue',              icon: 'mdi-ruler-square'   },
  { estado: 'En Fabricación',       color: 'orange',            icon: 'mdi-wrench'         },
  { estado: 'Fabricadas OK',        color: 'green',             icon: 'mdi-check-circle'   },
  { estado: 'Instalada',            color: 'purple',            icon: 'mdi-home-check'     },
]

function colorEstadoProd(estado) {
  const map = {
    'En Espera de Medidas': 'grey',
    'Lista para Corte':     'blue',
    'En Fabricación':       'orange',
    'Fabricadas OK':        'green',
    'Instalada':            'purple',
  }
  return map[estado] ?? 'grey'
}

function colorEstadoCot(estado) {
  const map = {
    'Aprobada':      'green',
    'En Producción': 'blue',
    'Entregada':     'purple',
    'Facturada':     'teal',
  }
  return map[estado] ?? 'grey'
}

function tarjetasPorEstado(estado) {
  return cotizacionesFiltradas.value.filter(c =>
    estado === null ? !c.estado_produccion : c.estado_produccion === estado
  )
}

// ── Cargar datos ─────────────────────────────────────────────────
async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get('/api/operaciones')
    cotizaciones.value = data.cotizaciones
    stats.value        = data.stats
  } catch {
    mostrarSnack('Error al cargar operaciones', 'error')
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

// ── Edición inline ───────────────────────────────────────────────
const CAMPOS_RECARGAR = ['estado_produccion', 'total', 'abono_manual', 'cant_manual', 'm2_manual', 'nombre_manual']
async function updateCampo(item, campo, valor) {
  item[campo] = valor
  try {
    await api.patch(`/api/operaciones/${item.id}`, { [campo]: valor })
    // Estos afectan valores derivados (tiempos, saldo, display) → refrescar
    if (CAMPOS_RECARGAR.includes(campo)) await cargar()
  } catch {
    mostrarSnack('Error al guardar', 'error')
    cargar()
  }
}

// ── Proyectos manuales ───────────────────────────────────────────
const dialogManual = ref({ show: false, loading: false, nombre_manual: '', material: 'PVC', estado_produccion: null, eett: '', cant_manual: null, m2_manual: null, total: null, abono_manual: null, fecha: new Date().toISOString().slice(0, 10) })

function abrirCrearManual() {
  dialogManual.value = { show: true, loading: false, nombre_manual: '', material: 'PVC', estado_produccion: null, eett: '', cant_manual: null, m2_manual: null, total: null, abono_manual: null, fecha: new Date().toISOString().slice(0, 10) }
}

async function crearManual() {
  const d = dialogManual.value
  if (!d.nombre_manual) return
  d.loading = true
  try {
    await api.post('/api/operaciones/manual', {
      nombre_manual: d.nombre_manual,
      material: d.material,
      estado_produccion: d.estado_produccion || undefined,
      eett: d.eett || undefined,
      cant_manual: d.cant_manual ?? undefined,
      m2_manual: d.m2_manual ?? undefined,
      total: d.total ?? undefined,
      abono_manual: d.abono_manual ?? undefined,
      fecha: d.fecha || undefined,
    })
    d.show = false
    await cargar()
    mostrarSnack('Proyecto creado')
  } catch {
    mostrarSnack('Error al crear el proyecto', 'error')
  } finally {
    d.loading = false
  }
}

async function borrarManual(item) {
  if (!confirm(`¿Borrar el proyecto "${item.cliente}"?`)) return
  try {
    await api.delete(`/api/operaciones/manual/${item.id}`)
    cotizaciones.value = cotizaciones.value.filter(c => c.id !== item.id)
    mostrarSnack('Proyecto borrado')
  } catch {
    mostrarSnack('Error al borrar', 'error')
  }
}

// ── Detalle de abonos ────────────────────────────────────────────
const dialogAbonos = ref({ show: false, loading: false, saving: false, item: null, abonos: [] })
const nuevoAbono   = ref({ fecha: new Date().toISOString().slice(0, 10), monto: null, nota: '' })
const sumaAbonos   = computed(() => dialogAbonos.value.abonos.reduce((s, a) => s + Number(a.monto || 0), 0))

async function abrirAbonos(item) {
  dialogAbonos.value = { show: true, loading: true, saving: false, item, abonos: [] }
  nuevoAbono.value = { fecha: new Date().toISOString().slice(0, 10), monto: null, nota: '' }
  try {
    const { data } = await api.get(`/api/operaciones/${item.id}/abonos`)
    dialogAbonos.value.abonos = data.abonos || []
  } catch {
    mostrarSnack('Error al cargar abonos', 'error')
  } finally {
    dialogAbonos.value.loading = false
  }
}
async function recargarAbonos() {
  const { data } = await api.get(`/api/operaciones/${dialogAbonos.value.item.id}/abonos`)
  dialogAbonos.value.abonos = data.abonos || []
}
async function agregarAbono() {
  const n = nuevoAbono.value
  if (!n.monto) return
  dialogAbonos.value.saving = true
  try {
    await api.post(`/api/operaciones/${dialogAbonos.value.item.id}/abonos`, { fecha: n.fecha, monto: Number(n.monto), nota: n.nota || undefined })
    nuevoAbono.value = { fecha: n.fecha, monto: null, nota: '' }
    await recargarAbonos()
    await cargar()
  } catch {
    mostrarSnack('Error al agregar el abono', 'error')
  } finally {
    dialogAbonos.value.saving = false
  }
}
async function borrarAbono(a) {
  try {
    await api.delete(`/api/operaciones/abonos/${a.id}`)
    await recargarAbonos()
    await cargar()
  } catch {
    mostrarSnack('Error al borrar el abono', 'error')
  }
}

// ── Vincular manual → cotización ─────────────────────────────────
const dialogVincular = ref({ show: false, loading: false, item: null, buscar: '', resultados: [] })
function abrirVincular(item) {
  dialogVincular.value = { show: true, loading: false, item, buscar: '', resultados: [] }
}
function buscarCotizaciones(term) {
  const t = (term || '').toLowerCase().trim()
  if (!t) { dialogVincular.value.resultados = []; return }
  dialogVincular.value.resultados = cotizaciones.value
    .filter(c => !c.es_manual && (String(c.id).includes(t) || c.cliente?.toLowerCase().includes(t)))
    .slice(0, 20)
}
async function confirmarVincular(cot) {
  if (!confirm(`¿Vincular "${dialogVincular.value.item.cliente}" a la cotización #${cot.id} (${cot.cliente})?\nEl proyecto manual se eliminará y su seguimiento pasa a la cotización.`)) return
  try {
    await api.post(`/api/operaciones/manual/${dialogVincular.value.item.id}/vincular`, { cotizacion_id: cot.id })
    dialogVincular.value.show = false
    await cargar()
    mostrarSnack('Vinculado — el seguimiento pasó a la cotización')
  } catch {
    mostrarSnack('Error al vincular', 'error')
  }
}

// ── Línea de tiempo ──────────────────────────────────────────────
const timeline  = ref({ show: false, item: null })
const nuevoHito = ref({ estado: null, fecha: '' })

async function abrirTimeline(item) {
  // Traer datos frescos para que la línea de tiempo refleje los últimos cambios
  await cargar()
  const fresh = cotizaciones.value.find(c => c.id === item.id) || item
  timeline.value = { show: true, item: fresh }
  nuevoHito.value = { estado: null, fecha: '' }
}

async function refrescarTimeline() {
  await cargar()
  const fresh = cotizaciones.value.find(c => c.id === timeline.value.item?.id)
  if (fresh) timeline.value.item = fresh
}

async function agregarHito() {
  const { estado, fecha } = nuevoHito.value
  if (!estado || !fecha) return
  try {
    await api.post(`/api/operaciones/${timeline.value.item.id}/historial`, { estado, fecha })
    mostrarSnack('Hito agregado')
    nuevoHito.value = { estado: null, fecha: '' }
    await refrescarTimeline()
  } catch {
    mostrarSnack('Error al agregar el hito', 'error')
  }
}

async function borrarHito(hito) {
  try {
    await api.delete(`/api/operaciones/historial/${hito.id}`)
    mostrarSnack('Hito eliminado')
    await refrescarTimeline()
  } catch {
    mostrarSnack('Error al eliminar el hito', 'error')
  }
}

async function guardarFechaHito(hito, fecha) {
  if (!fecha) return
  try {
    await api.patch(`/api/operaciones/historial/${hito.id}`, { fecha })
    mostrarSnack('Fecha actualizada')
    await refrescarTimeline()
  } catch {
    mostrarSnack('Error al actualizar la fecha', 'error')
  }
}

// ── Helpers ──────────────────────────────────────────────────────
const snack = ref({ show: false, color: 'success', msg: '' })
function mostrarSnack(msg, color = 'success') { snack.value = { show: true, color, msg } }

function fmt(val) {
  return new Intl.NumberFormat('es-CL', {
    style: 'currency', currency: 'CLP', maximumFractionDigits: 0,
  }).format(val || 0)
}

function fmtFechaCorta(iso) {
  if (!iso) return ''
  const [y, m, d] = iso.split('-')
  return `${d}-${m}`
}
</script>

<style scoped>
.operaciones-table :deep(td) {
  padding-top: 4px !important;
  padding-bottom: 4px !important;
}

/* La tabla scrollea horizontalmente dentro de la card si no cabe */
.operaciones-table :deep(.v-table__wrapper) {
  overflow-x: auto;
}

.operaciones-table :deep(.row-vencida td) {
  background: rgba(244, 67, 54, 0.07) !important;
}

.operaciones-table :deep(.row-alerta td) {
  background: rgba(255, 152, 0, 0.07) !important;
}

.date-input {
  background: transparent;
  border: none;
  color: inherit;
  font-size: 0.8rem;
  cursor: pointer;
  outline: none;
}

.date-vencida {
  color: rgb(244, 67, 54) !important;
  font-weight: 600;
}

.date-input--box {
  border: 1px solid rgba(var(--v-border-color), 0.4);
  border-radius: 6px;
  padding: 6px 10px;
  font-size: 0.85rem;
}

.sugerida-link {
  background: transparent;
  border: none;
  color: rgb(var(--v-theme-primary));
  font-size: 0.7rem;
  cursor: pointer;
  text-align: left;
  padding: 2px 0 0;
  opacity: 0.85;
}
.sugerida-link:hover {
  opacity: 1;
  text-decoration: underline;
}

/* Kanban */
.kanban-board {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 16px;
  align-items: flex-start;
}

.kanban-col {
  min-width: 240px;
  max-width: 260px;
  flex-shrink: 0;
  background: rgba(255,255,255,0.04);
  border-radius: 8px;
  overflow: hidden;
}

.kanban-col-header {
  display: flex;
  align-items: center;
  padding: 10px 12px;
  border-left: 3px solid;
  background: rgba(255,255,255,0.06);
  font-size: 11px;
}

.kanban-col-body {
  padding: 8px;
  max-height: calc(100vh - 300px);
  overflow-y: auto;
}

.kanban-card {
  cursor: default;
  transition: box-shadow 0.15s;
}

.kanban-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important;
}

.kanban-card--vencida {
  border-color: rgba(244, 67, 54, 0.5) !important;
}

/* ── Tablero (estilo Monday) ── */
.tablero-group-header {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; margin-bottom: 6px;
  border-left: 4px solid rgba(var(--v-border-color), 0.4);
  background: rgba(var(--v-theme-on-surface), 0.03);
  border-radius: 6px;
}
.tablero-card { overflow: hidden; }
.tablero-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.tablero-table th {
  text-align: left; padding: 8px 10px; white-space: nowrap;
  font-size: 0.68rem; text-transform: uppercase; letter-spacing: .04em;
  color: rgba(var(--v-theme-on-surface), 0.6);
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-on-surface), 0.02);
}
.tablero-table td {
  padding: 4px 10px; vertical-align: middle;
  border-bottom: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.5));
}
.tablero-table tbody tr:nth-child(even) { background: rgba(var(--v-theme-on-surface), 0.018); }
.tablero-table tbody tr:hover { background: rgba(var(--v-theme-primary), 0.06); }
.tablero-table tbody tr.row-vencida { background: rgba(var(--v-theme-error), 0.06); }
/* Primera columna (Elemento) fija al hacer scroll horizontal */
.tablero-table th:first-child,
.tablero-table td:first-child {
  position: sticky; left: 0; z-index: 1;
  background: rgb(var(--v-theme-surface));
}
.tablero-table tbody tr:nth-child(even) td:first-child { background: rgb(var(--v-theme-surface)); }
.tablero-subtotal td {
  padding: 8px 10px; font-weight: 700;
  border-top: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-on-surface), 0.03);
}
.cursor-pointer { cursor: pointer; }

.kanban-card--alerta {
  border-color: rgba(255, 152, 0, 0.5) !important;
}
</style>
