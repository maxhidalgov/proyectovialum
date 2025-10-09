# 🔄 Sincronización de Clientes de Bsale

## 📋 Resumen

El sistema mantiene una copia local de los clientes de Bsale en la base de datos MySQL para:
- ✅ Búsquedas más rápidas en el cotizador
- ✅ Evitar límites de rate en la API de Bsale
- ✅ Relaciones foreign key con cotizaciones
- ✅ Funcionamiento offline de la aplicación

---

## 🖱️ Sincronización Manual

### Desde la Interfaz Web

1. Ve a `http://tu-dominio/clientes`
2. Haz clic en el botón **"Sincronizar desde Bsale"**
3. Espera a que complete (unos segundos)
4. Los clientes se actualizarán automáticamente

### Desde Línea de Comandos

```bash
# Local (XAMPP)
php artisan bsale:sincronizar-clientes

# Railway
railway run php artisan bsale:sincronizar-clientes
```

---

## 📊 ¿Qué se Sincroniza?

De cada cliente de Bsale se guarda:
- ✅ ID de Bsale (para mantener la relación)
- ✅ Razón social / Nombre completo
- ✅ RUT (identification)
- ✅ Email
- ✅ Teléfono
- ✅ Dirección
- ✅ Ciudad y Comuna
- ✅ Tipo (empresa/persona)
- ✅ Giro

---

## 🔄 ¿Cuándo Sincronizar?

Se recomienda sincronizar:
- ✅ Después de crear clientes nuevos en Bsale
- ✅ Después de actualizar datos de clientes en Bsale
- ✅ Una vez por semana para mantener datos actualizados
- ✅ Si notas que falta un cliente en el cotizador

---

## ⚠️ Importante

- La sincronización **NO ELIMINA** clientes existentes
- Solo **ACTUALIZA** o **CREA** nuevos registros
- Es seguro ejecutarla múltiples veces
- No afecta las cotizaciones existentes

---

## 🚀 En Railway

La sincronización funciona exactamente igual:
1. El botón en `/clientes` funciona automáticamente
2. Usa la misma base de datos MySQL de Railway
3. El token de Bsale debe estar configurado en las variables de entorno

### Variables Requeridas en Railway

```env
BSALE_ACCESS_TOKEN=tu_token_de_bsale
```

---

## 📝 Logs

Los logs de sincronización se guardan en:
- Local: `storage/logs/laravel.log`
- Railway: Ver logs del servicio en el dashboard

Buscar por: "Sincronización de clientes Bsale completada"

---

## ✅ Estado Actual

- **Total clientes sincronizados**: 813
- **Con RUT**: 809
- **Sin RUT**: 4 (no tienen RUT en Bsale)

---

## 🛠️ Comandos Útiles

```bash
# Ver cuántos clientes hay
php artisan tinker --execute="echo \App\Models\Cliente::whereNotNull('bsale_id')->count();"

# Ver cuántos tienen RUT
php artisan tinker --execute="echo \App\Models\Cliente::whereNotNull('identification')->where('identification', '!=', '')->count();"

# Sincronizar solo 50 clientes (para pruebas)
php artisan bsale:sincronizar-clientes --limit=50
```
