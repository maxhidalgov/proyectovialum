-- =====================================================
-- SCRIPT DE MIGRACIÓN A PRODUCCIÓN
-- Sistema de Roles y Permisos ViaLum
-- Fecha: 2025-12-16
-- =====================================================

-- 1. CREAR TABLA DE PERMISOS
-- =====================================================
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_nombre_unique` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CREAR TABLA PIVOTE ROLE_PERMISSION
-- =====================================================
CREATE TABLE IF NOT EXISTS `role_permission` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission_role_id_permission_id_unique` (`role_id`,`permission_id`),
  KEY `role_permission_permission_id_foreign` (`permission_id`),
  CONSTRAINT `role_permission_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permission_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CREAR TABLA CACHE (para JWT blacklist)
-- =====================================================
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. INSERTAR PERMISOS (solo si no existen)
-- =====================================================
-- Insertar los 10 permisos del sistema (IGNORA si ya existen)
INSERT IGNORE INTO `permissions` (`nombre`, `descripcion`, `created_at`, `updated_at`) VALUES
('gestionar_usuarios', 'Crear, editar y eliminar usuarios del sistema', NOW(), NOW()),
('gestionar_roles', 'Modificar roles y permisos', NOW(), NOW()),
('gestionar_productos', 'Crear, editar y eliminar productos', NOW(), NOW()),
('ver_productos', 'Ver listado de productos', NOW(), NOW()),
('gestionar_cotizaciones', 'Crear, editar y eliminar cotizaciones', NOW(), NOW()),
('ver_cotizaciones', 'Ver listado de cotizaciones', NOW(), NOW()),
('aprobar_cotizaciones', 'Aprobar o rechazar cotizaciones', NOW(), NOW()),
('gestionar_clientes', 'Crear, editar y eliminar clientes', NOW(), NOW()),
('ver_clientes', 'Ver listado de clientes', NOW(), NOW()),
('ver_dashboard', 'Acceder al dashboard principal', NOW(), NOW());

-- 5. ASIGNAR PERMISOS A ROLES (solo si no existen)
-- =====================================================
-- ADMIN: Todos los permisos (IDs 1-10)
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT 
    (SELECT id FROM roles WHERE nombre = 'Admin' LIMIT 1) as role_id,
    id as permission_id,
    NOW(),
    NOW()
FROM permissions;

-- VENDEDOR: Cotizaciones, clientes, ver productos, dashboard (IDs 4, 5, 6, 7, 8, 9, 10)
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT 
    (SELECT id FROM roles WHERE nombre = 'Vendedor' LIMIT 1) as role_id,
    id as permission_id,
    NOW(),
    NOW()
FROM permissions
WHERE nombre IN ('ver_productos', 'gestionar_cotizaciones', 'ver_cotizaciones', 'aprobar_cotizaciones', 'gestionar_clientes', 'ver_clientes', 'ver_dashboard');

-- PRACTICANTE: Solo gestionar productos y ver productos (IDs 3, 4)
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT 
    (SELECT id FROM roles WHERE nombre = 'Practicante' LIMIT 1) as role_id,
    id as permission_id,
    NOW(),
    NOW()
FROM permissions
WHERE nombre IN ('gestionar_productos', 'ver_productos');

-- 6. CREAR USUARIO ADMIN (solo si no existe)
-- =====================================================
-- IMPORTANTE: Cambiar la contraseña después de ejecutar
INSERT IGNORE INTO `users` (`name`, `email`, `password`, `role_id`, `created_at`, `updated_at`)
VALUES (
    'Administrador',
    'admin@vialum.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    (SELECT id FROM roles WHERE nombre = 'Admin' LIMIT 1),
    NOW(),
    NOW()
);

-- =====================================================
-- VERIFICACIÓN
-- =====================================================
-- Verificar que todo se creó correctamente
SELECT 'PERMISOS CREADOS:' as '';
SELECT COUNT(*) as total_permisos FROM permissions;

SELECT 'ASIGNACIONES POR ROL:' as '';
SELECT 
    r.nombre as rol,
    COUNT(rp.permission_id) as total_permisos
FROM roles r
LEFT JOIN role_permission rp ON r.id = rp.role_id
GROUP BY r.id, r.nombre;

SELECT 'USUARIO ADMIN:' as '';
SELECT name, email, role_id FROM users WHERE email = 'admin@vialum.com';

-- =====================================================
-- INSTRUCCIONES POST-INSTALACIÓN
-- =====================================================
-- 1. Cambiar contraseña del admin desde el panel de administración
-- 2. Actualizar archivo .env con CACHE_STORE=database
-- 3. Asegurarse que JWT_SECRET está configurado
-- 4. Limpiar cache: php artisan cache:clear
-- 5. Verificar que las rutas /api/admin/* requieren autenticación
-- =====================================================
