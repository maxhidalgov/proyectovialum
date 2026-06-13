<script setup>
import { ref, onMounted } from 'vue'
import api from '@/axiosInstance'
import { useRouter } from 'vue-router'

definePage({
  meta: {
    requiresAuth: true,
    requiresAdmin: true, // Solo admin puede acceder
  },
})

const router = useRouter()
const loading = ref(false)
const error = ref(null)
const success = ref(null)
const users = ref([])
const roles = ref([])
const permissions = ref([])
const showDialog = ref(false)
const editMode = ref(false)
const currentUser = ref(null)
const activeTab = ref('users') // 'users' | 'roles' | 'margenes' | 'herramientas'

// ── Herramientas ──────────────────────────────────────────────────
const herramientaLoading = ref({})
const herramientaResultado = ref({})

// Estados para gestión de permisos
const selectedRole = ref(null)
const rolePermissions = ref([])
const savingPermissions = ref(false)

const form = ref({
  name: '',
  email: '',
  password: '',
  role_id: null,
})

// Verificar si el usuario actual es admin
onMounted(async () => {
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  
  console.log('👤 Usuario actual:', user)
  console.log('🔑 Rol del usuario:', user.role)
  
  if (!user.role) {
    console.warn('⚠️ No hay usuario logueado, redirigiendo al login...')
    router.push({ name: 'login' })
    
    return
  }
  
  // Comparación case-insensitive
  if (user.role.toLowerCase() !== 'admin') {
    console.warn('⚠️ Usuario no es Admin, redirigiendo al home...')
    router.push({ name: 'root' })
    
    return
  }
  
  console.log('✅ Usuario es Admin, cargando panel...')
  await loadUsers()
  await loadRoles()
  await loadPermissions()
  await loadMateriales()
})

// ── Márgenes por material ─────────────────────────────────────────
const materiales = ref([])
const savingMargen = ref(null)

const loadMateriales = async () => {
  const { data } = await api.get('/api/tipos_material')
  materiales.value = data.map(m => ({ ...m, margenInput: Math.round((m.margen ?? 0.50) * 100) }))
}

const guardarMargen = async (mat) => {
  savingMargen.value = mat.id
  try {
    await api.put(`/api/tipos_material/${mat.id}/margen`, { margen: mat.margenInput / 100 })
    success.value = `Margen de ${mat.nombre} actualizado`
  } finally {
    savingMargen.value = null
  }
}

const loadPermissions = async () => {
  try {
    const { data } = await api.get('/api/admin/permissions')
    permissions.value = data.permissions
  } catch (err) {
    error.value = 'Error al cargar permisos'
  }
}

const loadRolePermissions = async (roleId) => {
  loading.value = true
  
  try {
    const { data } = await api.get(`/api/admin/roles/${roleId}/permissions`)
    rolePermissions.value = data.permissions
  } catch (err) {
    error.value = 'Error al cargar permisos del rol'
  } finally {
    loading.value = false
  }
}

const saveRolePermissions = async () => {
  if (!selectedRole.value) return

  savingPermissions.value = true
  error.value = null
  success.value = null

  try {
    await api.put(`/api/admin/roles/${selectedRole.value}/permissions`, {
      permissions: rolePermissions.value,
    })
    success.value = 'Permisos actualizados exitosamente'
  } catch (err) {
    error.value = err.response?.data?.message || 'Error al actualizar permisos'
  } finally {
    savingPermissions.value = false
  }
}

const selectRole = async (roleId) => {
  selectedRole.value = roleId
  await loadRolePermissions(roleId)
}

const togglePermission = (permissionId) => {
  const index = rolePermissions.value.indexOf(permissionId)
  if (index > -1) {
    rolePermissions.value.splice(index, 1)
  } else {
    rolePermissions.value.push(permissionId)
  }
}

const hasPermission = (permissionId) => {
  return rolePermissions.value.includes(permissionId)
}

const loadUsers = async () => {
  loading.value = true
  
  try {
    const { data } = await api.get('/api/admin/users')
    users.value = data.users
  } catch (err) {
    error.value = 'Error al cargar usuarios'
  } finally {
    loading.value = false
  }
}

