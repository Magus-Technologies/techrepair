# Implementación de Estados de Orden Administrables

## ✅ CAMBIOS REALIZADOS

### 1. Base de Datos
- Creada tabla `estados_orden` con campos: id, codigo, nombre, color, icono, orden, activo, sistema
- Insertados 8 estados iniciales incluyendo "Proceso de Importación"
- Estados del sistema (ingresado, listo, entregado) marcados como no eliminables

### 2. Backend
- Creada función `getEstadosOT($db, $soloActivos)` en `config/app.php` para cargar estados desde BD
- Actualizada función `estadoOTBadge()` para usar estados dinámicos con cache
- Creado módulo CRUD completo en `modules/configuracion/estados.php`
- Validación implementada: no se pueden eliminar estados con OTs activas

### 3. Frontend
- Actualizado `modules/ot/ver.php` para usar estados dinámicos en SELECT
- **Modal completo para crear estados con nombre, color e ícono (todo en un solo lugar)**
- Vista previa en tiempo real del badge mientras configuras
- Actualizado `modules/ot/index.php` para filtros dinámicos
- Actualizados todos los módulos que usan estados: dashboard, clientes, reportes, whatsapp, caja
- Agregado enlace "Estados" en menú de Configuración (para administración avanzada)

### 4. Archivos Modificados
- `config/app.php` - Funciones getEstadosOT() y estadoOTBadge()
- `modules/ot/ver.php` - SELECT dinámico, botón "+" y modal para crear estados inline
- `modules/ot/api_agregar.php` - Agregado caso 'estado_orden' para crear estados via AJAX
- `modules/ot/index.php` - Filtros dinámicos
- `modules/configuracion/estados.php` - CRUD completo (NUEVO)
- `modules/dashboard/index.php` - Inicialización de cache
- `modules/clientes/ver.php` - Inicialización de cache
- `modules/ot/editar.php` - Inicialización de cache
- `modules/whatsapp/index.php` - Uso de getEstadosOT()
- `modules/reportes/index.php` - Uso de getEstadosOT()
- `modules/caja/detalle_ajax.php` - Uso de getEstadosOT()
- `includes/header.php` - Enlace en menú
- `migrations/007_estados_orden_administrables.sql` - Migración (NUEVO)

---

## 📋 INSTRUCCIONES DE INSTALACIÓN

### Paso 1: Ejecutar SQL
Abre phpMyAdmin o HeidiSQL y ejecuta el siguiente SQL en tu base de datos:

```sql
-- Migration: Estados de orden administrables
CREATE TABLE IF NOT EXISTS `estados_orden` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Código interno del estado',
  `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre visible del estado',
  `color` VARCHAR(20) NOT NULL DEFAULT 'secondary' COMMENT 'Color Bootstrap',
  `icono` VARCHAR(50) DEFAULT 'circle' COMMENT 'Icono Feather',
  `orden` INT NOT NULL DEFAULT 0 COMMENT 'Orden de visualización',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `sistema` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = No se puede eliminar',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar estados iniciales
