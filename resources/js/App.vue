<template>
    <div class="max-w-4xl mx-auto p-6">
        <h1 class="text-3xl font-bold text-center mb-6 text-gray-800">Gestión de Productos</h1>

        <!-- Barra de búsqueda -->
        <input 
            v-model="filtro" 
            type="text" 
            placeholder="Buscar productos..." 
            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400 mb-4"
        />

        <!-- Formulario para agregar o editar producto -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h4 class="text-xl font-semibold text-center mb-4">
                {{ modoEdicion ? 'Editar Producto' : 'Agregar Producto' }}
            </h4>
            <form @submit.prevent="handleSubmit()" class="space-y-4">

                <!-- Nombre -->
                <div>
                    <label class="block text-gray-700 font-medium">Nombre del Producto:</label>
                    <input v-model="productoActual.nombre" type="text"
                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400" required>
                </div>

                <!-- Tipo -->
                <div>
                    <label class="block text-gray-700 font-medium">Tipo:</label>
                    <select v-model="productoActual.tipo"
                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
                        <option value="perfil">Perfil</option>
                        <option value="vidrio">Vidrio</option>
                        <option value="herraje">Herraje</option>
                        <option value="accesorio">Accesorio</option>
                    </select>
                </div>

                <!-- Unidad de Medida -->
                <div>
                    <label class="block text-gray-700 font-medium">Unidad de medida:</label>
                    <select v-model="productoActual.unidad_id" class="w-full p-2 border rounded-lg">
                        <option disabled value="">Selecciona una unidad</option>
                        <option v-for="unidad in unidades" :key="unidad.id" :value="unidad.id">
                            {{ unidad.nombre }}
                        </option>
                    </select>
                </div>

                <!-- Largo Total -->
                <div>
                    <label class="block text-gray-700 font-medium">Largo total (en metros):</label>
                    <input v-model="productoActual.largo_total" type="number" step="0.01"
                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400" placeholder="Ej: 6.00">
                </div>

                <!-- Peso por Metro -->
                <div>
                    <label class="block text-gray-700 font-medium">Peso por metro (en kg):</label>
                    <input v-model="productoActual.peso_por_metro" type="number" step="0.01"
                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400" placeholder="Ej: 1.23">
                </div>

                <!-- Combinaciones Proveedor + Color + Costo -->
                <div class="mt-4">
                    <label class="block text-gray-700 font-medium mb-2">Combinaciones proveedor + color + costo:</label>
                    <div v-for="(combo, index) in combinacionesProveedorColor" :key="index" class="grid grid-cols-3 gap-4 mb-2">
                        <!-- Proveedor -->
                        <select v-model="combo.proveedor_id" class="p-2 border rounded-lg">
                            <option disabled value="">Selecciona proveedor</option>
                            <option v-for="proveedor in proveedores" :value="proveedor.id" :key="proveedor.id">
                                {{ proveedor.nombre }}
                            </option>
                        </select>

                        <!-- Color -->
                        <select v-model="combo.color_id" class="p-2 border rounded-lg">
                            <option disabled value="">Selecciona color</option>
                            <option v-for="color in colores" :value="color.id" :key="color.id">
                                {{ color.nombre }}
                            </option>
                        </select>

                        <!-- Costo + Botón eliminar -->
                        <div class="flex items-center gap-2">
                            <input v-model="combo.costo" type="number" step="0.01" class="p-2 border rounded-lg w-full" placeholder="Costo">
                            <button @click="eliminarCombinacion(index)" type="button" class="text-red-600 hover:underline">🗑</button>
                        </div>

                    </div>

                    <button type="button" @click="agregarCombinacion"
                        class="text-sm text-blue-600 hover:underline mt-2">+ Agregar combinación</button>
                </div>

                <!-- Botones -->
                <button type="submit"
                    class="w-full bg-blue-600 text-white p-2 rounded-lg hover:bg-blue-700 transition">
                    {{ modoEdicion ? 'Actualizar Producto' : 'Agregar Producto' }}
                </button>

                <button v-if="modoEdicion" @click="cancelarEdicion" type="button"
                    class="w-full mt-2 bg-gray-500 text-white p-2 rounded-lg hover:bg-gray-700 transition">
                    Cancelar
                </button>
            </form>
        </div>


        <!-- Lista de productos -->
        <div v-if="productosFiltrados.length" class="bg-white p-6 rounded-lg shadow-md">
            <h4 class="text-xl font-semibold text-center mb-4 text-gray-800">Lista de Productos</h4>
            <table class="w-full border-collapse border border-gray-300 rounded-lg">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="p-3">Nombre</th>
                        <th class="p-3">Tipo</th>
                        <th class="p-3">Unidad</th>
                        <th class="p-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="producto in productosFiltrados" :key="producto.id"
                        class="border-t border-gray-300 text-center hover:bg-gray-100 transition">
                        <td class="p-3">{{ producto.nombre }}</td>
                        <td class="p-3">{{ producto.tipo }}</td>
                        <td class="p-3">{{ producto.unidad?.nombre || 'N/A' }}</td>
                        <td class="p-3 flex justify-center space-x-2">
                            <button @click="editarProducto(producto)"
                                class="bg-yellow-500 text-white px-3 py-1 rounded-md hover:bg-yellow-700 transition">
                                Editar
                            </button>
                            <button @click="eliminarProducto(producto.id)"
                                class="bg-red-500 text-white px-3 py-1 rounded-md hover:bg-red-700 transition">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>


