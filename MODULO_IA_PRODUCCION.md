# Módulo IA Producción — Diseño del Sistema

> Diseñado: 30 Junio 2026

---

## Concepto Central

El chat es la **interfaz principal de entrada**. No hay formularios para reportar ausencias, incidentes o avances — todo se dice en lenguaje natural y Claude lo estructura automáticamente.

Las tablas, dashboards y bitácoras son **vistas de salida** de lo que el chat va construyendo.

```
Entradas (lenguaje natural)     →    Claude interpreta    →    DB estructurada
  Chat en la app                                                      ↓
  Grupo Telegram interno                                    Vistas de salida:
                                                            - Bitácora del día
                                                            - Plan semanal
                                                            - Dashboard producción
                                                            - Incidentes abiertos
```

---

## Flujo de Ejemplo

```
Tú:     "Hoy no vino Juanito en la mañana"
Claude: Registra ausencia parcial AM → revisa tareas asignadas a Juan →
        "Anotado. Juan tenía el corte de la obra #123 (entrega jueves).
         ¿Se lo asigno a alguien o lo retoma en la tarde?"

Tú:     "Hay que ir a medir donde el cliente Rivas,
         van Pedrito y Javier mañana"
Claude: Registra visita de medición → empleados Pedro + Javier → fecha mañana →
        "Agendado. ¿A qué hora van? ¿Lo dejo como tarea pendiente de cotización?"

Tú:     "En la instalación de López se quebró un termopanel,
         hay que fabricar uno nuevo y volver"
Claude: Registra incidente → tipo rotura_vidrio → requiere_refabricación →
        "Registrado como incidente abierto. El termopanel de López era
         1200×900 (6+6). ¿Lo agrego como urgente para esta semana?"

Pedro (Telegram): "Terminé el corte de la obra López"
Claude: Actualiza etapa corte → completada → asignado Pedro →
        [Bot en grupo]: "✅ Registrado. Siguiente etapa: armado. ¿Quién la toma?"
```

---

## Arquitectura

### Backend (Laravel)

```
POST /api/ia/chat
  - Recibe: { mensaje: string, conversacion_id?: int }
  - Inyecta contexto: cotizaciones en producción, empleados, incidentes abiertos
  - Llama Claude API con tools
  - Ejecuta tool calls (escritura en DB)
  - Guarda mensaje + respuesta en historial
  - Retorna: { respuesta: string, acciones_ejecutadas: [] }

POST /api/telegram/webhook
  - Recibe mensajes del grupo Telegram
  - Los pasa al mismo pipeline que /api/ia/chat
  - Bot responde en el grupo si es relevante
```

### Tools que tiene Claude

| Tool | Descripción |
|---|---|
| `get_contexto_produccion` | Lee cotizaciones activas, etapas, empleados, incidentes |
| `registrar_ausencia` | Ausencia total o parcial de un empleado |
| `registrar_horas_extra` | Horas extra por empleado, cotización y tarea |
| `registrar_incidente` | Problema en producción o instalación |
| `registrar_visita` | Visita de medición o instalación con empleados asignados |
| `actualizar_etapa` | Avance de etapa de producción (inicio / completada) |
| `crear_pendiente` | Tarea de seguimiento con fecha límite |
| `get_historial` | Lee mensajes anteriores para mantener contexto |

---

## Base de Datos — Tablas Nuevas

