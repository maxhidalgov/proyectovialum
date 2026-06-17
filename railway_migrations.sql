-- ============================================================
--  ProyectoVialum — Migraciones pendientes para Railway
--  Generado: 2026-06-04
--  Cubre: 2026-05-23 a 2026-06-03 (todo lo que no está en origin/main)
--
--  INSTRUCCIONES:
--   1. Abrir MySQL Workbench conectado a Railway
--   2. Seleccionar la BD del proyecto (USE nombre_bd; si es necesario)
--   3. Ejecutar este script completo
--   4. Todos los ALTER TABLE son condicionales — es seguro re-ejecutar
-- ============================================================

-- Desactivar safe update mode de Workbench (se restaura al final)
SET SQL_SAFE_UPDATES = 0;

-- ──────────────────────────────────────────────────────────
-- M01: create_reglas_conciliacion_table (2026-05-23)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `reglas_conciliacion` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`     VARCHAR(100)    NOT NULL,
  `patron`     VARCHAR(200)    NOT NULL,
  `categoria`  VARCHAR(80)     NOT NULL,
  `tipo`       CHAR(1)         NOT NULL DEFAULT 'A',
  `prioridad`  SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  `activa`     TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  INDEX `reglas_conciliacion_tipo_activa_prioridad_index` (`tipo`,`activa`,`prioridad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M02: create_empleados_table (2026-05-23)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `empleados` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(120)    NOT NULL,
  `rut`             VARCHAR(12)     NOT NULL,
  `cargo`           VARCHAR(100)    NULL,
  `sueldo_base`     DECIMAL(12,2)   NOT NULL,
  `fecha_ingreso`   DATE            NOT NULL,
  `fecha_egreso`    DATE            NULL,
  `activo`          TINYINT(1)      NOT NULL DEFAULT 1,
  `banco`           VARCHAR(60)     NULL,
  `cuenta_bancaria` VARCHAR(30)     NULL,
  `tipo_cuenta`     VARCHAR(20)     NULL,
  `notas`           TEXT            NULL,
  `created_at`      TIMESTAMP       NULL,
  `updated_at`      TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empleados_rut_unique` (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pagos_empleado` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empleado_id`  BIGINT UNSIGNED NOT NULL,
  `movimiento_id` BIGINT UNSIGNED NULL,
  `periodo`      DATE            NOT NULL,
  `monto`        DECIMAL(12,2)   NOT NULL,
  `tipo`         VARCHAR(30)     NOT NULL DEFAULT 'sueldo',
  `pagado`       TINYINT(1)      NOT NULL DEFAULT 0,
  `fecha_pago`   DATE            NULL,
  `notas`        TEXT            NULL,
  `created_at`   TIMESTAMP       NULL,
  `updated_at`   TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `pagos_empleado_empleado_id_foreign`
    FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pagos_empleado_movimiento_id_foreign`
    FOREIGN KEY (`movimiento_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M03: add_glosa_to_movimientos_bancarios (2026-05-23)
--      Railway ya tiene esta columna → condicional
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos_bancarios' AND COLUMN_NAME = 'glosa') = 0,
  'ALTER TABLE `movimientos_bancarios` ADD COLUMN `glosa` VARCHAR(255) NULL AFTER `descripcion`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M04: create_compra_movimiento_table (2026-05-23)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `compra_movimiento` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `compra_id`    BIGINT UNSIGNED NOT NULL,
  `movimiento_id` BIGINT UNSIGNED NOT NULL,
  `monto`        DECIMAL(12,2)   NOT NULL,
  `created_at`   TIMESTAMP       NULL,
  `updated_at`   TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `compra_movimiento_compra_id_movimiento_id_unique` (`compra_id`,`movimiento_id`),
  CONSTRAINT `compra_movimiento_compra_id_foreign`
    FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `compra_movimiento_movimiento_id_foreign`
    FOREIGN KEY (`movimiento_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M05: create_gastos_table (2026-05-25)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `gastos` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha`            DATE            NOT NULL,
  `descripcion`      VARCHAR(255)    NOT NULL,
  `categoria`        VARCHAR(100)    NULL,
  `monto`            DECIMAL(12,2)   NOT NULL,
  `proveedor`        VARCHAR(255)    NULL,
  `numero_documento` VARCHAR(100)    NULL,
  `notas`            TEXT            NULL,
  `created_at`       TIMESTAMP       NULL,
  `updated_at`       TIMESTAMP       NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M06: create_gasto_movimiento_table (2026-05-25)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `gasto_movimiento` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gasto_id`     BIGINT UNSIGNED NOT NULL,
  `movimiento_id` BIGINT UNSIGNED NOT NULL,
  `monto`        DECIMAL(12,2)   NOT NULL,
  `created_at`   TIMESTAMP       NULL,
  `updated_at`   TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gasto_movimiento_gasto_id_movimiento_id_unique` (`gasto_id`,`movimiento_id`),
  CONSTRAINT `gasto_movimiento_gasto_id_foreign`
    FOREIGN KEY (`gasto_id`) REFERENCES `gastos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gasto_movimiento_movimiento_id_foreign`
    FOREIGN KEY (`movimiento_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M07: create_venta_movimiento_table (2026-05-25)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `venta_movimiento` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `venta_id`     BIGINT UNSIGNED NOT NULL,
  `movimiento_id` BIGINT UNSIGNED NOT NULL,
  `monto`        DECIMAL(12,2)   NOT NULL,
  `created_at`   TIMESTAMP       NULL,
  `updated_at`   TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `venta_movimiento_venta_id_movimiento_id_unique` (`venta_id`,`movimiento_id`),
  CONSTRAINT `venta_movimiento_venta_id_foreign`
    FOREIGN KEY (`venta_id`) REFERENCES `documentos_facturacion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venta_movimiento_movimiento_id_foreign`
    FOREIGN KEY (`movimiento_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M08: update_documentos_facturacion_for_bsale_sync (2026-05-25)
-- ──────────────────────────────────────────────────────────
-- Hacer cotizacion_id nullable (idempotente)
ALTER TABLE `documentos_facturacion` MODIFY `cotizacion_id` BIGINT UNSIGNED NULL;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'cliente_id') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `cliente_id` BIGINT UNSIGNED NULL AFTER `cotizacion_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK para cliente_id
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion'
     AND CONSTRAINT_NAME = 'documentos_facturacion_cliente_id_foreign'
     AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
  'ALTER TABLE `documentos_facturacion` ADD CONSTRAINT `documentos_facturacion_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'bsale_cliente_rut') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `bsale_cliente_rut` VARCHAR(30) NULL AFTER `cliente_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'bsale_cliente_nombre') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `bsale_cliente_nombre` VARCHAR(255) NULL AFTER `bsale_cliente_rut`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'neto') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `neto` BIGINT NULL AFTER `monto`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'tipo_documento_bsale_id') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `tipo_documento_bsale_id` INT NULL AFTER `neto`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M09: create_transbank_tables (2026-05-25)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transbank_archivos` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periodo`               VARCHAR(7)      NOT NULL,
  `tipo`                  ENUM('credito','debito','prepago') NOT NULL,
  `nombre_archivo`        VARCHAR(255)    NOT NULL,
  `rut_empresa`           VARCHAR(20)     NULL,
  `total_ventas`          BIGINT          NOT NULL DEFAULT 0,
  `total_comision`        BIGINT          NOT NULL DEFAULT 0,
  `total_iva_comision`    BIGINT          NOT NULL DEFAULT 0,
  `total_servicio`        BIGINT          NOT NULL DEFAULT 0,
  `total_iva_servicio`    BIGINT          NOT NULL DEFAULT 0,
  `total_abono`           BIGINT          NOT NULL DEFAULT 0,
  `cantidad_transacciones` INT            NOT NULL DEFAULT 0,
  `created_at`            TIMESTAMP       NULL,
  `updated_at`            TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transbank_archivos_periodo_tipo_unique` (`periodo`,`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transbank_abonos` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `archivo_id`           BIGINT UNSIGNED NOT NULL,
  `fecha_abono`          DATE            NOT NULL,
  `total_venta_bruta`    BIGINT          NOT NULL DEFAULT 0,
  `total_comision`       BIGINT          NOT NULL DEFAULT 0,
  `total_iva_comision`   BIGINT          NOT NULL DEFAULT 0,
  `total_venta_neta`     BIGINT          NOT NULL DEFAULT 0,
  `total_servicio`       BIGINT          NOT NULL DEFAULT 0,
  `net_abono`            BIGINT          NOT NULL DEFAULT 0,
  `movimiento_bancario_id` BIGINT UNSIGNED NULL,
  `created_at`           TIMESTAMP       NULL,
  `updated_at`           TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  INDEX `transbank_abonos_archivo_id_fecha_abono_index` (`archivo_id`,`fecha_abono`),
  CONSTRAINT `transbank_abonos_archivo_id_foreign`
    FOREIGN KEY (`archivo_id`) REFERENCES `transbank_archivos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transbank_abonos_movimiento_bancario_id_foreign`
    FOREIGN KEY (`movimiento_bancario_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transbank_transacciones` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `abono_id`            BIGINT UNSIGNED NOT NULL,
  `tipo`                ENUM('Venta','Servicio','Anulacion') NOT NULL,
  `fecha_movimiento`    DATETIME        NULL,
  `tipo_tarjeta`        VARCHAR(30)     NULL,
  `monto_original`      BIGINT          NOT NULL DEFAULT 0,
  `monto_comision`      BIGINT          NOT NULL DEFAULT 0,
  `iva_comision`        BIGINT          NOT NULL DEFAULT 0,
  `total_abono`         BIGINT          NOT NULL DEFAULT 0,
  `monto_servicio`      BIGINT          NOT NULL DEFAULT 0,
  `iva_servicio`        BIGINT          NOT NULL DEFAULT 0,
  `nro_voucher`         VARCHAR(30)     NULL,
  `codigo_autorizacion` VARCHAR(20)     NULL,
  `tipo_documento`      VARCHAR(20)     NULL,
  `nro_tarjeta`         VARCHAR(30)     NULL,
  `created_at`          TIMESTAMP       NULL,
  `updated_at`          TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  INDEX `transbank_transacciones_abono_id_index` (`abono_id`),
  CONSTRAINT `transbank_transacciones_abono_id_foreign`
    FOREIGN KEY (`abono_id`) REFERENCES `transbank_abonos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M10: create_transbank_factura_table (2026-05-25)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transbank_factura` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaccion_id` BIGINT UNSIGNED NOT NULL,
  `documento_id`   BIGINT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP       NULL,
  `updated_at`     TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transbank_factura_transaccion_id_unique` (`transaccion_id`),
  UNIQUE KEY `transbank_factura_documento_id_unique` (`documento_id`),
  CONSTRAINT `transbank_factura_transaccion_id_foreign`
    FOREIGN KEY (`transaccion_id`) REFERENCES `transbank_transacciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transbank_factura_documento_id_foreign`
    FOREIGN KEY (`documento_id`) REFERENCES `documentos_facturacion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M11: add_nro_comprobante_to_documentos_facturacion (2026-05-25)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'nro_comprobante_transbank') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `nro_comprobante_transbank` VARCHAR(50) NULL AFTER `tipo_documento_bsale_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M12: add_pagado_con_tarjeta_to_documentos_facturacion (2026-05-26)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'pagado_con_tarjeta') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `pagado_con_tarjeta` TINYINT(1) NULL DEFAULT NULL AFTER `nro_comprobante_transbank`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Marcar docs con comprobante como pagados con tarjeta
UPDATE `documentos_facturacion`
SET `pagado_con_tarjeta` = 1
WHERE `nro_comprobante_transbank` IS NOT NULL
  AND `pagado_con_tarjeta` IS NULL;

-- ──────────────────────────────────────────────────────────
-- M13: create_ingresos_manuales_table (2026-05-26)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ingresos_manuales` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha`       DATE            NOT NULL,
  `descripcion` VARCHAR(255)    NULL,
  `monto`       DECIMAL(12,2)   NOT NULL,
  `categoria`   VARCHAR(255)    NOT NULL DEFAULT 'Ingreso',
  `notas`       TEXT            NULL,
  `created_at`  TIMESTAMP       NULL,
  `updated_at`  TIMESTAMP       NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ingreso_movimiento` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ingreso_id`   BIGINT UNSIGNED NOT NULL,
  `movimiento_id` BIGINT UNSIGNED NOT NULL,
  `monto`        DECIMAL(12,2)   NOT NULL,
  `created_at`   TIMESTAMP       NULL,
  `updated_at`   TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ingreso_movimiento_ingreso_id_movimiento_id_unique` (`ingreso_id`,`movimiento_id`),
  CONSTRAINT `ingreso_movimiento_ingreso_id_foreign`
    FOREIGN KEY (`ingreso_id`) REFERENCES `ingresos_manuales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ingreso_movimiento_movimiento_id_foreign`
    FOREIGN KEY (`movimiento_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M14: add_winperfil_sync_to_cotizaciones (2026-05-26)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cotizaciones' AND COLUMN_NAME = 'winperfil_numero') = 0,
  'ALTER TABLE `cotizaciones` ADD COLUMN `winperfil_numero` INT UNSIGNED NULL AFTER `adjunto_winperfil`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cotizaciones' AND COLUMN_NAME = 'winperfil_serie') = 0,
  'ALTER TABLE `cotizaciones` ADD COLUMN `winperfil_serie` CHAR(1) NULL AFTER `winperfil_numero`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cotizaciones' AND COLUMN_NAME = 'winperfil_synced_at') = 0,
  'ALTER TABLE `cotizaciones` ADD COLUMN `winperfil_synced_at` TIMESTAMP NULL AFTER `winperfil_serie`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cotizaciones' AND INDEX_NAME = 'uq_cotizacion_winperfil') = 0,
  'ALTER TABLE `cotizaciones` ADD UNIQUE KEY `uq_cotizacion_winperfil` (`winperfil_numero`,`winperfil_serie`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `winperfil_pedidos` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cotizacion_id`       BIGINT UNSIGNED NULL,
  `numero_presupuesto`  INT UNSIGNED    NOT NULL,
  `serie`               CHAR(1)         NOT NULL,
  `codigo_enlace`       VARCHAR(255)    NULL,
  `codigo_fase`         VARCHAR(255)    NULL,
  `base`                DECIMAL(12,2)   NULL,
  `iva`                 DECIMAL(5,2)    NULL,
  `estado_general`      VARCHAR(50)     NULL,
  `estado_produccion`   VARCHAR(50)     NULL,
  `raw_data`            JSON            NULL,
  `created_at`          TIMESTAMP       NULL,
  `updated_at`          TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_winperfil_pedido` (`numero_presupuesto`,`serie`),
  INDEX `winperfil_pedidos_cotizacion_id_index` (`cotizacion_id`),
  CONSTRAINT `winperfil_pedidos_cotizacion_id_foreign`
    FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M15: add_winperfil_grafico_to_cotizacion_detalles (2026-05-26)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cotizacion_detalles' AND COLUMN_NAME = 'winperfil_grafico') = 0,
  'ALTER TABLE `cotizacion_detalles` ADD COLUMN `winperfil_grafico` MEDIUMTEXT NULL AFTER `pulido`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M16: add_winperfil_grafico_png_to_cotizacion_detalles (2026-05-27)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cotizacion_detalles' AND COLUMN_NAME = 'winperfil_grafico_png') = 0,
  'ALTER TABLE `cotizacion_detalles` ADD COLUMN `winperfil_grafico_png` MEDIUMTEXT NULL AFTER `winperfil_grafico`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M17: add_chipax_id_to_movimientos_bancarios (2026-05-27)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos_bancarios' AND COLUMN_NAME = 'chipax_id') = 0,
  'ALTER TABLE `movimientos_bancarios` ADD COLUMN `chipax_id` BIGINT UNSIGNED NULL AFTER `id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos_bancarios' AND INDEX_NAME = 'movimientos_bancarios_chipax_id_unique') = 0,
  'ALTER TABLE `movimientos_bancarios` ADD UNIQUE KEY `movimientos_bancarios_chipax_id_unique` (`chipax_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos_bancarios' AND COLUMN_NAME = 'chipax_cuenta_id') = 0,
  'ALTER TABLE `movimientos_bancarios` ADD COLUMN `chipax_cuenta_id` INT UNSIGNED NULL AFTER `chipax_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M18: drop_mov_unique_from_movimientos_bancarios (2026-05-27)
-- ──────────────────────────────────────────────────────────
-- Primero agregar índice de búsqueda (no-unique)
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos_bancarios' AND INDEX_NAME = 'mov_busqueda') = 0,
  'ALTER TABLE `movimientos_bancarios` ADD INDEX `mov_busqueda` (`cuenta`,`fecha_contable`,`monto`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Luego eliminar el unique compuesto (si aún existe)
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos_bancarios' AND INDEX_NAME = 'mov_unique') > 0,
  'ALTER TABLE `movimientos_bancarios` DROP INDEX `mov_unique`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M19: add_pagado_historico_to_compras_table (2026-05-27)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compras' AND COLUMN_NAME = 'pagado_historico') = 0,
  'ALTER TABLE `compras` ADD COLUMN `pagado_historico` TINYINT(1) NOT NULL DEFAULT 0 AFTER `estado`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compras' AND COLUMN_NAME = 'fecha_pago_historico') = 0,
  'ALTER TABLE `compras` ADD COLUMN `fecha_pago_historico` DATE NULL AFTER `pagado_historico`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compras' AND COLUMN_NAME = 'nota_historico') = 0,
  'ALTER TABLE `compras` ADD COLUMN `nota_historico` VARCHAR(255) NULL AFTER `fecha_pago_historico`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Marcar facturas de 2024 y anteriores como históricas (si aún no se hizo)
