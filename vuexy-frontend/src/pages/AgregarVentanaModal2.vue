<template>
  <v-dialog v-model="localMostrar" max-width="1200px" persistent>
    <v-card class="pa-4">
      <v-card-title class="text-h5">
        {{ isEdit ? 'Editar ventana' : 'Agregar ventana' }}
      </v-card-title>
      <v-divider class="mb-4" />

      <v-form ref="formRef" @submit.prevent="onGuardar">
        <v-row dense>
          <v-col cols="12" md="4">
            <v-select
              v-model="ventana.tipo"
              :items="tiposVentanaFiltrados"
              item-title="nombre"
              item-value="id"
              label="Tipo de ventana"
              :rules="[v => !!v || 'Requerido']"
              outlined
              color="primary"
              required
            />
          </v-col>
          <v-col v-if="ventana.tipo !== 58" cols="6" md="2">
            <v-text-field
              v-model.number="ventana.ancho"
              label="Ancho (mm)"
              type="number"
              min="1"
              :rules="[v => !!v || 'Requerido']"
              outlined
              color="primary"
              required
            />
          </v-col>
          <v-col v-if="ventana.tipo !== 58" cols="6" md="2">
            <v-text-field
              v-model.number="ventana.alto"
              label="Alto (mm)"
              type="number"
              min="1"
              :rules="[v => !!v || 'Requerido']"
              outlined
              color="primary"
              required
            />
          </v-col>
          <v-col cols="6" md="2">
            <v-text-field
              v-model.number="ventana.cantidad"
              label="Cantidad"
              type="number"
              min="1"
              :rules="[v => !!v || 'Requerido']"
              outlined
              color="primary"
              required
            />
          </v-col>
        </v-row>

        <v-row dense>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventana.material"
              :items="materiales"
              item-title="nombre"
              item-value="id"
              label="Material"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventana.color"
              :items="colores"
              item-title="nombre"
              item-value="id"
              label="Color"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventana.tipoVidrio"
              :items="tiposVidrio"
              item-title="nombre"
              item-value="id"
              label="Tipo de vidrio"
              outlined
              color="primary"
            />
          </v-col>
          <v-col v-if="ventana.tipoVidrio" cols="6" md="3" :key="`col-vidrio-${ventana.tipoVidrio}`">
            <v-select
              v-model="ventana.productoVidrioProveedor"
              :items="productosVidrioFiltrados"
              item-title="nombre"
              item-value="id"
              label="Producto de vidrio"
              outlined
              color="primary"
              clearable
            >
              <template #no-data>
                <v-list-item>
                  <v-list-item-title>
                    No hay productos disponibles
                  </v-list-item-title>
                </v-list-item>
              </template>
            </v-select>
          </v-col>
          <v-col v-else cols="6" md="3">
            <v-select
              disabled
              label="Producto de vidrio"
              outlined
              color="primary"
              hint="Selecciona un tipo de vidrio primero"
              persistent-hint
            />
          </v-col>
        </v-row>

        <!-- Switch de Manillón para Corredera AL25 -->
        <v-row v-if="ventana.tipo === 55" dense class="mt-2">
          <v-col cols="12" md="6">
            <v-switch
              v-model="ventana.manillon"
              color="primary"
              label="Manillón"
              hide-details
              inset
            >
              <template v-slot:label>
                <span class="text-body-1">
                  <strong>Herraje:</strong> {{ ventana.manillon ? 'Manillón' : 'Pestillo' }}
                </span>
              </template>
            </v-switch>
            <div class="text-caption text-medium-emphasis ml-2">
              Seleccione el tipo de herraje para la ventana corredera
            </div>
          </v-col>
        </v-row>

        <!-- Hojas totales y móviles para Corredera AL25 -->
        <v-row v-if="ventana.tipo === 55" dense class="mt-2">
          <v-col cols="6" md="3">
            <v-select
              v-model="ventana.hojas_totales"
              :items="[2]"
              label="Hojas totales"
              outlined
              color="primary"
              disabled
              hint="AL25 siempre tiene 2 hojas"
              persistent-hint
            />
          </v-col>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventana.hojas_moviles"
              :items="[1, 2]"
              label="Hojas móviles"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="12" md="6" v-if="ventana.hojas_moviles === 1">
            <v-select
              v-model="ventana.hojaMovilSeleccionada"
              :items="[
                { value: 1, title: 'Hoja 1 (Izquierda)' },
                { value: 2, title: 'Hoja 2 (Derecha)' }
              ]"
              item-title="title"
              item-value="value"
              label="¿Cuál hoja se mueve?"
              outlined
              color="primary"
            />
          </v-col>
        </v-row>

        <!-- Configuración de Ventana Compuesta AL42 -->
        <v-row v-if="ventana.tipo === 57" dense class="mt-2">
          <v-col cols="12">
            <v-card variant="outlined" class="pa-3">
              <v-card-title class="text-subtitle-1 pa-0 mb-2">
                🪟 Configuración de Ventana Compuesta
              </v-card-title>
              
              <!-- Filas y Columnas -->
              <v-row dense>
                <v-col cols="6">
                  <v-text-field
                    v-model.number="ventana.filas"
                    label="Número de filas"
                    type="number"
                    min="1"
                    outlined
                    color="primary"
                    hint="Divisiones horizontales"
                    persistent-hint
                  />
                </v-col>
                <v-col cols="6">
                  <v-text-field
                    v-model.number="ventana.columnas"
                    label="Número de columnas"
                    type="number"
                    min="1"
                    outlined
                    color="primary"
                    hint="Divisiones verticales"
                    persistent-hint
                  />
                </v-col>
              </v-row>

              <!-- Dimensiones personalizadas -->
              <v-row dense class="mt-3">
                <v-col cols="12">
                  <div class="text-caption mb-2">📏 Dimensiones personalizadas:</div>
                </v-col>
                <!-- Altos de filas -->
                <v-col cols="6">
                  <div class="text-caption mb-1">Altos de filas (mm):</div>
                  <v-text-field
                    v-for="(alto, idx) in ventana.altosFilas"
                    :key="'alto-' + idx"
                    v-model.number="ventana.altosFilas[idx]"
                    :label="`Fila ${idx + 1}`"
                    type="number"
                    min="1"
                    outlined
                    dense
                    color="primary"
                    hide-details
                    class="mb-2"
                  />
                </v-col>
                <!-- Anchos de columnas -->
                <v-col cols="6">
                  <div class="text-caption mb-1">Anchos de columnas (mm):</div>
                  <v-text-field
                    v-for="(ancho, idx) in ventana.anchosColumnas"
                    :key="'ancho-' + idx"
                    v-model.number="ventana.anchosColumnas[idx]"
                    :label="`Columna ${idx + 1}`"
                    type="number"
                    min="1"
                    outlined
                    dense
                    color="primary"
                    hide-details
                    class="mb-2"
                  />
                </v-col>
              </v-row>

              <!-- Grid de secciones -->
              <div class="mt-4">
                <div class="text-caption mb-2">Configurar cada sección:</div>
                <div v-for="(fila, filaIdx) in ventana.secciones" :key="'fila-' + filaIdx" class="mb-2">
                  <v-row dense>
                    <v-col 
                      v-for="(seccion, colIdx) in fila" 
                      :key="'sec-' + filaIdx + '-' + colIdx"
                      :cols="12 / ventana.columnas"
                    >
                      <v-select
                        v-model="seccion.tipo"
                        :items="[
                          { value: 1, title: 'Fija' },
                          { value: 56, title: 'Proyectante' }
                        ]"
                        item-title="title"
                        item-value="value"
                        :label="`F${filaIdx + 1}C${colIdx + 1}`"
                        outlined
                        dense
                        color="primary"
                        hide-details
                      />
                    </v-col>
                  </v-row>
                </div>
              </div>
            </v-card>
          </v-col>
        </v-row>

          <!-- Agregar después de la primera v-row dense en AgregarVentanaModal2.vue -->

<!-- Anchos específicos para Bay Window -->
<v-row v-if="ventana.tipo === 47" dense>
  <v-col cols="4">
    <v-text-field
      v-model.number="ventana.ancho_izquierda"
      label="Ancho izquierda (mm)"
      type="number"
      outlined
      color="primary"
    />
  </v-col>
  <v-col cols="4">
    <v-text-field
      v-model.number="ventana.ancho_centro"
      label="Ancho centro (mm)"
      type="number"
      outlined
      color="primary"
    />
  </v-col>
  <v-col cols="4">
    <v-text-field
      v-model.number="ventana.ancho_derecha"
      label="Ancho derecha (mm)"
      type="number"
      outlined
      color="primary"
    />
  </v-col>
</v-row>

<!-- Switches para ventanas compuestas -->
<template v-if="ventana.tipo === 47">
  <v-row dense>
    <v-col cols="6">
      <v-switch
        v-model="ventana.tipoVentanaIzquierda.compuesta"
        label="¿Ventana izquierda compuesta?"
        color="primary"
      />
    </v-col>
    <v-col cols="6">
      <v-switch
        v-model="ventana.tipoVentanaDerecha.compuesta"
        label="¿Ventana derecha compuesta?"
        color="primary"
      />
    </v-col>
  </v-row>