const loadRoles = async () => {
  try {
    const { data } = await api.get('/api/admin/roles')
    roles.value = data.roles
  } catch (err) {
    error.value = 'Error al cargar roles'
  }
}

const openCreateDialog = () => {
  editMode.value = false
  form.value = {
    name: '',
    email: '',
    password: '',
    role_id: null,
  }
  showDialog.value = true
}

const openEditDialog = user => {
  editMode.value = true
  currentUser.value = user
  form.value = {
    name: user.name,
    email: user.email,
    password: '', // No mostrar password actual
    role_id: user.role?.id || null,
  }
  showDialog.value = true
}

const saveUser = async () => {
  error.value = null
  success.value = null
  loading.value = true

  try {
    if (editMode.value) {
      await api.put(`/api/admin/users/${currentUser.value.id}`, form.value)
      success.value = 'Usuario actualizado exitosamente'
    } else {
      await api.post('/api/admin/users', form.value)
      success.value = 'Usuario creado exitosamente'
    }
    
    showDialog.value = false
    await loadUsers()
  } catch (err) {
    error.value = err.response?.data?.message || 'Error al guardar usuario'
  } finally {
    loading.value = false
  }
}

const deleteUser = async id => {
  if (!confirm('¿Estás seguro de eliminar este usuario?')) return

  loading.value = true
  error.value = null

  try {
    await api.delete(`/api/admin/users/${id}`)
    success.value = 'Usuario eliminado exitosamente'
    await loadUsers()
  } catch (err) {
    error.value = err.response?.data?.error || 'Error al eliminar usuario'
  } finally {
    loading.value = false
  }
}

async function correrHerramienta(key, method, url) {
  herramientaLoading.value[key] = true
  herramientaResultado.value[key] = null
  try {
    const { data } = await api[method](url)
    herramientaResultado.value[key] = data
  } catch (e) {
    herramientaResultado.value[key] = { error: e.response?.data?.message || e.message }
  } finally {
    herramientaLoading.value[key] = false
  }
}

const getRoleBadgeColor = roleName => {
  switch (roleName) {
  case 'Admin':
    return 'error'
  case 'Vendedor':
    return 'primary'
  case 'Practicante':
    return 'warning'
  default:
    return 'default'
  }
}
</script>