```sql
-- Fecha de entrega en cotizaciones existente
ALTER TABLE cotizaciones
  ADD COLUMN fecha_entrega_prometida DATE NULL,
  ADD COLUMN fecha_entrega_real DATE NULL;

-- Etapas de producción por cotización
CREATE TABLE etapas_produccion (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  cotizacion_id BIGINT NOT NULL,
  etapa ENUM('corte','armado','vidriado','control','instalacion','entrega'),
  estado ENUM('pendiente','en_progreso','completado') DEFAULT 'pendiente',
  empleado_id BIGINT NULL,
  fecha_inicio DATE NULL,
  fecha_fin_estimada DATE NULL,
  fecha_fin_real DATE NULL,
  notas TEXT NULL,
  created_at TIMESTAMP, updated_at TIMESTAMP
);

-- Incidentes y problemas
CREATE TABLE incidentes_produccion (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  cotizacion_id BIGINT NULL,
  descripcion TEXT NOT NULL,
  tipo ENUM('rotura_vidrio','retraso','material_faltante','instalacion','otro'),
  estado ENUM('abierto','en_resolucion','resuelto') DEFAULT 'abierto',
  accion_requerida TEXT NULL,
  empleado_responsable_id BIGINT NULL,
  fecha_limite_resolucion DATE NULL,
  fecha_resuelto DATE NULL,
  created_at TIMESTAMP, updated_at TIMESTAMP
);

-- Registro de horas extra
CREATE TABLE horas_extra (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  empleado_id BIGINT NOT NULL,
  cotizacion_id BIGINT NULL,
  fecha DATE NOT NULL,
  horas DECIMAL(4,2) NOT NULL,
  descripcion TEXT NULL,
  created_at TIMESTAMP
);

-- Ausencias
CREATE TABLE ausencias_empleado (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  empleado_id BIGINT NOT NULL,
  fecha DATE NOT NULL,
  tipo ENUM('dia_completo','media_mañana','media_tarde','llegada_tarde'),
  motivo TEXT NULL,
  created_at TIMESTAMP
);

-- Visitas a clientes (mediciones, instalaciones)
CREATE TABLE visitas_cliente (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  cotizacion_id BIGINT NULL,
  cliente_id BIGINT NULL,
  tipo ENUM('medicion','instalacion','postventa','otro'),
  fecha DATE NOT NULL,
  hora TIME NULL,
  estado ENUM('programada','realizada','cancelada') DEFAULT 'programada',
  notas TEXT NULL,
  created_at TIMESTAMP, updated_at TIMESTAMP
);

-- Empleados asignados a visita (pivot)
CREATE TABLE visita_empleado (
  visita_id BIGINT,
  empleado_id BIGINT,
  PRIMARY KEY (visita_id, empleado_id)
);

-- Historial del chat IA
CREATE TABLE ia_mensajes (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  rol ENUM('user','assistant'),
  contenido TEXT NOT NULL,
  acciones_ejecutadas JSON NULL,  -- tools que se corrieron
  origen ENUM('app','telegram') DEFAULT 'app',
  created_at TIMESTAMP
);
```

---

## Etapas de Producción

| Etapa | Descripción |
|---|---|
| **Corte** | Perfiles cortados según hoja de cortes |
| **Armado** | Ensamble del marco y hojas |
| **Vidriado** | Instalación del vidrio / termopanel |
| **Control** | Revisión de calidad antes de salir |
| **Instalación** | Montaje en obra del cliente |
| **Entrega** | Confirmada y firmada por cliente |

---

## Integración WhatsApp

### Solución elegida: WAHA (self-hosted, open source)

**github.com/devlikeapro/waha**

WAHA es Baileys (la librería Node.js más popular para WhatsApp Web) empaquetado como contenedor Docker con REST API y webhooks listos. No hay que escribir código Node.js — solo desplegarlo y apuntar el webhook a Laravel.

**Ventajas:**
- Gratis, self-hosted, código abierto
- Control total de los datos (nada pasa por servidores de terceros)
- Soporta grupos sin restricciones
- Se despliega en Railway junto a la app Laravel

### Alternativas descartadas

| Opción | Por qué no |
|---|---|
| UltraMsg / ChatAPI | ~$15 USD/mes, datos en servidores externos |
| WhatsApp Business Cloud API (Meta) | No soporta bien grupos, verificación burocrática |
| Baileys directo (Node.js) | Requiere escribir microservicio propio |
| whatsapp-web.js | Usa Puppeteer (Chrome headless), pesado en RAM |

