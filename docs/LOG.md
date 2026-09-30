# Registro de trabajo

Entradas más recientes arriba. Formato:

## AAAA-MM-DD — Título corto
- **Qué se hizo:** 1-3 líneas.
- **Archivos principales:** rutas clave.
- **Pendiente / ojo con:** lo que quedó abierto, o "nada".

---

## 2026-09-30 — Prompt caching en servicios de IA
- **Qué se hizo:** Se agregó `cache_control` (ephemeral) al system prompt + tools de `AgenteLeadsService` e `IaProduccionService` para bajar el gasto de API directa (la consola de Anthropic marcó "low cache hit rate", ahorro estimado hasta ~46%). En Producción el system se dividió en dos bloques: estático (cacheado, incluye las tools por prefijo) y contexto en vivo (sin cachear, va después del breakpoint).
- **Archivos principales:** `app/Services/AgenteLeadsService.php`, `app/Services/IaProduccionService.php`.
- **Pendiente / ojo con:** Solo surte efecto en Railway (prod es quien gasta). Falta deploy. TTL de caché es 5 min; el mayor ahorro es dentro del loop de tools y en conversaciones activas.