<template>
  <VContainer fluid>
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between">
            <div>
              <h2>🔐 Panel de Administración</h2>
              <p class="text-caption text-disabled mb-0">
                Gestión de usuarios, roles y permisos del sistema
              </p>
            </div>
            <VBtn
              v-if="activeTab === 'users'"
              color="primary"
              @click="openCreateDialog"
            >
              <VIcon
                start
                icon="tabler-plus"
              />
              Crear Usuario
            </VBtn>
          </VCardTitle>

          <!-- Tabs -->
          <VTabs
            v-model="activeTab"
            class="mx-4"
          >
            <VTab value="users">
              <VIcon
                start
                icon="tabler-users"
              />
              Usuarios
            </VTab>
            <VTab value="roles">
              <VIcon start icon="tabler-shield-lock" />
              Roles y Permisos
            </VTab>
            <VTab value="margenes">
              <VIcon start icon="tabler-percentage" />
              Márgenes
            </VTab>
            <VTab value="herramientas">
              <VIcon start icon="tabler-tool" />
              Herramientas
            </VTab>
          </VTabs>

          <VDivider />

          <!-- Mensajes -->
          <VAlert
            v-if="error"
            type="error"
            closable
            @click:close="error = null"
            class="ma-4"
          >
            {{ error }}
          </VAlert>

          <VAlert
            v-if="success"
            type="success"
            closable
            @click:close="success = null"
            class="ma-4"
          >
            {{ success }}
          </VAlert>

          <!-- Tabla de usuarios -->
          <VCardText v-show="activeTab === 'users'">
            <VProgressLinear
              v-if="loading"
              indeterminate
              color="primary"
            />

            <VTable v-else>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Email</th>
                  <th>Rol</th>
                  <th>Fecha Creación</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="user in users"
                  :key="user.id"
                >
                  <td>{{ user.id }}</td>
                  <td>{{ user.name }}</td>
                  <td>{{ user.email }}</td>
                  <td>
                    <VChip
                      :color="getRoleBadgeColor(user.role?.nombre)"
                      size="small"
                    >
                      {{ user.role?.nombre || 'Sin rol' }}
                    </VChip>
                  </td>
                  <td>{{ new Date(user.created_at).toLocaleDateString() }}</td>
                  <td>
                    <VBtn
                      icon="tabler-edit"
                      size="small"
                      variant="text"
                      @click="openEditDialog(user)"
                    />
                    <VBtn
                      icon="tabler-trash"
                      size="small"
                      variant="text"
                      color="error"
                      @click="deleteUser(user.id)"
                    />
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>

          <!-- Gestión de Roles y Permisos -->
          <VCardText v-show="activeTab === 'roles'">
            <VRow>
              <VCol
                cols="12"
                md="4"
              >
                <VCard variant="outlined">
                  <VCardTitle>Seleccionar Rol</VCardTitle>
                  <VCardText>
                    <VList>
                      <VListItem
                        v-for="role in roles"
                        :key="role.id"
                        :active="selectedRole === role.id"
                        @click="selectRole(role.id)"
                      >
                        <template #prepend>
                          <VIcon
                            :color="selectedRole === role.id ? 'primary' : 'default'"
                            :icon="selectedRole === role.id ? 'tabler-check' : 'tabler-shield'"
                          />
                        </template>
                        <VListItemTitle>{{ role.nombre }}</VListItemTitle>
                      </VListItem>
                    </VList>
                  </VCardText>
                </VCard>
              </VCol>

              <VCol
                cols="12"
                md="8"
              >
                <VCard
                  v-if="selectedRole"
                  variant="outlined"
                >
                  <VCardTitle>
                    Permisos del Rol: {{ roles.find(r => r.id === selectedRole)?.nombre }}
                  </VCardTitle>
                  <VCardText>
                    <VProgressLinear
                      v-if="loading"
                      indeterminate
                      color="primary"
                    />
                    <div v-else>
                      <VRow>
                        <VCol
                          v-for="permission in permissions"
                          :key="permission.id"
                          cols="12"
                          md="6"
                        >
                          <VCheckbox
                            :model-value="hasPermission(permission.id)"
                            :label="permission.nombre"
                            :hint="permission.descripcion"
                            persistent-hint
                            @update:model-value="togglePermission(permission.id)"
                          />
                        </VCol>
                      </VRow>

                      <VDivider class="my-4" />

                      <VBtn
                        color="primary"
                        :loading="savingPermissions"
                        @click="saveRolePermissions"
                      >
                        <VIcon
                          start
                          icon="tabler-device-floppy"
                        />
                        Guardar Cambios
                      </VBtn>
                    </div>
                  </VCardText>
                </VCard>

                <VAlert
                  v-else
                  type="info"
                  variant="tonal"
                >
                  Selecciona un rol de la lista para gestionar sus permisos
                </VAlert>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tab Márgenes por material -->
    <VCardText v-show="activeTab === 'margenes'">
      <VRow>
        <VCol v-for="mat in materiales" :key="mat.id" cols="12" md="4">
          <VCard variant="outlined">
            <VCardTitle>{{ mat.nombre }}</VCardTitle>
            <VCardText>
              <VTextField
                v-model.number="mat.margenInput"
                label="Margen de venta (%)"
                type="number"
                min="1"
                max="99"
                suffix="%"
                hint="Ej: 50 = precio = costo / (1 - 0.50)"
                persistent-hint
              />
            </VCardText>
            <VCardActions>
              <VBtn
                color="primary"
                :loading="savingMargen === mat.id"
                @click="guardarMargen(mat)"
              >
                Guardar
              </VBtn>
            </VCardActions>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>

    <!-- Tab Herramientas -->
    <VCardText v-show="activeTab === 'herramientas'">
      <VRow>

        <!-- Vincular NCs pendientes -->
        <VCol cols="12" md="6">
          <VCard variant="outlined">
            <VCardTitle class="text-subtitle-1">Vincular Notas de Crédito pendientes</VCardTitle>
            <VCardText class="text-body-2 text-medium-emphasis">
              Lee el XML de cada NC sin referencia y la vincula a su factura original.
              Esto corrige el saldo pendiente de facturas que tienen NC pero no se aplicaron.
            </VCardText>
            <VCardActions>
              <VBtn
                color="warning"
                :loading="herramientaLoading['vincular-ncs']"
                @click="correrHerramienta('vincular-ncs', 'post', '/api/compras/vincular-ncs')"
              >
                <VIcon start>mdi-link-variant</VIcon>
                Ejecutar
              </VBtn>
            </VCardActions>
            <VCardText v-if="herramientaResultado['vincular-ncs']">
              <VAlert
                :type="herramientaResultado['vincular-ncs'].error ? 'error' : 'success'"
                variant="tonal"
                density="compact"
              >
                <template v-if="herramientaResultado['vincular-ncs'].error">
                  {{ herramientaResultado['vincular-ncs'].error }}
                </template>
                <template v-else>
                  Total NCs procesadas: <strong>{{ herramientaResultado['vincular-ncs'].total }}</strong> ·
                  Vinculadas: <strong>{{ herramientaResultado['vincular-ncs'].vinculadas }}</strong> ·
                  Errores: <strong>{{ herramientaResultado['vincular-ncs'].errores }}</strong>
                </template>
              </VAlert>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Cargar XMLs pendientes -->
        <VCol cols="12" md="6">
          <VCard variant="outlined">
            <VCardTitle class="text-subtitle-1">Cargar XMLs de compras pendientes</VCardTitle>
            <VCardText class="text-body-2 text-medium-emphasis">
              Descarga y parsea los XMLs de compras que aún no tienen líneas de detalle.
              Necesario antes de vincular NCs si las compras son nuevas.
            </VCardText>
            <VCardActions>
              <VBtn
                color="info"
                :loading="herramientaLoading['cargar-xmls']"
                @click="correrHerramienta('cargar-xmls', 'post', '/api/compras/cargar-xmls-pendientes')"
              >
                <VIcon start>mdi-xml</VIcon>
                Ejecutar
              </VBtn>
            </VCardActions>
            <VCardText v-if="herramientaResultado['cargar-xmls']">
              <VAlert
                :type="herramientaResultado['cargar-xmls'].error ? 'error' : 'success'"
                variant="tonal"
                density="compact"
              >
                <template v-if="herramientaResultado['cargar-xmls'].error">
                  {{ herramientaResultado['cargar-xmls'].error }}
                </template>
                <template v-else>
                  Procesadas: <strong>{{ herramientaResultado['cargar-xmls'].procesadas }}</strong> ·
                  Errores: <strong>{{ herramientaResultado['cargar-xmls'].errores }}</strong> ·
                  Restantes: <strong>{{ herramientaResultado['cargar-xmls'].restantes }}</strong>
                </template>
              </VAlert>
            </VCardText>
          </VCard>
        </VCol>

      </VRow>
    </VCardText>

    <!-- Dialog crear/editar usuario -->
    <VDialog
      v-model="showDialog"
      max-width="600"
    >
      <VCard>
        <VCardTitle>
          {{ editMode ? 'Editar Usuario' : 'Crear Usuario' }}
        </VCardTitle>

        <VCardText>
          <VForm @submit.prevent="saveUser">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="form.name"
                  label="Nombre completo"
                  required
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="form.email"
                  label="Email"
                  type="email"
                  required
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="form.password"
                  label="Contraseña"
                  type="password"
                  :hint="editMode ? 'Dejar vacío para no cambiar' : ''"
                  :required="!editMode"
                />
              </VCol>

              <VCol cols="12">
                <VSelect
                  v-model="form.role_id"
                  :items="roles"
                  item-title="nombre"
                  item-value="id"
                  label="Rol"
                  required
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            color="default"
            @click="showDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="loading"
            @click="saveUser"
          >
            {{ editMode ? 'Actualizar' : 'Crear' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>

<style scoped>
.text-caption {
  font-size: 0.875rem;
}
</style>
