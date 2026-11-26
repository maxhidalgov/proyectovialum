-- ============================================================================
-- Consultas de SOLO LECTURA (SELECT) — agente cotizador / convención de precios
-- Idea del 2026-11-26. No modifican datos. Correr en el cliente SQL del usuario
-- `lector_claude` o en phpMyAdmin (BD local o producción, según se necesite).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) ¿Cuántas ventanas se han cotizado por tipo? (para elegir los tipos de la v1)
--    Nota: las cotizaciones importadas de Winperfil NO usan la tabla `ventanas`
--    (van en cotizacion_detalles con tipo_item = 'winperfil'); ver 1b.
-- ----------------------------------------------------------------------------
SELECT
    tv.id                                              AS tipo_id,
    COALESCE(tv.nombre, '(tipo borrado)')              AS tipo,
    COUNT(*)                                           AS lineas,
    SUM(v.cantidad)                                    AS unidades,
    COUNT(DISTINCT v.cotizacion_id)                    AS cotizaciones,
    SUM(v.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)) AS lineas_ultimos_12m,
    SUM(v.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH))  AS lineas_ultimos_3m,
    ROUND(100 * COUNT(*) / (SELECT COUNT(*) FROM ventanas), 1)  AS pct_de_lineas,
    ROUND(AVG(v.ancho))                                AS ancho_prom_mm,
    ROUND(AVG(v.alto))                                 AS alto_prom_mm,
    MIN(v.ancho) AS ancho_min, MAX(v.ancho) AS ancho_max,
    MIN(v.alto)  AS alto_min,  MAX(v.alto)  AS alto_max
FROM ventanas v
LEFT JOIN tipos_ventana tv ON tv.id = v.tipo_ventana_id
GROUP BY tv.id, tv.nombre
ORDER BY lineas DESC;

-- 1b) Líneas Winperfil (otra tabla) para tener el panorama completo
SELECT tipo_item, COUNT(*) AS lineas, COUNT(DISTINCT cotizacion_id) AS cotizaciones
FROM cotizacion_detalles
GROUP BY tipo_item
ORDER BY lineas DESC;

-- ----------------------------------------------------------------------------
-- 2) Cotizaciones creadas por el agente
--    CORRECCIÓN (2026-11-26): una versión anterior de esta consulta detectaba al agente por la forma
--    del `config` (claves tipo_vidrio + manillon). Esa señal NO es exclusiva: el cotizador normal
--    también guarda `manillon:false` (array_filter con `!== null` conserva los false) y deja un config
--    igual cuando no hay proveedor_vidrio. En producción dio 93 "candidatas" que eran del cotizador.
--    Las cotizaciones antiguas del agente NO tienen una marca fiable de origen.
--    Desde la corrección, el agente guarda config.origen = 'agente' (y proveedor_vidrio):
-- ----------------------------------------------------------------------------
SELECT c.id AS cotizacion, c.fecha, c.created_at, c.vendedor_id, ec.nombre AS estado, c.total,
       SUM(v.precio) AS suma_precio, COUNT(*) AS lineas, MAX(v.cantidad) AS max_cantidad
FROM cotizaciones c
JOIN ventanas v ON v.cotizacion_id = c.id
LEFT JOIN estados_cotizacion ec ON ec.id = c.estado_cotizacion_id
WHERE JSON_UNQUOTE(JSON_EXTRACT(v.config, '$.origen')) = 'agente'
GROUP BY c.id, c.fecha, c.created_at, c.vendedor_id, ec.nombre, c.total
ORDER BY c.id DESC;

-- ----------------------------------------------------------------------------
-- 3) Panorama general del descuadre en TODAS las cotizaciones (no solo del agente):
--    cantidad > 1 con total ≠ Σ precio, para ver si el problema existe fuera del agente.
-- ----------------------------------------------------------------------------
SELECT c.id, c.fecha, c.total, SUM(v.precio) AS suma_precio, SUM(v.precio * v.cantidad) AS suma_precio_x_cant,
       MAX(v.cantidad) AS max_cantidad
FROM cotizaciones c
JOIN ventanas v ON v.cotizacion_id = c.id
WHERE NOT EXISTS (SELECT 1 FROM cotizacion_detalles d WHERE d.cotizacion_id = c.id)
GROUP BY c.id, c.fecha, c.total
HAVING MAX(v.cantidad) > 1 AND ABS(c.total - SUM(v.precio)) >= 1
ORDER BY c.id DESC;

-- ----------------------------------------------------------------------------
-- 4) ¿Qué tan poblados están precio_unitario / costo_unitario? (decide si se puede
--    confiar en ellos o hay que derivarlos de precio / cantidad)
-- ----------------------------------------------------------------------------
SELECT
    COUNT(*)                                                    AS lineas,
    SUM(precio_unitario IS NULL)                                AS sin_precio_unitario,
    SUM(costo_unitario  IS NULL)                                AS sin_costo_unitario,
    SUM(cantidad > 1)                                           AS lineas_cantidad_mayor_1,
    SUM(cantidad > 1 AND precio_unitario IS NOT NULL
        AND ABS(precio_unitario * cantidad - precio) > cantidad) AS cantidad_mayor_1_unitario_no_cuadra
FROM ventanas;