</template>

  <!-- Configuración completa de Bay Window -->
  <template v-if="ventana.tipo === 47">
    <!-- === IZQUIERDA === -->
    <v-row dense>
      <v-col cols="12"><h4>Ventana Izquierda</h4></v-col>

      <template v-if="ventana.tipoVentanaIzquierda.compuesta">
        <!-- Parte superior -->
        <v-col cols="6">
          <h5>Parte superior</h5>
          <v-select
            v-model="ventana.tipoVentanaIzquierda.partes[0].tipo"
            :items="tiposVentanaBayKonva"
            item-title="nombre"
            item-value="id"
            label="Tipo"
            outlined
            dense
          />
          <v-text-field
            v-model.number="ventana.tipoVentanaIzquierda.partes[0].alto"
            label="Alto (mm)"
            type="number"
            outlined
            dense
          />
          <!-- Solo si es Abatir -->
          <template v-if="isAbatir(ventana.tipoVentanaIzquierda.partes[0].tipo)">
            <v-radio-group v-model="ventana.tipoVentanaIzquierda.partes[0].ladoApertura" row density="compact" label="Lado apertura">
              <v-radio label="Izquierda" value="izquierda" />
              <v-radio label="Derecha" value="derecha" />
            </v-radio-group>
            <v-radio-group v-model="ventana.tipoVentanaIzquierda.partes[0].direccionApertura" row density="compact" label="Dirección apertura">
              <v-radio label="Interior" value="interior" />
              <v-radio label="Exterior" value="exterior" />
            </v-radio-group>
          </template>
        </v-col>

        <!-- Parte inferior -->
        <v-col cols="6">
          <h5>Parte inferior</h5>
          <v-select
            v-model="ventana.tipoVentanaIzquierda.partes[1].tipo"
            :items="tiposVentanaBayKonva"
            item-title="nombre"
            item-value="id"
            label="Tipo"
            outlined
            dense
          />
          <v-text-field
            v-model.number="ventana.tipoVentanaIzquierda.partes[1].alto"
            label="Alto (mm)"
            type="number"
            outlined
            dense
          />
          <!-- Solo si es Abatir -->
          <template v-if="isAbatir(ventana.tipoVentanaIzquierda.partes[1].tipo)">
            <v-radio-group v-model="ventana.tipoVentanaIzquierda.partes[1].ladoApertura" row density="compact" label="Lado apertura">
              <v-radio label="Izquierda" value="izquierda" />
              <v-radio label="Derecha" value="derecha" />
            </v-radio-group>
            <v-radio-group v-model="ventana.tipoVentanaIzquierda.partes[1].direccionApertura" row density="compact" label="Dirección apertura">
              <v-radio label="Interior" value="interior" />
              <v-radio label="Exterior" value="exterior" />
            </v-radio-group>
          </template>
        </v-col>
      </template>

      <!-- Simple -->
      <template v-else>
        <v-col cols="4">
          <v-select
            v-model="ventana.tipoVentanaIzquierda.partes[0].tipo"
            :items="tiposVentanaBayKonva"
            item-title="nombre"
            item-value="id"
            label="Tipo de ventana izquierda"
            outlined
          />
        </v-col>
        <!-- Selectores solo si Abatir -->
        <v-col cols="4" v-if="isAbatir(ventana.tipoVentanaIzquierda.partes[0].tipo)">
          <v-radio-group v-model="ventana.tipoVentanaIzquierda.ladoApertura" row density="compact" label="Lado apertura">
            <v-radio label="Izquierda" value="izquierda" />
            <v-radio label="Derecha" value="derecha" />
          </v-radio-group>
        </v-col>
        <v-col cols="4" v-if="isAbatir(ventana.tipoVentanaIzquierda.partes[0].tipo)">
          <v-radio-group v-model="ventana.tipoVentanaIzquierda.direccionApertura" row density="compact" label="Dirección apertura">
            <v-radio label="Interior" value="interior" />
            <v-radio label="Exterior" value="exterior" />
          </v-radio-group>
        </v-col>
      </template>
    </v-row>

    <!-- === CENTRO === (sin cambios de UI) -->
    <v-row dense>
      <v-col cols="12"><h4>Ventana Centro</h4></v-col>
      <v-col cols="4">
        <v-select
          v-model="ventana.tipoVentanaCentro.tipo"
          :items="tiposVentanaCentro"
          item-title="nombre"
          item-value="id"
          label="Tipo de ventana centro"
          outlined
          color="primary"
        />
      </v-col>

      <!-- Opciones corredera -->
      <template v-if="ventana.tipoVentanaCentro && [3, 46].includes(ventana.tipoVentanaCentro.tipo)">
        <v-col cols="4">
          <v-select
            v-model="ventana.tipoVentanaCentro.hojas_totales"
             :items="[1, 2, 3, 4]"
            label="Hojas totales"
            outlined
            color="primary"
          />
        </v-col>
        <v-col cols="4">
          <v-select
            v-model="ventana.tipoVentanaCentro.hojas_moviles"
            :items="[1, 2, 3, 4]"
            label="Hojas móviles"
            :disabled="!ventana.tipoVentanaCentro.hojas_totales"
            outlined
            color="primary"
          />
        </v-col>
        <v-col cols="12" v-if="ventana.tipoVentanaCentro.hojas_moviles === 1">
          <v-radio-group
            v-model="ventana.tipoVentanaCentro.hojaMovilSeleccionada"
            row
            label="Selecciona la hoja que se mueve"
          >
            <v-radio label="Mover hoja izquierda (1)" :value="1" />
            <v-radio label="Mover hoja derecha (2)" :value="2" />
          </v-radio-group>
        </v-col>
      </template>
    </v-row>

    <!-- === DERECHA === -->
    <v-row dense>
      <v-col cols="12"><h4>Ventana Derecha</h4></v-col>

      <template v-if="ventana.tipoVentanaDerecha.compuesta">
        <!-- Parte superior -->
        <v-col cols="6">
          <h5>Parte superior</h5>
          <v-select
            v-model="ventana.tipoVentanaDerecha.partes[0].tipo"
            :items="tiposVentanaBayKonva"
            item-title="nombre"
            item-value="id"
            label="Tipo"
            outlined
            dense
          />
          <v-text-field
            v-model.number="ventana.tipoVentanaDerecha.partes[0].alto"
            label="Alto (mm)"
            type="number"
            outlined
            dense
          />
          <!-- Solo si es Abatir -->
          <template v-if="isAbatir(ventana.tipoVentanaDerecha.partes[0].tipo)">
            <v-radio-group v-model="ventana.tipoVentanaDerecha.partes[0].ladoApertura" row density="compact" label="Lado apertura">
              <v-radio label="Izquierda" value="izquierda" />
              <v-radio label="Derecha" value="derecha" />
            </v-radio-group>
            <v-radio-group v-model="ventana.tipoVentanaDerecha.partes[0].direccionApertura" row density="compact" label="Dirección apertura">
              <v-radio label="Interior" value="interior" />
              <v-radio label="Exterior" value="exterior" />
            </v-radio-group>
          </template>
        </v-col>

        <!-- Parte inferior -->
        <v-col cols="6">
          <h5>Parte inferior</h5>
          <v-select
            v-model="ventana.tipoVentanaDerecha.partes[1].tipo"
            :items="tiposVentanaBayKonva"
            item-title="nombre"
            item-value="id"
            label="Tipo"
            outlined
            dense
          />
          <v-text-field
            v-model.number="ventana.tipoVentanaDerecha.partes[1].alto"
            label="Alto (mm)"
            type="number"
            outlined
            dense
          />
          <!-- Solo si es Abatir -->
          <template v-if="isAbatir(ventana.tipoVentanaDerecha.partes[1].tipo)">
            <v-radio-group v-model="ventana.tipoVentanaDerecha.partes[1].ladoApertura" row density="compact" label="Lado apertura">
              <v-radio label="Izquierda" value="izquierda" />
              <v-radio label="Derecha" value="derecha" />
            </v-radio-group>
            <v-radio-group v-model="ventana.tipoVentanaDerecha.partes[1].direccionApertura" row density="compact" label="Dirección apertura">
              <v-radio label="Interior" value="interior" />
              <v-radio label="Exterior" value="exterior" />
            </v-radio-group>
          </template>
        </v-col>
      </template>

      <!-- Simple -->
      <template v-else>
        <v-col cols="4">
          <v-select
            v-model="ventana.tipoVentanaDerecha.partes[0].tipo"
            :items="tiposVentanaBayKonva"
            item-title="nombre"
            item-value="id"
            label="Tipo de ventana derecha"
            outlined
          />
        </v-col>
        <!-- Selectores solo si Abatir -->
        <v-col cols="4" v-if="isAbatir(ventana.tipoVentanaDerecha.partes[0].tipo)">
          <v-radio-group v-model="ventana.tipoVentanaDerecha.ladoApertura" row density="compact" label="Lado apertura">
            <v-radio label="Izquierda" value="izquierda" />
            <v-radio label="Derecha" value="derecha" />
          </v-radio-group>
        </v-col>
        <v-col cols="4" v-if="isAbatir(ventana.tipoVentanaDerecha.partes[0].tipo)">
          <v-radio-group v-model="ventana.tipoVentanaDerecha.direccionApertura" row density="compact" label="Dirección apertura">
            <v-radio label="Interior" value="interior" />
            <v-radio label="Exterior" value="exterior" />
          </v-radio-group>
        </v-col>
      </template>
    </v-row>
  </template>


        <!-- Solo para correderas (tipo 3 o 46) -->
        <v-row v-if="[3, 46, 52].includes(ventana.tipo)">
          <v-col cols="6" md="3">
            <v-select
              v-model="ventana.hojas_totales"
              :items="[2, 3, 4, 6]"
              label="Hojas totales"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="6" md="3">
            <v-select
              v-model="ventana.hojas_moviles"
              :items="[1, 2, 3, 4]"
              label="Hojas móviles"
              :disabled="!ventana.hojas_totales"
              :rules="[v => !v || v <= ventana.hojas_totales || 'No puede exceder total']"
              outlined
              color="primary"
            />
          </v-col>
          <v-col cols="12" md="6" v-if="ventana.hojas_moviles === 1">
            <v-radio-group
              v-model="ventana.hojaMovilSeleccionada"
              row
              label="Selecciona la hoja que se mueve"
            >
              <v-radio label="Mover hoja izquierda (1)" :value="1" />
              <v-radio label="Mover hoja derecha (2)" :value="2" />
            </v-radio-group>
          </v-col>
        </v-row>
        <v-row v-if="ventana.tipo === 50" dense>
          <v-col cols="12" md="4">
            <v-radio-group v-model="ventana.ladoApertura" label="Lado apertura" row>
              <v-radio label="Izquierda" value="izquierda" />
              <v-radio label="Derecha" value="derecha" />
            </v-radio-group>
          </v-col>
          <v-col cols="12" md="4">
            <v-radio-group v-model="ventana.direccionApertura" label="Apertura" row>
              <v-radio label="Interior" value="interior" />
              <v-radio label="Exterior" value="exterior" />
            </v-radio-group>
          </v-col>
          <v-col cols="12" md="4">
            <v-switch
              v-model="ventana.pasoLibre"
              inset
              color="primary"
              :label="ventana.pasoLibre ? 'Paso libre (sin perfil inferior)' : 'Paso cerrado'"
            />
          </v-col>
        </v-row>
        <v-row v-if="ventana.tipo === 51" dense>
          <v-col cols="12" md="4">
            <v-radio-group v-model="ventana.hojaActiva" label="Hoja activa" row>
              <v-radio label="Izquierda" value="izquierda" />
              <v-radio label="Derecha" value="derecha" />
            </v-radio-group>
          </v-col>
          <v-col cols="12" md="4">
            <v-radio-group v-model="ventana.direccionApertura" label="Apertura" row>
              <v-radio label="Interior" value="interior" />
              <v-radio label="Exterior" value="exterior" />
            </v-radio-group>
          </v-col>
          <v-col cols="12" md="4">
            <v-switch
              v-model="ventana.pasoLibre"
              inset
              color="primary"
              :label="ventana.pasoLibre ? 'Paso libre (sin perfil inferior)' : 'Paso cerrado'"
            />
          </v-col>
        </v-row>
            <v-row v-if="ventana.tipo === 53" dense>
              <v-col cols="12" md="6">
                <v-radio-group v-model="ventana.ladoApertura" label="Lado de apertura (movimiento)" row>
                  <v-radio label="Hacia la derecha" value="derecha" />
                  <v-radio label="Hacia la izquierda" value="izquierda" />
                </v-radio-group>
              </v-col>
            </v-row>
        
              <!-- Ventana compuesta flexible (id 54) -->
  <v-card v-if="ventana.tipo === 54" class="pa-3" variant="tonal">
    <v-row dense>
      <v-col cols="12" sm="3">
        <v-select
          v-model="ventana.orientacionComp"
          :items="[{title:'Horizontal',value:'horizontal'},{title:'Vertical',value:'vertical'}]"
          label="Orientación"
          density="comfortable"
        />
      </v-col>
      <v-col cols="12" sm="3">
        <v-text-field
          v-model.number="ventana.cantidadComp"
          type="number" min="2" max="8"
          label="Cantidad de ventanas"
          density="comfortable"
          @blur="ajustarCantidadItems()"
        />
      </v-col>
    </v-row>

    <v-divider class="my-2" />

    <v-row dense>
      <v-col
        v-for="(it,idx) in ventana.itemsComp"
        :key="'comp-item-'+idx"
        cols="12" md="6"
      >
        <v-card class="pa-3">
          <div class="d-flex align-center justify-space-between">
            <strong>Sección {{ idx + 1 }}</strong>
            <v-text-field
              v-model.number="it.sizePercent"
              type="number" min="0" max="100"
              suffix="%"
              label="Tamaño"
              density="compact"
              style="max-width:140px"
            />
          </div>

          <v-select
            v-model="it.tipo"
            :items="tiposVentanaBayKonva"
            item-title="nombre"
            item-value="id"
            label="Tipo de ventana"
            density="comfortable"
          />

          <!-- Solo si es Abatir (49) mostrar lado/dirección -->
          <template v-if="isAbatir(it.tipo)">
            <v-radio-group v-model="it.ladoApertura" row density="compact" label="Lado apertura">
              <v-radio label="Izquierda" value="izquierda" />
              <v-radio label="Derecha" value="derecha" />
            </v-radio-group>
            <v-radio-group v-model="it.direccionApertura" row density="compact" label="Dirección apertura">
              <v-radio label="Interior" value="interior" />
              <v-radio label="Exterior" value="exterior" />
            </v-radio-group>
          </template>
        </v-card>
      </v-col>
    </v-row>

    <v-alert type="info" variant="text" density="compact" class="mt-2">
      Si no defines porcentajes, las secciones se reparten por igual.
    </v-alert>
  </v-card>


        <!-- Espacio para tu visor Konva o componentes visuales -->
        <v-row>
          <v-col cols="12">
            <VentanaFijaAL42
              v-if="ventana.tipo === 1"
              :ancho="ventana.ancho"
              :alto="ventana.alto"
              :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
              :material="ventana.material"
              :tipoVidrio="ventana.tipoVidrio"
              :productoVidrioProveedor="ventana.productoVidrioProveedor"
            />
            <VentanaEditor
              v-else-if="ventana.tipo === 2"
              :ancho="ventana.ancho"
              :alto="ventana.alto"
              :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
              :material="ventana.material"
              :tipoVidrio="ventana.tipoVidrio"
              :productoVidrioProveedor="ventana.productoVidrioProveedor"
            />
            <VentanaProyectante
              v-else-if="ventana.tipo === 45"
              :ancho="ventana.ancho"
              :alto="ventana.alto"
              :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
              :material="ventana.material"
              :tipoVidrio="ventana.tipoVidrio"
              :productoVidrioProveedor="ventana.productoVidrioProveedor"
            />
            <VentanaProyectanteAL42
              v-else-if="ventana.tipo === 56"
              :ancho="ventana.ancho"
              :alto="ventana.alto"
              :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
              :material="ventana.material"
              :tipoVidrio="ventana.tipoVidrio"
              :productoVidrioProveedor="ventana.productoVidrioProveedor"
            />
            <VentanaCorrederaAL25
              v-else-if="ventana.tipo === 55"
              :ancho="ventana.ancho"
              :alto="ventana.alto"
              :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
              :hoja1AlFrente="ventana.hoja1AlFrente"
              :hojas-moviles="ventana.hojas_moviles || 2"
              :hoja-movil-seleccionada="ventana.hojaMovilSeleccionada || 1"
            />
            <VistaVentanaCompuestaAL42
              v-else-if="ventana.tipo === 57"
              :key="`compuesta-${ventana.ancho}-${ventana.alto}-${ventana.filas}-${ventana.columnas}`"
              :ancho="ventana.ancho"
              :alto="ventana.alto"
              :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
              :filas="ventana.filas"
              :columnas="ventana.columnas"
              :altos-filas="ventana.altosFilas"
              :anchos-columnas="ventana.anchosColumnas"
              :secciones="ventana.secciones"
            />
            <VentanaCorredera
              v-else-if="ventana.tipo === 3"
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
            <VistaVentanaCorrederaAndes
              v-else-if="ventana.tipo === 46"
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
              :ancho="ventana.ancho"
              :alto="ventana.alto"
              :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
              :lado-apertura="ventana.ladoApertura"
            />

            <BayWindow
              v-else-if="ventana.tipo === 47"
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
            <VentanaAbatir
              v-else-if="ventana.tipo === 49"
              :ancho="ventana.ancho"
              :alto="ventana.alto"
              :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
              :material="ventana.material"
              :tipoVidrio="ventana.tipoVidrio"
              :productoVidrioProveedor="ventana.productoVidrioProveedor"
              :lado-inicial="ventana.ladoApertura || 'izquierda'"
              v-model:direccionApertura="ventana.direccionApertura"
            />
            <PuertaS60
              v-else-if="ventana.tipo === 50"
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
              :ancho="ventana.ancho"
              :alto="ventana.alto"
              :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
              :hoja-activa="ventana.hojaActiva"
              :direccion-apertura="ventana.direccionApertura"
              :paso-libre="ventana.pasoLibre"
            />
            <VistaVentanaCompuestaDinamica
              :key="`compuesta-${forceReRenderKey}`"
              v-else-if="ventana.tipo === 54"
              :ancho="ventana.ancho || 2000"
              :alto="ventana.alto || 2000"
              :color-marco="colores.find(c => c.id === ventana.color)?.nombre || 'blanco'"
              :orientacion="ventana.orientacionComp || 'horizontal'"
              :items="ventana.itemsComp || []"
              :forceReRender="forceReRenderKey"
              @agregar="handleAgregar"
              @agregar-borde="handleAgregarBorde"
              @editar-ventana="handleEditarVentana"
              @eliminar-ventana="handleEliminarVentana"
            />
            <ArmadorVentanasComplejas
              v-else-if="ventana.tipo === 58"
              :tipos-ventana="tiposVentanaBayKonva"
              :perfiles-marco="perfilesMarco"
              :perfiles-divisores="perfilesDivisores"
              :color-marco="(colores.find(c => c.id === ventana.color)?.nombre || 'blanco').toLowerCase()"
              @actualizar="(config) => { 
                console.log('📦 Configuración armador actualizada:', config)
                ventana.configuracionArmador = config
                recalcularCostos() 
              }"
              @cancelar="() => {}"
            />

          </v-col>
        </v-row>

        <v-divider class="my-4" />

        <!-- Mostrar costos y materiales -->
        <v-row>
          <v-col cols="12" md="4">
            <v-alert v-if="ventana.costo_total_unitario" type="info" variant="outlined">
              <strong>Costo unitario:</strong> ${{ ventana.costo_total_unitario }}
            </v-alert>
          </v-col>
          <v-col cols="12" md="4">
            <v-alert v-if="ventana.costo_total" type="info" variant="tonal">
              <strong>Costo total:</strong> ${{ ventana.costo_total }}
            </v-alert>
          </v-col>
          <v-col cols="12" md="4">
            <v-alert v-if="ventana.precio" type="success" variant="tonal">
              <strong>Precio de venta:</strong> ${{ ventana.precio }}
            </v-alert>
          </v-col>
        </v-row>

        <!-- Detalle de materiales usados -->
        <v-row v-if="ventana.materiales && ventana.materiales.length">
          <v-col cols="12">
            <v-card variant="outlined">
              <v-card-title class="d-flex align-center">
                <span>Detalle de materiales</span>
                <v-spacer />
                <v-btn 
                  color="success" 
                  variant="tonal" 
                  size="small"
                  @click="descargarMateriales"
                >
                  <v-icon left>mdi-download</v-icon>
                  Descargar Excel
                </v-btn>
              </v-card-title>
              <v-data-table
                :headers="[
                  { title: 'Material', key: 'nombre' },
                  { title: 'Proveedor', key: 'proveedor' },
                  { title: 'Cantidad', key: 'cantidad' },
                  { title: 'Unidad', key: 'unidad' },
                  { title: 'Costo unitario', key: 'costo_unitario' },
                  { title: 'Costo total', key: 'costo_total' }
                ]"
                :items="ventana.materiales"
                class="mt-2"
                dense
                :items-per-page="10"
                :items-per-page-options="[5, 10, 25, 50, { value: -1, title: 'Todos' }]"
              >
                <template #item.costo_unitario="{ item }">
                  ${{ item.costo_unitario }}
                </template>
                <template #item.costo_total="{ item }">
                  ${{ item.costo_total }}
                </template>
              </v-data-table>
            </v-card>
          </v-col>
        </v-row>

        <!-- Agrega esto cerca de donde muestras los otros costos -->
        <v-row v-if="ventana.tipo === 54">
          <v-col cols="12">
            <v-alert color="success" variant="tonal">
              <strong>Costo total compuesta:</strong> ${{ costoTotalCompuesta.toLocaleString() }}
              <div class="text-caption">
                Suma de todas las ventanas individuales con sus medidas específicas
              </div>
            </v-alert>
          </v-col>
        </v-row>

        <v-card-actions class="justify-end">
          <v-btn color="secondary" variant="text" @click="cerrarModal">
            Cancelar
          </v-btn>
          <v-btn color="primary" type="submit">
            {{ isEdit ? 'Guardar cambios' : 'Agregar ventana' }}
          </v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>

  <!-- Menú para elegir tipo de ventana a agregar -->