UPDATE `compras`
SET
  `pagado_historico`     = 1,
  `fecha_pago_historico` = `fecha_emision`,
  `nota_historico`       = 'Historial pre-conciliación (anterior a 2025)'
WHERE YEAR(`fecha_emision`) <= 2024
  AND `pagado_historico` = 0;

-- ──────────────────────────────────────────────────────────
-- M20: add_chipax_id_to_gastos_and_ingresos (2026-05-28)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos' AND COLUMN_NAME = 'chipax_id') = 0,
  'ALTER TABLE `gastos` ADD COLUMN `chipax_id` BIGINT UNSIGNED NULL AFTER `id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos' AND COLUMN_NAME = 'chipax_proveedor') = 0,
  'ALTER TABLE `gastos` ADD COLUMN `chipax_proveedor` VARCHAR(255) NULL AFTER `chipax_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingresos_manuales' AND COLUMN_NAME = 'chipax_id') = 0,
  'ALTER TABLE `ingresos_manuales` ADD COLUMN `chipax_id` BIGINT UNSIGNED NULL AFTER `id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingresos_manuales' AND COLUMN_NAME = 'chipax_folio') = 0,
  'ALTER TABLE `ingresos_manuales` ADD COLUMN `chipax_folio` INT UNSIGNED NULL AFTER `chipax_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Unique para ingresos_manuales.chipax_id
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingresos_manuales' AND INDEX_NAME = 'ingresos_manuales_chipax_id_unique') = 0,
  'ALTER TABLE `ingresos_manuales` ADD UNIQUE KEY `ingresos_manuales_chipax_id_unique` (`chipax_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M21: add_chipax_fields_to_empleados_and_pagos (2026-05-28)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleados' AND COLUMN_NAME = 'chipax_id') = 0,
  'ALTER TABLE `empleados` ADD COLUMN `chipax_id` BIGINT UNSIGNED NULL AFTER `id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleados' AND INDEX_NAME = 'empleados_chipax_id_unique') = 0,
  'ALTER TABLE `empleados` ADD UNIQUE KEY `empleados_chipax_id_unique` (`chipax_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagos_empleado' AND COLUMN_NAME = 'chipax_remuneracion_id') = 0,
  'ALTER TABLE `pagos_empleado` ADD COLUMN `chipax_remuneracion_id` BIGINT UNSIGNED NULL AFTER `id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M22: add_pagado_historico_to_gastos_and_ingresos (2026-05-28)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos' AND COLUMN_NAME = 'pagado_historico') = 0,
  'ALTER TABLE `gastos` ADD COLUMN `pagado_historico` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notas`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos' AND COLUMN_NAME = 'fecha_pago_historico') = 0,
  'ALTER TABLE `gastos` ADD COLUMN `fecha_pago_historico` DATE NULL AFTER `pagado_historico`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingresos_manuales' AND COLUMN_NAME = 'pagado_historico') = 0,
  'ALTER TABLE `ingresos_manuales` ADD COLUMN `pagado_historico` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notas`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingresos_manuales' AND COLUMN_NAME = 'fecha_pago_historico') = 0,
  'ALTER TABLE `ingresos_manuales` ADD COLUMN `fecha_pago_historico` DATE NULL AFTER `pagado_historico`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `gastos`
