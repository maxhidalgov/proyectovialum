<template>
  <v-dialog v-model="dialog" max-width="800px" persistent>
    <v-card>
      <v-card-title class="text-h5 pa-4">
        <v-icon class="mr-2" color="success">mdi-receipt</v-icon>
        Generar Documento Electrónico
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text class="pa-4">
        <!-- Información de la cotización -->
        <v-row class="mb-4">
          <v-col cols="12">
            <v-card outlined class="pa-3">
              <h4 class="mb-2">Cotización #{{ cotizacion?.numero || 'N/A' }}</h4>
              <p class="mb-1">
                <strong>Cliente:</strong> {{ cotizacion?.cliente?.nombre || 'N/A' }}
              </p>
              <p class="mb-1">
                <strong>Total:</strong> ${{ formatearPrecio(cotizacion?.total || 0) }}
              </p>
              <p class="mb-0">
                <strong>Fecha:</strong> {{ formatearFecha(cotizacion?.created_at) }}
              </p>
            </v-card>
          </v-col>
        </v-row>

        <!-- Formulario -->
        <v-form ref="form" v-model="formValid">
          <v-row>
            <!-- Tipo de documento -->
            <v-col cols="12" md="6">
              <v-select
                v-model="formulario.tipo_documento"
                :items="tiposDocumento"
                item-value="id"
                item-title="name"
                label="Tipo de documento"
                :rules="[v => !!v || 'Tipo de documento es requerido']"
                required
                outlined
                prepend-inner-icon="mdi-file-document"
              ></v-select>
            </v-col>

            <!-- Oficina -->
            <v-col cols="12" md="6">
              <v-select
                v-model="formulario.oficina_id"
                :items="oficinas"
                item-value="id"
                item-title="name"
                label="Oficina"
                :rules="[v => !!v || 'Oficina es requerida']"
                required
                outlined
                prepend-inner-icon="mdi-office-building"
              ></v-select>
            </v-col>

            <!-- Cliente BSALE -->
            <v-col cols="12">
              <v-autocomplete
                v-model="formulario.cliente_bsale_id"
                :items="clientesBsale"
                item-value="id"
                item-title="displayName"
                label="Cliente en BSALE"
                :search-input.sync="busquedaCliente"
                :loading="cargandoClientes"
                :rules="[v => !!v || 'Cliente BSALE es requerido']"
                required
                outlined
                prepend-inner-icon="mdi-account"
                clearable
                @update:search="buscarClientes"
              >
                <template v-slot:no-data>
                  <v-list-item>
                    <v-list-item-content>
                      <v-list-item-title>
                        No se encontraron clientes
                      </v-list-item-title>
                      <v-list-item-subtitle>
                        <v-btn
                          color="primary"
                          text
                          small
                          @click="abrirCrearCliente"
                        >
                          Crear cliente nuevo
                        </v-btn>
                      </v-list-item-subtitle>
                    </v-list-item-content>
                  </v-list-item>
                </template>
              </v-autocomplete>
            </v-col>

            <!-- Método de pago -->
            <v-col cols="12" md="6">
              <v-select
                v-model="formulario.metodo_pago"
                :items="metodosPago"
                item-title="text"
                item-value="value"
                label="Método de pago"
                outlined
                prepend-inner-icon="mdi-credit-card"
              ></v-select>
            </v-col>

            <!-- Condiciones de pago -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="formulario.condiciones_pago"
                label="Condiciones de pago"
                outlined
                prepend-inner-icon="mdi-calendar-clock"
                placeholder="Ej: 30 días"
              ></v-text-field>
            </v-col>

            <!-- Fecha de vencimiento -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="formulario.fecha_vencimiento"
                label="Fecha de vencimiento"
                type="date"
                outlined
                prepend-inner-icon="mdi-calendar"
              ></v-text-field>
            </v-col>

            <!-- Observaciones -->
            <v-col cols="12">
              <v-textarea
                v-model="formulario.observaciones"
                label="Observaciones"
                outlined
                rows="3"
                prepend-inner-icon="mdi-note-text"
                placeholder="Observaciones adicionales para el documento..."
              ></v-textarea>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="pa-4">
        <v-spacer></v-spacer>
        <v-btn
          color="grey"
          text
          @click="cerrar"
          :disabled="generandoDocumento"
        >
          Cancelar
        </v-btn>
        <v-btn
          color="success"
          @click="generarDocumento"
          :loading="generandoDocumento"
          :disabled="!formValid"
        >
          <v-icon left>mdi-receipt</v-icon>
          Generar Documento
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Modal para crear cliente -->
    <ModalCrearClienteBsale
      v-model="dialogCrearCliente"
      :cotizacion="cotizacion"
      @cliente-creado="onClienteCreado"
    />
  </v-dialog>
