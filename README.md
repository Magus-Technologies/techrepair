# TechRepair Pro — Sistema de Gestión Técnica
**Versión:** 1.0.0  
**Stack:** PHP puro + PDO | MySQL | Bootstrap 5 | JS Vanilla

---

## 🚀 Instalación en XAMPP

### 1. Copiar archivos
```
Copiar la carpeta `techrepair/` dentro de:
C:\xampp\htdocs\techrepair\
```

### 2. Crear la base de datos
1. Iniciar Apache y MySQL en XAMPP Control Panel
2. Abrir `http://localhost/phpmyadmin`
3. Clic en **Importar**
4. Seleccionar el archivo: `sql/techrepair.sql`
5. Clic en **Continuar**

### 3. Configurar conexión (si cambias contraseña de MySQL)
Editar `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'techrepair');
define('DB_USER', 'root');
define('DB_PASS', '');  // ← tu contraseña de MySQL
```

### 4. Acceder al sistema
```
URL: http://localhost/techrepair/
Email: admin@techrepair.com
Contraseña: Admin123!
```

---

## 📁 Estructura del proyecto

```
techrepair/
├── config/
│   ├── database.php        ← Conexión PDO
│   └── app.php             ← Constantes, helpers, funciones
├── includes/
│   ├── header.php          ← Layout: sidebar + topbar
│   ├── footer.php          ← Scripts JS + cierre HTML
│   └── whatsapp.php        ← Helper envío WhatsApp API
├── modules/
│   ├── auth/               ← Login, logout
│   ├── dashboard/          ← Dashboard con KPIs y kanban
│   ├── ot/                 ← Órdenes de trabajo (CORE)
│   │   ├── index.php       ← Listado con filtros
│   │   ├── nueva.php       ← Formulario crear OT
│   │   ├── ver.php         ← Detalle OT + timeline
│   │   ├── editar.php      ← Editar OT
│   │   └── pdf.php         ← Generar PDF OT
│   ├── clientes/           ← CRM básico
│   ├── inventario/         ← Stock, kardex, alertas
│   ├── ventas/             ← POS + historial
│   ├── caja/               ← Caja diaria
│   ├── reportes/           ← Analytics con charts
│   ├── tecnicos/           ← Usuarios y roles
│   ├── garantias/          ← Control garantías
│   └── configuracion/      ← Config sistema
├── public/
│   └── estado.php          ← Portal público cliente (sin login)
├── assets/
│   ├── css/app.css
│   ├── js/app.js
│   └── img/uploads/        ← Fotos de equipos
└── sql/
    └── techrepair.sql      ← Schema + datos iniciales
```

---

## 🔑 Módulos incluidos

| Módulo | Descripción |
|--------|-------------|
| **OT (Órdenes de trabajo)** | Core del sistema. Registro, checklist, fotos, firma digital, timeline |
| **Portal cliente** | `/public/estado.php?codigo=XXXXXXXX` — consulta sin login |
| **Diagnóstico/Presupuesto** | En el detalle de OT |
| **Inventario** | Productos, stock, kardex, alertas mínimo |
| **POS / Ventas** | Carrito, métodos de pago (Yape/Plin/tarjeta/efectivo) |
| **Clientes (CRM)** | Historial de reparaciones y compras por cliente |
| **Caja diaria** | Apertura, movimientos, cierre |
| **Reportes** | Charts de ventas, OTs por estado, top técnicos |
| **Técnicos/Usuarios** | Roles: admin, técnico, vendedor |
| **Garantías** | Vigentes, vencidas, reclamadas |
| **Configuración** | Empresa, WhatsApp API, SMTP |

---

## 📱 Portal público del cliente
El cliente puede consultar el estado de su equipo sin login:
```
http://localhost/techrepair/public/estado.php
Ingresar código: (8 caracteres, ej: A1B2C3D4)
```
El código se genera automáticamente al crear la OT y aparece en el PDF y WhatsApp.

---

## 💬 Integración WhatsApp Business
1. Ir a **Configuración → WhatsApp Business API**
2. Ingresar tu **API Token** y **Phone Number ID** de Meta for Developers
3. Los mensajes se envían automáticamente al cambiar estado de OT

---

## 📊 Roles del sistema

| Rol | Permisos |
|-----|----------|
| **admin** | Acceso completo |
| **tecnico** | OTs, inventario, clientes |
| **vendedor** | Ventas/POS, clientes |

---

## 🔜 Próximas fases
- **Fase 2:** Módulo de edición de OT, PDF/imprimir OT, entrada de inventario
- **Fase 3:** Facturación electrónica SUNAT (OSE/PSE)
- **Fase 4:** Notificaciones automáticas WhatsApp + email
- **Fase 5:** App móvil (PWA) para técnicos

---

## 📞 Credenciales demo
```
Admin:   admin@techrepair.com / Admin123!
```
> Cambiar contraseña en producción desde Configuración → Usuarios
