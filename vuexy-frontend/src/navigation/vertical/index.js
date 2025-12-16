// Función para obtener permisos del usuario desde localStorage
const getUserPermissions = () => {
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  
  return user.permissions || []
}

// Función para verificar si el usuario tiene un permiso
const hasPermission = (permission) => {
  const permissions = getUserPermissions()
  
  return permissions.includes(permission)
}

// Función para verificar si el usuario tiene al menos uno de los permisos
const hasAnyPermission = (permissionList) => {
  const permissions = getUserPermissions()
  
  return permissionList.some(p => permissions.includes(p))
}

// Configuración del menú con permisos
const allMenuItems = [
  {
    title: 'Home',
    to: { name: 'root' },
    icon: { icon: 'tabler-smart-home' },
  },
  {
    title: 'Dashboard',
    to: { name: 'dashboardventas' },
    icon: { icon: 'mdi mdi-view-dashboard' },
    permission: 'ver_dashboard', // Solo si tiene este permiso
  },
  {
    title: 'Compras',
    to: { name: 'comprasmensuales' },
    icon: { icon: 'mdi mdi-view-dashboard' },
    permission: 'ver_dashboard',
  },
  {
    title: 'Productos',
    icon: { icon: 'mdi-cube' },
    permissions: ['ver_productos', 'gestionar_productos'], // Si tiene cualquiera de estos
    children: [
      {
        title: 'Agregar Producto',
        to: { name: 'agregar-producto' },
        icon: { icon: 'mdi-plus-box' },
        permission: 'gestionar_productos',
      },
      {
        title: 'Lista de Precios',
        to: { name: 'lista-precios' },
        icon: { icon: 'mdi-currency-usd' },
        permission: 'ver_productos',
      },
    ],
  },
  {
    title: 'Cotizador',
    to: { name: 'cotizador' },
    icon: { icon: 'mdi-calculator' },
    permission: 'gestionar_cotizaciones',
  },
  {
    title: 'Clientes',
    to: { name: 'clientes' },
    icon: { icon: 'mdi-account-multiple' },
    permissions: ['ver_clientes', 'gestionar_clientes'],
  },
  {
    title: 'Cotizaciones',
    to: { name: 'cotizaciones' },
    icon: { icon: 'tabler-file' },
    permissions: ['ver_cotizaciones', 'gestionar_cotizaciones'],
  },
  {
    title: 'Facturacion',
    to: { name: 'facturacion' },
    icon: { icon: 'mdi-file-document-multiple' },
    permission: 'gestionar_cotizaciones',
  },
  {
    title: 'Visor',
    to: { name: 'visor' },
    icon: { icon: 'tabler-file' },
    // Sin permiso = siempre visible
  },
  {
    title: 'Visorfabrik',
    to: { name: 'visor3d' },
    icon: { icon: 'tabler-file' },
  },
]

// Filtrar menú según permisos
const filterMenuByPermissions = (items) => {
  return items.filter(item => {
    // Si tiene permiso específico, verificar
    if (item.permission && !hasPermission(item.permission)) {
      return false
    }
    
    // Si tiene lista de permisos, verificar que tenga al menos uno
    if (item.permissions && !hasAnyPermission(item.permissions)) {
      return false
    }
    
    // Si tiene hijos, filtrarlos también
    if (item.children) {
      item.children = filterMenuByPermissions(item.children)
      // Si no quedan hijos visibles, ocultar el padre
      if (item.children.length === 0) {
        return false
      }
    }
    
    return true
  })
}

// Exportar menú filtrado
export default filterMenuByPermissions(allMenuItems)
