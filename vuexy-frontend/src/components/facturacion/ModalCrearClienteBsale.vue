<template>
  <v-dialog v-model="dialog" max-width="600px" persistent>
    <v-card>
      <v-card-title class="text-h5 pa-4">
        <v-icon class="mr-2" color="primary">mdi-account-plus</v-icon>
        Crear Cliente en BSALE
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text class="pa-4">
        <v-form ref="form" v-model="formValid">
          <v-row>
            <!-- Nombre -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="formulario.nombre"
                label="Nombre"
                :rules="[v => !!v || 'Nombre es requerido']"
                required
                outlined
                prepend-inner-icon="mdi-account"
              ></v-text-field>
            </v-col>

            <!-- Apellido -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="formulario.apellido"
                label="Apellido"
                outlined
                prepend-inner-icon="mdi-account"
              ></v-text-field>
            </v-col>

            <!-- RUT -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="formulario.rut"
                label="RUT"
                :rules="[v => !!v || 'RUT es requerido']"
                required
                outlined
                prepend-inner-icon="mdi-card-account-details"
                placeholder="12345678-9"
              ></v-text-field>
            </v-col>

            <!-- Email -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="formulario.email"
                label="Email"
                type="email"
                :rules="emailRules"
                outlined
                prepend-inner-icon="mdi-email"
              ></v-text-field>
            </v-col>

            <!-- Teléfono -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="formulario.telefono"
                label="Teléfono"
                outlined
                prepend-inner-icon="mdi-phone"
                placeholder="+56 9 1234 5678"
              ></v-text-field>
            </v-col>

            <!-- Empresa -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="formulario.empresa"
                label="Empresa"
                outlined
                prepend-inner-icon="mdi-office-building"
              ></v-text-field>
            </v-col>

            <!-- Giro/Actividad -->
            <v-col cols="12">
              <v-text-field
                v-model="formulario.giro"
                label="Giro/Actividad"
                :rules="[v => !!v || 'Giro es requerido']"
                required
                outlined
                prepend-inner-icon="mdi-briefcase"
                placeholder="Ej: Comercio al por menor"
              ></v-text-field>
            </v-col>

            <!-- Dirección -->
            <v-col cols="12">
              <v-text-field
                v-model="formulario.direccion"
                label="Dirección"
                outlined
                prepend-inner-icon="mdi-map-marker"
                placeholder="Ej: Av. Principal 123"
              ></v-text-field>
            </v-col>

            <!-- Comuna -->
            <v-col cols="12" md="6">
              <v-select
                v-model="formulario.comuna_id"
                :items="comunas"
                item-value="id"
                item-title="name"
                label="Comuna"
                :rules="[v => !!v || 'Comuna es requerida']"
                required
                outlined
                prepend-inner-icon="mdi-city"
              ></v-select>
            </v-col>

            <!-- Enviar email de bienvenida -->
            <v-col cols="12">
              <v-checkbox
                v-model="formulario.enviar_email"
                label="Enviar email de bienvenida al cliente"
                color="primary"
              ></v-checkbox>
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
          :disabled="creandoCliente"
        >
          Cancelar
        </v-btn>
        <v-btn
          color="primary"
          @click="crearCliente"
          :loading="creandoCliente"
          :disabled="!formValid"
        >
          <v-icon left>mdi-account-plus</v-icon>
          Crear Cliente
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
import api from '@/axiosInstance'

export default {
  name: 'ModalCrearClienteBsale',
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
  emits: ['update:modelValue', 'cliente-creado'],
  data() {
    return {
      formValid: false,
      creandoCliente: false,
      comunas: [
        { id: 1, name: 'Santiago' },
        { id: 2, name: 'Las Condes' },
        { id: 3, name: 'Providencia' },
        { id: 4, name: 'Ñuñoa' },
        { id: 5, name: 'La Reina' },
        { id: 6, name: 'Vitacura' },
        { id: 7, name: 'Lo Barnechea' },
        { id: 8, name: 'Maipú' },
        { id: 9, name: 'Puente Alto' },
        { id: 10, name: 'La Florida' }
      ],
      formulario: {
        nombre: '',
        apellido: '',
        rut: '',
        email: '',
        telefono: '',
        empresa: '',
        giro: '',
        direccion: '',
        comuna_id: 1,
        enviar_email: false
      },
      emailRules: [
        v => !v || /.+@.+\..+/.test(v) || 'Email debe ser válido'
      ]
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
      if (newVal && this.cotizacion) {
        this.precargarDatos()
      }
    }
  },
  methods: {
    precargarDatos() {
      if (this.cotizacion?.cliente) {
        const cliente = this.cotizacion.cliente
        this.formulario.nombre = cliente.nombre || ''
        this.formulario.rut = cliente.rut || ''
        this.formulario.email = cliente.email || ''
        this.formulario.telefono = cliente.telefono || ''
        this.formulario.direccion = cliente.direccion || ''
        this.formulario.giro = 'Comercio al por menor' // Valor por defecto
      }
    },

    async crearCliente() {
      if (!this.formValid) return

      this.creandoCliente = true
      try {
        const payload = {
          nombre: this.formulario.nombre,
          apellido: this.formulario.apellido,
          rut: this.formulario.rut,
          email: this.formulario.email,
          telefono: this.formulario.telefono,
          empresa: this.formulario.empresa,
          giro: this.formulario.giro,
          direccion: this.formulario.direccion,
          comuna_id: this.formulario.comuna_id,
          enviar_email: this.formulario.enviar_email
        }

        const response = await api.post('/api/bsale/clientes', payload)

        if (response.data.success) {
          this.$emit('cliente-creado', response.data.cliente)
          this.cerrar()
        } else {
          throw new Error(response.data.error || 'Error desconocido')
        }

      } catch (error) {
        console.error('Error creando cliente:', error)
        const mensaje = error.response?.data?.error || error.message || 'Error al crear cliente'
        this.$toast.error(mensaje)
      } finally {
        this.creandoCliente = false
      }
    },

    cerrar() {
      this.dialog = false
      this.resetearFormulario()
    },

    resetearFormulario() {
      this.formulario = {
        nombre: '',
        apellido: '',
        rut: '',
        email: '',
        telefono: '',
        empresa: '',
        giro: '',
        direccion: '',
        comuna_id: 1,
        enviar_email: false
      }
      this.$refs.form?.resetValidation()
    }
  }
}
</script>

<style scoped>
.v-card {
  overflow-y: auto;
}
</style>