<v-dialog v-model="showAgregarMenu" max-width="400">
  <v-card>
    <v-card-title>Agregar ventana</v-card-title>
    <v-divider />
    <v-list>
      <v-list-item
        v-for="tipo in tiposVentanaBayKonva"
        :key="tipo.id"
        @click="agregarVentanaSimple(tipo.id)"
      >
        <v-list-item-title>{{ tipo.nombre }}</v-list-item-title>
      </v-list-item>
      <v-list-item @click="agregarVentanaCompuesta">
        <v-list-item-title>Compuesta (anidada)</v-list-item-title>
      </v-list-item>
    </v-list>
    <v-card-actions>
      <v-spacer />
      <v-btn text @click="showAgregarMenu = false">Cancelar</v-btn>
    </v-card-actions>
  </v-card>
</v-dialog>

<!-- Modal de edición de ventana individual -->
<v-dialog v-model="showEditarModal" max-width="600px">
  <v-card>
    <v-card-title>
      <span class="text-h5">Editar Ventana Individual</span>
    </v-card-title>
    
    <v-card-text>
      <v-container>
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="ventanaEditando.ancho"
              label="Ancho (mm)"
              type="number"
              variant="outlined"
            ></v-text-field>
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="ventanaEditando.alto"
              label="Alto (mm)"
              type="number"
              variant="outlined"
            ></v-text-field>
          </v-col>
        </v-row>
        
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="ventanaEditando.sizePercent"
              label="Porcentaje del espacio (%)"
              type="number"
              min="10"
              max="80"
              variant="outlined"
            ></v-text-field>
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="ventanaEditando.tipo"
              :items="tiposVentanaBayKonva"
              item-title="nombre"
              item-value="id"
              label="Tipo de ventana"
              variant="outlined"
            ></v-select>
          </v-col>
        </v-row>
        
        <!-- ✅ Configuración específica para Abatibles -->
        <v-row v-if="isAbatir(ventanaEditando.tipo)">
          <v-col cols="12" sm="6">
            <v-radio-group v-model="ventanaEditando.ladoApertura" row density="compact" label="Lado apertura">
              <v-radio label="Izquierda" value="izquierda" />
              <v-radio label="Derecha" value="derecha" />
            </v-radio-group>
          </v-col>
          <v-col cols="12" sm="6">
            <v-radio-group v-model="ventanaEditando.direccionApertura" row density="compact" label="Dirección apertura">
              <v-radio label="Interior" value="interior" />
              <v-radio label="Exterior" value="exterior" />
            </v-radio-group>
          </v-col>
        </v-row>
        
        <!-- ✅ Muestra información de costo -->
        <v-row>
          <v-col cols="12">
            <v-alert color="info" variant="tonal">
              <div class="text-h6">Costo estimado: ${{ costoVentanaIndividual.toLocaleString() }}</div>
              <div class="text-caption">
                ID Tipo: {{ ventanaEditando.tipo }} | 
                Área: {{ areaVentanaIndividual.toFixed(2) }} m² | 
                Tipo: {{ getTipoVentanaNombre(ventanaEditando.tipo) }}
              </div>
            </v-alert>
          </v-col>
        </v-row>
      </v-container>
    </v-card-text>
    
    <v-card-actions>
      <v-spacer></v-spacer>
      <v-btn color="blue-darken-1" variant="text" @click="showEditarModal = false">
        Cancelar
      </v-btn>
      <v-btn color="blue-darken-1" variant="text" @click="guardarCambiosVentana">
        Guardar
      </v-btn>
    </v-card-actions>
  </v-card>
