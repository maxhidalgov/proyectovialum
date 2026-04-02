-- Insertar estados faltantes en estados_cotizacion
-- Ejecutar en MySQL Workbench conectado a Railway

INSERT INTO estados_cotizacion (nombre, created_at, updated_at)
SELECT 'En Producción', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM estados_cotizacion WHERE nombre = 'En Producción');

INSERT INTO estados_cotizacion (nombre, created_at, updated_at)
SELECT 'Entregada', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM estados_cotizacion WHERE nombre = 'Entregada');

-- Verificar resultado
SELECT * FROM estados_cotizacion ORDER BY id;
