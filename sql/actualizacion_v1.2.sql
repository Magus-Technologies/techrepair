-- ============================================================
-- TechRepair Pro — Actualización v1.2
-- Ejecutar en phpMyAdmin sobre la BD techrepair
-- ============================================================

USE `techrepair`;

-- Plantillas de WhatsApp
CREATE TABLE IF NOT EXISTS `wa_plantillas` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre`      VARCHAR(150) NOT NULL,
  `categoria`   ENUM('reparacion','venta','general') DEFAULT 'general',
  `texto`       TEXT NOT NULL,
  `usuario_id`  INT UNSIGNED,
  `activo`      TINYINT(1) DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Plantillas por defecto
INSERT INTO `wa_plantillas` (`nombre`, `categoria`, `texto`) VALUES
('Equipo listo para recoger',   'reparacion', 'Hola {nombre} 👋\n\n¡Tu equipo ya está *listo para recoger* en {empresa}! 🎉\n\n📋 OT: *{codigo_ot}*\n🔑 Código de consulta: *{codigo_consulta}*\n\nRecuerda traer tu DNI. ¡Te esperamos!'),
('Equipo en reparación',        'reparacion', 'Hola {nombre}, tu equipo está siendo reparado en {empresa} 🔧\n\n📋 OT: *{codigo_ot}*\n\nTe avisamos en cuanto esté listo.'),
('Presupuesto para aprobación', 'reparacion', 'Hola {nombre} 👋\n\nHemos revisado tu equipo en {empresa} y tenemos el presupuesto listo.\n\n📋 OT: *{codigo_ot}*\n💰 Total: *{total}*\n\nResponde este mensaje para confirmar o coordinar. ¡Gracias!'),
('Equipo entregado - Gracias',  'reparacion', '¡Gracias por confiar en {empresa}! 🙏\n\nTu equipo fue entregado correctamente.\n📋 OT: *{codigo_ot}*\n\nRecuerda que cuentas con garantía. ¡Estamos para servirte!'),
('Recordatorio de recojo',      'reparacion', 'Hola {nombre}, te recordamos que tu equipo lleva varios días listo en {empresa}.\n\n📋 OT: *{codigo_ot}*\n\nPor favor coordina el recojo. ¡Gracias!'),
('Consulta estado en línea',    'reparacion', 'Hola {nombre}, puedes consultar el estado de tu reparación en línea con tu código: *{codigo_consulta}* 🔑'),
('Promoción / Oferta especial', 'venta',      'Hola {nombre} 👋\n\n{empresa} tiene ofertas especiales para ti. Visítanos o escríbenos para más información. ¡Te esperamos!'),
('Saludo general',              'general',    'Hola {nombre}, gracias por contactar a {empresa} 😊 ¿En qué podemos ayudarte hoy?');

-- Marcas personalizadas (para el formulario de OT)
CREATE TABLE IF NOT EXISTS `marcas_equipo` (
  `id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre`  VARCHAR(100) NOT NULL UNIQUE,
  `activo`  TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

INSERT IGNORE INTO `marcas_equipo` (`nombre`) VALUES
('HP'),('Dell'),('Lenovo'),('Asus'),('Acer'),('Apple'),('Samsung'),('Toshiba'),
('Sony'),('MSI'),('Gigabyte'),('Huawei'),('LG'),('Microsoft'),('Razer'),
('PlayStation'),('Xbox'),('Nintendo'),('Genérico');

-- Items de checklist personalizados
CREATE TABLE IF NOT EXISTS `checklist_items` (
  `id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre`  VARCHAR(150) NOT NULL,
  `activo`  TINYINT(1) DEFAULT 1,
  `orden`   INT DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO `checklist_items` (`nombre`, `orden`) VALUES
('Pantalla sin daños',        1),
('Carcasa / chasis',          2),
('Teclado funcional',         3),
('Touchpad / mouse',          4),
('Puertos y conexiones',      5),
('Batería incluida',          6),
('Cargador incluido',         7),
('Accesorios adicionales',    8),
('Cliente respalda datos',    9);

-- ============================================================
-- Actualización v1.3 — Compras, caja con denominaciones
-- ============================================================

-- Tabla de compras
CREATE TABLE IF NOT EXISTS `compras` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `proveedor`     VARCHAR(200),
  `tipo_doc`      ENUM('factura','boleta','guia','sin_doc') DEFAULT 'factura',
  `nro_doc`       VARCHAR(50),
  `total`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago`   ENUM('efectivo','transferencia','tarjeta','credito') DEFAULT 'efectivo',
  `notas`         TEXT,
  `usuario_id`    INT UNSIGNED NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `compra_detalle` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `compra_id`   INT UNSIGNED NOT NULL,
  `producto_id` INT UNSIGNED NOT NULL,
  `cantidad`    DECIMAL(10,2) NOT NULL,
  `precio_unit` DECIMAL(10,2) NOT NULL,
  `subtotal`    DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`compra_id`)   REFERENCES `compras`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`)
) ENGINE=InnoDB;

-- Columnas nuevas en cajas para denominaciones
ALTER TABLE `cajas`
  ADD COLUMN IF NOT EXISTS `denominaciones_apertura` JSON,
  ADD COLUMN IF NOT EXISTS `denominaciones_cierre`   JSON,
  ADD COLUMN IF NOT EXISTS `diferencia_cierre`       DECIMAL(10,2) DEFAULT 0.00;
