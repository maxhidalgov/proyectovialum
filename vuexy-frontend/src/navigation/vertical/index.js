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

// Configuración del menú con permisos POR ÁREA.
// Cada sección (y sus hijos) se gatea con el permiso de su área. Ver
// database/migrations/2026_08_03_140000_permisos_por_area.php
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
    permission: 'area_ventas',
  },

  // ── Ventas ──────────────────────────────────────────────────────────────
  {
    title: 'Ventas',
    icon: { icon: 'mdi-point-of-sale' },
    permission: 'area_ventas',
    children: [
      { title: 'Cotizar proyecto',to: { name: 'cotizador' },     icon: { icon: 'mdi-calculator' },              permission: 'area_ventas' },
      { title: 'Cotizaciones',    to: { name: 'cotizaciones' },  icon: { icon: 'tabler-file' },                 permission: 'area_ventas' },
      { title: 'Venta Express',   to: { name: 'venta-express' }, icon: { icon: 'mdi-flash' },                   permission: 'area_ventas' },
      { title: 'Cotización rápida', to: { name: 'cotizacion-rapida' }, icon: { icon: 'mdi-note-plus-outline' }, permission: 'area_ventas' },
      { title: 'Facturación',     to: { name: 'facturacion' },   icon: { icon: 'mdi-file-document-multiple' },  permission: 'area_ventas' },
      { title: 'Boletas/Facturas',to: { name: 'historial-ventas' }, icon: { icon: 'mdi-receipt-text-outline' }, permission: 'area_ventas' },
      { title: 'Órdenes de Corte',to: { name: 'ordenes-corte' }, icon: { icon: 'mdi-content-cut' },             permission: 'area_ventas' },
    ],
  },

  // ── Clientes ────────────────────────────────────────────────────────────
  {
    title: 'Clientes',
    icon: { icon: 'mdi-account-multiple' },
    permission: 'area_clientes',
    children: [
      { title: 'Clientes', to: { name: 'clientes' }, icon: { icon: 'mdi-account-multiple' }, permission: 'area_clientes' },
      { title: 'CRM',      to: { name: 'crm' },      icon: { icon: 'mdi-view-kanban' },      permission: 'area_clientes' },
    ],
  },

  // ── Producción ──────────────────────────────────────────────────────────
  {
    title: 'Producción',
    icon: { icon: 'mdi-factory' },
    permission: 'area_produccion',
    children: [
      { title: 'Operaciones',        to: { name: 'operaciones' },    icon: { icon: 'mdi-view-column' },     permission: 'area_produccion' },
      { title: 'Producción',         to: { name: 'produccion' },     icon: { icon: 'mdi-scissors-cutting' },permission: 'area_produccion' },
      { title: 'Órdenes de Compra',  to: { name: 'ordenes-compra' }, icon: { icon: 'mdi-cart-arrow-down' }, permission: 'area_produccion' },
      { title: 'Calendario',         to: { name: 'calendario' },     icon: { icon: 'mdi-calendar-month' },  permission: 'area_produccion' },
      { title: 'Winperfil',          to: { name: 'winperfil' },      icon: { icon: 'mdi-window-maximize' }, permission: 'area_produccion' },
    ],
  },

  // ── Productos ───────────────────────────────────────────────────────────
  {
    title: 'Productos',
    icon: { icon: 'mdi-cube' },
    permission: 'area_productos',
    children: [
      { title: 'Agregar Producto', to: { name: 'agregar-producto' }, icon: { icon: 'mdi-plus-box' },      permission: 'area_productos' },
      { title: 'Lista de Precios', to: { name: 'lista-precios' },    icon: { icon: 'mdi-currency-usd' },  permission: 'area_productos' },
      { title: 'Importador',       to: { name: 'importador' },       icon: { icon: 'tabler-file-import' },permission: 'area_productos' },
      { title: 'Inventario',       to: { name: 'inventario' },       icon: { icon: 'mdi-warehouse' },     permission: 'area_productos' },
    ],
  },

  // ── Compras ─────────────────────────────────────────────────────────────
  {
    title: 'Compras',
    icon: { icon: 'mdi-cart-arrow-down' },
    permission: 'area_compras',
    children: [
      { title: 'Facturas de Compra', to: { name: 'compras' },          icon: { icon: 'mdi-file-document-outline' }, permission: 'area_compras' },
      { title: 'Compras Mensuales',  to: { name: 'comprasmensuales' },  icon: { icon: 'mdi-chart-bar' },             permission: 'area_compras' },
      { title: 'Proveedores',        to: { name: 'proveedores' },       icon: { icon: 'mdi-truck' },                 permission: 'area_compras' },
    ],
  },

  // ── Finanzas ────────────────────────────────────────────────────────────
  {
    title: 'Finanzas',
    icon: { icon: 'mdi-bank' },
    permission: 'area_finanzas',
    children: [
      { title: 'Dashboard',                     to: { name: 'dashboard-financiero' },      icon: { icon: 'mdi-view-dashboard-outline' },   permission: 'area_finanzas' },
      { title: 'Estado de Resultados',          to: { name: 'eerr' },                      icon: { icon: 'mdi-chart-line' },              permission: 'area_finanzas' },
      { title: 'Sugerencias ⚡',                to: { name: 'sugerencias-conciliacion' },  icon: { icon: 'mdi-lightning-bolt' },          permission: 'area_finanzas' },
      { title: 'Conciliación',                  to: { name: 'conciliacion' },              icon: { icon: 'mdi-bank-outline' },            permission: 'area_finanzas' },
      { title: 'Registro de Ventas',            to: { name: 'registro-ventas' },           icon: { icon: 'mdi-format-list-bulleted' },    permission: 'area_finanzas' },
      { title: 'Cuentas por Cobrar',            to: { name: 'cuentas-por-cobrar' },        icon: { icon: 'mdi-file-document-plus' },      permission: 'area_finanzas' },
      { title: 'Boletas',                       to: { name: 'boletas' },                   icon: { icon: 'mdi-receipt-text-outline' },    permission: 'area_finanzas' },
      { title: 'Transbank',                     to: { name: 'transbank' },                 icon: { icon: 'mdi-credit-card-outline' },     permission: 'area_finanzas' },
      { title: 'Ingresos sin doc SII',          to: { name: 'ingresos-manuales' },         icon: { icon: 'mdi-receipt-text-plus' },       permission: 'area_finanzas' },
      { title: 'Registro de Compras',           to: { name: 'registro-compras' },          icon: { icon: 'mdi-format-list-bulleted-type' },permission: 'area_finanzas' },
      { title: 'Cuentas por Pagar',             to: { name: 'cuentas-por-pagar' },         icon: { icon: 'mdi-file-document-minus' },     permission: 'area_finanzas' },
      { title: 'Gastos Generales',              to: { name: 'gastos-generales' },          icon: { icon: 'mdi-receipt-text-minus' },      permission: 'area_finanzas' },
    ],
  },

  // ── RR.HH. ──────────────────────────────────────────────────────────────
  {
    title: 'RR.HH.',
    icon: { icon: 'mdi-account-group' },
    permission: 'area_rrhh',
    children: [
      { title: 'Empleados',  to: { name: 'empleados' },  icon: { icon: 'mdi-account-group' },       permission: 'area_rrhh' },
      { title: 'Asistencia', to: { name: 'asistencia' }, icon: { icon: 'mdi-clock-check-outline' }, permission: 'area_rrhh' },
    ],
  },

  // ── IA / Administración ─────────────────────────────────────────────────
  {
    title: 'Asistente IA',
    to: { name: 'ia-produccion' },
    icon: { icon: 'mdi-robot' },
    permission: 'area_produccion',
  },
  {
    title: 'Administración',
    icon: { icon: 'tabler-settings' },
    permission: 'area_admin',
    children: [
      { title: 'Gestión de Usuarios', to: { name: 'admin-secret-panel' }, icon: { icon: 'tabler-users' }, permission: 'area_admin' },
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