SET `pagado_historico` = 1, `fecha_pago_historico` = `fecha`
WHERE `chipax_id` IS NOT NULL AND YEAR(`fecha`) <= 2024 AND `pagado_historico` = 0;

UPDATE `ingresos_manuales`
SET `pagado_historico` = 1, `fecha_pago_historico` = `fecha`
WHERE `chipax_id` IS NOT NULL AND YEAR(`fecha`) <= 2024 AND `pagado_historico` = 0;

-- ──────────────────────────────────────────────────────────
-- M23: add_chipax_tipo_to_gastos (2026-05-28)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos' AND COLUMN_NAME = 'chipax_tipo') = 0,
  'ALTER TABLE `gastos` ADD COLUMN `chipax_tipo` VARCHAR(30) NULL AFTER `chipax_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Eliminar unique simple en chipax_id (si existe, porque M20 lo creó sólo en ingresos, no en gastos)
-- En gastos la unique se crea aquí como composite
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos' AND INDEX_NAME = 'gastos_chipax_id_unique') > 0,
  'ALTER TABLE `gastos` DROP INDEX `gastos_chipax_id_unique`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Agregar unique compuesto (chipax_id, chipax_tipo)
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos' AND INDEX_NAME = 'gastos_chipax_id_tipo_unique') = 0,
  'ALTER TABLE `gastos` ADD UNIQUE KEY `gastos_chipax_id_tipo_unique` (`chipax_id`,`chipax_tipo`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Marcar gastos existentes importados de Chipax como tipo 'gasto'
