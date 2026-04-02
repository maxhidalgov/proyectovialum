<template>
  <v-container fluid>
    <v-card class="pa-6" elevation="2">
      <v-card-title class="text-h5 mb-4">
        {{ modoEdicion ? 'Editar Cotización' : 'Nueva Cotización' }}
      </v-card-title>
      

      <!-- Datos Generales -->
      <div class="text-subtitle-1 font-weight-bold mb-2">Datos generales</div>
      <v-divider class="mb-4" />

      <v-row align="start">
        <!-- Cliente -->
        <v-col cols="12" md="5">
          <div style="position: relative;">
            <v-text-field
              v-model="terminoBusquedaCliente"
              @input="buscarClientesSimple"
              @focus="onFocusBuscador"
              @click:clear="limpiarBusqueda"
              label="Cliente"
              placeholder="Buscar por RUT o nombre..."
              variant="outlined"
              density="compact"
              clearable
              :loading="buscandoClientes"
              color="primary"
              :append-inner-icon="form.cliente ? 'mdi-check-circle' : 'mdi-magnify'"
              :hint="form.cliente ? `Seleccionado: ${form.cliente.razon_social || (form.cliente.first_name + ' ' + form.cliente.last_name).trim()}` : ''"
              persistent-hint
            />
            <!-- DROPDOWN DE RESULTADOS -->
            <v-card
              v-if="mostrarDropdown && clientesBuscados.length > 0"
              style="position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; max-height: 300px; overflow-y: auto;"
              class="mt-1"
              elevation="8"
            >
              <v-list density="compact">
                <v-list-item
                  v-for="cliente in clientesBuscados"
                  :key="cliente.id"
                  @click="seleccionarCliente(cliente)"
                >
                  <template #prepend>
                    <v-icon>mdi-account</v-icon>
                  </template>
                  <v-list-item-title>{{ cliente.razon_social || `${cliente.first_name || ''} ${cliente.last_name || ''}`.trim() }}</v-list-item-title>
                  <v-list-item-subtitle>{{ cliente.identification }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-card>
          </div>
        </v-col>

        <!-- Botón nuevo cliente -->
        <v-col cols="auto" class="pt-1">
          <v-btn
            icon="mdi-plus"
            color="primary"
            variant="tonal"
            size="small"
            @click="abrirModalCliente"
          />
        </v-col>

        <!-- Observaciones -->
        <v-col cols="12" md="5">
          <v-textarea
            v-model="cotizacion.observaciones"
            label="Observaciones"
            rows="1"
            variant="outlined"
            density="compact"
            color="primary"
            auto-grow
          />
        </v-col>
      </v-row>
      <v-divider class="my-4" />

      <!-- Lista de Ventanas -->
      <div class="text-subtitle-1 font-weight-bold mb-2">Items de la Cotización</div>
      <v-divider class="mb-4" />

      <!-- Botones para agregar items -->
      <v-row class="mb-4" align="center">
        <v-col cols="auto">
          <v-btn color="primary" variant="tonal" @click="toggleSeccionVentana" :disabled="!tiposVentanaTodos.length">
            <v-icon start>mdi-window-closed-variant</v-icon>
            Ventana
          </v-btn>
        </v-col>
        <v-col cols="auto">
          <v-btn color="success" variant="tonal" @click="abrirModalProductos">
            <v-icon start>mdi-package-variant</v-icon>
            Productos
          </v-btn>
        </v-col>
      </v-row>

      <!-- Sección colapsable de pre-configuración de ventanas -->
      <v-expand-transition>
        <v-card v-if="mostrarSeccionVentana" class="mb-4" variant="outlined">
          <v-card-text>
            <div class="text-subtitle-2 font-weight-bold mb-3">Pre-configuración de Ventana</div>
            <v-row dense>
              <v-col cols="12" md="6">
                <v-select
                  v-model="cotizacion.material"
                  :items="materiales"
                  item-title="nombre"
                  item-value="id"
                  label="Material"
                  variant="outlined"
                  density="compact"
                  color="primary"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="cotizacion.color"
                  :items="coloresFiltrados"
                  item-title="nombre"
                  item-value="id"
                  label="Color"
                  variant="outlined"
                  density="compact"
                  color="primary"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="cotizacion.tipoVidrio"
                  :items="tiposVidrio"
                  item-title="nombre"
                  item-value="id"
                  label="Tipo de vidrio"
                  variant="outlined"
                  density="compact"
                  color="primary"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="cotizacion.productoVidrioProveedor"
                  :items="productosVidrioFiltradosGeneral"
                  item-title="nombre"
                  item-value="id"
                  label="Producto de vidrio"
                  variant="outlined"
                  density="compact"
                  color="primary"
                />
              </v-col>
            </v-row>
            <v-btn color="primary" variant="flat" @click="abrirModalVentana" block class="mt-3">
              <v-icon start>mdi-plus</v-icon>
              Agregar Ventana
            </v-btn>
          </v-card-text>
        </v-card>
      </v-expand-transition>

      <v-data-table 
        v-if="cotizacion.ventanas.length > 0"
        :headers="headersVentanas"
        :items="cotizacion.ventanas"
        class="mt-4"
        :items-per-page="5"
      >


<template #item.tipo="{ item }">
  {{ mapaTiposVentana[Number(item.tipo)] || item.tipo }}
</template>


        <template #item.acciones="{ item, index }">
          <v-btn icon @click="editarVentana(index)">
            <v-icon>mdi-pencil</v-icon>
          </v-btn>
          <v-btn icon color="error" @click="eliminarVentana(index)">
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </template>
      </v-data-table>

      <v-alert v-else type="info" variant="tonal" class="mt-4">
        No hay ventanas agregadas. Haz clic en "Agregar Ventana" para comenzar.
      </v-alert>

      <!-- Tabla de productos -->
      <v-data-table
        v-if="cotizacion.productos.length > 0"
        :headers="headersProductos"
        :items="cotizacion.productos"
        class="mt-4"
        :items-per-page="5"
      >
        <template #top>
          <v-toolbar flat color="transparent">
            <v-toolbar-title class="text-subtitle-1">Productos</v-toolbar-title>
          </v-toolbar>
        </template>

        <template #item.nombre="{ item }">
          <div>
            <div class="font-weight-medium">{{ item.nombre }}</div>
            <div v-if="item.descripcion && item.descripcion !== item.nombre" class="text-caption text-grey">
              {{ item.descripcion }}
            </div>
          </div>
        </template>

        <template #item.precio_costo="{ item }">
          ${{ formatearNumero(item.precio_costo) }}
        </template>

        <template #item.margen="{ item }">
          {{ item.margen }}%
        </template>

        <template #item.precio_venta="{ item }">
          ${{ formatearNumero(item.precio_venta) }}
        </template>

        <template #item.acciones="{ item, index }">
          <v-btn icon color="error" @click="eliminarProducto(index)">
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </template>
      </v-data-table>

      <!-- Modal para AGREGAR ventana -->
      <AgregarVentanaModal
        v-model:mostrar="mostrarModalVentana"
        :materiales="materiales"
        :colores="colores"
        :tiposVidrio="tiposVidrio"
        :productosVidrio="productosVidrio"
        :tiposVentana="tiposVentanaTodos"
        :material-default="cotizacion.material"
        :color-default="cotizacion.color"
        :tipo-vidrio-default="cotizacion.tipoVidrio"
        :producto-vidrio-default="cotizacion.productoVidrioProveedor"
        @guardar="guardarVentana"
      />

      <!-- Modal para EDITAR ventana -->
      <EditarVentanaModal
        v-model:mostrar="mostrarModalEditar"
        :ventana="ventanaEnEdicion"
        :materiales="materiales"
        :colores="colores"
        :tiposVidrio="tiposVidrio"
        :productosVidrio="productosVidrio"
        :tiposVentana="tiposVentanaTodos"
        @guardar="guardarVentana"
      />

      <!-- Modal para agregar productos -->
      <ModalProductos
        v-model:mostrar="mostrarModalProductos"
        @agregar-productos="agregarProductosCotizacion"
      />

      <v-divider class="my-4" />

      <!-- Total en tiempo real -->
      <v-row v-if="cotizacion.ventanas.length > 0 || cotizacion.productos.length > 0" class="mb-2" justify="end">
        <v-col cols="auto">
          <v-card variant="tonal" color="primary" rounded="lg" min-width="260">
            <v-card-text class="pa-3">
              <v-row dense no-gutters>
                <v-col>
                  <div class="text-caption text-medium-emphasis">Subtotal ventanas</div>
                  <div class="text-caption text-medium-emphasis mt-1">Subtotal productos</div>
                  <v-divider class="my-2" />
                  <div class="text-subtitle-2 font-weight-bold">Total neto</div>
                  <div class="text-caption text-medium-emphasis">IVA (19%)</div>
                  <div class="text-subtitle-1 font-weight-bold mt-1">Total con IVA</div>
                </v-col>
                <v-col cols="auto" class="text-right">
                  <div class="text-caption">${{ formatearNumero(totalVentanas) }}</div>
                  <div class="text-caption mt-1">${{ formatearNumero(totalProductos) }}</div>
                  <v-divider class="my-2" />
                  <div class="text-subtitle-2 font-weight-bold">${{ formatearNumero(totalNeto) }}</div>
                  <div class="text-caption">${{ formatearNumero(totalIva) }}</div>
                  <div class="text-subtitle-1 font-weight-bold mt-1 text-primary">${{ formatearNumero(totalConIva) }}</div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <div class="d-flex justify-end">
        <v-btn
          color="primary"
          size="large"
          :loading="loading"
          :disabled="loading"
          @click="guardarCotizacion"
        >
          <template #loader>
            <v-progress-circular indeterminate color="white" size="20" />
          </template>
          <v-icon start>mdi-content-save</v-icon>
          {{ modoEdicion ? 'Guardar Cambios' : 'Guardar Cotización' }}
        </v-btn>
      </div>
       <!-- Renderización de ventanas para captura de imágenes -->
      <div v-if="cotizacion.ventanas.length > 0" class="mt-6">
        <v-card-subtitle class="text-h5">Vista previa de ventanas</v-card-subtitle>
        <v-divider class="mb-4" />
        <div v-for="(ventana, index) in cotizacion.ventanas" :key="index" class="mb-4">
          <v-card class="pa-4" outlined>
            <v-card-title>{{ mapaTiposVentana[ventana.tipo] || `Ventana ${index + 1}` }}</v-card-title>
            <v-row>
              <v-col cols="6">
                <VentanaFijaAL42
                  v-if="ventana.tipo === 1"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                />
                <VentanaEditor
                  v-else-if="ventana.tipo === 2"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                />
                <VentanaProyectante
                  v-else-if="ventana.tipo === 45"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                />
                <VentanaProyectanteAL42
                  v-else-if="ventana.tipo === 56"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                />
                <VentanaCorrederaAL25
                  v-else-if="ventana.tipo === 55"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :hoja1AlFrente="ventana.hoja1AlFrente"
                  :hojas-moviles="ventana.hojas_moviles || 2"
                  :hoja-movil-seleccionada="ventana.hojaMovilSeleccionada || 1"
                />
                <VistaVentanaCompuestaAL42
                  v-else-if="ventana.tipo === 57"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :filas="ventana.filas || 1"
                  :columnas="ventana.columnas || 1"
                  :altos-filas="ventana.altosFilas || []"
                  :anchos-columnas="ventana.anchosColumnas || []"
                  :secciones="ventana.secciones || [[{ tipo: 1 }]]"
                />
                <VentanaCorredera
                  v-else-if="ventana.tipo === 3"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :hojas-totales="ventana.hojas_totales"
                  :hojas-moviles="ventana.hojas_moviles"
                  :hoja-movil-seleccionada="ventana.hojaMovilSeleccionada"
                  :orden-hoja1-al-frente="ventana.hoja1AlFrente"
                />
                <VentanaCorredera98
                  v-else-if="ventana.tipo === 52"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :hojas-totales="ventana.hojas_totales"
                  :hojas-moviles="ventana.hojas_moviles"
                  :hoja-movil-seleccionada="ventana.hojaMovilSeleccionada"
                  :orden-hoja1-al-frente="ventana.hoja1AlFrente"
              />
                <BayWindow
                  v-else-if="ventana.tipo === 47"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :ancho-izquierda="ventana.ancho_izquierda"
                  :ancho-centro="ventana.ancho_centro"
                  :ancho-derecha="ventana.ancho_derecha"
                  :tipo-ventana-izquierda="ventana.tipoVentanaIzquierda"
                  :tipo-ventana-centro="ventana.tipoVentanaCentro"
                  :tipo-ventana-derecha="ventana.tipoVentanaDerecha"
                />
                <VistaVentanaCorrederaAndes
                  v-else-if="ventana.tipo === 46"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :hojas-totales="ventana.hojas_totales"
                  :hojas-moviles="ventana.hojas_moviles"
                  :hoja-movil-seleccionada="ventana.hojaMovilSeleccionada"
                  :orden-hoja1-al-frente="ventana.hoja1AlFrente"
                />
                <VistaVentanaMonorriel
                  v-else-if="ventana.tipo === 53"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :lado-apertura="ventana.ladoApertura"
                />
                <VentanaAbatir
                  v-else-if="ventana.tipo === 49"  
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :lado-inicial="ventana.ladoApertura || 'izquierda'"
                />
                <PuertaS60
                  v-else-if="ventana.tipo === 50"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :material="ventana.material"
                  :tipoVidrio="ventana.tipoVidrio"
                  :productoVidrioProveedor="ventana.productoVidrioProveedor"
                  :lado-apertura="ventana.ladoApertura"
                  :direccion-apertura="ventana.direccionApertura"
                  :paso-libre="ventana.pasoLibre"
                />
                <VistaMamparaS60
                  v-else-if="ventana.tipo === 51"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :hoja-activa="ventana.hojaActiva"
                  :direccion-apertura="ventana.direccionApertura"
                  :paso-libre="ventana.pasoLibre"
                />
                <ArmadorUniversal
                  v-else-if="ventana.tipo === 58"
                  :ref="el => { if (el) ventanaRefs[index] = el }"
                  :ancho="ventana.ancho"
                  :alto="ventana.alto"
                  :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
                  :configuracion-inicial="ventana.configuracionArmador"
                  :tipos-ventana="tiposVentanaBayKonva"
                />
              </v-col>
              <v-col cols="6">
                <v-card variant="outlined">
                  <v-card-title>Detalles</v-card-title>
                  <v-card-text>
                    <p><strong>Ancho:</strong> {{ ventana.ancho }}mm</p>
                    <p><strong>Alto:</strong> {{ ventana.alto }}mm</p>
                    <p><strong>Cantidad:</strong> {{ ventana.cantidad }}</p>
                    <p><strong>Precio:</strong> ${{ formatearNumero(ventana.precio) }}</p>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </v-card>
        </div>
      </div>
    </v-card>
  </v-container>

  <!-- Modal para crear cliente LOCAL -->
  <v-dialog v-model="modalCliente" max-width="600" persistent>
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between pa-4">
        <div class="d-flex align-center gap-2">
          <v-icon color="primary">mdi-account-plus</v-icon>
          Crear Cliente Nuevo
        </div>
        <v-btn icon="mdi-close" variant="text" @click="modalCliente = false" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-4">
        <v-form ref="formCliente">
          <v-row>
            <v-col cols="12">
              <v-select
                v-model="nuevoCliente.tipo_cliente"
                :items="[{ title: 'Empresa', value: 'empresa' }, { title: 'Persona natural', value: 'persona' }]"
                label="Tipo de cliente *"
                variant="outlined"
                density="compact"
                :rules="[v => !!v || 'Requerido']"
              />
            </v-col>
            <template v-if="nuevoCliente.tipo_cliente === 'empresa'">
              <v-col cols="12">
                <v-text-field
                  v-model="nuevoCliente.razon_social"
                  label="Razón social *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Razón social es requerida']"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="nuevoCliente.giro"
                  label="Giro"
                  variant="outlined"
                  density="compact"
                />
              </v-col>
            </template>
            <template v-else-if="nuevoCliente.tipo_cliente === 'persona'">
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="nuevoCliente.first_name"
                  label="Nombre *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Nombre es requerido']"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="nuevoCliente.last_name"
                  label="Apellido"
                  variant="outlined"
                  density="compact"
                />
              </v-col>
            </template>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="nuevoCliente.identification"
                label="RUT"
                variant="outlined"
                density="compact"
                placeholder="12.345.678-9"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="nuevoCliente.phone"
                label="Teléfono"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="nuevoCliente.email"
                label="Email"
                type="email"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12">
              <v-text-field
                v-model="nuevoCliente.address"
                label="Dirección"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="nuevoCliente.ciudad"
                label="Ciudad"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="nuevoCliente.comuna"
                label="Comuna"
                variant="outlined"
                density="compact"
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="modalCliente = false">Cancelar</v-btn>
        <v-btn
          color="primary"
          variant="flat"
          :loading="guardandoCliente"
          @click="guardarCliente"
        >
          <v-icon start>mdi-content-save</v-icon>
          Guardar cliente
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Snackbar -->
  <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="snackbar.timeout" location="bottom right" multi-line>
    {{ snackbar.text }}
    <template #actions>
      <v-btn variant="text" @click="snackbar.show = false">Cerrar</v-btn>
    </template>
  </v-snackbar>

</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import debounce from 'lodash/debounce'
import api from '@/axiosInstance'
import { useRouter, useRoute } from 'vue-router'
import Visor3D from '@/layouts/components/Visor3D.vue'
import { color } from 'three/src/nodes/TSL.js'
import VistaVentanaCorrederaAndes from '@/components/VistaVentanaCorrederaAndes.vue'
import AgregarVentanaModal from '@/pages/AgregarVentanaModal2.vue'
import EditarVentanaModal from '@/pages/EditarVentanaModal.vue'
import ModalProductos from '@/pages/ModalProductos.vue'
import VentanaFijaAL42 from '@/components/VistaVentanaFijaAL42.vue'
import VentanaEditor from '@/components/VistaVentanaFijaS60.vue'
import VentanaCorredera from '@/components/VistaVentanaCorredera.vue'
import VentanaProyectante from '@/components/VistaVentanaProyectanteS60.vue'
import BayWindow from '@/components/VistaBayWindow.vue'
import VentanaAbatir from '@/components/VistaVentanaAbatirS60.vue'
import PuertaS60 from '@/components/VistaPuertaS60.vue'
import VistaMamparaS60 from '@/components/VistaMamparaS60.vue'
import VentanaCorredera98 from '@/components/VistaVentanaCorredera98.vue'
import VistaVentanaMonorriel from '@/components/VistaVentanaMonorriel.vue'
import VistaVentanaCompuestaDinamica from '@/components/VistaVentanaCompuestaDinamica.vue'
import VentanaProyectanteAL42 from '@/components/VistaVentanaProyectanteAL42.vue'
import VentanaCorrederaAL25 from '@/components/VistaVentanaCorrederaAL25.vue'
import VistaVentanaCompuestaAL42 from '@/components/VistaVentanaCompuestaAL42.vue'
import ArmadorUniversal from '@/components/ArmadorUniversal.vue'



const ventanaRefs = ref([]) // mantener referencias
const tiposVentanaTodos = ref([])

const margenVenta = 0.45 // Margen del 45%
const router = useRouter()
const route = useRoute()

// Detectar modo edición
const modoEdicion = ref(false)
const cotizacionId = ref(null)

const mapaTiposVentana = computed(() => {
  const map = {}
  for (const t of tiposVentanaTodos.value) {
    map[Number(t.id)] = t.nombre
  }
  return map
})

// Cotización general
const cotizacion = reactive({
  cliente_id: null,
  observaciones: '',
  material: '',
  color: '',
  tipoVidrio: '',
  productoVidrioProveedor: '',
  ventanas: [],
  productos: [], // Productos agregados a la cotización
       
})

const tiposVentanaBayKonva = [
  { id: 1, nombre: 'Fija' },
  { id: 2, nombre: 'Proyectante' },
  { id: 3, nombre: 'Corredera' },

]
const tiposVentanaCentro = [
  { id: 2, nombre: 'Fija' },
  { id: 3, nombre: 'Corredera Sliding' },
  { id: 45, nombre: 'Proyectante S60' },
  //{ id: 46, nombre: 'Corredera Andes' },
]

const mostrarDetalles = ref({})
const loading = ref(false)

// Formulario de cliente
const form = reactive({
  cliente: null,
})

// Datos generales
const materiales = ref([])
const colores = ref([])
const tiposVidrio = ref([])
const productosVidrio = ref([])

// Colores filtrados por material: Aluminio solo muestra sus colores, PVC los suyos
const coloresFiltrados = computed(() => {
  const idsAluminio = [1, 2, 4, 7, 8] // Blanco, Negro, Roble, Mate, Titanio
  const idsPVC      = [1, 2, 4, 5, 6] // Blanco, Negro, Roble, Nogal, Grafito
  const ids = cotizacion.material === 1 ? idsAluminio : idsPVC
  return colores.value.filter(c => ids.includes(c.id))
})

const clientes = ref([])
const clientesBuscados = ref([])
const buscandoClientes = ref(false)
const clienteAutocomplete = ref(null)
const terminoBusquedaCliente = ref('')
const mostrarDropdown = ref(false)

const clienteSearch = ref('')
const modalCliente = ref(false)
const formCliente = ref(null)
const comboboxKey = ref(0)
const guardandoCliente = ref(false)

const snackbar = ref({ show: false, text: '', color: 'success', timeout: 5000 })
const mostrarNotificacion = (text, color = 'success', timeout = 5000) => {
  snackbar.value = { show: true, text, color, timeout }
}

const nuevoClienteVacio = () => ({
  tipo_cliente: null, razon_social: '', first_name: '', last_name: '',
  identification: '', email: '', phone: '', address: '', ciudad: '', comuna: '', giro: '',
})
const nuevoCliente = ref(nuevoClienteVacio())

const productosVidrioCombinados = computed(() => {
  return productosVidrio.value.flatMap(p => {
    if (!p.colores_por_proveedor || !Array.isArray(p.colores_por_proveedor)) {
      return []
    }
    return p.colores_por_proveedor.map(cpp => ({
      id: `${p.id}-${cpp.proveedor_id}`,
      producto_id: p.id,
      proveedor_id: cpp.proveedor_id,
      nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Proveedor desconocido'})`
    }))
  })
})

const productosVidrioFiltradosGeneral = computed(() => {
  const tipo = cotizacion.tipoVidrio
  return productosVidrio.value
    .filter(p => p.tipo_producto_id === tipo)
    .flatMap(p => {
      // Validar que colores_por_proveedor exista y sea un array
      if (!p.colores_por_proveedor || !Array.isArray(p.colores_por_proveedor)) {
        return []
      }
      return p.colores_por_proveedor.map(cpp => ({
        id: cpp.id,  // ✅ ID real de la tabla producto_color_proveedor
        producto_id: p.id,
        proveedor_id: cpp.proveedor_id,
        nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Proveedor desconocido'})`
      }))
    })
})

onMounted(async () => {
  console.log('�🚀🚀 ARCHIVO INDEX.VUE CARGADO')
  console.log('🔄 Iniciando carga de datos...')
  
  // Detectar modo edición
  if (route.query.id) {
    modoEdicion.value = true
    cotizacionId.value = route.query.id
    console.log('📝 MODO EDICIÓN activado - ID:', cotizacionId.value)
  }
  
  try {
    // Cargar datos básicos (rápido)
    const [
      materialesRes, coloresRes, tiposProductoRes,
      productosRes, tiposVentanaRes
    ] = await Promise.all([
      api.get('/api/tipos_material'),
      api.get('/api/colores'),
      api.get('/api/tipos_producto'),
      api.get('/api/productos'),
      api.get('/api/tipos_ventana')
    ])
    
    console.log('✅ Datos básicos cargados')

  materiales.value = materialesRes.data
  colores.value = coloresRes.data
  tiposVidrio.value = tiposProductoRes.data.filter(tp => [1, 2].includes(tp.id))
  productosVidrio.value = productosRes.data.filter(p => [1, 2].includes(p.tipo_producto_id))
  
  console.log('📦 PRODUCTOS DE VIDRIO CARGADOS:', productosVidrio.value.length)
  console.log('📦 Primer producto:', productosVidrio.value[0])
  if (productosVidrio.value[0]) {
    console.log('   - Tiene colores_por_proveedor?', !!productosVidrio.value[0].colores_por_proveedor)
    console.log('   - Es array?', Array.isArray(productosVidrio.value[0].colores_por_proveedor))
    console.log('   - Cantidad:', productosVidrio.value[0].colores_por_proveedor?.length)
    const idsDisponibles = productosVidrio.value.flatMap(p => 
      (p.colores_por_proveedor || []).map(cpp => cpp.id)
    )
    console.log('   - IDs disponibles:', idsDisponibles)
    console.log('   - ¿Incluye ID 64?:', idsDisponibles.includes(64))
    console.log('   - ¿Incluye ID 2?:', idsDisponibles.includes(2))
  }
  
  // 🔍 Log de estructura completa de un producto para verificar
  if (productosVidrio.value.length > 0) {
    console.log('📦 ESTRUCTURA COMPLETA DEL PRIMER PRODUCTO:')
    console.log(JSON.stringify(productosVidrio.value[0], null, 2))
  }
  
  tiposVentanaTodos.value = tiposVentanaRes.data
  console.log('TIPOS VENTANA CARGADOS:', tiposVentanaTodos.value.length)
  console.log('🔍 Mapa de tipos ventana:', tiposVentanaTodos.value.map(t => `${t.id}: ${t.nombre}`))
  
  // Cargar solo los primeros clientes (rápido)
  console.log('🔄 Cargando primeros clientes...')
  cargarClientesIniciales()
  
  // SI ESTÁ EN MODO EDICIÓN, CARGAR LA COTIZACIÓN
  if (modoEdicion.value) {
    await cargarCotizacionExistente()
  }
  
  // Cerrar dropdown al hacer clic fuera
  document.addEventListener('click', (e) => {
    const target = e.target
    if (!target.closest('.v-text-field') && !target.closest('.v-card')) {
      mostrarDropdown.value = false
    }
  })
  
  } catch (error) {
    console.error('❌ Error cargando datos:', error)
    alert('Error cargando datos: ' + error.message)
  }
})

const buscarRelacionVidrioProveedor = (id) => {
  id = parseInt(id)
  return productosVidrio.value.flatMap(p => {
    if (!p.colores_por_proveedor || !Array.isArray(p.colores_por_proveedor)) {
      return []
    }
    return p.colores_por_proveedor.map(cpp => ({
      id: cpp.id,
      producto_id: p.id,
      proveedor_id: cpp.proveedor_id
    }))
  }).find(p => p.id === id)
}

// Función para cargar cotización existente en modo edición
const cargarCotizacionExistente = async () => {
  try {
    console.log('📥 Cargando cotización ID:', cotizacionId.value)
    const response = await api.get(`/api/cotizaciones/${cotizacionId.value}`)
    const cotizacionData = response.data
    
    console.log('✅ Cotización cargada:', cotizacionData)
    console.log('📦 DETALLES COMPLETOS:', cotizacionData.detalles)
    
    // Poblar cliente
    if (cotizacionData.cliente) {
      form.cliente = cotizacionData.cliente
      cotizacion.cliente_id = cotizacionData.cliente.id
      terminoBusquedaCliente.value = cotizacionData.cliente.razon_social
    }
    
    // Poblar observaciones
    cotizacion.observaciones = cotizacionData.observaciones || ''
    
    // Poblar ventanas
    if (cotizacionData.ventanas && cotizacionData.ventanas.length > 0) {
      cotizacion.ventanas = cotizacionData.ventanas.map(v => {
        console.log('🔄 Mapeando ventana desde BD - DATOS COMPLETOS:', JSON.stringify(v, null, 2))
        console.log('🔄 tipo_ventana_id:', v.tipo_ventana_id)
        console.log('🔄 material_id:', v.material_id)
        console.log('🔄 color_id:', v.color_id)
        console.log('🔄 tipo_vidrio_id:', v.tipo_vidrio_id)
        console.log('🔄 producto_vidrio_proveedor_id:', v.producto_vidrio_proveedor_id)
        
        // Inferir material y tipo_vidrio si no vienen en la BD
        let materialInferido = v.material_id
        let tipoVidrioInferido = v.tipo_vidrio_id
        
        console.log('🔧 Valores originales - material_id:', v.material_id, 'tipo_vidrio_id:', v.tipo_vidrio_id)
        
        if (!tipoVidrioInferido && v.producto_vidrio_proveedor_id) {
          const idBuscado = parseInt(v.producto_vidrio_proveedor_id)
          console.log('🔧 Buscando ID:', idBuscado)
          
          // Buscar en TODOS los colores_por_proveedor de TODOS los productos
          for (const producto of productosVidrio.value) {
            if (producto.colores_por_proveedor) {
              for (const cpp of producto.colores_por_proveedor) {
                if (parseInt(cpp.id) === idBuscado) {
                  tipoVidrioInferido = producto.tipo_producto_id
                  console.log('✅ ENCONTRADO! Producto:', producto.nombre, '→ tipo_producto_id:', tipoVidrioInferido)
                  break
                }
              }
              if (tipoVidrioInferido) break
            }
          }
          
          if (!tipoVidrioInferido) {
            console.log('❌ ID', idBuscado, 'no encontrado en ningún producto')
          }
        }
        
        if (!materialInferido && v.tipo_ventana_id) {
          console.log('🔧 Intentando inferir material...')
          console.log('🔧 tiposVentanaTodos.value disponibles:', tiposVentanaTodos.value.length)
          console.log('🔧 Buscando tipo_ventana_id:', v.tipo_ventana_id)
          
          // Buscar el material desde el tipo de ventana
          const tipoVentana = tiposVentanaTodos.value.find(tv => tv.id === v.tipo_ventana_id)
          console.log('🔧 Tipo de ventana encontrado:', tipoVentana)
          
          if (tipoVentana) {
            materialInferido = tipoVentana.material_id
            console.log('✅ material inferido:', materialInferido, 'desde tipo ventana:', tipoVentana.nombre)
          } else {
            console.log('❌ No se encontró el tipo de ventana con ID:', v.tipo_ventana_id)
          }
        }
        
        console.log('🔧 Valores finales - materialInferido:', materialInferido, 'tipoVidrioInferido:', tipoVidrioInferido)
        
        const relacion = buscarRelacionVidrioProveedor(v.producto_vidrio_proveedor_id)
        
        const ventanaMapeada = {
          tipo: v.tipo_ventana_id,
          ancho: v.ancho,
          alto: v.alto,
          cantidad: v.cantidad || 1,
          material: materialInferido,
          color: v.color_id,
          tipoVidrio: tipoVidrioInferido,
          productoVidrioProveedor: v.producto_vidrio_proveedor_id,
          productoVidrio: relacion?.producto_id,
          proveedorVidrio: relacion?.proveedor_id,
          costo: v.costo || 0,
          costo_unitario: v.costo_unitario || 0,
          costo_total: v.costo || 0,
          precio: v.precio || 0,
          precio_unitario: v.precio_unitario || 0,
          hojas_totales: v.hojas_totales,
          hojas_moviles: v.hojas_moviles,
          materiales: v.materiales || [],
          // Para ventanas compuestas
          tipoVentanaIzquierda: v.tipo_ventana_izquierda,
          tipoVentanaCentro: v.tipo_ventana_centro,
          tipoVentanaDerecha: v.tipo_ventana_derecha,
          ancho_izquierda: v.ancho_izquierda,
          ancho_centro: v.ancho_centro,
          ancho_derecha: v.ancho_derecha,
          // ID para actualización
          id: v.id
        }
        
        console.log('🔄 Ventana mapeada:', ventanaMapeada)
        console.log('🔄 tipoVidrio en ventana mapeada:', ventanaMapeada.tipoVidrio)
        
        return ventanaMapeada
      })
      
      console.log('✅ Ventanas cargadas:', cotizacion.ventanas.length)
    }
    
    // Poblar productos
    if (cotizacionData.detalles && cotizacionData.detalles.length > 0) {
      cotizacion.productos = cotizacionData.detalles
        .filter(d => d.tipo_item === 'producto')
        .map(p => {
          console.log('📦 Detalle del producto:', p)
          
          // Obtener info del producto desde las relaciones (manejar snake_case y camelCase)
          const productoInfo = p.producto_lista || p.productoLista || {}
          const listaPrecioInfo = p.lista_precio || p.listaPrecio || {}
          const tipoProductoInfo = productoInfo.tipo_producto || productoInfo.tipoProducto || {}
          const unidadInfo = productoInfo.unidad || {}
          
          return {
            id: p.id, // ID para actualización
            producto_lista_id: p.producto_lista_id,
            lista_precio_id: p.lista_precio_id,
            descripcion: p.descripcion,
            cantidad: p.cantidad,
            precio_venta: p.precio_unitario,
            total: p.total,
            // Información adicional del producto
            codigo: productoInfo.codigo || '',
            nombre: productoInfo.nombre || p.descripcion,
            tipo: tipoProductoInfo.nombre || '',
            unidad: unidadInfo.nombre || unidadInfo.simbolo || '',
            precio_costo: listaPrecioInfo.precio_costo || 0,
            margen: listaPrecioInfo.margen || 0,
          }
        })
      
      console.log('✅ Productos cargados:', cotizacion.productos.length)
      console.log('📦 Primer producto mapeado:', cotizacion.productos[0])
    }
    
  } catch (error) {
    console.error('❌ Error cargando cotización:', error)
    alert('Error al cargar la cotización')
    router.push({ name: 'cotizaciones' })
  }
}

// Computed para mostrar productos de vidrio con proveedor
const productosVidrioFiltradosConProveedor = (ventana) => {
  const tipo = ventana.tipoVidrio ?? cotizacion.tipoVidrio
  return productosVidrio.value
    .filter(p => p.tipo_producto_id === tipo)
    .flatMap(p => {
      if (!p.colores_por_proveedor || !Array.isArray(p.colores_por_proveedor)) {
        return []
      }
      return p.colores_por_proveedor.map(cpp => ({
        id: cpp.id, // ✅ ID real de la tabla producto_color_proveedor
        producto_id: p.id,
        proveedor_id: cpp.proveedor_id,
        nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Proveedor desconocido'})`
      }))
    })
}

// Función de clientes filtrados eliminada - ahora usamos búsqueda async

// Ventanas
const mostrarModalVentana = ref(false) // Para agregar
const mostrarModalEditar = ref(false) // Para editar
const mostrarSeccionVentana = ref(false)
const mostrarModalProductos = ref(false)
const ventanaEnEdicion = ref(null)

const headersVentanas = [
  { title: 'Tipo', key: 'tipo' },
  { title: 'Ancho', key: 'ancho' },
  { title: 'Alto', key: 'alto' },
  { title: 'Cantidad', key: 'cantidad' },
  { title: 'Precio', key: 'precio', align: 'end' },
  { title: 'Acciones', key: 'acciones', sortable: false },
]

const headersProductos = [
  { title: 'Código', key: 'codigo' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Color', key: 'color' },
  { title: 'Proveedor', key: 'proveedor' },
  { title: 'Tipo', key: 'tipo_producto' },
  { title: 'Unidad', key: 'unidad' },
  { title: 'Cantidad', key: 'cantidad' },
  { title: 'Precio Costo (Neto)', key: 'precio_costo', align: 'end' },
  { title: 'Precio Venta (Neto)', key: 'precio_venta', align: 'end' },
  { title: 'Acciones', key: 'acciones', sortable: false },
]

const abrirModalVentana = () => {
  console.log('➕ ABRIENDO MODAL PARA NUEVA VENTANA')
  console.log('➕ productosVidrio disponibles:', productosVidrio.value.length)
  ventanaEnEdicion.value = null // Para agregar nueva
  mostrarModalVentana.value = true
}

const toggleSeccionVentana = () => {
  mostrarSeccionVentana.value = !mostrarSeccionVentana.value
}

const abrirModalProductos = () => {
  mostrarModalProductos.value = true
}

const editarVentana = (index) => {
  console.log('🔧 EDITANDO VENTANA - Índice:', index)
  const ventanaOriginal = cotizacion.ventanas[index]
  console.log('🔧 Datos originales de la ventana:', ventanaOriginal)
  
  // Clonar la ventana
  const ventanaCompleta = { ...ventanaOriginal, index }
  
  // ✅ INFERIR tipoVidrio si falta (AQUÍ EN INDEX, NO EN EL MODAL)
  if (!ventanaCompleta.tipoVidrio && ventanaCompleta.productoVidrioProveedor) {
    const idBuscado = parseInt(ventanaCompleta.productoVidrioProveedor)
    console.log('🔍 Buscando tipoVidrio para producto ID:', idBuscado)
    
    for (const producto of productosVidrio.value) {
      if (producto.colores_por_proveedor) {
        const encontrado = producto.colores_por_proveedor.find(
          cpp => parseInt(cpp.id) === idBuscado
        )
        if (encontrado) {
          ventanaCompleta.tipoVidrio = producto.tipo_producto_id
          console.log('✅ tipoVidrio inferido:', ventanaCompleta.tipoVidrio)
          break
        }
      }
    }
  }
  
  // ✅ INFERIR material_id si falta (AQUÍ EN INDEX, NO EN EL MODAL)
  if (!ventanaCompleta.material && ventanaCompleta.tipo) {
    const tipoVentana = tiposVentanaTodos.value.find(tv => parseInt(tv.id) === parseInt(ventanaCompleta.tipo))
    if (tipoVentana && tipoVentana.material_id) {
      ventanaCompleta.material = tipoVentana.material_id
      console.log('✅ material_id inferido:', ventanaCompleta.material)
    }
  }
  
  console.log('✅ Ventana COMPLETA preparada:', ventanaCompleta)
  console.log('✅ tipoVidrio final:', ventanaCompleta.tipoVidrio)
  console.log('✅ material final:', ventanaCompleta.material)
  console.log('✅ productoVidrioProveedor final:', ventanaCompleta.productoVidrioProveedor)
  
  // Asignar ventana YA COMPLETA
  ventanaEnEdicion.value = ventanaCompleta
  
  // Abrir modal de EDICIÓN (no el de agregar)
  mostrarModalEditar.value = true
}

const guardarVentana = (ventana) => {
    console.log('💾 VENTANA RECIBIDA DEL MODAL:', ventana)
    console.log('💾 tipoVidrio en ventana recibida:', ventana.tipoVidrio)
    console.log('💾 productoVidrioProveedor en ventana recibida:', ventana.productoVidrioProveedor)
    console.log('💾 color en ventana recibida:', ventana.color)
    console.log('💾 tipo en ventana recibida:', ventana.tipo)
  if (ventana.index !== undefined) {
    cotizacion.ventanas[ventana.index] = { ...ventana }
  } else {
    cotizacion.ventanas.push({ ...ventana })
  }
  console.log('💾 Ventana guardada en cotizacion.ventanas:', cotizacion.ventanas[cotizacion.ventanas.length - 1])
  mostrarModalVentana.value = false
}

const agregarProductosCotizacion = (productos) => {
  console.log('📦 PRODUCTOS RECIBIDOS DEL MODAL:', productos)
  // Agregar productos al arreglo de la cotización
  productos.forEach(item => {
    const productoParaCotizacion = {
      producto_lista_id: item.producto_lista_id, // ✅ ID del producto
      lista_precio_id: item.lista_precio_id, // ✅ ID de la lista de precios
      nombre: item.nombre || item.producto?.nombre,
      codigo: item.codigo || item.producto?.codigo_proveedor,
      color: item.color || '-',
      proveedor: item.proveedor || '-',
      tipo_producto: item.tipo || item.producto?.tipoProducto?.nombre || '-',
      unidad: item.unidad || item.producto?.unidad?.abreviacion || '-',
      cantidad: item.cantidad,
      precio_costo: item.precio_costo,
      margen: item.margen,
      precio_venta: item.precio_venta,
      descripcion: item.descripcion || item.nombre || item.producto?.nombre || '',
      total: item.precio_venta * item.cantidad,
      // Campos adicionales para vidrios
      esVidrio: item.esVidrio || false,
      ancho_mm: item.ancho_mm || null,
      alto_mm: item.alto_mm || null,
      m2: item.m2 || null,
      pulido: item.pulido || false
    }
    
    console.log('✅ PRODUCTO AGREGADO A COTIZACIÓN:', productoParaCotizacion)
    cotizacion.productos.push(productoParaCotizacion)
  })
}

const eliminarProducto = (index) => {
  cotizacion.productos.splice(index, 1)
}

const formatearNumero = (numero) => {
  return new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(Number(numero) || 0)
}

const totalVentanas = computed(() =>
  cotizacion.ventanas.reduce((sum, v) => sum + (Number(v.precio) || 0), 0)
)
const totalProductos = computed(() =>
  cotizacion.productos.reduce((sum, p) => sum + (Number(p.precio_venta) * Number(p.cantidad) || 0), 0)
)
const totalNeto = computed(() => totalVentanas.value + totalProductos.value)
const totalIva = computed(() => Math.round(totalNeto.value * 0.19))
const totalConIva = computed(() => totalNeto.value + totalIva.value)

const eliminarVentana = (index) => {
  cotizacion.ventanas.splice(index, 1)
}

const agregarVentana = (ventanaModal = null) => {
  const base = {
    tipo: null,
    ancho: null,
    alto: null,
    cantidad: 1,
    material: cotizacion.material,
    color: cotizacion.color,
    tipoVidrio: cotizacion.tipoVidrio,
    productoVidrioProveedor: cotizacion.productoVidrioProveedor ?? null,
    hojas_totales: 2,
    hojas_moviles: 2,
    
    // Para Ventana Compuesta AL42 (tipo 57)
    filas: 1,
    columnas: 1,
    altosFilas: [],
    anchosColumnas: [],
    secciones: [[{ tipo: 1 }]],
    
    materiales: [],
    costo_total: 0,
    costo_total_unitario: 0,
    costo: 0,
    precio_unitario: 0,
    precio: 0,
    hoja1AlFrente: true,
    tipoVentanaIzquierda: {
      compuesta: false,
      partes: [
        { tipo: null, alto: null }, // Parte superior
        { tipo: null, alto: null }, // Parte inferior (solo si compuesta = true)
      ]
    },
    tipoVentanaDerecha: {
      compuesta: false,
      partes: [
        { tipo: null, alto: null },
        { tipo: null, alto: null },
      ]
    },
     tipoVentanaCentro: {
    tipo: null,
    hojas_totales: null,
    hojas_moviles: null,
    hojaMovilSeleccionada: null,
    hoja1AlFrente: true
  },
    ancho_izquierda: null,
    ancho_centro: null,
    ancho_derecha: null,

  }

  const nuevaVentana = { ...base, ...(ventanaModal || {}) }

    if (nuevaVentana.tipo === 47) {
    nuevaVentana.ancho_izquierda = null
    nuevaVentana.ancho_centro = null
    nuevaVentana.ancho_derecha = null
  }

  cotizacion.ventanas.push(nuevaVentana)

  const relacion = buscarRelacionVidrioProveedor(nuevaVentana.productoVidrioProveedor)

  if (
    nuevaVentana.tipo &&
    nuevaVentana.ancho &&
    nuevaVentana.alto &&
    relacion
  ) {
    const payload = {
      ...nuevaVentana,
      productoVidrio: relacion.producto_id,
      proveedorVidrio: relacion.proveedor_id,
      hojas_moviles: nuevaVentana.tipo === 3 || nuevaVentana.tipo === 46 ? nuevaVentana.hojas_moviles : undefined,
    }
    recalcularCosto(payload, nuevaVentana)
  }
}



const tiposVentanaFiltrados = (ventana) => {
  const materialId = ventana.material ?? cotizacion.material
  return tiposVentanaTodos.value.filter(t => t.material_id === materialId)
}

const recalcularCosto = debounce(async (payload, ventanaRef) => {
  // Validación de campos requeridos
  if (!payload.productoVidrio || !payload.proveedorVidrio) {
    console.warn('⚠️ Faltan datos en el payload para calcular materiales:', payload)
    return
  }

  try {
    const res = await api.post('/api/cotizador/calcular-materiales', payload)

    // Asignar costo unitario (para mostrar si se desea)
    ventanaRef.costo_unitario = res.data.costo_unitario

    // Multiplicar por cantidad para obtener el costo total real
    const cantidad = Number(ventanaRef.cantidad) > 0 ? Number(ventanaRef.cantidad) : 1
    ventanaRef.costo_total = res.data.costo_unitario * cantidad

    // Calcular precio unitario con margen
    const precioUnitario = Math.ceil(res.data.costo_unitario / (1 - margenVenta))
    
    // Precio total = precio unitario × cantidad
    ventanaRef.precio_unitario = precioUnitario
    ventanaRef.precio = precioUnitario * cantidad

    // Asignar materiales
    ventanaRef.materiales = res.data.materiales
  } catch (err) {
    console.error('❌ Error al calcular materiales', err)
    ventanaRef.costo_total = 0
    ventanaRef.materiales = []
  }
}, 1000)

watch(() => cotizacion.ventanas, (ventanas) => {
  ventanas.forEach((ventana) => {
    watch(() => [
      ventana.tipo,
      ventana.ancho,
      ventana.alto,
      ventana.cantidad,
      ventana.material,
      ventana.color,
      ventana.tipoVidrio,
      ventana.productoVidrioProveedor,
      ventana.hojas_totales,
      ventana.hojas_moviles
    ],
    () => {
      const errores = []

      if (!ventana.tipo) errores.push('tipo_ventana_id faltante')
      if (!ventana.ancho) errores.push('ancho faltante')
      if (!ventana.alto) errores.push('alto faltante')
      if (!ventana.cantidad || ventana.cantidad <= 0) errores.push('cantidad inválida')
      if (!ventana.productoVidrioProveedor) errores.push('productoVidrioProveedor faltante')

      const relacion = buscarRelacionVidrioProveedor(ventana.productoVidrioProveedor)

      if (!relacion) errores.push(`relación producto-proveedor no encontrada (ID: ${ventana.productoVidrioProveedor})`)

      if (errores.length > 0) {
        // ℹ️ ESTE ES SOLO UN AVISO, NO UN ERROR CRÍTICO
        // Se dispara cuando los datos aún no están completos (ej: al abrir modal)
        console.log(`ℹ️ Esperando datos completos para recalcular ventana (tipo ${ventana.tipo}):`, errores.join(', '))
        return
      }

      const payload = {
        ...ventana,
        productoVidrio: relacion.producto_id,
        proveedorVidrio: relacion.proveedor_id,
        hojas_moviles: ventana.tipo === 3 || ventana.tipo === 46 ? ventana.hojas_moviles : undefined,
      }

      console.log('✅ Recalculando ventana:', payload)
      recalcularCosto(payload, ventana)
    },
    { deep: true, immediate: false })
  })
}, { deep: true })

watch(() => form.cliente, cliente => {
  console.log('✅ Cliente seleccionado:', cliente)
  if (cliente) {
    console.log('✅ Nombre:', cliente.razon_social)
    console.log('✅ RUT:', cliente.identification)
  }
})

const abrirModalCliente = () => {
  modalCliente.value = true
}

// FUNCIONES SIMPLES QUE SÍ FUNCIONAN
const buscarClientesSimple = async () => {
  const query = terminoBusquedaCliente.value?.trim()
  console.log('🔍 BÚSQUEDA LOCAL:', query)
  
  // Si el usuario empieza a escribir de nuevo, limpiar la selección anterior
  if (form.cliente && query !== form.cliente.razon_social) {
    form.cliente = null
    cotizacion.cliente_id = null
  }
  
  if (!query || query.length < 2) {
    clientesBuscados.value = []
    mostrarDropdown.value = false
    return
  }
  
  buscandoClientes.value = true
  mostrarDropdown.value = true
  
  try {
    // Ahora busca en la base de datos local en lugar de Bsale
    const response = await api.get(`/api/clientes/buscar?q=${encodeURIComponent(query)}`)
    console.log('✅ RESPUESTA LOCAL:', response.data)
    
    if (response.data?.length > 0) {
      clientesBuscados.value = response.data.map(cliente => ({
        id: cliente.id,
        razon_social: cliente.razon_social || `${cliente.first_name || ''} ${cliente.last_name || ''}`.trim() || 'Sin nombre',
        identification: cliente.identification || '',
        email: cliente.email || '',
        phone: cliente.phone || ''
      }))
      console.log('✅ CLIENTES PROCESADOS:', clientesBuscados.value)
      mostrarDropdown.value = true
    } else {
      clientesBuscados.value = []
      mostrarDropdown.value = false
      console.log('❌ NO HAY CLIENTES')
    }
  } catch (error) {
    console.error('❌ ERROR:', error)
    clientesBuscados.value = []
    mostrarDropdown.value = false
  } finally {
    buscandoClientes.value = false
  }
}

const seleccionarCliente = (cliente) => {
  console.log('✅ CLIENTE SELECCIONADO:', cliente)
  form.cliente = cliente
  cotizacion.cliente_id = cliente.id // ✅ Actualizar el ID en cotización
  terminoBusquedaCliente.value = cliente.razon_social // Mostrar el nombre en el input
  mostrarDropdown.value = false // Ocultar dropdown
  clientesBuscados.value = [] // Limpiar resultados
}

const onFocusBuscador = () => {
  // Mostrar dropdown si hay resultados
  if (clientesBuscados.value.length > 0) {
    mostrarDropdown.value = true
  }
}

const limpiarBusqueda = () => {
  terminoBusquedaCliente.value = ''
  clientesBuscados.value = []
  mostrarDropdown.value = false
  form.cliente = null
}

const guardarCliente = async () => {
  const { valid } = await formCliente.value.validate()
  if (!valid) return
  try {
    guardandoCliente.value = true
    const res = await api.post('/api/clientes', nuevoCliente.value)
    const clienteCreado = res.data.cliente || res.data

    // Seleccionar el cliente recién creado
    form.cliente = clienteCreado
    cotizacion.cliente_id = clienteCreado.id
    terminoBusquedaCliente.value =
      clienteCreado.razon_social ||
      `${clienteCreado.first_name || ''} ${clienteCreado.last_name || ''}`.trim()
    mostrarDropdown.value = false
    clientesBuscados.value = []

    modalCliente.value = false
    nuevoCliente.value = nuevoClienteVacio()
    formCliente.value.reset()

    mostrarNotificacion('Cliente creado y seleccionado correctamente.', 'success')
  } catch (error) {
    const msg = error.response?.data?.message || error.message
    mostrarNotificacion('Error al crear cliente: ' + msg, 'error', 8000)
  } finally {
    guardandoCliente.value = false
  }
}

// Función de búsqueda de clientes con debounce
const buscarClientes = async (query) => {
  // Si no hay query, usar el término de búsqueda del input
  if (!query) {
    query = terminoBusquedaCliente.value
  }
  console.log('🔍 Buscando clientes con query:', query)
  
  if (!query || query.length < 2) {
    // Si no hay búsqueda, mostrar los clientes iniciales
    clientesBuscados.value = clientes.value.slice(0, 20)
    console.log('📋 Mostrando clientes iniciales:', clientesBuscados.value.length)
    return
  }

  buscandoClientes.value = true
  
  try {
    console.log('🌐 Buscando en API de Bsale...')
    const response = await api.get(`/api/bsale-clientes/buscar?q=${encodeURIComponent(query)}`)
    
    console.log('✅ Respuesta de API:', response.data)
    
    if (response.data && response.data.items && response.data.items.length > 0) {
      clientesBuscados.value = response.data.items.map(cliente => {
        console.log('🔍 Procesando cliente:', cliente)
        
        // Construir razon_social de manera más robusta
        let razonSocial = ''
        if (cliente.company && cliente.company.trim()) {
          razonSocial = cliente.company.trim()
        } else if (cliente.firstName || cliente.lastName) {
          razonSocial = `${cliente.firstName || ''} ${cliente.lastName || ''}`.trim()
        } else if (cliente.razon_social) {
          razonSocial = cliente.razon_social
        } else if (cliente.displayName) {
          razonSocial = cliente.displayName
        } else {
          razonSocial = 'Cliente sin nombre'
        }
        
        // Asegurarse de que no esté vacío
        if (!razonSocial || razonSocial.trim() === '') {
          razonSocial = `Cliente ID: ${cliente.id}`
        }
        
        const clienteProcesado = {
          id: cliente.id,
          razon_social: razonSocial,
          identification: cliente.identification || '',
          email: cliente.email || '',
          phone: cliente.phone || '',
          address: cliente.address || '',
          city: cliente.city || '',
          municipality: cliente.municipality || '',
          first_name: cliente.firstName || '',
          last_name: cliente.lastName || '',
          company: cliente.company || '',
          tipo_cliente: cliente.companyOrPerson == 1 ? 'empresa' : 'persona'
        }
        
        console.log('✅ Cliente procesado:', clienteProcesado)
        return clienteProcesado
      })
      
      console.log('✅ Total clientes procesados:', clientesBuscados.value.length)
      console.log('✅ Lista final:', clientesBuscados.value)
      
      // Verificar estructura para autocomplete
      if (clientesBuscados.value.length > 0) {
        console.log('🔍 Primer cliente para autocomplete:', {
          id: clientesBuscados.value[0].id,
          razon_social: clientesBuscados.value[0].razon_social,
          hasId: !!clientesBuscados.value[0].id,
          hasTitle: !!clientesBuscados.value[0].razon_social
        })
        
        // Forzar que se abra el menú después de un pequeño delay
        setTimeout(() => {
          if (clienteAutocomplete.value && clienteAutocomplete.value.menu) {
            console.log('🎯 Forzando apertura del menú...')
            clienteAutocomplete.value.menu = true
          }
        }, 100)
      }
    } else {
      console.log('❌ No se encontraron clientes en la respuesta')
      clientesBuscados.value = []
    }
    
  } catch (error) {
    console.error('❌ Error en búsqueda:', error)
    console.error('❌ Detalles del error:', error.response?.data)
    clientesBuscados.value = []
  } finally {
    buscandoClientes.value = false
  }
}

const buscarClientesDebounced = debounce(buscarClientes, 300)

// Texto dinámico para cuando no hay resultados
const getNoDataText = () => {
  if (buscandoClientes.value) {
    return 'Buscando clientes...'
  }
  if (!clienteSearch.value || clienteSearch.value.length < 2) {
    return 'Escribe al menos 2 caracteres para buscar'
  }
  return 'No se encontraron clientes con ese criterio'
}

// Función para cargar solo los primeros clientes (rápido)
const cargarClientesIniciales = async () => {
  try {
    // Cargar solo los primeros 50 clientes (límite de Bsale por página)
    const response = await api.get('/api/bsale-clientes?limit=50&offset=0')
    console.log('✅ Primeros clientes cargados:', response.data)
    
    const clientesProcesados = response.data.items?.map(cliente => ({
      id: cliente.id,
      razon_social: cliente.razon_social || cliente.displayName || 'Sin nombre',
      identification: cliente.identification,
      email: cliente.email,
      phone: cliente.phone,
      address: cliente.address,
      city: cliente.city,
      municipality: cliente.municipality,
      first_name: cliente.firstName,
      last_name: cliente.lastName,
      company: cliente.company,
      tipo_cliente: cliente.companyOrPerson == 1 ? 'empresa' : 'persona'
    })) || []
    
    clientes.value = clientesProcesados
    clientesBuscados.value = clientesProcesados.slice(0, 20)
    
    console.log('✅ Clientes iniciales listos:', clientes.value.length)
  } catch (error) {
    console.error('❌ Error cargando clientes iniciales:', error)
    clientesBuscados.value = []
  }
}

const exportarImagenesVentanas = async () => {
  await new Promise(resolve => setTimeout(resolve, 2000))
  const imagenes = []
  
  console.log('🔍 INICIANDO CAPTURA DE IMÁGENES')
  console.log('🔍 VENTANA REFS:', ventanaRefs.value)
  console.log('🔍 TOTAL VENTANAS:', cotizacion.ventanas.length)
  
  for (let i = 0; i < cotizacion.ventanas.length; i++) {
    const ventana = cotizacion.ventanas[i]
    console.log(`🔍 VENTANA ${i} - TIPO:`, ventana.tipo)
    
    try {
      const componente = ventanaRefs.value[i]
      console.log(`🔍 COMPONENTE ${i}:`, componente)
      console.log(`🔍 TIPO DE COMPONENTE ${i}:`, typeof componente)
      console.log(`🔍 $el DE COMPONENTE ${i}:`, componente?.$el)
      console.log(`🔍 TIPO DE $el ${i}:`, typeof componente?.$el)
      
      // ✅ VERIFICAR SI EL COMPONENTE TIENE MÉTODO exportarImagen
      if (componente?.exportarImagen && typeof componente.exportarImagen === 'function') {
        console.log(`🔧 Usando exportarImagen() del componente ${i}`)
        try {
          const base64 = await componente.exportarImagen()
          if (base64 && base64 !== null) {
            console.log(`✅ IMAGEN ${i} CAPTURADA VIA exportarImagen:`, base64.substring(0, 50))
            imagenes.push(base64)
            continue
          } else {
            console.warn(`⚠️ exportarImagen() devolvió null para componente ${i}`)
          }
        } catch (exportError) {
          console.error(`❌ Error en exportarImagen del componente ${i}:`, exportError)
        }
      }
      
      // ✅ VERIFICAR QUE $el EXISTE Y ES UN ELEMENTO DOM
      if (componente?.$el && 
          componente.$el.nodeType === Node.ELEMENT_NODE && 
          typeof componente.$el.querySelectorAll === 'function') {
        
        console.log(`🔍 ELEMENTO DOM ${i} VÁLIDO:`, componente.$el.tagName)
        
        const todosLosCanvas = componente.$el.querySelectorAll('canvas')
        console.log(`🔍 CANVAS ENCONTRADOS EN COMPONENTE ${i}:`, todosLosCanvas.length)
        
        let canvas = null
        
        // Buscar canvas con contenido
        for (let j = 0; j < todosLosCanvas.length; j++) {
          const testCanvas = todosLosCanvas[j]
          console.log(`🔍 CANVAS ${i}.${j} - DIMENSIONES:`, testCanvas.width, 'x', testCanvas.height)
          
          try {
            const ctx = testCanvas.getContext('2d')
            const imageData = ctx.getImageData(0, 0, testCanvas.width, testCanvas.height)
            const hasContent = imageData.data.some(pixel => pixel !== 0)
            
            console.log(`🔍 CANVAS ${i}.${j} - TIENE CONTENIDO:`, hasContent)
            
            if (hasContent) {
              canvas = testCanvas
              break
            }
          } catch (canvasError) {
            console.error(`❌ Error verificando canvas ${i}.${j}:`, canvasError)
          }
        }
        
        if (canvas) {
          // ✅ FORZAR REDIBUJADO PARA KONVA
          try {
            const stage = canvas.getStage?.()
            if (stage) {
              console.log(`🔄 Forzando redibujado de Konva en ventana ${i}`)
              stage.draw()
              await new Promise(resolve => setTimeout(resolve, 500))
            }
          } catch (e) {
            console.log(`ℹ️ Ventana ${i} no es Konva`)
          }
          
          const base64 = canvas.toDataURL('image/png')
          console.log(`✅ IMAGEN ${i} CAPTURADA VIA CANVAS:`, base64.substring(0, 50))
          imagenes.push(base64)
        } else if (todosLosCanvas.length > 0) {
          // ✅ USAR PRIMER CANVAS AUNQUE ESTÉ VACÍO
          console.log(`🔧 Usando primer canvas aunque esté vacío...`)
          try {
            const base64 = todosLosCanvas[0].toDataURL('image/png')
            imagenes.push(base64)
          } catch (toDataError) {
            console.error(`❌ Error en toDataURL:`, toDataError)
            imagenes.push(null)
          }
        } else {
          console.warn(`⚠️ No se encontraron canvas en componente ${i}`)
          imagenes.push(null)
        }
      } else {
        console.warn(`⚠️ Componente ${i} no tiene $el válido o querySelectorAll`)
        console.log(`🔍 ¿$el existe?:`, !!componente?.$el)
        console.log(`🔍 ¿Es Element?:`, componente?.$el instanceof Element)
        console.log(`🔍 ¿Tiene querySelectorAll?:`, typeof componente?.$el?.querySelectorAll)
        
        // ✅ ÚLTIMO RECURSO: BUSCAR EN DOCUMENT
        console.log(`🔧 Último recurso: buscando canvas globalmente...`)
        const canvasGlobales = document.querySelectorAll('canvas')
        console.log(`🔍 Canvas globales encontrados:`, canvasGlobales.length)
        
        if (canvasGlobales.length > i) {
          try {
            const base64 = canvasGlobales[i].toDataURL('image/png')
            console.log(`✅ IMAGEN ${i} CAPTURADA VIA BÚSQUEDA GLOBAL`)
            imagenes.push(base64)
          } catch (globalError) {
            console.error(`❌ Error en canvas global:`, globalError)
            imagenes.push(null)
          }
        } else {
          imagenes.push(null)
        }
      }
    } catch (error) {
      console.error(`❌ ERROR GENERAL capturando imagen ${i}:`, error)
      imagenes.push(null)
    }
  }

  console.log('🖼️ RESULTADO FINAL:', imagenes.map((img, i) => `${i}: ${img ? 'OK' : 'NULL'}`))
  return imagenes
}

const guardarCotizacion = async () => {
  loading.value = true
  try {
    const imagenes = await exportarImagenesVentanas()
        // ✅ AGREGAR ESTOS LOGS
    console.log('🖼️ IMÁGENES CAPTURADAS:', imagenes)
    console.log('🖼️ NÚMERO DE IMÁGENES:', imagenes.length)
    console.log('🖼️ PRIMERA IMAGEN (primeros 100 chars):', imagenes[0]?.substring(0, 100))
    const clienteSeleccionado = form.cliente
    
    // Validar que haya cliente y al menos un item (ventana o producto)
    if (!clienteSeleccionado) {
      alert('Debes seleccionar un cliente')
      return
    }
    
    const tieneVentanas = cotizacion.ventanas.length > 0
    const tieneProductos = cotizacion.productos && cotizacion.productos.length > 0
    
    if (!tieneVentanas && !tieneProductos) {
      alert('Debes agregar al menos una ventana o un producto a la cotización')
      return
    }
    
    const usuarioActual = JSON.parse(localStorage.getItem('user') || '{}')

    const payload = {
      cliente_id: clienteSeleccionado.id,
      vendedor_id: usuarioActual.id ?? 1,
      fecha: new Date().toISOString().split('T')[0],
      estado_cotizacion_id: cotizacion.estado_cotizacion_id ?? 1, // default: Evaluacióna
      observaciones: cotizacion.observaciones,
      imagenes_ventanas: imagenes, // base64 strings
      ventanas: cotizacion.ventanas.map((v, index) => {
        console.log(`🔍 Mapeando ventana ${index}:`, v)
        console.log(`🔍 v.tipoVidrio (para tipo_vidrio_id):`, v.tipoVidrio)
        console.log(`🔍 v.productoVidrioProveedor:`, v.productoVidrioProveedor)
        
        const relacion = buscarRelacionVidrioProveedor(v.productoVidrioProveedor)
        
        const ventanaMapeada = {
          id: v.id, // ✅ Incluir ID para actualización
          tipo_ventana_id: v.tipo,
          ancho: v.ancho,
          alto: v.alto,
          cantidad: v.cantidad,
          color_id: v.color,
          tipo_vidrio_id: v.tipoVidrio, // ✅ AGREGAR ESTE CAMPO
          producto_vidrio_proveedor_id: v.productoVidrioProveedor,  
          producto_id: relacion?.producto_id,
          proveedor_id: relacion?.proveedor_id,
          costo: v.costo_total || v.costo || 0,
          costo_unitario: v.costo_unitario || 0,
          precio: v.precio || 0,
          precio_unitario: v.precio_unitario || 0,
          // Campos para Bay Window
          tipo_ventana_izquierda: v.tipoVentanaIzquierda ?? null,
          tipo_ventana_centro: v.tipoVentanaCentro ?? null,
          tipo_ventana_derecha: v.tipoVentanaDerecha ?? null,
          ancho_izquierda: v.ancho_izquierda ?? null,
          ancho_centro: v.ancho_centro ?? null,
          ancho_derecha: v.ancho_derecha ?? null,
          // Campos para Correderas
          hojas_totales: v.hojas_totales ?? null,
          hojas_moviles: v.hojas_moviles ?? null,
          hoja_movil_seleccionada: v.hojaMovilSeleccionada ?? null,
          hoja1_al_frente: v.hoja1AlFrente ?? null,
        }
        
        console.log(`🔍 Ventana ${index} mapeada:`, ventanaMapeada)
        console.log(`🔍 tipo_vidrio_id en ventana mapeada:`, ventanaMapeada.tipo_vidrio_id)
        
        return ventanaMapeada
      }),
      productos: (cotizacion.productos || []).map(p => ({
        id: p.id, // ✅ Incluir ID para actualización
        producto_lista_id: p.producto_lista_id,
        lista_precio_id: p.lista_precio_id,
        descripcion: p.descripcion || p.nombre || '',
        cantidad: p.cantidad,
        precio_unitario: p.precio_venta / p.cantidad, // Precio unitario
        total: p.total || (p.precio_venta * p.cantidad),
        // Campos adicionales para vidrios
        esVidrio: p.esVidrio || false,
        ancho_mm: p.ancho_mm || null,
        alto_mm: p.alto_mm || null,
        m2: p.m2 || null,
        pulido: p.pulido || false
      })),
    }
        // ✅ AGREGAR ESTE LOG
    console.log('📤 PAYLOAD A ENVIAR:', payload)
    console.log('📤 VENTANAS ESPECÍFICAS:', payload.ventanas)
    console.log('📤 PRODUCTOS ESPECÍFICOS:', payload.productos)

    // ✅ USAR PUT SI ESTÁ EN MODO EDICIÓN, POST SI ES NUEVA
    if (modoEdicion.value) {
      console.log('🔄 Actualizando cotización existente ID:', cotizacionId.value)
      await api.put(`/api/cotizaciones/${cotizacionId.value}`, payload)
      alert('Cotización actualizada correctamente')
    } else {
      console.log('✨ Creando nueva cotización')
      await api.post('/api/cotizaciones', payload)
      alert('Cotización guardada correctamente')
    }

    router.push({ name: 'cotizaciones' })

  } catch (error) {
    console.error('Error al guardar cotización:', error)
    
    // Mostrar mensaje específico si es error de cliente
    if (error.response?.data?.message) {
      alert(error.response.data.message)
    } else {
      alert('Error al guardar la cotización')
    }
  } finally {
    loading.value = false
  }
}





</script>



<style scoped>
.v-card-subtitle {
  font-weight: 600;
}
</style>
