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
    permission: 'ver_dashboard',
  },

  // ── Ventas ──────────────────────────────────────────────────────────────
  {
    title: 'Ventas',
    icon: { icon: 'mdi-point-of-sale' },
    permissions: ['ver_cotizaciones', 'gestionar_cotizaciones'],
    children: [
      {
        title: 'Cotizador',
        to: { name: 'cotizador' },
        icon: { icon: 'mdi-calculator' },
        permission: 'gestionar_cotizaciones',
      },
      {
        title: 'Cotizaciones',
        to: { name: 'cotizaciones' },
        icon: { icon: 'tabler-file' },
        permissions: ['ver_cotizaciones', 'gestionar_cotizaciones'],
      },
      {
        title: 'Venta Express',
        to: { name: 'venta-express' },
        icon: { icon: 'mdi-flash' },
        permission: 'gestionar_cotizaciones',
      },
      {
        title: 'Facturación',
        to: { name: 'facturacion' },
        icon: { icon: 'mdi-file-document-multiple' },
        permission: 'gestionar_cotizaciones',
      },
      {
        title: 'Órdenes de Corte',
        to: { name: 'ordenes-corte' },
        icon: { icon: 'mdi-content-cut' },
        permission: 'gestionar_cotizaciones',
      },
    ],
  },

  // ── Clientes ────────────────────────────────────────────────────────────
  {
    title: 'Clientes',
    icon: { icon: 'mdi-account-multiple' },
    permissions: ['ver_clientes', 'gestionar_clientes', 'ver_cotizaciones', 'gestionar_cotizaciones'],
    children: [
      {
        title: 'Clientes',
        to: { name: 'clientes' },
        icon: { icon: 'mdi-account-multiple' },
        permissions: ['ver_clientes', 'gestionar_clientes'],
      },
      {
        title: 'CRM',
        to: { name: 'crm' },
        icon: { icon: 'mdi-view-kanban' },
        permissions: ['ver_cotizaciones', 'gestionar_cotizaciones'],
      },
    ],
  },

  // ── Producción ──────────────────────────────────────────────────────────
  {
    title: 'Producción',
    icon: { icon: 'mdi-factory' },
    permissions: ['gestionar_cotizaciones', 'ver_dashboard'],
    children: [
      {
        title: 'Operaciones',
        to: { name: 'operaciones' },
        icon: { icon: 'mdi-view-column' },
        permission: 'gestionar_cotizaciones',
      },
      {
        title: 'Producción',
        to: { name: 'produccion' },
        icon: { icon: 'mdi-scissors-cutting' },
        permission: 'gestionar_cotizaciones',
      },
      {
        title: 'Órdenes de Compra',
        to: { name: 'ordenes-compra' },
        icon: { icon: 'mdi-cart-arrow-down' },
        permission: 'gestionar_cotizaciones',
      },
      {
        title: 'Calendario',
        to: { name: 'calendario' },
        icon: { icon: 'mdi-calendar-month' },
        permission: 'gestionar_cotizaciones',
      },
      {
        title: 'Winperfil',
        to: { name: 'winperfil' },
        icon: { icon: 'mdi-window-maximize' },
        permission: 'ver_dashboard',
      },
    ],
  },

  // ── Productos ───────────────────────────────────────────────────────────
  {
    title: 'Productos',
    icon: { icon: 'mdi-cube' },
    permissions: ['ver_productos', 'gestionar_productos'],
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
      {
        title: 'Importador',
        to: { name: 'importador' },
        icon: { icon: 'tabler-file-import' },
        permission: 'gestionar_productos',
      },
      {
        title: 'Inventario',
        to: { name: 'inventario' },
        icon: { icon: 'mdi-warehouse' },
        permissions: ['ver_productos', 'gestionar_productos'],
      },
    ],
  },

  // ── Compras ─────────────────────────────────────────────────────────────
  {
    title: 'Compras',
    icon: { icon: 'mdi-cart-arrow-down' },
    permissions: ['ver_dashboard', 'gestionar_usuarios'],
    children: [
      {
        title: 'Facturas de Compra',
        to: { name: 'compras' },
        icon: { icon: 'mdi-file-document-outline' },
        permission: 'ver_dashboard',
      },
      {
        title: 'Compras Mensuales',
        to: { name: 'comprasmensuales' },
        icon: { icon: 'mdi-chart-bar' },
        permission: 'ver_dashboard',
      },
      {
        title: 'Proveedores',
        to: { name: 'proveedores' },
        icon: { icon: 'mdi-truck' },
        permission: 'gestionar_usuarios',
      },
    ],
  },

  // ── Finanzas ────────────────────────────────────────────────────────────
  {
    title: 'Finanzas',
    icon: { icon: 'mdi-bank' },
    permission: 'gestionar_usuarios',
    children: [
      // Reportes
      {
        title: 'Dashboard',
        to: { name: 'dashboard-financiero' },
        icon: { icon: 'mdi-view-dashboard-outline' },
        permission: 'gestionar_usuarios',
      },
      {
        title: 'Estado de Resultados',
        to: { name: 'eerr' },
        icon: { icon: 'mdi-chart-line' },
        permission: 'gestionar_usuarios',
      },
      // Conciliación
      {
        title: 'Sugerencias ⚡',
        to: { name: 'sugerencias-conciliacion' },
        icon: { icon: 'mdi-lightning-bolt' },
        permission: 'gestionar_usuarios',
      },
      {
        title: 'Conciliación',
        to: { name: 'conciliacion' },
        icon: { icon: 'mdi-bank-outline' },
        permission: 'gestionar_usuarios',
      },
      // Ingresos
      {
        title: 'Registro de Ventas',
        to: { name: 'registro-ventas' },
        icon: { icon: 'mdi-format-list-bulleted' },
        permission: 'gestionar_usuarios',
      },
      {
        title: 'Historial de Ventas',
        to: { name: 'historial-ventas' },
        icon: { icon: 'mdi-history' },
        permission: 'gestionar_usuarios',
      },
      {
        title: 'Cuentas por Cobrar',
        to: { name: 'cuentas-por-cobrar' },
        icon: { icon: 'mdi-file-document-plus' },
        permission: 'gestionar_usuarios',
      },
      {
        title: 'Boletas',
        to: { name: 'boletas' },
        icon: { icon: 'mdi-receipt-text-outline' },
        permission: 'gestionar_usuarios',
      },
      {
        title: 'Transbank',
        to: { name: 'transbank' },
        icon: { icon: 'mdi-credit-card-outline' },
        permission: 'gestionar_usuarios',
      },
      {
        title: 'Ingresos sin doc SII',
        to: { name: 'ingresos-manuales' },
        icon: { icon: 'mdi-receipt-text-plus' },
        permission: 'gestionar_usuarios',
      },
      // Egresos
      {
        title: 'Registro de Compras',
        to: { name: 'registro-compras' },
        icon: { icon: 'mdi-format-list-bulleted-type' },
        permission: 'gestionar_usuarios',
      },
      {
        title: 'Cuentas por Pagar',
        to: { name: 'cuentas-por-pagar' },
        icon: { icon: 'mdi-file-document-minus' },
        permission: 'gestionar_usuarios',
      },
      {
        title: 'Gastos Generales',
        to: { name: 'gastos-generales' },
        icon: { icon: 'mdi-receipt-text-minus' },
        permission: 'gestionar_usuarios',
      },
    ],
  },

  // ── RR.HH. ──────────────────────────────────────────────────────────────
  {
    title: 'RR.HH.',
    icon: { icon: 'mdi-account-group' },
    permission: 'gestionar_usuarios',
    children: [
      {
        title: 'Empleados',
        to: { name: 'empleados' },
        icon: { icon: 'mdi-account-group' },
        permission: 'gestionar_usuarios',
      },
      {
        title: 'Asistencia',
        to: { name: 'asistencia' },
        icon: { icon: 'mdi-clock-check-outline' },
        permission: 'gestionar_usuarios',
      },
    ],
  },

  // ── IA / Administración ─────────────────────────────────────────────────
  {
    title: 'Asistente IA',
    to: { name: 'ia-produccion' },
    icon: { icon: 'mdi-robot' },
    permission: 'gestionar_cotizaciones',
  },
  {
    title: 'Administración',
    icon: { icon: 'tabler-settings' },
    permission: 'gestionar_usuarios',
    children: [
      {
        title: 'Gestión de Usuarios',
        to: { name: 'admin-secret-panel' },
        icon: { icon: 'tabler-users' },
        permission: 'gestionar_usuarios',
      },
    ],
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

// Exportar función para obtener menú filtrado dinámicamente
export const getNavItems = () => filterMenuByPermissions(JSON.parse(JSON.stringify(allMenuItems)))

export default getNavItems()