### Arquitectura

```
Grupo WhatsApp interno
        ↓
  WAHA (Docker en Railway)
  github.com/devlikeapro/waha
        ↓  POST webhook
  Laravel /api/whatsapp/webhook
        ↓
  Claude API  →  tools  →  DB
        ↓
  WAHA API  →  respuesta en el grupo (opcional)
```

### Setup

1. Agregar servicio Docker en Railway apuntando a `devlikeapro/waha`
2. Configurar variable de entorno en WAHA:
   ```
   WHATSAPP_HOOK_URL=https://proyectovialum.up.railway.app/api/whatsapp/webhook
   WHATSAPP_HOOK_EVENTS=message
   ```
3. Abrir la UI de WAHA → escanear QR con el número bot
4. Agregar ese número al grupo interno de WhatsApp
5. Laravel ya recibe los mensajes del grupo

### Payload que llega a Laravel

```json
{
  "event": "message",
  "session": "default",
  "payload": {
    "id": "...",
    "from": "56912345678@c.us",
    "to":   "56998765432-1234@g.us",
    "participant": "56912345678@c.us",
    "body": "Terminé el corte de la obra López",
    "fromMe": false,
    "timestamp": 1751234567
  }
}
```

### Mapeo número → empleado

WAHA entrega el número de WhatsApp del que escribe. Laravel lo cruza con la tabla `empleados` (campo `whatsapp` o `telefono`) para saber quién es:

```php
$numero = str_replace('@c.us', '', $payload['participant']);
$empleado = Empleado::where('telefono', 'like', "%{$numero}%")->first();
```

### Comportamiento del bot en el grupo

```
Modo silencioso (recomendado al inicio):
  - Escucha todo, registra sin interrumpir la conversación
  - Solo responde si hay ambigüedad o acción crítica

Modo reactivo (opcional):
  - Confirma registros importantes con ✅
  - Alerta cuando algo requiere acción inmediata
```

---

## Páginas Frontend Nuevas

| Página | Ruta | Descripción |
|---|---|---|
| `ia-produccion/index.vue` | `/ia-produccion` | Chat principal con IA (mismo pipeline que WhatsApp) |
| `bitacora/index.vue` | `/bitacora` | Log del día (solo lectura) |
| `produccion/kanban.vue` | `/produccion/kanban` | Etapas por cotización (vista tablero) |

### Chat IA (`/ia-produccion`)

- Interfaz tipo mensajería (burbuja usuario / burbuja IA)
- Historial persistente con fecha
- Indicador de acciones ejecutadas por mensaje ("✅ Ausencia registrada · ✅ Tarea reasignada")
- Panel lateral: resumen del día (incidentes abiertos, entregas próximas)

### Bitácora del Día (`/bitacora`)

Muestra cronológicamente todo lo registrado hoy:
```
08:30  👤 Ausencia — Juan Herrera (media mañana)
09:15  🔧 Etapa completada — Corte obra López (Pedro García)
10:40  ⚠️  Incidente — Termopanel roto en instalación Rivas
11:00  📍 Visita agendada — Medición cliente Martínez (Pedro + Javier, mañana 9AM)
```

---

## Métricas Calculables

| Métrica | Cómo |
|---|---|
| **m² producidos / semana** | `SUM(ancho × alto)` de etapas "entrega" completadas |
| **Tasa de cumplimiento** | Cotizaciones entregadas en fecha / total entregadas |
| **Tiempo promedio por cotización** | `fecha_entrega_real - fecha inicio etapa corte` |
| **Productividad por empleado** | m² en etapas completadas por empleado / período |
| **Incidentes por tipo** | COUNT agrupado por `tipo` en rango de fechas |

---

## Check-ins Diarios (Opcional)

Un trigger programado (lunes a viernes, 8:30 AM) envía un mensaje de check-in:

```
Buenos días. Resumen activo:

📦 En producción:
  #123 López      — entrega JUEVES ⚠️ (2 días)
  #118 Martínez   — entrega la próxima semana
  #131 Rivas      — sin etapas asignadas aún

⚠️  Incidentes abiertos: 1 (termopanel Rivas)

¿Qué pasó ayer? ¿Algún avance o novedad para hoy?
```

---

## Integración Workera (Reloj Control de Asistencia)

### Qué cambia con esta integración

Sin Workera, las ausencias y horas extra se reportan manualmente vía chat. Con la API de Workera, el sistema las lee automáticamente del reloj biométrico — el chat pasa de ser la **única fuente** a ser el **complemento** (para contexto, motivos, notas).

```
Reloj biométrico → Workera → API → Laravel → Claude → Acción
```

### Endpoints clave

| Endpoint | Uso en el sistema |
|---|---|
| `GET /employee` | Sync inicial de empleados hacia tabla local `empleados` |
| `GET /attendanceData` | Leer marcaciones diarias — detectar ausencias automáticamente |
| `GET /workshift/schedules` | Horario esperado por empleado — base para calcular ausencias |
| `GET /permission` | Leer permisos/licencias ya registradas en Workera |
| `POST /permission` | Registrar ausencia/permiso desde el chat directo en Workera |
| `GET /overtimeAuthorization` | Leer horas extra autorizadas |
| `POST /overtimeAuthorization` | Autorizar horas extra desde el chat |

### Autenticación

Headers en cada request:
```
API_USER: {valor desde perfil Workera}
API_KEY:  {valor desde perfil Workera}
```
Base URL: `https://api.workera.com/apiClient/v1/`

### Flujo de check-in automático diario

```
9:00 AM — Laravel job lee /attendanceData del día
         → Cruza con /workshift/schedules (horario esperado)
         → Detecta quién no marcó entrada

Claude recibe el resultado y genera alerta:
  "Juan Herrera no ha marcado entrada hoy (turno desde 08:00).
   Pedro García llegó 45 min tarde.
   ¿Ausencia justificada para Juan? ¿Anoto el atraso de Pedro?"

Tú respondes en el chat
  → Claude registra motivo en DB local
  → Opcionalmente POST /permission en Workera si corresponde
```

### Cálculo automático de horas extra

```
Horas trabajadas reales  =  hora_salida - hora_entrada  (de /attendanceData)
Horas del turno          =  start - end  (de /workshift/schedules)
Horas extra              =  max(0, trabajadas - turno)
```

Con esto, cuando alguien dice "Juan hizo horas extra hoy en el corte de la obra López", Claude puede:
1. Verificar cuántas horas extra reales marcó Juan en el reloj
2. Registrarlas asociadas a la cotización correspondiente
3. Hacer `POST /overtimeAuthorization` si se requiere autorización formal

### Sync de empleados

Al inicializar o periódicamente:
```
GET /employee?page=1
  → Para cada empleado: upsert en tabla `empleados` local
  → Mapeo: code → workera_code, name+lastName → nombre, identification → rut
```

Agregar columna `workera_code` a tabla `empleados` para cruzar datos.

### Tabla a agregar

```sql
ALTER TABLE empleados ADD COLUMN workera_code VARCHAR(50) NULL;
```

---

## Fases de Implementación

| Fase | Qué se construye |
|---|---|
| **1** | Migraciones: tablas nuevas + `fecha_entrega_prometida` en cotizaciones |
| **2** | Backend: `IaProduccionController` + Claude API + tools básicos |
| **3** | Frontend: página de chat `/ia-produccion` |
| **4** | *(más adelante)* Bot WhatsApp (WAHA): webhook + mismo pipeline que el chat |
| **5** | Bitácora del día + Dashboard enriquecido con métricas |
| **6** | Check-ins diarios programados |
| **7** | Plan semanal generado por IA + asignación de tareas |