UPDATE `gastos`
SET `chipax_tipo` = 'gasto'
WHERE `chipax_id` IS NOT NULL AND `chipax_tipo` IS NULL;

-- ──────────────────────────────────────────────────────────
-- M24: fix_pagos_empleado_unique (2026-05-28)
-- ──────────────────────────────────────────────────────────
-- Agregar índice simple en empleado_id (cubre la FK antes de quitar el composite)
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagos_empleado' AND INDEX_NAME = 'pagos_empleado_empleado_id_idx') = 0,
  'ALTER TABLE `pagos_empleado` ADD INDEX `pagos_empleado_empleado_id_idx` (`empleado_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Eliminar unique compuesto (empleado_id, periodo, tipo) si aún existe
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagos_empleado' AND INDEX_NAME = 'pagos_empleado_empleado_id_periodo_tipo_unique') > 0,
  'ALTER TABLE `pagos_empleado` DROP INDEX `pagos_empleado_empleado_id_periodo_tipo_unique`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Limpiar duplicados (el de mayor id sin chipax_remuneracion_id)
DELETE p1 FROM `pagos_empleado` p1
INNER JOIN `pagos_empleado` p2
  ON p1.empleado_id = p2.empleado_id
  AND p1.periodo = p2.periodo
  AND p1.tipo = p2.tipo
  AND p1.id > p2.id
