export default [
  {
    title: 'Home',
    to: { name: 'root' },
    icon: { icon: 'tabler-smart-home' },
  },
  // {
  //   title: 'Second pages',
  //   to: { name: 'second-page' },
  //   icon: { icon: 'tabler-file' },
  // },
  {
    title: 'Productos',
    icon: { icon: 'mdi-cube' },
    children: [
      {
        title: 'Agregar Producto',
        to: { name: 'agregar-producto' },
        icon: { icon: 'mdi-plus-box' },
      },
      // {
      //   title: 'Listado de Productos',
      //   to: { name: 'listado-productos' },
      //   icon: { icon: 'mdi-format-list-bulleted' },
      // },
    ],
  },
  {
    title: 'Cotizador',
    to: { name: 'cotizador' },
    icon: { icon: 'mdi-calculator' },
  },
  // {
  //   title: 'Ventana',
  //   to: { name: 'ventanapreview' },
  //   icon: { icon: 'tabler-file' },
  // },
  // {
  //   title: 'Cotizador2',
  //   to: { name: 'cotizador2' },
  //   icon: { icon: 'tabler-file' },
  // },
  {
    title: 'Clientes',
    to: { name: 'clientes' },
    icon: { icon: 'mdi-account-multiple' },
  },
  {
    title: 'Cotizaciones',
    to: { name: 'cotizaciones' },
    icon: { icon: 'tabler-file' },
  },
  
]
