# 🏗️ Armador Universal de Ventanas

Sistema interactivo para diseñar ventanas compuestas personalizadas con divisiones recursivas y selección de perfiles.

## 📋 Características Implementadas

### ✅ Fase 1: Estructura Base con 1 Nivel de Subdivisión

1. **Canvas Interactivo Konva.js**
   - Visualización en tiempo real de la ventana
   - Secciones seleccionables y clickeables
   - Indicadores visuales de hover y selección

2. **Selector de Perfiles Personalizados**
   - Perfil de marco exterior (filtrado por `tipo_producto_id`)
   - Perfil divisor horizontal
   - Perfil divisor vertical
   - Autocomplete con información del producto (nombre y código)

3. **Sistema de Divisiones**
   - División horizontal (apila secciones verticalmente)
   - División vertical (coloca secciones lado a lado)
   - Divisiones recursivas (cada sección puede subdividirse)
   - Distribución proporcional por porcentaje

4. **Asignación de Tipos de Ventana**
   - Cada sección puede ser "rellenada" con un tipo de ventana
   - Compatible con tipos: Fija, Proyectante, Corredera, Abatible, Puerta S60
   - Cálculo automático de materiales por sección

5. **Backend de Cálculo**
   - Método `calcularVentanaUniversal()` en `CalculoVentanaService.php`
   - Procesamiento recursivo de secciones
   - Cálculo de perímetro de marco
   - Cálculo de divisores horizontales y verticales
   - Llamadas recursivas al sistema de cálculo existente

## 🎯 Cómo Usar el Armador

### 1. Acceder al Armador
- Ir al **Cotizador** (`/cotizador`)
- Click en "Agregar Ventana"
- Seleccionar **Material** (Aluminio o PVC)
- Seleccionar **Tipo de Ventana: "Ventana Universal"** (ID 58)

### 2. Configurar Dimensiones y Perfiles
```
1. Ancho Total: [2000] mm
2. Alto Total:  [2000] mm
3. Perfil Marco: [Seleccionar de lista]
4. Perfil Divisor Horizontal: [Seleccionar de lista]
5. Perfil Divisor Vertical: [Seleccionar de lista]
```

### 3. Dividir Secciones
1. **Hacer click** en una sección del canvas para seleccionarla
2. Usar los botones del panel de control:
   - **Dividir Horizontal**: Crea 2 secciones apiladas (50% cada una)
   - **Dividir Vertical**: Crea 2 secciones lado a lado (50% cada una)

### 4. Asignar Tipos de Ventana
- Con una sección seleccionada, elegir tipo del dropdown:
  - Fija (ID 2)
  - Proyectante (ID 45)
  - Corredera (ID 3)
  - Abatible (ID 49)
  - Puerta S60 (ID 50)

### 5. Eliminar Secciones
- Seleccionar sección
- Click en botón "Eliminar" (rojo)
- Si queda solo 1 subsección, se colapsa automáticamente

### 6. Guardar Configuración
- Click en **"Aplicar Configuración"**
- La configuración se guarda en `ventana.configuracionArmador`
- Se puede editar posteriormente

## 🗂️ Estructura de Archivos

```
vuexy-frontend/src/components/
├── ArmadorUniversal.vue         # Componente principal
└── SeccionArmador.vue           # Componente recursivo para secciones

app/Services/
└── CalculoVentanaService.php    # Método calcularVentanaUniversal()
```

## 📊 Estructura de Datos

