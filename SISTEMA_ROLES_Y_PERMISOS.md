# 🔐 Sistema de Autenticación y Roles - ViaLum

## ✅ Cambios Implementados

### Backend (Laravel)

1. **Sistema de Permisos Granulares**
   - Nueva tabla `permissions` con permisos específicos
   - Tabla pivote `role_permission` para relación many-to-many
   - 10 permisos definidos: gestionar_usuarios, gestionar_productos, gestionar_cotizaciones, etc.

2. **Roles con Permisos**
   - **Admin**: Todos los permisos
   - **Vendedor**: Cotizaciones, clientes, ver productos, dashboard
   - **Practicante**: Solo crear/editar productos

3. **Middleware de Permisos**
   - `CheckPermission` middleware valida permisos antes de acceder a rutas protegidas
   - Se aplica con `middleware('permission:nombre_permiso')`

4. **Rutas Protegidas**
   - ⛔ **Registro público DESHABILITADO** - `/api/register` comentado
   - ✅ Productos: POST/PUT/DELETE requieren permiso `gestionar_productos`
   - ✅ Rutas admin: `/api/admin/*` requieren permiso `gestionar_usuarios`

5. **UserController**
   - CRUD completo de usuarios (solo admin)
   - Endpoints: GET/POST/PUT/DELETE `/api/admin/users`
   - GET `/api/admin/roles` para obtener roles disponibles

6. **AuthController Mejorado**
   - Login ahora retorna permisos del usuario
   - Frontend puede validar permisos localmente

### Frontend (Vue 3)

1. **Login Actualizado**
   - ⛔ Botón "Create an account" eliminado
   - Solo login con credenciales existentes

2. **Panel de Administración Secreto**
   - Ruta: `http://localhost/proyectovialum/public/#/admin-secret-panel`
   - Solo accesible para admin autenticado
   - Funciones:
     - Listar todos los usuarios
     - Crear nuevos usuarios con rol
     - Editar usuarios existentes
     - Eliminar usuarios
     - Asignar roles

---

## 🚀 Instalación y Configuración

### 1. Ejecutar Migraciones

```bash
cd c:\xampp\htdocs\proyectovialum
php artisan migrate
```

Esto creará las tablas:
- `permissions` - Permisos del sistema
- `role_permission` - Relación roles-permisos

### 2. Ejecutar Seeder

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

Esto creará:
- ✅ 10 permisos
- ✅ 3 roles (Admin, Vendedor, Practicante)
- ✅ Asignación de permisos a roles
- ✅ **Usuario Admin por defecto**:
  - Email: `admin@vialum.com`
  - Password: `admin123`

⚠️ **IMPORTANTE**: Cambiar la contraseña del admin en producción

### 3. Compilar Frontend (Opcional)

Si modificaste archivos Vue:

```bash
cd vuexy-frontend
npm run build
```

Luego copiar dist a public:

```bash
xcopy /E /I /Y dist ..\public
```

---

## 📋 Uso del Sistema

### Como Administrador

1. **Iniciar Sesión**
   - Ir a: `http://localhost/proyectovialum/public/`
   - Email: `admin@vialum.com`
   - Password: `admin123`

2. **Acceder al Panel de Admin**
   - URL SECRETA: `http://localhost/proyectovialum/public/#/admin-secret-panel`
   - ⚠️ **NO COMPARTIR ESTA URL** - Solo para ti

3. **Crear Usuario Practicante**
   - Clic en "Crear Usuario"
   - Nombre: `Juan Pérez`
   - Email: `juan@vialum.com`
   - Password: `practica123`
   - Rol: **Practicante**
   - Guardar

4. **Crear Usuario Vendedor**
   - Nombre: `María López`
   - Email: `maria@vialum.com`
   - Password: `venta123`
   - Rol: **Vendedor**

### Permisos por Rol

| Permiso | Admin | Vendedor | Practicante |
|---------|-------|----------|-------------|
| Gestionar Usuarios | ✅ | ❌ | ❌ |
| Gestionar Roles | ✅ | ❌ | ❌ |
| **Gestionar Productos** | ✅ | ❌ | ✅ |
| Ver Productos | ✅ | ✅ | ✅ |
| Gestionar Cotizaciones | ✅ | ✅ | ❌ |
| Ver Cotizaciones | ✅ | ✅ | ❌ |
| Aprobar Cotizaciones | ✅ | ✅ | ❌ |
| Gestionar Clientes | ✅ | ✅ | ❌ |
| Ver Clientes | ✅ | ✅ | ❌ |
| Ver Dashboard | ✅ | ✅ | ❌ |

---

## 🔒 Seguridad

1. **No hay registro público**
   - Solo admin puede crear cuentas
   - URL del panel de admin debe mantenerse privada

2. **Middleware de permisos**
   - Backend valida permisos en cada petición
   - Frontend solo muestra lo permitido (pero backend es la autoridad)

3. **Tokens JWT**
   - Autenticación stateless
   - Expiran automáticamente

---

## 🛠️ Agregar Nuevos Permisos

1. **Editar Seeder**
   ```php
   // database/seeders/RolesAndPermissionsSeeder.php
   $permissions = [
       // ... existentes
       ['nombre' => 'eliminar_cotizaciones', 'descripcion' => 'Eliminar cotizaciones permanentemente'],
   ];
   ```

2. **Re-ejecutar Seeder**
   ```bash
   php artisan db:seed --class=RolesAndPermissionsSeeder
   ```

3. **Proteger Ruta**
   ```php
   // routes/api.php
   Route::middleware(['auth:api', 'permission:eliminar_cotizaciones'])->group(function () {
       Route::delete('/cotizaciones/{id}/hard', [CotizacionController::class, 'hardDelete']);
   });
   ```

---

## 📞 Soporte

Si encuentras errores:

1. Verifica que las migraciones se ejecutaron: `php artisan migrate:status`
2. Verifica que el seeder creó datos: `SELECT * FROM roles;` en phpMyAdmin
3. Verifica que el admin existe: `SELECT * FROM users WHERE email='admin@vialum.com';`

---

## 🎯 Próximos Pasos Recomendados

- [ ] Cambiar password del admin en producción
- [ ] Agregar validación de permisos en frontend (mostrar/ocultar botones)
- [ ] Implementar log de auditoría (quién hizo qué)
- [ ] Agregar 2FA para admin
- [ ] Implementar recuperación de contraseña