</v-dialog>
</template>

<script setup>
import { ref, watch, computed, nextTick, onMounted } from 'vue'
import VentanaFijaAL42 from '@/components/VistaVentanaFijaAL42.vue'
import VentanaEditor from '@/components/VistaVentanaFijaS60.vue'
import VentanaCorredera from '@/components/VistaVentanaCorredera.vue'
import VentanaProyectante from '@/components/VistaVentanaProyectanteS60.vue'
import VentanaProyectanteAL42 from '@/components/VistaVentanaProyectanteAL42.vue'
import VentanaCorrederaAL25 from '@/components/VistaVentanaCorrederaAL25.vue'
import VistaVentanaCompuestaAL42 from '@/components/VistaVentanaCompuestaAL42.vue'
import VistaVentanaCorrederaAndes from '@/components/VistaVentanaCorrederaAndes.vue'
import BayWindow from '@/components/VistaBayWindow.vue'
import VentanaAbatir from '@/components/VistaVentanaAbatirS60.vue'
import PuertaS60 from '@/components/VistaPuertaS60.vue'
import VistaMamparaS60 from '@/components/VistaMamparaS60.vue'
import VentanaCorredera98 from '@/components/VistaVentanaCorredera98.vue'
import VistaVentanaMonorriel from '@/components/VistaVentanaMonorriel.vue'
import VistaVentanaCompuestaDinamica from '@/components/VistaVentanaCompuestaDinamica.vue'
import ArmadorVentanasComplejas from '@/components/ArmadorVentanasComplejas.vue'

import api from '@/axiosInstance'

const props = defineProps({
  mostrar: Boolean,
  materiales: Array,
  colores: Array,
  tiposVidrio: Array,
  tiposVentana: Array,
  productosVidrio: Array,
  ventana: Object,
  materialDefault: [String, Number],
  colorDefault: [String, Number],
  tipoVidrioDefault: [String, Number],
  productoVidrioDefault: [String, Number],

})

const emit = defineEmits(['update:mostrar', 'guardar'])

const localMostrar = ref(props.mostrar)
const datosListos = ref(false) // ⬅️ Flag para indicar que los datos están listos
watch(() => props.mostrar, val => { localMostrar.value = val })
watch(localMostrar, val => { emit('update:mostrar', val) })

const isEdit = computed(() => !!props.ventana)

// defaults para items
const baseItemComp = () => ({
  tipo: 2,
  sizePercent: null,
  ladoApertura: 'izquierda',
  direccionApertura: 'interior'
})

function ajustarCantidadItems() {
  const n = Math.min(Math.max(Number(ventana.value.cantidadComp || 2), 2), 8)
  ventana.value.cantidadComp = n
  
  // PROTECCIÓN: Si no existe el array, inicialízalo
  if (!ventana.value.itemsComp) {
    ventana.value.itemsComp = []
  }
  
  // Si hay menos items de los necesarios, agrega SOLO los faltantes
  if (ventana.value.itemsComp.length < n) {
    const faltantes = n - ventana.value.itemsComp.length
    for (let i = 0; i < faltantes; i++) {
      ventana.value.itemsComp.push(baseItemComp())
    }
  }
  // Si hay más items, recorta SOLO los sobrantes
  else if (ventana.value.itemsComp.length > n) {
    ventana.value.itemsComp.splice(n)
  }
  
  // Asegura que todos los items tengan tipo válido SIN recrear el array
  ventana.value.itemsComp.forEach(it => {
    if (!it.tipo) it.tipo = 2
  })
  
  console.log('ajustarCantidadItems ejecutado, items finales:', ventana.value.itemsComp)
}