<script>
import axios from 'axios';
import Swal from 'sweetalert2';

export default {
    data() {
    return {
        productoActual: {
            nombre: '',
            tipo: 'perfil',
            unidad_id: '',
            largo_total: '',
            peso_por_metro: ''
        },
        proveedores: [],
        productos: [],
        filtro: '',
        unidades: [],
        colores: [], // 👈 Almacena los colores cargados desde la API
        combinacionesProveedorColor: [],
        coloresPorProveedor: [], // 👈 Asegúrate de que esta propiedad existe y es un array vacío
        modoEdicion: false,
        productoSeleccionado: null
    };
},

    computed: {
        productosFiltrados() {
            const termino = (this.filtro || '').toLowerCase().trim(); // ✅ Evita error si filtro es undefined

            if (!termino) return this.productos;

            return this.productos.filter(producto =>
                producto.nombre.toLowerCase().includes(termino) ||
                producto.tipo.toLowerCase().includes(termino)
            );
        }
    },
    methods: {
        mostrarNotificacion(mensaje, tipo = 'success') {
            Swal.fire({
                icon: tipo,
                title: mensaje,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
        },
        async cargarColores() {
            try {
                const response = await axios.get('/api/colores');
                this.colores = response.data;
            } catch (error) {
                console.error('Error al cargar colores:', error);
            }
        },

        agregarCombinacion() {
            this.combinacionesProveedorColor.push({
                proveedor_id: '',
                color_id: '',
                costo: ''
            });
        },

        async cargarProductos() {
            try {
                const response = await axios.get('/api/productos');
                this.productos = response.data;
            } catch (error) {
                console.error("❌ Error cargando productos:", error);
            }
        },

        async cargarProveedores() {
            try {
                const response = await axios.get('/api/proveedores'); // 👈 Asegúrate de que coincida con la ruta
                this.proveedores = response.data;
                console.log("✅ Proveedores cargados:", this.proveedores); // 👀 Verifica en la consola
            } catch (error) {
                console.error("❌ Error cargando proveedores:", error);
            }
        },

        async crearProducto() {
            try {
                await axios.post('/api/productos', {
                nombre: this.productoActual.nombre,
                tipo: this.productoActual.tipo,
                unidad_id: this.productoActual.unidad_id,
                largo_total: this.productoActual.largo_total,
                peso_por_metro: this.productoActual.peso_por_metro,
                producto_color_proveedor: this.combinacionesProveedorColor.map(entry => ({
                    proveedor_id: entry.proveedor_id,
                    color_id: entry.color_id,
                    costo: entry.costo
                }))
                });
                this.mostrarNotificacion("Producto agregado con éxito");
                this.cargarProductos();
                this.resetFormulario();
            } catch (error) {
                console.error("❌ Error al crear producto:", error.response?.data || error);
                this.mostrarNotificacion("Error al crear producto", "error");
            }
        },
        async actualizarProducto() {
            if (!this.productoSeleccionado) return;

            try {
                await axios.put(`/api/productos/${this.productoSeleccionado.id}`, {
                    nombre: this.productoActual.nombre,
                    tipo: this.productoActual.tipo,
                    unidad_id: this.productoActual.unidad_id,
                    largo_total: this.productoActual.largo_total,
                    peso_por_metro: this.productoActual.peso_por_metro,
                    producto_color_proveedor: this.combinacionesProveedorColor.map(entry => ({
                        proveedor_id: entry.proveedor_id,
                        color_id: entry.color_id,
                        costo: entry.costo
                    }))
                });
                this.mostrarNotificacion("Producto actualizado correctamente");
                this.cargarProductos();
                this.modoEdicion = false;
                this.resetFormulario();
            } catch (error) {
                console.error("❌ Error al actualizar producto:", error.response?.data || error);
                this.mostrarNotificacion("Error al actualizar producto", "error");
            }
        },
        async eliminarProducto(id) {
            try {
                const confirm = await Swal.fire({
                    title: '¿Estás seguro?',
                    text: 'No podrás deshacer esta acción.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar'
                });

                if (confirm.isConfirmed) {
                    await axios.delete(`/api/productos/${id}`);
                    this.cargarProductos();
                    Swal.fire('¡Eliminado!', 'El producto ha sido eliminado.', 'success');
                }

            } catch (error) {
                console.error('Error al eliminar el producto:', error);
                Swal.fire('Error', 'No se pudo eliminar el producto', 'error');
            }
        },
        editarProducto(producto) {
            this.modoEdicion = true;
            this.productoSeleccionado = producto;

            this.productoActual = {
                id: producto.id,
                nombre: producto.nombre,
                tipo: producto.tipo,
                unidad_id: producto.unidad_id || '',
                largo_total: producto.largo_total || '',
                peso_por_metro: producto.peso_por_metro || ''
            };

            // 🧩 Cargar las combinaciones proveedor + color + costo
            this.combinacionesProveedorColor = (producto.colores_por_proveedor || []).map(entry => ({
                proveedor_id: entry.proveedor_id,
                color_id: entry.color_id,
                costo: entry.costo
            }));
        },
        cancelarEdicion() {
            this.modoEdicion = false;
            this.resetFormulario();
        },
        async cargarUnidades() {
            try {
                const response = await axios.get('/api/unidades');
                this.unidades = response.data;
            } catch (error) {
                console.error('Error al cargar unidades:', error);
            }
        },
        eliminarCombinacion(index) {
            this.combinacionesProveedorColor.splice(index, 1);
        },
        resetFormulario() {
            this.productoActual = {
                nombre: '',
                tipo: 'perfil',
                unidad_id: '',
                largo_total: '',
                peso_por_metro: ''
            };
            this.productoSeleccionado = null;
            this.modoEdicion = false;
            this.combinacionesProveedorColor = []; // 🔥 limpiar combinaciones
        },
        async handleSubmit() {
            this.modoEdicion ? await this.actualizarProducto() : await this.crearProducto();
        }
    },
    mounted() {
        this.cargarColores();
        this.cargarProductos();
        this.cargarProveedores();
        this.cargarUnidades(); // 👈 nuevo
    }
};
</script>