WHERE p1.chipax_remuneracion_id IS NULL
  AND p2.chipax_remuneracion_id IS NOT NULL;

-- Agregar unique en chipax_remuneracion_id
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagos_empleado' AND INDEX_NAME = 'pagos_empleado_chipax_rem_unique') = 0,
  'ALTER TABLE `pagos_empleado` ADD UNIQUE KEY `pagos_empleado_chipax_rem_unique` (`chipax_remuneracion_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M25: change_glosa_to_text_in_movimientos_bancarios (2026-06-02)
-- ──────────────────────────────────────────────────────────
-- MODIFY es idempotente — ampliar de VARCHAR(255) a TEXT
ALTER TABLE `movimientos_bancarios` MODIFY `glosa` TEXT NULL;

-- ──────────────────────────────────────────────────────────
-- M26: add_fecha_hora_mov_to_movimientos_bancarios (2026-06-02)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movimientos_bancarios' AND COLUMN_NAME = 'fecha_hora_mov') = 0,
  'ALTER TABLE `movimientos_bancarios` ADD COLUMN `fecha_hora_mov` DATETIME NULL AFTER `fecha_contable`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M27: add_nc_fields_to_compras (2026-06-03)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compras' AND COLUMN_NAME = 'nc_referencia_id') = 0,
  'ALTER TABLE `compras` ADD COLUMN `nc_referencia_id` BIGINT UNSIGNED NULL AFTER `pagado_historico`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compras'
     AND CONSTRAINT_NAME = 'compras_nc_referencia_id_foreign'
     AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
  'ALTER TABLE `compras` ADD CONSTRAINT `compras_nc_referencia_id_foreign` FOREIGN KEY (`nc_referencia_id`) REFERENCES `compras` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compras' AND COLUMN_NAME = 'nc_revision_estado') = 0,
  "ALTER TABLE `compras` ADD COLUMN `nc_revision_estado` ENUM('requiere_revision','reembolso_pendiente','aplicado','ignorado') NULL AFTER `nc_referencia_id`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M28: add_nc_referencia_to_documentos_facturacion (2026-06-03)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'nc_referencia_df_id') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `nc_referencia_df_id` BIGINT UNSIGNED NULL AFTER `tipo_documento_bsale_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion'
     AND CONSTRAINT_NAME = 'documentos_facturacion_nc_referencia_df_id_foreign'
     AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
  'ALTER TABLE `documentos_facturacion` ADD CONSTRAINT `documentos_facturacion_nc_referencia_df_id_foreign` FOREIGN KEY (`nc_referencia_df_id`) REFERENCES `documentos_facturacion` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'nc_revision_estado') = 0,
  "ALTER TABLE `documentos_facturacion` ADD COLUMN `nc_revision_estado` ENUM('requiere_revision','reembolso_pendiente','aplicado','ignorado') NULL AFTER `nc_referencia_df_id`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M29: create_compra_nc_aplicacion_table (2026-06-03)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `compra_nc_aplicacion` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nc_id`      BIGINT UNSIGNED NOT NULL,
  `factura_id` BIGINT UNSIGNED NOT NULL,
  `monto`      DECIMAL(12,2)   NOT NULL,
  `fecha`      DATE            NOT NULL,
  `nota`       VARCHAR(500)    NULL,
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `compra_nc_aplicacion_nc_id_foreign`
    FOREIGN KEY (`nc_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `compra_nc_aplicacion_factura_id_foreign`
    FOREIGN KEY (`factura_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M30: create_venta_nc_aplicacion_table (2026-06-03)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `venta_nc_aplicacion` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nc_id`      BIGINT UNSIGNED NOT NULL,
  `factura_id` BIGINT UNSIGNED NOT NULL,
  `monto`      DECIMAL(12,2)   NOT NULL,
  `fecha`      DATE            NOT NULL,
  `nota`       VARCHAR(500)    NULL,
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `venta_nc_aplicacion_nc_id_foreign`
    FOREIGN KEY (`nc_id`) REFERENCES `documentos_facturacion` (`id`) ON DELETE CASCADE,
  CONSTRAINT `venta_nc_aplicacion_factura_id_foreign`
    FOREIGN KEY (`factura_id`) REFERENCES `documentos_facturacion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  REGISTRAR EN TABLA migrations (para que artisan migrate las omita)
-- ============================================================
SET @batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES
  ('2026_05_23_144749_create_reglas_conciliacion_table',              @batch),
  ('2026_05_23_150455_create_empleados_table',                        @batch),
  ('2026_05_23_162128_add_glosa_to_movimientos_bancarios',            @batch),
  ('2026_05_23_164048_create_compra_movimiento_table',                @batch),
  ('2026_05_25_142029_create_gastos_table',                           @batch),
  ('2026_05_25_142030_create_gasto_movimiento_table',                 @batch),
  ('2026_05_25_180000_create_venta_movimiento_table',                 @batch),
  ('2026_05_25_200000_update_documentos_facturacion_for_bsale_sync',  @batch),
  ('2026_05_25_210000_create_transbank_tables',                       @batch),
  ('2026_05_25_220000_create_transbank_factura_table',                @batch),
  ('2026_05_25_230000_add_nro_comprobante_to_documentos_facturacion', @batch),
  ('2026_05_26_000000_add_pagado_con_tarjeta_to_documentos_facturacion', @batch),
  ('2026_05_26_100000_create_ingresos_manuales_table',                @batch),
  ('2026_05_26_200000_add_winperfil_sync_to_cotizaciones',            @batch),
  ('2026_05_26_210000_add_winperfil_grafico_to_cotizacion_detalles',  @batch),
  ('2026_05_27_152721_add_winperfil_grafico_png_to_cotizacion_detalles', @batch),
  ('2026_05_27_170447_add_chipax_id_to_movimientos_bancarios',        @batch),
  ('2026_05_27_193618_drop_mov_unique_from_movimientos_bancarios',    @batch),
  ('2026_05_27_235401_add_pagado_historico_to_compras_table',         @batch),
  ('2026_05_28_000001_add_chipax_id_to_gastos_and_ingresos',         @batch),
  ('2026_05_28_000002_add_chipax_fields_to_empleados_and_pagos',     @batch),
  ('2026_05_28_000003_add_pagado_historico_to_gastos_and_ingresos',  @batch),
  ('2026_05_28_100000_add_chipax_tipo_to_gastos',                     @batch),
  ('2026_05_28_200000_fix_pagos_empleado_unique',                     @batch),
  ('2026_06_02_200000_change_glosa_to_text_in_movimientos_bancarios', @batch),
  ('2026_06_02_220000_add_fecha_hora_mov_to_movimientos_bancarios',   @batch),
  ('2026_06_03_164418_add_nc_fields_to_compras',                      @batch),
  ('2026_06_03_164419_add_nc_referencia_to_documentos_facturacion',   @batch),
  ('2026_06_03_164420_create_compra_nc_aplicacion_table',             @batch),
  ('2026_06_03_164420_create_venta_nc_aplicacion_table',              @batch);

-- ============================================================
--  FIN DEL SCRIPT
-- ============================================================
SET SQL_SAFE_UPDATES = 1;
SELECT 'Migraciones aplicadas correctamente' AS resultado;