### Configuración del Armador
```javascript
{
  ancho: 2000,                           // mm
  alto: 2000,                            // mm
  perfilMarcoId: 148,                    // ID del producto
  perfilDivisorHorizontalId: 149,        // ID del producto
  perfilDivisorVerticalId: 149,          // ID del producto
  secciones: [
    {
      tipo: 'compuesta',                 // 'vacio' | 'compuesta' | 'ventana'
      tipoVentanaId: null,               // ID tipo ventana si tipo='ventana'
      orientacion: 'horizontal',          // 'horizontal' | 'vertical'
      porcentaje: 100,                   // % del espacio disponible
      subsecciones: [
        {
          tipo: 'ventana',
          tipoVentanaId: 2,              // Fija
          porcentaje: 50,
          subsecciones: []
        },
        {
          tipo: 'ventana',
          tipoVentanaId: 45,             // Proyectante
          porcentaje: 50,
          subsecciones: []
        }
      ]
    }
  ]
}
```

## 🔧 Flujo de Cálculo Backend

1. **Validación**: Verifica que exista `configuracionArmador`
2. **Marco Exterior**: Calcula perímetro con perfil seleccionado
3. **Procesamiento Recursivo**:
   ```php
   foreach (secciones as seccion) {
     if (tipo === 'compuesta') {
       // Agregar divisores
       // Procesar subsecciones recursivamente
     }
     if (tipo === 'ventana') {
       // Llamar a calcularMateriales() con tipoVentanaId
       // Agregar materiales de la ventana
     }
   }
   ```
4. **Consolidación**: Suma todos los materiales y costos

## 🎨 Estados Visuales

- **Sección Vacía**: Gris claro (#FAFAFA) con texto "Click para dividir"
- **Sección Compuesta**: Transparente con divisores grises (#757575)
- **Sección con Ventana**: Azul claro (#E3F2FD) con nombre del tipo
- **Sección Seleccionada**: Borde naranja (#FF5722) punteado
- **Sección Hover**: Borde azul (#2196F3) punteado

## 🚀 Próximas Fases (No Implementadas)

### Fase 2: Drag & Drop y Redimensionamiento
- [ ] Arrastrar divisores para cambiar porcentajes
- [ ] Transformers de Konva para resize
- [ ] Snap to grid opcional

### Fase 3: Recursividad Profunda
- [ ] Múltiples niveles de subdivisión (actualmente soportado en backend)
- [ ] Visualización mejorada de niveles anidados
- [ ] Breadcrumb de navegación

### Fase 4: Características Avanzadas
- [ ] Guardar/Cargar plantillas
- [ ] Exportar a PDF/imagen
- [ ] Vista 3D de la ventana
- [ ] Biblioteca de diseños predefinidos

## 🐛 Consideraciones y Limitaciones

1. **Perfiles**: Asegúrate de que los productos tengan el `tipo_producto_id` correcto
2. **Colores**: El color se aplica a todos los perfiles (marco y divisores)
3. **Vidrio**: Usa la configuración global de la ventana (no específica por sección)
4. **Visualización**: Las proporciones en Konva son aproximadas (escala adaptativa)

## 📝 Ejemplo de Uso

### Ventana con 2 Secciones Verticales
```
1. Configurar: 2000mm x 2000mm
2. Seleccionar perfiles
3. Click en sección inicial
4. "Dividir Vertical"
5. Seleccionar sección izquierda → Asignar "Fija"
6. Seleccionar sección derecha → Asignar "Proyectante"
7. "Aplicar Configuración"
```

### Ventana con 4 Cuadrantes
```
1. Configurar dimensiones y perfiles
2. Click en sección → "Dividir Horizontal" (2 secciones apiladas)
3. Seleccionar sección superior → "Dividir Vertical" (2 columnas)
4. Seleccionar sección inferior → "Dividir Vertical" (2 columnas)
5. Asignar tipos a cada cuadrante
6. "Aplicar Configuración"
```

## 🔗 Integración con Sistema Existente

- **Compatible** con todos los tipos de ventana existentes (1-57)
- **Usa** el mismo sistema de cálculo de materiales
- **Se guarda** en la cotización como `configuracionArmador`
- **Aparece** en el PDF/vista previa de cotización

---

**Versión**: 1.0.0 (Fase 1)  
**Fecha**: 21 de Noviembre 2025  
**Tipo de Ventana**: ID 58 - Ventana Universal