const baseVentana = {
  tipo: null,
  ancho: null,
  alto: null,
  cantidad: 1,
  material: props.materialDefault ?? null,
  color: props.colorDefault ?? null,
  tipoVidrio: props.tipoVidrioDefault ?? null,
  productoVidrioProveedor: props.productoVidrioDefault ?? null,
  hojas_totales: 2,
  hojas_moviles: 2,
  hojaMovilSeleccionada: null, // Para AL25 con 1 hoja móvil: 1=izquierda, 2=derecha
  manillon: false, // Para Corredera AL25 (tipo 55): false=pestillo, true=manillón
  
  // Para Ventana Compuesta AL42 (tipo 57)
  filas: 1,
  columnas: 1,
  altosFilas: [],
  anchosColumnas: [],
  secciones: [[{ tipo: 1 }]], // Array 2D: secciones[fila][col] = { tipo: 1|56 }
  
  materiales: [],
  costo_total: 0,
  costo_total_unitario: 0,
  costo: 0,
  precio_unitario: 0,
  precio: 0,
  hoja1AlFrente: true,
  ladoApertura: 'izquierda',
  direccionApertura: 'interior',
  pasoLibre: false,
  hojaActiva: 'derecha',
  tipoVentanaIzquierda: {
    compuesta: false,
    // Defaults para simple
    ladoApertura: 'izquierda',
    direccionApertura: 'interior',
    partes: [
      { tipo: null, alto: null, ladoApertura: 'izquierda', direccionApertura: 'interior' },
      { tipo: null, alto: null, ladoApertura: 'izquierda', direccionApertura: 'interior' }
    ]
  },
  tipoVentanaDerecha: {
    compuesta: false,
    // Defaults para simple
    ladoApertura: 'izquierda',
    direccionApertura: 'interior',
    partes: [
      { tipo: null, alto: null, ladoApertura: 'izquierda', direccionApertura: 'interior' },
      { tipo: null, alto: null, ladoApertura: 'izquierda', direccionApertura: 'interior' }
    ]
  },
  tipoVentanaCentro: { tipo: null, hojas_totales: 2, hojas_moviles: 2, hojaMovilSeleccionada: null, hoja1AlFrente: true },
  ancho_izquierda: null,
  ancho_centro: null,
  ancho_derecha: null,
    orientacionComp: 'horizontal',
  cantidadComp: 2,
  itemsComp: [baseItemComp(), baseItemComp()],
  configuracionArmador: null, // Para ventana tipo 58 (Armador Universal)
}

const ventana = ref({ ...baseVentana, ...(props.ventana || {}) })

// Tipos de ventana disponibles para el armador
const tiposVentanaBayKonvaBase = [
  { id: 2, nombre: 'Fija' },
  { id: 45, nombre: 'Proyectante' },
  { id: 3, nombre: 'Corredera' },
  { id: 49, nombre: 'Abatible' },
  { id: 50, nombre: 'Puerta S60' },
]

// Filtrar tipos de ventana por material
const tiposVentanaBayKonva = computed(() => {
  const materialId = ventana.value.material ?? props.materialDefault
  // Filtrar usando los tipos completos de props.tiposVentana
  const tiposDisponibles = props.tiposVentana.filter(t => t.material_id === materialId)
  
  console.log('🔍 Filtrando tipos ventana:', {
    materialId,
    tiposVentanaTotal: props.tiposVentana.length,
    tiposDisponibles: tiposDisponibles.length,
    tiposDisponiblesIds: tiposDisponibles.map(t => ({ id: t.id, nombre: t.nombre })),
    tiposBase: tiposVentanaBayKonvaBase.length
  })
  
  // Retornar solo los IDs que están en tiposVentanaBayKonvaBase y coinciden con el material
  const resultado = tiposVentanaBayKonvaBase.filter(base => 
    tiposDisponibles.some(t => t.id === base.id)
  )
  
  console.log('✅ Tipos ventana filtrados:', resultado)
  
  // Si no hay coincidencias con la base, retornar todos los disponibles
  if (resultado.length === 0 && tiposDisponibles.length > 0) {
    console.log('⚠️ No hay coincidencias con base, usando todos los disponibles')
    return tiposDisponibles.map(t => ({ id: t.id, nombre: t.nombre }))
  }
  
  return resultado
})

const tiposVentanaCentro = [
  { id: 2, nombre: 'Fija' },
  { id: 3, nombre: 'Corredera Sliding' },
  { id: 45, nombre: 'Proyectante S60' },
  { id: 50, nombre: 'Puerta S60' },
]

// Perfiles para el armador
const perfilesMarco = ref([])
const perfilesDivisores = ref([])

// Cargar perfiles al montar
onMounted(async () => {
  try {
    // Cargar todos los productos y filtrar por categoría
    const resProductos = await api.get('/api/productos')
    const todosProductos = resProductos.data.data || []
    
    // TODO: Ajustar estos filtros según tu BD
    // Opción 1: Filtrar por tipo_producto_id
    // perfilesMarco.value = todosProductos.filter(p => p.tipo_producto_id === 1)
    // perfilesDivisores.value = todosProductos.filter(p => p.tipo_producto_id === 2)
    
    // Opción 2: Filtrar por nombre que contenga ciertas palabras
    perfilesMarco.value = todosProductos.filter(p => 
      p.nombre && (
        p.nombre.toLowerCase().includes('marco') ||
        p.nombre.toLowerCase().includes('jamba')
      )
    )
    
    perfilesDivisores.value = todosProductos.filter(p => 
      p.nombre && (
        p.nombre.toLowerCase().includes('divisor') ||
        p.nombre.toLowerCase().includes('travesaño') ||
        p.nombre.toLowerCase().includes('parteluz')
      )
    )
    
    console.log('Perfiles cargados:', {
      marcos: perfilesMarco.value.length,
      divisores: perfilesDivisores.value.length
    })
  } catch (error) {
    console.error('Error cargando perfiles:', error)
  }
})


watch(
  () => props.mostrar,
  (val) => {
    if (val && !props.ventana) {
      // Modo AGREGAR - valores por defecto
      ventana.value = {
        ...baseVentana,
        material: props.materialDefault ?? null,
        color: props.colorDefault ?? null,
        tipoVidrio: props.tipoVidrioDefault ?? null,
        productoVidrioProveedor: props.productoVidrioDefault ?? null,
        tipoVentanaIzquierda: {
          compuesta: false,
          ladoApertura: 'izquierda',
          direccionApertura: 'interior',
          partes: [
            { tipo: null, alto: null, hojas_totales: 2, hojas_moviles: 2, hojaMovilSeleccionada: 1, ladoApertura: 'izquierda', direccionApertura: 'interior' },
            { tipo: null, alto: null, hojas_totales: 2, hojas_moviles: 2, hojaMovilSeleccionada: 1, ladoApertura: 'izquierda', direccionApertura: 'interior' }
          ]
        },
        tipoVentanaDerecha: {
          compuesta: false,
          ladoApertura: 'izquierda',
          direccionApertura: 'interior',
          partes: [
            { tipo: null, alto: null, hojas_totales: 2, hojas_moviles: 2, hojaMovilSeleccionada: 1, ladoApertura: 'izquierda', direccionApertura: 'interior' },
            { tipo: null, alto: null, hojas_totales: 2, hojas_moviles: 2, hojaMovilSeleccionada: 1, ladoApertura: 'izquierda', direccionApertura: 'interior' }
          ]
        },
      }
    }
    
    if (val && props.ventana) {
      // Modo EDITAR - los datos YA vienen completos desde index.vue
      console.log('📥 MODAL - Recibiendo ventana completa:', props.ventana)
      console.log('📥 MODAL - tipoVidrio recibido:', props.ventana.tipoVidrio)
      console.log('📥 MODAL - material recibido:', props.ventana.material)
      
      // Simplemente asignar TODO - sin inferencia
      ventana.value = { 
        ...baseVentana, 
        ...props.ventana
      }
      
      console.log('✅ MODAL - Ventana asignada:', ventana.value)
    }
  },
  { immediate: true }
)

const productosVidrioFiltrados = computed(() => {
  console.log('🔍 MODAL - Calculando productosVidrioFiltrados')
  console.log('🔍 MODAL - ventana.value.tipoVidrio:', ventana.value.tipoVidrio)
  
  if (!ventana.value.tipoVidrio) {
    console.log('🔍 MODAL - No hay tipoVidrio, retornando []')
    return []
  }
  
  const tipoVidrioBuscado = parseInt(ventana.value.tipoVidrio)
  
  const filtrados = props.productosVidrio
    .filter(p => parseInt(p.tipo_producto_id) === tipoVidrioBuscado)
    .flatMap(p => {
      console.log('🔍 MODAL - Procesando producto:', p.nombre, 'tipo_producto_id:', p.tipo_producto_id)
      if (!p.colores_por_proveedor || !Array.isArray(p.colores_por_proveedor)) {
        console.warn('⚠️ MODAL - Producto sin colores_por_proveedor:', p.nombre)
        return []
      }
      const items = p.colores_por_proveedor.map(cpp => ({
        id: cpp.id,
        producto_id: p.id,
        proveedor_id: cpp.proveedor_id,
        nombre: `${p.nombre} (${cpp.proveedor?.nombre || 'Proveedor desconocido'})`
      }))
      console.log('   → Generó', items.length, 'items con IDs:', items.map(i => i.id))
      return items
    })
  
  console.log('✅ MODAL - productosVidrioFiltrados total:', filtrados.length)
  console.log('✅ MODAL - IDs disponibles:', filtrados.map(p => p.id))
  console.log('✅ MODAL - ¿Incluye ID 64?:', filtrados.some(p => parseInt(p.id) === 64))
  
  return filtrados
})

const tiposVentanaFiltrados = computed(() => {
  const materialId = ventana.value.material ?? props.materialDefault
  return props.tiposVentana.filter(t => t.material_id === materialId)
})

const formRef = ref(null)

const cerrarModal = () => {
  localMostrar.value = false
}

const margenVenta = 0.45