INSERT INTO `estados_orden` (`codigo`, `nombre`, `color`, `icono`, `orden`, `sistema`) VALUES
('ingresado',          'Ingresado',              'secondary', 'inbox',            1, 1),
('en_revision',        'En revisión',            'info',      'search',           2, 0),
('proceso_importacion','Proceso de Importación', 'warning',   'package',          3, 0),
('en_reparacion',      'En reparación',          'warning',   'tool',             4, 0),
('listo',              'Listo',                  'success',   'check-circle',     5, 1),
('entregado',          'Entregado',              'primary',   'package',          6, 1),
('cancelado',          'Cancelado',              'danger',    'x-circle',         7, 0),
('devolucion',         'Devolución',             'dark',      'corner-down-left', 8, 0);
```

### Paso 2: Verificar instalación
1. Abre phpMyAdmin
2. Selecciona tu base de datos
3. Verifica que existe la tabla `estados_orden`
4. Verifica que tiene 8 registros

---

## 🧪 PASOS DE PRUEBA

### Prueba 1: Ver módulo de configuración de estados
1. Abre tu navegador
2. Inicia sesión en TechRepair
3. En el menú lateral, ve a **Administración** → **Configuración**
4. Haz clic en **Estados de Orden**
5. ✅ Deberías ver una tabla con 8 estados
6. ✅ Deberías ver "Proceso de Importación" en la lista
7. ✅ Los estados "Ingresado", "Listo" y "Entregado" deben tener badge "Sistema"

### Prueba 2: Crear un nuevo estado personalizado
1. En la página de Estados, completa el formulario:
   - Código: `esperando_repuesto`
   - Nombre: `Esperando repuesto`
   - Color: `warning` (Amarillo)
   - Ícono: `clock`
   - Orden: `9`
2. Haz clic en **Crear estado**
3. ✅ Deberías ver mensaje "Estado creado"
4. ✅ El nuevo estado aparece en la tabla

### Prueba 3: Editar un estado
1. En la tabla de estados, haz clic en el ícono de lápiz (editar) del estado "En revisión"
2. Cambia el nombre a "En diagnóstico"
3. Haz clic en **Guardar cambios**
4. ✅ Deberías ver mensaje "Estado actualizado"
5. ✅ El nombre cambió en la tabla

### Prueba 4: Desactivar/Activar un estado
1. En la tabla, haz clic en el botón "✅ Activo" del estado "Devolución"
2. ✅ El botón cambia a "⏸ Inactivo"
3. Haz clic nuevamente
4. ✅ El botón vuelve a "✅ Activo"

### Prueba 5: Intentar eliminar estado del sistema
1. Intenta hacer clic en el ícono de basura del estado "Ingresado"
2. ✅ NO debería aparecer el botón de eliminar (estados del sistema no se pueden eliminar)

### Prueba 6: Ver estados en OT
1. Ve a **Órdenes de trabajo**
2. Abre cualquier OT existente
3. En el panel derecho, busca la sección **CAMBIAR ESTADO**
4. Abre el SELECT de estados
5. ✅ Deberías ver "Proceso de Importación" en la lista
6. ✅ Deberías ver todos los estados activos
7. ✅ Si creaste "Esperando repuesto", debería aparecer

### Prueba 7: Cambiar estado a "Proceso de Importación"
1. En la misma OT, selecciona "Proceso de Importación"
2. Escribe un comentario: "Equipo en proceso de importación desde China"
3. Haz clic en **Actualizar estado**
4. ✅ Deberías ver mensaje "Estado actualizado a: Proceso de Importación"
5. ✅ El badge del estado cambió a amarillo con el texto correcto
6. ✅ En el historial/timeline aparece el cambio con tu comentario

### Prueba 7.1: Crear estado directamente desde OT con configuración completa
1. En la misma OT, en la sección **CAMBIAR ESTADO**
2. Haz clic en el botón verde **"+"** junto al SELECT de estados
3. ✅ Se abre un modal "Nuevo estado de orden"ñ
4. Escribe nombre: "Esperando cliente"
5. Selecciona color: **🟡 Amarillo** (warning)
6. Selecciona ícono: **🕐 Reloj** (clock)
7. ✅ La vista previa muestra el badge amarillo con reloj y el texto
8. Haz clic en **Crear estado**
9. ✅ El modal se cierra
10. ✅ Aparece mensaje verde: "Estado 'Esperando cliente' creado correctamente"
11. ✅ El nuevo estado aparece seleccionado en el SELECT
12. ✅ Puedes cambiar la OT a ese estado inmediatamente
13. Cambia el estado y guarda
14. ✅ El badge de la OT muestra el color amarillo con ícono de reloj

### Prueba 8: Filtrar OTs por estado
1. Ve a **Órdenes de trabajo** (listado)
2. En los filtros, abre el SELECT "Todos los estados"
3. ✅ Deberías ver "Proceso de Importación" en la lista
4. Selecciona "Proceso de Importación"
5. Haz clic en **Filtrar**
6. ✅ Solo deberían aparecer las OTs con ese estado

### Prueba 9: Validación de eliminación
1. Ve a **Configuración** → **Estados de Orden**
2. Cambia una OT existente al estado "Devolución"
3. Intenta eliminar el estado "Devolución"
4. ✅ Deberías ver error: "No se puede eliminar: hay X órdenes de trabajo con este estado"

### Prueba 10: Eliminar estado sin OTs
1. Si creaste "Esperando repuesto" y NO lo usaste en ninguna OT
2. Haz clic en el ícono de basura
3. Confirma la eliminación
4. ✅ Deberías ver mensaje "Estado eliminado"
5. ✅ El estado desaparece de la tabla

---

## ✅ RESULTADOS ESPERADOS

Si todo funciona correctamente:
- ✅ Puedes crear estados completos (nombre, color, ícono) directamente desde la OT
- ✅ **No necesitas ir a Configuración para uso normal** - todo se hace desde el modal
- ✅ Vista previa en tiempo real mientras configuras el estado
- ✅ Los estados del sistema (ingresado, listo, entregado) NO se pueden eliminar
- ✅ No puedes eliminar estados que tienen OTs asociadas
- ✅ El estado "Proceso de Importación" aparece en todos los SELECT de estados
- ✅ Los cambios de estado se reflejan inmediatamente en badges y filtros
- ✅ El historial de OT muestra correctamente los nombres de estados
- ✅ Los estados creados inline aparecen con el color e ícono que elegiste

## ❌ INDICADORES DE PROBLEMAS

Si algo salió mal:
- ❌ Error "Table 'estados_orden' doesn't exist" → No ejecutaste el SQL
- ❌ No aparece "Estados" en el menú → Limpia cache del navegador (Ctrl+F5)
- ❌ Los estados no cambian en el SELECT → Verifica que ejecutaste el SQL correctamente
- ❌ Error al cambiar estado → Verifica que el código del estado existe en la tabla
- ❌ Los badges muestran nombres incorrectos → Limpia cache y recarga la página

---

## 🔧 NOTAS TÉCNICAS

### Compatibilidad hacia atrás
- La constante `ESTADOS_OT` se mantiene en `config/app.php` como fallback
- Si la tabla `estados_orden` está vacía, el sistema usa la constante
- Código legacy que use `ESTADOS_OT` directamente seguirá funcionando

### Performance
- La función `estadoOTBadge()` usa cache estático para evitar múltiples queries
- Solo se hace 1 query a `estados_orden` por request
- Los estados se cargan una vez y se reutilizan

### Seguridad
- Solo usuarios con rol `admin` pueden acceder al CRUD de estados
- Los estados del sistema están protegidos contra eliminación
- Validación de OTs asociadas antes de eliminar

### Personalización
- Puedes agregar más estados según necesites
- Los colores disponibles: primary, secondary, success, danger, warning, info, dark
- Los íconos son de Feather Icons: https://feathericons.com

### Creación inline de estados
- **Desde la OT**: Usa el botón "+" junto al SELECT de estados para crear estados completos
- **Configura todo en el modal**: nombre, color (7 opciones), ícono (15 opciones)
- **Vista previa en tiempo real**: ves cómo quedará el badge mientras lo configuras
- **No necesitas ir a Configuración** para uso normal - todo se hace desde el modal
- El módulo de Configuración → Estados es solo para administración avanzada (editar, reordenar, eliminar)
- Esta funcionalidad es similar a cómo funcionan los tipos de equipo y marcas en Nueva OT

---

## 📝 RESUMEN

**Implementación completada:**
- ✅ Tabla `estados_orden` creada
- ✅ 8 estados iniciales insertados (incluyendo "Proceso de Importación")
- ✅ CRUD completo funcional en módulo de Configuración (para administración avanzada)
- ✅ **Modal completo en OT para crear estados con nombre, color e ícono**
- ✅ **Vista previa en tiempo real del badge**
- ✅ **No necesitas ir a Configuración para uso normal**
- ✅ Validaciones implementadas
- ✅ Todos los módulos actualizados
- ✅ Compatibilidad hacia atrás mantenida

**Próximos pasos sugeridos:**
- Probar en producción con datos reales
- Capacitar al equipo sobre el nuevo modal de creación de estados
- El módulo Configuración → Estados es opcional, solo para administración avanzada (reordenar, editar múltiples, eliminar)
