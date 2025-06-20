INSERT INTO `tipos_material` (`id`, `nombre`, `created_at`, `updated_at`) VALUES
(1, 'Aluminio', '2025-03-25 21:59:53', '2025-03-25 21:59:53'),
(2, 'PVC', '2025-03-25 21:59:53', '2025-03-25 21:59:53');

INSERT INTO `tipos_producto` (`id`, `nombre`, `created_at`, `updated_at`) VALUES
(1, 'Vidrio Monolitico', '2025-03-25 22:08:14', '2025-03-25 22:08:14'),
(2, 'Termopanel', '2025-03-25 22:08:40', '2025-03-25 22:08:40'),
(3, 'Perfil/Tira', '2025-03-25 22:08:40', '2025-03-25 22:08:40'),
(4, 'Herraje', '2025-03-25 22:08:40', '2025-03-25 22:08:40'),
(5, 'Otros', '2025-03-25 22:08:40', '2025-03-25 22:08:40'),
(6, 'Refuerzo', '2025-03-26 21:37:12', '2025-03-26 21:37:12');

INSERT INTO `tipos_ventana` (`id`, `nombre`, `material_id`, `created_at`, `updated_at`) VALUES
(1, 'Ventana Fija Al42', 1, '2025-03-26 20:08:47', '2025-03-26 20:08:47'),
(2, 'Ventana Fija S60', 2, '2025-03-26 21:11:53', '2025-03-26 21:11:53'),
(3, 'Ventana Corredera Sliding 80', 2, '2025-04-15 14:34:20', '2025-04-15 14:34:26'),
(45, 'Proyectante S60', 2, NULL, NULL),
(46, 'Ventana Corredera Andes', 2, NULL, NULL);

INSERT INTO `unidades` (`id`, `nombre`, `requiere_division`, `descripcion`, `created_at`, `updated_at`) VALUES
(1, 'unidad', 0, 'Producto individual, no requiere división', '2025-03-22 17:55:19', '2025-03-22 17:55:19'),
(2, 'barra', 1, 'Producto en barras largas que se divide según largo', '2025-03-22 17:55:19', '2025-03-22 17:55:19'),
(3, 'metro', 0, 'Producto calculado directamente por metro lineal', '2025-03-22 17:55:19', '2025-03-22 17:55:19'),
(4, 'm2', 0, 'metro cuadrado para cristales', '2025-03-26 15:08:20', '2025-03-26 15:08:28');