async function recalcularCostos() {
  // Para tipo 58, validar que tenga configuración del armador en lugar de ancho/alto
  const tienesDimensiones = ventana.value.tipo === 58 
    ? ventana.value.configuracionArmador?.ancho && ventana.value.configuracionArmador?.alto
    : ventana.value.ancho && ventana.value.alto
  
  if (
    ventana.value.tipo &&
    tienesDimensiones &&
    ventana.value.cantidad &&
    ventana.value.color &&
    ventana.value.productoVidrioProveedor
  ) {
    try {
      // Busca la relación producto-proveedor
      const relacion = props.productosVidrio
        .flatMap(p => p.colores_por_proveedor.map(cpp => ({
          id: cpp.id,
          producto_id: p.id,
          proveedor_id: cpp.proveedor_id
        })))
        .find(p => p.id === ventana.value.productoVidrioProveedor)

      if (!relacion) {
        console.error('❌ No se encontró la relación producto-proveedor para ID:', ventana.value.productoVidrioProveedor)
        return
      }

      const payload = {
        tipo_ventana_id: ventana.value.tipo,
        tipo: ventana.value.tipo,
        ancho: ventana.value.tipo === 58 && ventana.value.configuracionArmador 
          ? ventana.value.configuracionArmador.ancho 
          : ventana.value.ancho,
        alto: ventana.value.tipo === 58 && ventana.value.configuracionArmador 
          ? ventana.value.configuracionArmador.alto 
          : ventana.value.alto,
        cantidad: ventana.value.cantidad,
        color_id: ventana.value.color,
        color: ventana.value.color,
        producto_vidrio_proveedor_id: ventana.value.productoVidrioProveedor,
        producto_id: relacion.producto_id,
        proveedor_id: relacion.proveedor_id,
        productoVidrio: relacion.producto_id,
        proveedorVidrio: relacion.proveedor_id,
        tipoVidrio: ventana.value.tipoVidrio,
        manillon: ventana.value.tipo === 55 ? ventana.value.manillon : undefined, // ✅ Para Corredera AL25
        
        // ✅ Propiedades específicas por tipo
        hojas_totales: [3, 46, 52, 55].includes(ventana.value.tipo) ? ventana.value.hojas_totales : undefined,
        hojas_moviles: [3, 46, 52, 55].includes(ventana.value.tipo) ? ventana.value.hojas_moviles : undefined,
        hojaMovilSeleccionada: [3, 46, 52, 55].includes(ventana.value.tipo) ? ventana.value.hojaMovilSeleccionada : undefined,
        hoja1AlFrente: [3, 46, 52].includes(ventana.value.tipo) ? ventana.value.hoja1AlFrente : undefined,
        direccionApertura: ventana.value.direccionApertura,
        ladoApertura: ventana.value.ladoApertura,
        pasoLibre: [50, 51].includes(ventana.value.tipo) ? ventana.value.pasoLibre : undefined,
        hojaActiva: ventana.value.tipo === 51 ? ventana.value.hojaActiva : undefined,

        // ✅ Ventana Compuesta AL42 (tipo 57)
        ...(ventana.value.tipo === 57 && {
          filas: ventana.value.filas,
          columnas: ventana.value.columnas,
          altos_filas: ventana.value.altosFilas,
          anchos_columnas: ventana.value.anchosColumnas,
          secciones: ventana.value.secciones,
        }),

        // ✅ Ventana Universal - Armador (tipo 58)
        ...(ventana.value.tipo === 58 && ventana.value.configuracionArmador && {
          configuracionArmador: ventana.value.configuracionArmador,
        }),

        ...(ventana.value.tipo === 47 && {
          ancho_izquierda: ventana.value.ancho_izquierda,
          ancho_centro: ventana.value.ancho_centro,
          ancho_derecha: ventana.value.ancho_derecha,
          tipoVentanaIzquierda: ventana.value.tipoVentanaIzquierda,
          tipoVentanaCentro: ventana.value.tipoVentanaCentro,
          tipoVentanaDerecha: ventana.value.tipoVentanaDerecha,
        })

      }

      console.log('💰 Payload a calcular-materiales:', payload)
      console.log('📦 Tipo 58 - configuracionArmador:', ventana.value.configuracionArmador)
      
      const { data } = await api.post('/api/cotizador/calcular-materiales', payload)
      
      console.log('✅ Respuesta del backend:', data)
      
      ventana.value.costo_total_unitario = data.costo_unitario
      ventana.value.costo_total = data.costo_unitario * ventana.value.cantidad
      ventana.value.precio = Math.ceil(ventana.value.costo_total / (1 - margenVenta))
      ventana.value.materiales = data.materiales

      console.log('💵 Valores asignados:')
      console.log('   - costo_total_unitario:', ventana.value.costo_total_unitario)
      console.log('   - costo_total:', ventana.value.costo_total)
      console.log('   - precio:', ventana.value.precio)
      console.log('   - materiales:', ventana.value.materiales?.length || 0, 'items')
    } catch (e) {
      console.error('❌ Error en recalcularCostos:', e)
      ventana.value.costo_total_unitario = 0
      ventana.value.costo_total = 0
      ventana.value.precio = 0
      ventana.value.materiales = []
    }
  } else {
    ventana.value.costo_total_unitario = 0
    ventana.value.costo_total = 0
    ventana.value.precio = 0
    ventana.value.materiales = []
  }
}

const onGuardar = () => {
  console.log('💾 MODAL - Guardando ventana')
  console.log('💾 MODAL - ventana.value completo:', ventana.value)
  console.log('💾 MODAL - tipoVidrio antes de guardar:', ventana.value.tipoVidrio)
  console.log('💾 MODAL - productoVidrioProveedor antes de guardar:', ventana.value.productoVidrioProveedor)
  console.log('💾 MODAL - color antes de guardar:', ventana.value.color)
  
  // Validación básica
  if (!ventana.value.tipo || !ventana.value.ancho || !ventana.value.alto || !ventana.value.cantidad) {
    alert('Completa todos los campos obligatorios')
    return
  }
  
  const ventanaAGuardar = { ...ventana.value, index: props.ventana?.index }
  console.log('💾 MODAL - Enviando ventana al componente padre:', ventanaAGuardar)
  
  emit('guardar', ventanaAGuardar)
  cerrarModal()
}

watch(() => ventana.value.material, () => {
  ventana.value.tipo = null
})

watch(
  () => [
    ventana.value.tipo,
    ventana.value.ancho,
    ventana.value.alto,
    ventana.value.cantidad,
    ventana.value.material,
    ventana.value.color,
    ventana.value.tipoVidrio,
    ventana.value.productoVidrioProveedor,
    ventana.value.manillon, // ✅ Para Corredera AL25
    ventana.value.hojas_totales, // ✅ Para Corredera AL25 y otras
    ventana.value.hojas_moviles, // ✅ Para Corredera AL25 y otras  
    ventana.value.hojaMovilSeleccionada, // ✅ Para Corredera AL25 con 1 hoja móvil
    ventana.value.direccionApertura, // ✅ AGREGAR ESTA LÍNEA
    ventana.value.ladoApertura,      // ✅ OPCIONAL: También lado apertura
    ventana.value.pasoLibre,
    ventana.value.ancho_izquierda,
    ventana.value.ancho_centro,
    ventana.value.ancho_derecha,
    // ✅ AGREGAR configuraciones profundas de Bay Window
    ventana.value.tipoVentanaIzquierda?.compuesta,
    ventana.value.tipoVentanaIzquierda?.ladoApertura,
    ventana.value.tipoVentanaIzquierda?.direccionApertura,
    ventana.value.tipoVentanaIzquierda?.partes?.[0]?.tipo,
    ventana.value.tipoVentanaIzquierda?.partes?.[0]?.alto,
    ventana.value.tipoVentanaIzquierda?.partes?.[0]?.ladoApertura,
    ventana.value.tipoVentanaIzquierda?.partes?.[0]?.direccionApertura,
    ventana.value.tipoVentanaIzquierda?.partes?.[1]?.tipo,
    ventana.value.tipoVentanaIzquierda?.partes?.[1]?.alto,
    ventana.value.tipoVentanaIzquierda?.partes?.[1]?.ladoApertura,
    ventana.value.tipoVentanaIzquierda?.partes?.[1]?.direccionApertura,
    
    // Centro
    ventana.value.tipoVentanaCentro?.tipo,
    ventana.value.tipoVentanaCentro?.hojas_totales,
    ventana.value.tipoVentanaCentro?.hojas_moviles,
    ventana.value.tipoVentanaCentro?.hojaMovilSeleccionada,
    ventana.value.tipoVentanaCentro?.hoja1AlFrente,
    
    // Derecha
    ventana.value.tipoVentanaDerecha?.compuesta,
    ventana.value.tipoVentanaDerecha?.ladoApertura,
    ventana.value.tipoVentanaDerecha?.direccionApertura,
    ventana.value.tipoVentanaDerecha?.partes?.[0]?.tipo,
    ventana.value.tipoVentanaDerecha?.partes?.[0]?.alto,
    ventana.value.tipoVentanaDerecha?.partes?.[0]?.ladoApertura,
    ventana.value.tipoVentanaDerecha?.partes?.[0]?.direccionApertura,
    ventana.value.tipoVentanaDerecha?.partes?.[1]?.tipo,
    ventana.value.tipoVentanaDerecha?.partes?.[1]?.alto,
    ventana.value.tipoVentanaDerecha?.partes?.[1]?.ladoApertura,
    ventana.value.tipoVentanaDerecha?.partes?.[1]?.direccionApertura,
    
    // ✅ Ventana Compuesta AL42 (tipo 57)
    ventana.value.filas,
    ventana.value.columnas,
    JSON.stringify(ventana.value.altosFilas),
    JSON.stringify(ventana.value.anchosColumnas),
    JSON.stringify(ventana.value.secciones),
    
    // ✅ Ventana Universal - Armador (tipo 58)
    JSON.stringify(ventana.value.configuracionArmador),
  ],
  recalcularCostos,
  { immediate: true }
)

// watcher para productoVidrioProveedor
watch(() => ventana.value.productoVidrioProveedor, (nuevoId) => {
  const producto = props.productosVidrio
    .flatMap(p => p.colores_por_proveedor.map(cpp => ({ ...p, cpp })))
    .find(p => p.cpp.id === nuevoId)
  ventana.value.productoVidrio = producto ? producto.id : null
})

// Mostrar selectores solo si el tipo es "Abatir"
const isAbatir = (t) => {
  if (t === null || t === undefined) return false
  const n = Number(t)
  return (!Number.isNaN(n) && n === 49) || String(t).toLowerCase().includes('abat')
}

