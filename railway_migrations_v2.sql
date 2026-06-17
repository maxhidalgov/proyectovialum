-- ============================================================
--  ProyectoVialum — Migraciones pendientes para Railway
--  Generado: 2026-06-12
--  Cubre: 2026-06-08 → 2026-06-12
--  (Requiere haber ejecutado railway_migrations.sql primero)
--
--  INSTRUCCIONES:
--   1. Abrir MySQL Workbench conectado a Railway
--   2. Seleccionar la BD (USE nombre_bd si es necesario)
--   3. Ejecutar este script completo
--   4. Todos los ALTER TABLE son condicionales — seguro re-ejecutar
-- ============================================================

SET SQL_SAFE_UPDATES = 0;

-- ──────────────────────────────────────────────────────────
-- M31: add_monto_cobrado_manual_to_documentos_facturacion (2026-06-08)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'monto_cobrado_manual') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `monto_cobrado_manual` DECIMAL(14,2) NULL AFTER `pagado_con_tarjeta`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'cobrado_manual_nota') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `cobrado_manual_nota` VARCHAR(255) NULL AFTER `monto_cobrado_manual`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M32: add_chipax_monto_por_cobrar_to_documentos_facturacion (2026-06-08)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'chipax_monto_por_cobrar') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `chipax_monto_por_cobrar` DECIMAL(14,2) NULL AFTER `cobrado_manual_nota`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'chipax_cobranza_synced_at') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `chipax_cobranza_synced_at` TIMESTAMP NULL AFTER `chipax_monto_por_cobrar`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M33: add_forma_pago_to_documentos_facturacion (2026-06-10)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'payment_type_id') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `payment_type_id` TINYINT UNSIGNED NULL AFTER `chipax_cobranza_synced_at`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documentos_facturacion' AND COLUMN_NAME = 'forma_pago') = 0,
  'ALTER TABLE `documentos_facturacion` ADD COLUMN `forma_pago` VARCHAR(30) NULL AFTER `payment_type_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M34: create_boleta_resumenes_tables (2026-06-10)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `boleta_resumenes` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periodo`         VARCHAR(7)      NOT NULL,
  `forma_pago`      VARCHAR(30)     NOT NULL,
  `total_boletas`   INT UNSIGNED    NOT NULL DEFAULT 0,
  `monto_total`     DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `conciliado`      TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP       NULL,
  `updated_at`      TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `boleta_resumenes_periodo_forma_pago_unique` (`periodo`,`forma_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `boleta_resumen_movimiento` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `boleta_resumen_id` BIGINT UNSIGNED NOT NULL,
  `movimiento_id`     BIGINT UNSIGNED NOT NULL,
  `monto`             DECIMAL(14,2)   NOT NULL,
  `created_at`        TIMESTAMP       NULL,
  `updated_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `boleta_resumen_movimiento_unique` (`boleta_resumen_id`,`movimiento_id`),
  CONSTRAINT `brm_boleta_resumen_id_foreign`
    FOREIGN KEY (`boleta_resumen_id`) REFERENCES `boleta_resumenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `brm_movimiento_id_foreign`
    FOREIGN KEY (`movimiento_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M35: create_boleta_periodo_movimiento_table (2026-06-10)
-- ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `boleta_periodo_movimiento` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periodo`       VARCHAR(7)      NOT NULL,
  `movimiento_id` BIGINT UNSIGNED NOT NULL,
  `monto`         DECIMAL(14,2)   NOT NULL,
  `created_at`    TIMESTAMP       NULL,
  `updated_at`    TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `boleta_periodo_movimiento_unique` (`periodo`,`movimiento_id`),
  CONSTRAINT `bpm_movimiento_id_foreign`
    FOREIGN KEY (`movimiento_id`) REFERENCES `movimientos_bancarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────
-- M36: add_conciliado_transbank_to_boleta_resumenes (2026-06-10)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'boleta_resumenes' AND COLUMN_NAME = 'conciliado_transbank') = 0,
  'ALTER TABLE `boleta_resumenes` ADD COLUMN `conciliado_transbank` TINYINT(1) NOT NULL DEFAULT 0 AFTER `conciliado`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M37: add_transbank_transaccion_id_to_ingresos_manuales (2026-06-11)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingresos_manuales' AND COLUMN_NAME = 'transbank_transaccion_id') = 0,
  'ALTER TABLE `ingresos_manuales` ADD COLUMN `transbank_transaccion_id` BIGINT UNSIGNED NULL AFTER `chipax_folio`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingresos_manuales'
     AND CONSTRAINT_NAME = 'ingresos_manuales_transbank_transaccion_id_foreign'
     AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
  'ALTER TABLE `ingresos_manuales` ADD CONSTRAINT `ingresos_manuales_transbank_transaccion_id_foreign` FOREIGN KEY (`transbank_transaccion_id`) REFERENCES `transbank_transacciones` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────
-- M38: add_categoria_to_compras + create_reglas_categoria_proveedor (2026-06-12)
-- ──────────────────────────────────────────────────────────
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compras' AND COLUMN_NAME = 'categoria') = 0,
  'ALTER TABLE `compras` ADD COLUMN `categoria` VARCHAR(100) NULL AFTER `estado`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `reglas_categoria_proveedor` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rut_emisor`    VARCHAR(20)     NOT NULL,
  `nombre_emisor` VARCHAR(255)    NULL,
  `categoria`     VARCHAR(100)    NOT NULL,
  `created_at`    TIMESTAMP       NULL,
  `updated_at`    TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reglas_categoria_proveedor_rut_emisor_unique` (`rut_emisor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aplicar reglas a compras existentes en Railway
UPDATE `compras` c
JOIN `reglas_categoria_proveedor` r ON LOWER(c.rut_emisor) = r.rut_emisor
SET c.categoria = r.categoria
WHERE c.categoria IS NULL;

-- ============================================================
--  REGISTRAR EN TABLA migrations (para que artisan migrate las omita)
-- ============================================================
SET @batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES
  ('2026_06_08_202712_add_monto_cobrado_manual_to_documentos_facturacion',       @batch),
  ('2026_06_08_222318_add_chipax_monto_por_cobrar_to_documentos_facturacion',    @batch),
  ('2026_06_10_165757_add_forma_pago_to_documentos_facturacion',                 @batch),
  ('2026_06_10_165819_create_boleta_resumenes_tables',                           @batch),
  ('2026_06_10_201932_create_boleta_periodo_movimiento_table',                   @batch),
  ('2026_06_10_230000_add_conciliado_transbank_to_boleta_resumenes',             @batch),
  ('2026_06_11_170019_add_transbank_transaccion_id_to_ingresos_manuales',        @batch),
  ('2026_06_12_164311_add_categoria_to_compras_and_create_reglas_proveedor',     @batch);

-- ============================================================
--  FIN DEL SCRIPT
-- ============================================================
SET SQL_SAFE_UPDATES = 1;
SELECT 'Migraciones v2 aplicadas correctamente' AS resultado;
