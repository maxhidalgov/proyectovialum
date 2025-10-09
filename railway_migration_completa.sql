-- =====================================================
-- MIGRACIONES PARA RAILWAY - LISTA DE PRECIOS
-- Ejecutar en MySQL Workbench conectado a Railway
-- =====================================================

-- 1️⃣ CREAR TABLA lista_precios
CREATE TABLE IF NOT EXISTS `lista_precios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `producto_id` bigint(20) unsigned NOT NULL,
  `producto_color_proveedor_id` bigint(20) unsigned DEFAULT NULL,
  `precio_costo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `margen` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje de margen',
  `precio_venta` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vigencia_desde` date DEFAULT NULL,
  `vigencia_hasta` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lista_precios_producto_id_foreign` (`producto_id`),
  KEY `lista_precios_producto_id_index` (`producto_id`),
  KEY `lista_precios_activo_index` (`activo`),
  KEY `lista_precios_producto_color_proveedor_id_foreign` (`producto_color_proveedor_id`),
  KEY `lista_precios_producto_color_proveedor_id_index` (`producto_color_proveedor_id`),
  CONSTRAINT `lista_precios_producto_id_foreign` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lista_precios_producto_color_proveedor_id_foreign` FOREIGN KEY (`producto_color_proveedor_id`) REFERENCES `producto_color_proveedor` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2️⃣ CREAR O ACTUALIZAR TABLA cotizacion_detalles
-- Primero, intentar crear la tabla completa si no existe
CREATE TABLE IF NOT EXISTS `cotizacion_detalles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cotizacion_id` bigint(20) unsigned NOT NULL,
  `producto_id` bigint(20) unsigned DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  `precio_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tipo_item` enum('ventana','producto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ventana',
  `producto_lista_id` bigint(20) unsigned DEFAULT NULL,
  `lista_precio_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cotizacion_detalles_cotizacion_id_foreign` (`cotizacion_id`),
  CONSTRAINT `cotizacion_detalles_cotizacion_id_foreign` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si la tabla ya existe, agregar columnas que falten
SET @dbname = DATABASE();
SET @tablename = 'cotizacion_detalles';

-- Agregar cotizacion_id si no existe
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'cotizacion_id')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD COLUMN cotizacion_id BIGINT(20) UNSIGNED NOT NULL FIRST"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Agregar producto_id si no existe
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'producto_id')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD COLUMN producto_id BIGINT(20) UNSIGNED NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Agregar descripcion si no existe
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'descripcion')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD COLUMN descripcion TEXT NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Agregar cantidad si no existe
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'cantidad')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD COLUMN cantidad DECIMAL(10,2) NOT NULL DEFAULT 1"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Agregar precio_unitario si no existe
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'precio_unitario')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD COLUMN precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Agregar total si no existe
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'total')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD COLUMN total DECIMAL(12,2) NOT NULL DEFAULT 0"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Agregar tipo_item si no existe
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'tipo_item')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD COLUMN tipo_item ENUM('ventana', 'producto') NOT NULL DEFAULT 'ventana'"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Agregar producto_lista_id si no existe
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'producto_lista_id')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD COLUMN producto_lista_id BIGINT(20) UNSIGNED NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Agregar lista_precio_id si no existe
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'lista_precio_id')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD COLUMN lista_precio_id BIGINT(20) UNSIGNED NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3️⃣ AGREGAR FOREIGN KEYS A cotizacion_detalles (si no existen)
-- Verificar si existe el FK de cotizacion_id primero
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (constraint_name = 'cotizacion_detalles_cotizacion_id_foreign')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD CONSTRAINT cotizacion_detalles_cotizacion_id_foreign FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- producto_lista_id foreign key
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (constraint_name = 'cotizacion_detalles_producto_lista_id_foreign')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD CONSTRAINT cotizacion_detalles_producto_lista_id_foreign FOREIGN KEY (producto_lista_id) REFERENCES productos(id) ON DELETE CASCADE"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- lista_precio_id foreign key
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (constraint_name = 'cotizacion_detalles_lista_precio_id_foreign')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD CONSTRAINT cotizacion_detalles_lista_precio_id_foreign FOREIGN KEY (lista_precio_id) REFERENCES lista_precios(id) ON DELETE SET NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 4️⃣ AGREGAR ÍNDICES A cotizacion_detalles (si no existen)
-- Índice tipo_item
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = 'cotizacion_detalles_tipo_item_index')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD INDEX cotizacion_detalles_tipo_item_index (tipo_item)"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Índice producto_lista_id
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = 'cotizacion_detalles_producto_lista_id_index')
  ) > 0,
  "SELECT 1",
  "ALTER TABLE cotizacion_detalles ADD INDEX cotizacion_detalles_producto_lista_id_index (producto_lista_id)"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 5️⃣ REGISTRAR MIGRACIONES EN LA TABLA migrations (para que Laravel no las ejecute de nuevo)
INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES
('2025_10_08_144234_create_lista_precios_table', 3),
('2025_10_08_193815_add_producto_color_proveedor_id_to_lista_precios_table', 3),
('2025_10_08_144316_add_productos_to_cotizacion_detalle', 3),
('2025_10_08_150936_fix_cotizacion_detalles_structure', 3);

-- =====================================================
-- ✅ MIGRACIONES COMPLETADAS
-- =====================================================
-- Verificar la estructura creada:
-- SHOW CREATE TABLE lista_precios;
-- SHOW CREATE TABLE cotizacion_detalles;
-- SELECT * FROM migrations WHERE migration LIKE '%lista%' OR migration LIKE '%cotizacion_detalle%';