// Función para descargar materiales como CSV/Excel
const descargarMateriales = () => {
  if (!ventana.value.materiales || ventana.value.materiales.length === 0) {
    alert('No hay materiales para descargar')
    return
  }

  // Crear CSV
  const headers = ['Material', 'Proveedor', 'Cantidad', 'Unidad', 'Costo Unitario', 'Costo Total']
  const rows = ventana.value.materiales.map(m => [
    m.nombre || '',
    m.proveedor || '',
    m.cantidad || 0,
    m.unidad || '',
    m.costo_unitario || 0,
    m.costo_total || 0
  ])

  // Construir CSV
  let csvContent = headers.join(',') + '\n'
  rows.forEach(row => {
    csvContent += row.map(cell => {
      // Escapar comas y comillas
      const cellStr = String(cell).replace(/"/g, '""')
      return cellStr.includes(',') ? `"${cellStr}"` : cellStr
    }).join(',') + '\n'
  })

  // Crear blob y descargar
  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)
  
  const tipoVentana = props.tiposVentana.find(t => t.id === ventana.value.tipo)?.nombre || 'ventana'
  const filename = `materiales_${tipoVentana}_${new Date().toISOString().split('T')[0]}.csv`
  
  link.setAttribute('href', url)
  link.setAttribute('download', filename)
  link.style.visibility = 'hidden'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// ✅ almacena refs de componentes renderizados en el modal
const ventanaRefs = ref([])
const setVentanaRef = (idx, el) => {
  if (!ventanaRefs.value) ventanaRefs.value = []
  ventanaRefs.value[idx] = el || null
}

// si cambia la cantidad, limpia refs para evitar huecos
watch(() => ventana.value?.itemsComp?.length, () => {
  ventanaRefs.value = []
})

// Inicializa cuando el usuario seleccione tipo 54 (si entra desde otro tipo)
watch(() => ventana.value.tipo, (val) => {
  if (Number(val) === 54) {
    ventana.value.orientacionComp ??= 'horizontal'
    ventana.value.cantidadComp ??= 2
    // Siempre inicializa con tipo válido
    ventana.value.itemsComp ??= [baseItemComp(), baseItemComp()]
  }
  
  // Inicializa ventana compuesta AL42 (tipo 57)
  if (Number(val) === 57) {
    ventana.value.filas ??= 1
    ventana.value.columnas ??= 1
    actualizarGridSecciones()
  }
})

// Watcher para actualizar grid cuando cambien filas o columnas
watch(
  () => [ventana.value.filas, ventana.value.columnas],
  () => {
    if (ventana.value.tipo === 57) {
      actualizarGridSecciones()
    }
  }
)

// Watcher para actualizar dimensiones cuando cambien alto o ancho
// Solo recalcula proporcionalmente si todas las dimensiones son iguales (no personalizadas)
watch(
  () => [ventana.value.alto, ventana.value.ancho],
  ([nuevoAlto, nuevoAncho], [viejoAlto, viejoAncho]) => {
    if (ventana.value.tipo === 57 && nuevoAlto && nuevoAncho) {
      const filas = ventana.value.filas || 1
      const columnas = ventana.value.columnas || 1
      
      // Verificar si las dimensiones actuales son proporcionales (no personalizadas)
      const altosUniformes = ventana.value.altosFilas?.every((v, i, arr) => Math.abs(v - arr[0]) < 1)
      const anchosUniformes = ventana.value.anchosColumnas?.every((v, i, arr) => Math.abs(v - arr[0]) < 1)
      
      // Solo recalcular si son uniformes (el usuario no ha personalizado)
      if (nuevoAlto !== viejoAlto && altosUniformes && ventana.value.altosFilas) {
        ventana.value.altosFilas = Array(filas).fill(nuevoAlto / filas)
      }
      
      if (nuevoAncho !== viejoAncho && anchosUniformes && ventana.value.anchosColumnas) {
        ventana.value.anchosColumnas = Array(columnas).fill(nuevoAncho / columnas)
      }
    }
  }
)

// Función para actualizar el grid de secciones
function actualizarGridSecciones() {
  const filas = ventana.value.filas || 1
  const columnas = ventana.value.columnas || 1
  
  // Crear grid 2D con todas las secciones
  const nuevoGrid = []
  for (let f = 0; f < filas; f++) {
    const fila = []
    for (let c = 0; c < columnas; c++) {
      // Mantener configuración existente o crear nueva
      const seccionExistente = ventana.value.secciones?.[f]?.[c]
      fila.push(seccionExistente || { tipo: 1 }) // Default: Fija
    }
    nuevoGrid.push(fila)
  }
  
  ventana.value.secciones = nuevoGrid
  
  // Calcular tamaños proporcionales
  const altoTotal = ventana.value.alto || 1000
  const anchoTotal = ventana.value.ancho || 1000
  
  ventana.value.altosFilas = Array(filas).fill(altoTotal / filas)
  ventana.value.anchosColumnas = Array(columnas).fill(anchoTotal / columnas)
}

// (opcional) si no necesitas refs para el preview, elimina setVentanaRef/ventanaRefs

const showAgregarMenu = ref(false)
const agregarIdx = ref(null)

function handleAgregar(idx) {
  agregarIdx.value = idx
  showAgregarMenu.value = true
}

function handleAgregarBorde(posicion) {
  console.log('=== AGREGAR BORDE ===')
  console.log('Posición:', posicion)
  console.log('Orientación actual:', ventana.value.orientacionComp)
  console.log('Items ANTES:', JSON.stringify(ventana.value.itemsComp, null, 2))
  
  const nuevaOrientacion = ['arriba', 'abajo'].includes(posicion) ? 'vertical' : 'horizontal'
  console.log('Nueva orientación necesaria:', nuevaOrientacion)
  
  // ✅ Si la orientación CAMBIA, envuelve los items existentes
  if (ventana.value.orientacionComp !== nuevaOrientacion) {
    console.log('Orientación cambió, envolviendo items existentes...')
    
    // Envuelve los items actuales en una compuesta con la orientación vieja
    const itemsActuales = [...ventana.value.itemsComp]
    const compuestaExistente = {
      tipo: 'compuesta',
      orientacion: ventana.value.orientacionComp, // orientación vieja
      items: itemsActuales
    }
    
    // Reemplaza el array con la compuesta envuelta
    ventana.value.itemsComp = [compuestaExistente]
    
    // Cambia a la nueva orientación
    ventana.value.orientacionComp = nuevaOrientacion
    
    console.log('Items después de envolver:', JSON.stringify(ventana.value.itemsComp, null, 2))
  } else {
    console.log('Orientación NO cambió, no envolviendo')
  }
  
  // Define índice para insertar
  switch(posicion) {
    case 'arriba':
    case 'izquierda':
      agregarIdx.value = 0
      break
    case 'abajo':
    case 'derecha':
      agregarIdx.value = ventana.value.itemsComp.length
      break
  }
  
  console.log('Índice para insertar:', agregarIdx.value)
  console.log('Orientación final:', ventana.value.orientacionComp)
  console.log('=== FIN DEBUG ===')
  
  showAgregarMenu.value = true
}

// ✅ También fuerza re-render cuando se agregan ventanas:
function agregarVentanaSimple(tipoId) {
  const nuevoItem = baseItemComp()
  nuevoItem.tipo = tipoId || 2
  ventana.value.itemsComp.splice(agregarIdx.value, 0, nuevoItem)
  
  // Fuerza reactividad
  ventana.value.itemsComp = [...ventana.value.itemsComp]
  
  // ✅ AGREGA ESTO:
  forceReRenderKey.value++
  
  console.log('Items después de agregar:', ventana.value.itemsComp)
  showAgregarMenu.value = false
}

function agregarVentanaCompuesta() {
  const nuevoItem = {
    tipo: 'compuesta',
    orientacion: 'horizontal',
    items: [
      { tipo: 2, ladoApertura: 'izquierda', direccionApertura: 'interior' },
      { tipo: 2, ladoApertura: 'izquierda', direccionApertura: 'interior' }
    ]
  }
  ventana.value.itemsComp.splice(agregarIdx.value, 0, nuevoItem)
  
  // ✅ AGREGA ESTO:
  forceReRenderKey.value++
  
  showAgregarMenu.value = false
}

const ventanaEditando = ref(null)
const showEditarModal = ref(false)
const pathEditando = ref('')

function handleEditarVentana(path) {
  console.log('=== EDITAR VENTANA INDIVIDUAL ===')
  console.log('Path:', path)
  
  pathEditando.value = path
  const ventanaData = getVentanaByPath(path)
  
  if (ventanaData) {
    const dimensionesReales = calcularDimensionesRealPorPath(path)
    
    ventanaEditando.value = {
      tipo: ventanaData.tipo || 2,
      ancho: dimensionesReales.ancho,
      alto: dimensionesReales.alto,
      sizePercent: ventanaData.sizePercent || null,
      ladoApertura: ventanaData.ladoApertura || 'izquierda',
      direccionApertura: ventanaData.direccionApertura || 'interior',
      ...ventanaData
    }
    
    console.log('Dimensiones reales calculadas:', dimensionesReales)
    console.log('Datos de ventana a editar:', ventanaEditando.value)
    showEditarModal.value = true
  } else {
    console.error('No se encontró ventana en path:', path)
  }
}

function calcularDimensionesRealPorPath(path) {
  const indices = path.split('.').map(Number)
  
  let anchoActual = ventana.value.ancho || 2000
  let altoActual = ventana.value.alto || 2000
  let orientacionActual = ventana.value.orientacionComp || 'horizontal'
  let itemsActuales = ventana.value.itemsComp
  
  console.log('🔍 Calculando dimensiones para path:', path)
  console.log('Dimensiones iniciales:', { ancho: anchoActual, alto: altoActual, orientacion: orientacionActual })
  
  for (let nivel = 0; nivel < indices.length; nivel++) {
    const indice = indices[nivel]
    const item = itemsActuales[indice]
    
    if (!item) {
      console.error('Item no encontrado en nivel', nivel, 'índice', indice)
      break
    }
    
    console.log(`Nivel ${nivel}, índice ${indice}:`, item)
    
    const totalItems = itemsActuales.length
    const porcentajes = itemsActuales.map(it => Number(it.sizePercent) || 0)
    const sumaPorcentajes = porcentajes.reduce((a, b) => a + b, 0)
    
    let porcentajeItem
    if (sumaPorcentajes > 0) {
      porcentajeItem = porcentajes[indice] / sumaPorcentajes
    } else {
      porcentajeItem = 1 / totalItems
    }
    
    console.log(`Porcentaje del item en este nivel: ${porcentajeItem} (${(porcentajeItem * 100).toFixed(1)}%)`)
    
    if (orientacionActual === 'horizontal') {
      anchoActual = anchoActual * porcentajeItem
    } else {
      altoActual = altoActual * porcentajeItem
    }
    
    console.log(`Nuevas dimensiones: ${anchoActual} x ${altoActual}`)
    
    if (nivel === indices.length - 1) {
      break
    }
    
    if (item.tipo === 'compuesta') {
      orientacionActual = item.orientacion || 'horizontal'
      itemsActuales = item.items || []
      console.log(`Navegando a compuesta con orientación: ${orientacionActual}`)
    } else {
      console.error('Item no es compuesta pero no es el último nivel')
      break
    }
  }
  
  const resultado = {
    ancho: Math.round(anchoActual),
    alto: Math.round(altoActual)
  }
  
  console.log('✅ Dimensiones finales calculadas:', resultado)
  return resultado
}

// ✅ Cálculos de costo para ventana individual
const areaVentanaIndividual = computed(() => {
  const ancho = Number(ventanaEditando.value.ancho) || 0
  const alto = Number(ventanaEditando.value.alto) || 0
  return (ancho * alto) / 1000000 // mm² a m²
})

const costoVentanaIndividual = computed(() => {
  const area = areaVentanaIndividual.value
  const tipo = ventanaEditando.value.tipo
  
  // ✅ Precios por m² según ID de tipo de ventana (REALES)
  const precios = {
    2: 150000,   // Fija
    3: 200000,   // Corredera
    45: 220000,  // Proyectante  
    49: 250000,  // Abatible
    50: 280000,  // Puerta S60
    // Agrega más tipos según tus IDs reales
  }
  
  return area * (precios[tipo] || 150000)
})

function getTipoVentanaNombre(tipo) {
  const ventanaTipo = tiposVentanaBayKonva.value.find(t => t.id === tipo)
  return ventanaTipo ? ventanaTipo.nombre : `Tipo ${tipo}`
}

// ✅ Función para calcular costo total de todas las ventanas
function calcularCostoTotalCompuesta() {
  function calcularCostoItem(item, anchoContainer, altoContainer) {
    if (item.tipo === 'compuesta') {
      // Si es compuesta, suma recursivamente
      return item.items?.reduce((sum, subItem) => {
        return sum + calcularCostoItem(subItem, anchoContainer, altoContainer)
      }, 0) || 0
    } else {
      // Si es ventana simple, calcula su costo
      const ancho = item.ancho || anchoContainer
      const alto = item.alto || altoContainer
      const area = (ancho * alto) / 1000000
      const precios = { 2: 150000, 3: 200000, 45: 220000, 49: 250000, 50: 280000 }
      return area * (precios[item.tipo] || 150000)
    }
  }
  
  return ventana.value.itemsComp?.reduce((total, item) => {
    return total + calcularCostoItem(item, ventana.value.ancho, ventana.value.alto)
  }, 0) || 0
}

// ✅ Computed para mostrar costo total
const costoTotalCompuesta = computed(() => calcularCostoTotalCompuesta())

// ✅ AGREGA ESTA FUNCIÓN al script (después de calcularDimensionesRealPorPath)

function getVentanaByPath(path) {
  const indices = path.split('.').map(Number)
  let current = ventana.value.itemsComp
  
  console.log('Navegando path:', path, 'indices:', indices)
  console.log('Items iniciales:', current)
  
  for (let i = 0; i < indices.length - 1; i++) {
    const index = indices[i]
    console.log(`Navegando índice ${i}: ${index}`)
    
    if (current[index]?.tipo === 'compuesta') {
      current = current[index].items
      console.log('Items en nivel', i + 1, ':', current)
    } else {
      console.error('No es compuesta en índice', index)
      return null
    }
  }
  
  const finalIndex = indices[indices.length - 1]
  console.log('Índice final:', finalIndex, 'Item encontrado:', current[finalIndex])
  return current[finalIndex]
}

function updateVentanaByPath(path, newData) {
  const indices = path.split('.').map(Number)
  let current = ventana.value.itemsComp
  
  for (let i = 0; i < indices.length - 1; i++) {
    const index = indices[i]
    if (current[index]?.tipo === 'compuesta') {
      current = current[index].items
    } else {
      console.error('Error: no es compuesta en índice', index)
      return false
    }
  }
  
  const finalIndex = indices[indices.length - 1]
  if (current[finalIndex]) {
    // ✅ IMPORTANTE: Actualiza TODAS las propiedades que cambiaron
    const itemActualizado = {
      ...current[finalIndex],
      tipo: newData.tipo,
      ancho: newData.ancho,           // ✅ Dimensión específica
      alto: newData.alto,             // ✅ Dimensión específica  
      sizePercent: newData.sizePercent,
      ladoApertura: newData.ladoApertura,
      direccionApertura: newData.direccionApertura
    }
    
    // ✅ REEMPLAZA el item completo (no solo assign)
    current[finalIndex] = itemActualizado
    
    console.log('Ventana actualizada:', current[finalIndex])
    
    // ✅ FUERZA REACTIVIDAD múltiple para asegurar re-render
    // Opción 1: Recrea el array completo
    ventana.value.itemsComp = JSON.parse(JSON.stringify(ventana.value.itemsComp))
    
    // ✅ Opción 2: Fuerza update del objeto padre
    ventana.value = { ...ventana.value }
    
    // ✅ Opción 3: Dispara evento de Vue para forzar update
    nextTick(() => {
      console.log('✅ Forzando re-render después de actualizar ventana')
    })
    
    return true
  }
  return false
}
const forceReRenderKey = ref(0)

// ✅ Y AGREGA ESTA FUNCIÓN TAMBIÉN (guardarCambiosVentana)
function guardarCambiosVentana() {
  console.log('=== GUARDANDO CAMBIOS ===')
  console.log('Datos a guardar:', ventanaEditando.value)
  console.log('Path:', pathEditando.value)
  
  const success = updateVentanaByPath(pathEditando.value, {
    tipo: ventanaEditando.value.tipo,
    ancho: ventanaEditando.value.ancho,
    alto: ventanaEditando.value.alto,
    sizePercent: ventanaEditando.value.sizePercent,
    ladoApertura: ventanaEditando.value.ladoApertura,
    direccionApertura: ventanaEditando.value.direccionApertura
  })
  
  if (success) {
    console.log('✅ Ventana actualizada correctamente')
    debugItemsCompuesta()
    
    // ✅ FUERZA RE-RENDER DEL COMPONENTE VISUAL
    forceReRenderKey.value++
    console.log('🔄 Forzando re-render con key:', forceReRenderKey.value)
    
    showEditarModal.value = false
  } else {
    console.error('❌ Error al actualizar ventana')
    alert('Error al actualizar la ventana')
  }
}

// ✅ AGREGA esta función para debug después de guardarCambiosVentana:

function debugItemsCompuesta() {
  console.log('=== DEBUG ITEMS COMPUESTA ===')
  console.log('Items actuales:', JSON.stringify(ventana.value.itemsComp, null, 2))
  console.log('Orientación:', ventana.value.orientacionComp)
  console.log('Dimensiones contenedor:', { ancho: ventana.value.ancho, alto: ventana.value.alto })
  console.log('=== FIN DEBUG ===')
}


// ✅ NUEVO: Función para eliminar ventana por path
function handleEliminarVentana(path) {
  console.log('=== ELIMINAR VENTANA ===')
  console.log('Path:', path)
  
  if (confirm('¿Estás seguro de eliminar esta ventana?')) {
    const success = eliminarVentanaByPath(path)
    
    if (success) {
      console.log('✅ Ventana eliminada correctamente')
      forceReRenderKey.value++
    } else {
      console.error('❌ Error al eliminar ventana')
      alert('Error al eliminar la ventana')
    }
  }
}

// ✅ NUEVO: Función para eliminar ventana por path
function eliminarVentanaByPath(path) {
  const indices = path.split('.').map(Number)
  let current = ventana.value.itemsComp
  let parent = null
  let parentIndex = null
  
  // Navega hasta el penúltimo nivel
  for (let i = 0; i < indices.length - 1; i++) {
    const index = indices[i]
    parent = current
    parentIndex = index
    
    if (current[index]?.tipo === 'compuesta') {
      current = current[index].items
    } else {
      console.error('Error: no es compuesta en índice', index)
      return false
    }
  }
  
  const finalIndex = indices[indices.length - 1]
  
  // Si estamos en el nivel raíz
  if (parent === null) {
    parent = { items: ventana.value.itemsComp }
    current = ventana.value.itemsComp
  }
  
  if (current[finalIndex]) {
    // Elimina el item
    current.splice(finalIndex, 1)
    
    console.log('Ventana eliminada. Items restantes:', current.length)
    
    // Si queda solo 1 item en una compuesta anidada, simplifica
    if (current.length === 1 && parent !== null && parentIndex !== null) {
      const itemRestante = current[0]
      if (parent.items) {
        parent.items[parentIndex] = itemRestante
      } else {
        parent[parentIndex] = itemRestante
      }
      console.log('Compuesta simplificada porque quedó solo 1 item')
    }
    
    // Si no quedan items en el nivel raíz, inicializa con defaults
    if (ventana.value.itemsComp.length === 0) {
      ventana.value.itemsComp = [baseItemComp(), baseItemComp()]
      console.log('Sin items restantes, reiniciando con defaults')
    }
    
    // Fuerza reactividad
    ventana.value.itemsComp = [...ventana.value.itemsComp]
    
    return true
  }
  return false
}

// ✅ NUEVO: Función para reiniciar toda la ventana compuesta
function reiniciarVentanaCompuesta() {
  if (confirm('¿Estás seguro de reiniciar toda la configuración? Se perderán todos los cambios.')) {
    ventana.value.orientacionComp = 'horizontal'
    ventana.value.cantidadComp = 2
    ventana.value.itemsComp = [baseItemComp(), baseItemComp()]
    
    forceReRenderKey.value++
    console.log('🔄 Ventana compuesta reiniciada')
  }
}
</script>