</template>

<script>
import api from '@/axiosInstance'
import ModalCrearClienteBsale from './ModalCrearClienteBsale.vue'

export default {
  name: 'ModalBsale',
  components: {
    ModalCrearClienteBsale
  },
  props: {
    modelValue: {
      type: Boolean,
      default: false
    },
    cotizacion: {
      type: Object,
      default: null
    }
  },
  emits: ['update:modelValue', 'documento-generado'],
  data() {
    return {
      formValid: false,
      generandoDocumento: false,
      cargandoClientes: false,
      busquedaCliente: '',
      dialogCrearCliente: false,
      tiposDocumento: [
        { id: 3, name: 'Nota de Venta', codeSii: '', description: 'Documento de preventa' },
        { id: 5, name: 'Factura Electrónica', codeSii: '33', description: 'Documento tributario electrónico' },
        { id: 1, name: 'Boleta Electrónica', codeSii: '39', description: 'Boleta electrónica para consumidor final' }
      ],
      oficinas: [],
      clientesBsale: [],
      metodosPago: [
        { text: 'Efectivo', value: 'efectivo' },
        { text: 'Transferencia', value: 'transferencia' },
        { text: 'Cheque', value: 'cheque' },
        { text: 'Tarjeta de Crédito', value: 'tarjeta_credito' },
        { text: 'Tarjeta de Débito', value: 'tarjeta_debito' }
      ],
      formulario: {
        tipo_documento: null,
        oficina_id: null,
        cliente_bsale_id: null,
        metodo_pago: 'transferencia',
        condiciones_pago: '30 días',
        fecha_vencimiento: this.getFechaVencimiento(),
        observaciones: ''
      }
    }
  },
  computed: {
    dialog: {
      get() {
        return this.modelValue
      },
      set(value) {
        this.$emit('update:modelValue', value)
      }
    }
  },
  watch: {
    dialog(newVal) {
      if (newVal) {
        this.cargarDatosIniciales()
      }
    }
  },
  methods: {
    async cargarDatosIniciales() {
      console.log('🔄 Cargando datos iniciales BSALE...')
      
      try {
        // Cargar oficinas desde Bsale
        await this.cargarOficinas()
        
        // Cargar tipos de documento desde Bsale
        await this.cargarTiposDocumento()
        
        // Buscar cliente del cliente de la cotización
        if (this.cotizacion?.cliente?.rut || this.cotizacion?.cliente?.identification) {
          await this.buscarClientesPorRut(this.cotizacion.cliente.rut || this.cotizacion.cliente.identification)
        }

        console.log('✅ Datos iniciales cargados correctamente')

      } catch (error) {
        console.error('❌ Error cargando datos iniciales:', error)
        console.error('Response:', error.response?.data)
        console.error('Status:', error.response?.status)
        console.error('URL:', error.config?.url)
        this.$toast?.error('Error al cargar datos de BSALE')
      }
    },

    async cargarOficinas() {
      try {
        const response = await api.get('/api/bsale-oficinas')
        this.oficinas = response.data.items.map(oficina => ({
          id: oficina.id,
          name: oficina.name,
          description: oficina.description,
          address: oficina.address
        }))
        console.log('✅ Oficinas cargadas:', this.oficinas.length)
      } catch (error) {
        console.error('❌ Error cargando oficinas:', error)
        // Fallback con datos básicos
        this.oficinas = [
          { id: 1, name: 'Oficina Principal' }
        ]
      }
    },

    async cargarTiposDocumento() {
      try {
        const response = await api.get('/api/bsale-tipos-documento')
        this.tiposDocumento = response.data.items.map(tipo => ({
          id: tipo.id,
          name: tipo.name,
          codeSii: tipo.codeSii,
          description: tipo.description
        }))
        console.log('✅ Tipos de documento cargados:', this.tiposDocumento.length)
      } catch (error) {
        console.error('❌ Error cargando tipos de documento:', error)
        // Mantener los tipos estáticos como fallback
        console.log('Usando tipos de documento estáticos como fallback')
      }
    },

    async buscarClientes(busqueda) {
      if (!busqueda || busqueda.length < 2) return
      
      this.cargandoClientes = true
      try {
        const response = await api.get('/api/bsale/clientes', {
          params: { search: busqueda }
        })
        
        if (response.data.success) {
          this.clientesBsale = response.data.clientes.items?.map(cliente => ({
            ...cliente,
            displayName: `${cliente.company || `${cliente.firstName || ''} ${cliente.lastName || ''}`.trim() || 'Sin nombre'} - ${cliente.code}`
          })) || []
        }
      } catch (error) {
        console.error('Error buscando clientes:', error)
      } finally {
        this.cargandoClientes = false
      }
    },

    async buscarClientesPorRut(rut) {
      this.cargandoClientes = true
      try {
        const response = await api.get('/api/bsale/clientes', {
          params: { search: rut }
        })
        
        if (response.data.success) {
          const clientes = response.data.clientes.items || []
          this.clientesBsale = clientes.map(cliente => ({
            ...cliente,
            displayName: `${cliente.company || `${cliente.firstName || ''} ${cliente.lastName || ''}`.trim() || 'Sin nombre'} - ${cliente.code}`
          }))

          // Si encontramos el cliente, seleccionarlo automáticamente
          const clienteEncontrado = clientes.find(c => c.code === rut)
          if (clienteEncontrado) {
            this.formulario.cliente_bsale_id = clienteEncontrado.id
          }
        }
      } catch (error) {
        console.error('Error buscando cliente por RUT:', error)
      } finally {
        this.cargandoClientes = false
      }
    },

    abrirCrearCliente() {
      this.dialogCrearCliente = true
    },

    onClienteCreado(nuevoCliente) {
      // Agregar el nuevo cliente a la lista
      this.clientesBsale.unshift({
        ...nuevoCliente,
        displayName: `${nuevoCliente.company || `${nuevoCliente.firstName || ''} ${nuevoCliente.lastName || ''}`.trim() || 'Sin nombre'} - ${nuevoCliente.code}`
      })
      
      // Seleccionarlo automáticamente
      this.formulario.cliente_bsale_id = nuevoCliente.id
      
      this.$toast.success('Cliente creado exitosamente')
    },

    async generarDocumento() {
      if (!this.formValid) return

      this.generandoDocumento = true
      
      try {
        const payload = {
          cotizacion_id: this.cotizacion.id,
          tipo_documento: this.formulario.tipo_documento,
          cliente_bsale_id: this.formulario.cliente_bsale_id,
          metodo_pago: this.formulario.metodo_pago,
          condiciones_pago: this.formulario.condiciones_pago,
          fecha_vencimiento: this.formulario.fecha_vencimiento,
          observaciones: this.formulario.observaciones
        }

        const response = await api.post('/api/bsale/documento', payload)

        if (response.data && response.data.success) {
          const documento = response.data.documento  // Cambiado de response.data.data
          const tipoDoc = this.tiposDocumento.find(t => t.id === this.formulario.tipo_documento)
          
          this.$toast.success(
            `${tipoDoc?.name || 'Documento'} #${documento.number} generado exitosamente\n` +
            `Total: $${documento.totalAmount?.toLocaleString('es-CL')}\n` +
            `ID BSALE: ${documento.id}`,
            { timeout: 8000 }
          )
          
          // Si hay URL del PDF, mostrar opción para descargar
          if (documento.urlPdf) {
            this.$toast.info(`PDF disponible: ${documento.urlPdf}`, {
              timeout: 10000,
              action: {
                text: 'Abrir PDF',
                onClick: () => window.open(documento.urlPdf, '_blank')
              }
            })
          }
          
          this.$emit('documento-generado', documento)
          this.cerrar()
        } else {
          throw new Error(response.data?.error || response.data?.message || 'Error desconocido')
        }

      } catch (error) {
        console.error('Error generando documento:', error)
        const mensaje = error.response?.data?.error || error.response?.data?.message || error.message || 'Error al generar documento'
        this.$toast.error(mensaje)
      } finally {
        this.generandoDocumento = false
      }
    },

    cerrar() {
      this.dialog = false
      this.resetearFormulario()
    },

    resetearFormulario() {
      this.formulario = {
        tipo_documento: null,
        oficina_id: null,
        cliente_bsale_id: null,
        metodo_pago: 'transferencia',
        condiciones_pago: '30 días',
        fecha_vencimiento: this.getFechaVencimiento(),
        observaciones: ''
      }
      this.$refs.form?.resetValidation()
    },

    getFechaVencimiento() {
      const fecha = new Date()
      fecha.setDate(fecha.getDate() + 30) // 30 días a partir de hoy
      return fecha.toISOString().split('T')[0]
    },

    formatearPrecio(precio) {
      return new Intl.NumberFormat('es-CL').format(precio)
    },

    formatearFecha(fecha) {
      if (!fecha) return 'N/A'
      return new Date(fecha).toLocaleDateString('es-CL')
    }
  }
}
</script>

<style scoped>
.v-card {
  overflow-y: auto;
}
</style>
