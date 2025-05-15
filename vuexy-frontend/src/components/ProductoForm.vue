<!-- src/pages/productos/ProductoForm.vue -->
<template>
    <v-card>
      <v-card-title>
        {{ modoEdicion ? 'Editar Producto' : 'Agregar Producto' }}
      </v-card-title>
  
      <v-card-text>
        <v-form @submit.prevent="handleSubmit">
          <v-text-field v-model="productoActual.nombre" label="Nombre del Producto" required></v-text-field>
  
          <v-select
            v-model="productoActual.tipo"
            :items="['perfil', 'vidrio', 'herraje', 'accesorio']"
            label="Tipo"
          ></v-select>
  
          <v-select
            v-model="productoActual.unidad_id"
            :items="unidades"
            item-title="nombre"
            item-value="id"
            label="Unidad de medida"
          ></v-select>
  
          <v-text-field
            v-model="productoActual.largo_total"
            label="Largo total (en metros)"
            type="number"
            step="0.01"
          ></v-text-field>
  
          <v-text-field
            v-model="productoActual.peso_por_metro"
            label="Peso por metro (en kg)"
            type="number"
            step="0.01"
          ></v-text-field>
  
          <v-divider class="my-4"></v-divider>
  
          <h3 class="text-lg font-medium mb-2">Combinaciones proveedor + color + costo</h3>
  
          <div v-for="(combo, index) in combinacionesProveedorColor" :key="index" class="d-flex gap-2 mb-2">
            <v-select
              v-model="combo.proveedor_id"
              :items="proveedores"
              item-title="nombre"
              item-value="id"
              label="Proveedor"
            ></v-select>
  
            <v-select
              v-model="combo.color_id"
              :items="colores"
              item-title="nombre"
              item-value="id"
              label="Color"
            ></v-select>
  
            <v-text-field
              v-model="combo.costo"
              type="number"
              step="0.01"
              label="Costo"
            ></v-text-field>
  
            <v-btn icon color="red" @click="eliminarCombinacion(index)">
              <v-icon>mdi-delete</v-icon>
            </v-btn>
          </div>
  
          <v-btn color="blue" variant="text" @click="agregarCombinacion">+ Agregar combinación</v-btn>
  
          <v-divider class="my-4"></v-divider>
  
          <v-btn color="primary" type="submit" class="me-2">
            {{ modoEdicion ? 'Actualizar' : 'Agregar' }}
          </v-btn>
          <v-btn color="secondary" @click="cancelarEdicion" v-if="modoEdicion">Cancelar</v-btn>
        </v-form>
      </v-card-text>
    </v-card>
  </template>
  
  <script setup>
  defineProps([
    'productoActual',
    'modoEdicion',
    'proveedores',
    'colores',
    'unidades',
    'combinacionesProveedorColor'
  ])
  
  defineEmits([
    'submit',
    'cancelar-edicion',
    'agregar-combinacion',
    'eliminar-combinacion'
  ])
  
  const handleSubmit = () => emit('submit')
  const cancelarEdicion = () => emit('cancelar-edicion')
  const agregarCombinacion = () => emit('agregar-combinacion')
  const eliminarCombinacion = index => emit('eliminar-combinacion', index)
  </script>
  