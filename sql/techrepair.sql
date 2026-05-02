-- ============================================================
-- TECHREPAIR PRO — Base de datos completa
-- Motor: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `techrepair` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `techrepair`;

-- ------------------------------------------------------------
-- 1. USUARIOS DEL SISTEMA (admin, técnico, vendedor)
-- ------------------------------------------------------------
CREATE TABLE `usuarios` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre`        VARCHAR(100)  NOT NULL,
  `apellido`      VARCHAR(100)  NOT NULL,
  `email`         VARCHAR(150)  NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)  NOT NULL,
  `rol`           ENUM('admin','tecnico','vendedor') NOT NULL DEFAULT 'tecnico',
  `telefono`      VARCHAR(20),
  `avatar`        VARCHAR(255),
  `activo`        TINYINT(1)    NOT NULL DEFAULT 1,
  `ultimo_acceso` DATETIME,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. CLIENTES
-- ------------------------------------------------------------
CREATE TABLE `clientes` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `codigo`          VARCHAR(20)  NOT NULL UNIQUE COMMENT 'CLI-0001',
  `tipo`            ENUM('persona','empresa') NOT NULL DEFAULT 'persona',
  `nombre`          VARCHAR(200) NOT NULL,
  `ruc_dni`         VARCHAR(20),
  `email`           VARCHAR(150),
  `telefono`        VARCHAR(20),
  `whatsapp`        VARCHAR(20),
  `direccion`       TEXT,
  `distrito`        VARCHAR(100),
  `segmento`        ENUM('nuevo','frecuente','empresa','vip') DEFAULT 'nuevo',
  `notas`           TEXT,
  `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_ruc_dni` (`ruc_dni`),
  INDEX `idx_telefono` (`telefono`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. TIPOS DE EQUIPO
-- ------------------------------------------------------------
CREATE TABLE `tipos_equipo` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre`      VARCHAR(100) NOT NULL,
  `icono`       VARCHAR(50)  DEFAULT 'laptop',
  `activo`      TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO `tipos_equipo` (`nombre`, `icono`) VALUES
('Laptop',           'laptop'),
('PC de escritorio', 'desktop'),
('PlayStation',      'gamepad-2'),
('Xbox',             'gamepad-2'),
('Nintendo Switch',  'gamepad-2'),
('Tablet',           'tablet'),
('Smartphone',       'smartphone'),
('Impresora',        'printer'),
('Monitor',          'monitor'),
('Otros',            'package');

-- ------------------------------------------------------------
-- 4. EQUIPOS REGISTRADOS
-- ------------------------------------------------------------
CREATE TABLE `equipos` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tipo_equipo_id`   INT UNSIGNED NOT NULL,
  `cliente_id`       INT UNSIGNED NOT NULL,
  `marca`            VARCHAR(100),
  `modelo`           VARCHAR(150),
  `serial`           VARCHAR(100),
  `color`            VARCHAR(50),
  `descripcion`      TEXT,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`tipo_equipo_id`) REFERENCES `tipos_equipo`(`id`),
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`),
  INDEX `idx_serial` (`serial`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. ÓRDENES DE TRABAJO (OT) — corazón del sistema
-- ------------------------------------------------------------
CREATE TABLE `ordenes_trabajo` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `codigo_ot`            VARCHAR(20)  NOT NULL UNIQUE COMMENT 'OT-2024-0001',
  `codigo_publico`       VARCHAR(12)  NOT NULL UNIQUE COMMENT 'Para consulta del cliente: ABC12345',
  `cliente_id`           INT UNSIGNED NOT NULL,
  `equipo_id`            INT UNSIGNED NOT NULL,
  `tecnico_id`           INT UNSIGNED,
  `usuario_creador_id`   INT UNSIGNED NOT NULL,
  -- Estado
  `estado`               ENUM('ingresado','en_revision','en_reparacion','listo','entregado','cancelado')
                         NOT NULL DEFAULT 'ingresado',
  -- Diagnóstico
  `problema_reportado`   TEXT NOT NULL COMMENT 'Lo que dice el cliente',
  `diagnostico_inicial`  TEXT         COMMENT 'Primera revisión del técnico',
  `diagnostico_tecnico`  TEXT         COMMENT 'Diagnóstico detallado',
  `observaciones`        TEXT,
  -- Checklist físico (JSON)
  `checklist`            JSON         COMMENT 'Estado físico del equipo al ingreso',
  -- Presupuesto
  `costo_diagnostico`    DECIMAL(10,2) DEFAULT 0.00,
  `costo_repuestos`      DECIMAL(10,2) DEFAULT 0.00,
  `costo_mano_obra`      DECIMAL(10,2) DEFAULT 0.00,
  `costo_total`          DECIMAL(10,2) DEFAULT 0.00,
  `descuento`            DECIMAL(10,2) DEFAULT 0.00,
  `precio_final`         DECIMAL(10,2) DEFAULT 0.00,
  -- Aprobación
  `presupuesto_aprobado` TINYINT(1)   DEFAULT 0,
  `aprobado_por`         ENUM('firma','whatsapp','llamada','email') DEFAULT 'firma',
  `fecha_aprobacion`     DATETIME,
  -- Firma digital (base64 o ruta)
  `firma_cliente`        TEXT         COMMENT 'SVG base64 de la firma',
  -- Garantía
  `garantia_dias`        INT          DEFAULT 30,
  `garantia_vence`       DATE,
  -- Fechas
  `fecha_ingreso`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_estimada`       DATE,
  `fecha_entrega`        DATETIME,
  -- Pago
  `pagado`               TINYINT(1)   DEFAULT 0,
  `metodo_pago`          ENUM('efectivo','yape','plin','tarjeta','transferencia'),
  `fecha_pago`           DATETIME,
  `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`cliente_id`)         REFERENCES `clientes`(`id`),
  FOREIGN KEY (`equipo_id`)          REFERENCES `equipos`(`id`),
  FOREIGN KEY (`tecnico_id`)         REFERENCES `usuarios`(`id`),
  FOREIGN KEY (`usuario_creador_id`) REFERENCES `usuarios`(`id`),
  INDEX `idx_estado`        (`estado`),
  INDEX `idx_codigo_publico`(`codigo_publico`),
  INDEX `idx_fecha_ingreso` (`fecha_ingreso`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. FOTOS DE EQUIPOS / OT
-- ------------------------------------------------------------
CREATE TABLE `fotos_ot` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ot_id`       INT UNSIGNED NOT NULL,
  `ruta`        VARCHAR(255) NOT NULL,
  `descripcion` VARCHAR(255),
  `tipo`        ENUM('ingreso','proceso','entrega') DEFAULT 'ingreso',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ot_id`) REFERENCES `ordenes_trabajo`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. HISTORIAL DE ESTADOS DE OT
-- ------------------------------------------------------------
CREATE TABLE `historial_ot` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ot_id`        INT UNSIGNED NOT NULL,
  `usuario_id`   INT UNSIGNED NOT NULL,
  `estado_antes` VARCHAR(50),
  `estado_nuevo` VARCHAR(50)  NOT NULL,
  `comentario`   TEXT,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ot_id`)      REFERENCES `ordenes_trabajo`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. REPUESTOS EN OT (detalle de partes usadas)
-- ------------------------------------------------------------
CREATE TABLE `ot_repuestos` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ot_id`         INT UNSIGNED NOT NULL,
  `producto_id`   INT UNSIGNED,
  `descripcion`   VARCHAR(255) NOT NULL,
  `cantidad`      DECIMAL(10,2) NOT NULL DEFAULT 1,
  `precio_unit`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `subtotal`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`ot_id`) REFERENCES `ordenes_trabajo`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. CATEGORÍAS DE INVENTARIO
-- ------------------------------------------------------------
CREATE TABLE `categorias` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre`     VARCHAR(100) NOT NULL,
  `tipo`       ENUM('repuesto','hardware','ofimatica','accesorio','software') NOT NULL,
  `descripcion`TEXT,
  `activo`     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO `categorias` (`nombre`, `tipo`) VALUES
('Pantallas / Displays',     'repuesto'),
('Baterías',                 'repuesto'),
('Teclados laptop',          'repuesto'),
('Placas madre',             'repuesto'),
('Fuentes de poder',         'repuesto'),
('Discos SSD',               'hardware'),
('Discos HDD',               'hardware'),
('Memorias RAM',             'hardware'),
('Procesadores',             'hardware'),
('Tarjetas de video',        'hardware'),
('Mouse',                    'ofimatica'),
('Teclados',                 'ofimatica'),
('Cables y adaptadores',     'accesorio'),
('Audífonos / Headsets',     'accesorio'),
('Antivirus / Licencias',    'software'),
('Pads térmicos / Pasta',    'repuesto'),
('Ventiladores / Coolers',   'repuesto');

-- ------------------------------------------------------------
-- 10. PRODUCTOS / INVENTARIO
-- ------------------------------------------------------------
CREATE TABLE `productos` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `codigo`          VARCHAR(50)  NOT NULL UNIQUE,
  `nombre`          VARCHAR(200) NOT NULL,
  `descripcion`     TEXT,
  `categoria_id`    INT UNSIGNED NOT NULL,
  `marca`           VARCHAR(100),
  `modelo`          VARCHAR(150),
  `serial`          VARCHAR(100),
  `ubicacion`       VARCHAR(100) COMMENT 'Estante/fila/columna en almacén',
  -- Precios
  `precio_costo`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `precio_venta`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  -- Stock
  `stock_actual`    DECIMAL(10,2) NOT NULL DEFAULT 0,
  `stock_minimo`    DECIMAL(10,2) NOT NULL DEFAULT 1,
  `stock_maximo`    DECIMAL(10,2) DEFAULT 100,
  `unidad`          VARCHAR(20)   DEFAULT 'unidad',
  -- Estado
  `activo`          TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`categoria_id`) REFERENCES `categorias`(`id`),
  INDEX `idx_categoria` (`categoria_id`),
  INDEX `idx_stock_minimo` (`stock_actual`, `stock_minimo`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11. KARDEX (movimientos de inventario)
-- ------------------------------------------------------------
CREATE TABLE `kardex` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `producto_id`   INT UNSIGNED  NOT NULL,
  `tipo`          ENUM('entrada','salida','ajuste','devolucion') NOT NULL,
  `cantidad`      DECIMAL(10,2) NOT NULL,
  `stock_antes`   DECIMAL(10,2) NOT NULL,
  `stock_despues` DECIMAL(10,2) NOT NULL,
  `precio_unit`   DECIMAL(10,2) DEFAULT 0.00,
  `motivo`        VARCHAR(255),
  `referencia`    VARCHAR(100) COMMENT 'OT-2024-0001 o VTA-0001',
  `usuario_id`    INT UNSIGNED  NOT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`),
  FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios`(`id`),
  INDEX `idx_producto_fecha` (`producto_id`, `created_at`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 12. VENTAS
-- ------------------------------------------------------------
CREATE TABLE `ventas` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `codigo`         VARCHAR(20)   NOT NULL UNIQUE COMMENT 'VTA-2024-0001',
  `cliente_id`     INT UNSIGNED,
  `usuario_id`     INT UNSIGNED  NOT NULL,
  `tipo_doc`       ENUM('boleta','factura','ticket','sin_comprobante') DEFAULT 'boleta',
  `serie_doc`      VARCHAR(10),
  `num_doc`        VARCHAR(20),
  -- Totales
  `subtotal`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `igv`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `descuento`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  -- Pago
  `metodo_pago`    ENUM('efectivo','yape','plin','tarjeta','transferencia','mixto') NOT NULL DEFAULT 'efectivo',
  `monto_pagado`   DECIMAL(10,2),
  `vuelto`         DECIMAL(10,2) DEFAULT 0.00,
  -- Estado
  `estado`         ENUM('completada','anulada','pendiente') DEFAULT 'completada',
  `notas`          TEXT,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`),
  INDEX `idx_fecha` (`created_at`),
  INDEX `idx_estado` (`estado`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 13. DETALLE DE VENTAS
-- ------------------------------------------------------------
CREATE TABLE `venta_detalle` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `venta_id`     INT UNSIGNED  NOT NULL,
  `producto_id`  INT UNSIGNED  NOT NULL,
  `cantidad`     DECIMAL(10,2) NOT NULL,
  `precio_unit`  DECIMAL(10,2) NOT NULL,
  `descuento`    DECIMAL(10,2) DEFAULT 0.00,
  `subtotal`     DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`venta_id`)    REFERENCES `ventas`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 14. CAJA DIARIA
-- ------------------------------------------------------------
CREATE TABLE `cajas` (
  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id`        INT UNSIGNED  NOT NULL,
  `fecha`             DATE          NOT NULL,
  `saldo_inicial`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_ingresos`    DECIMAL(10,2) DEFAULT 0.00,
  `total_egresos`     DECIMAL(10,2) DEFAULT 0.00,
  `saldo_final`       DECIMAL(10,2) DEFAULT 0.00,
  `estado`            ENUM('abierta','cerrada') DEFAULT 'abierta',
  `observaciones`     TEXT,
  `fecha_cierre`      DATETIME,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`),
  UNIQUE KEY `uq_fecha_usuario` (`fecha`, `usuario_id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 15. MOVIMIENTOS DE CAJA
-- ------------------------------------------------------------
CREATE TABLE `movimientos_caja` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `caja_id`      INT UNSIGNED  NOT NULL,
  `tipo`         ENUM('ingreso','egreso') NOT NULL,
  `concepto`     VARCHAR(255)  NOT NULL,
  `monto`        DECIMAL(10,2) NOT NULL,
  `referencia`   VARCHAR(100),
  `usuario_id`   INT UNSIGNED  NOT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`caja_id`)    REFERENCES `cajas`(`id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 16. GARANTÍAS
-- ------------------------------------------------------------
CREATE TABLE `garantias` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tipo`           ENUM('reparacion','producto') NOT NULL,
  `referencia_id`  INT UNSIGNED NOT NULL COMMENT 'ot_id o venta_id',
  `cliente_id`     INT UNSIGNED NOT NULL,
  `descripcion`    TEXT         NOT NULL,
  `fecha_inicio`   DATE         NOT NULL,
  `fecha_vence`    DATE         NOT NULL,
  `estado`         ENUM('vigente','vencida','reclamada') DEFAULT 'vigente',
  `notas`          TEXT,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 17. NOTIFICACIONES
-- ------------------------------------------------------------
CREATE TABLE `notificaciones` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ot_id`        INT UNSIGNED,
  `cliente_id`   INT UNSIGNED,
  `tipo`         ENUM('whatsapp','email','sistema') NOT NULL,
  `asunto`       VARCHAR(255),
  `mensaje`      TEXT NOT NULL,
  `estado`       ENUM('pendiente','enviado','error') DEFAULT 'pendiente',
  `enviado_at`   DATETIME,
  `error_msg`    TEXT,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ot_id`)      REFERENCES `ordenes_trabajo`(`id`),
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 18. CONFIGURACIÓN DEL SISTEMA
-- ------------------------------------------------------------
CREATE TABLE `configuracion` (
  `id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `clave`  VARCHAR(100) NOT NULL UNIQUE,
  `valor`  TEXT,
  `tipo`   VARCHAR(50)  DEFAULT 'texto',
  `grupo`  VARCHAR(50)  DEFAULT 'general'
) ENGINE=InnoDB;

INSERT INTO `configuracion` (`clave`, `valor`, `tipo`, `grupo`) VALUES
('empresa_nombre',       'TechRepair Pro',       'texto',    'empresa'),
('empresa_ruc',          '20000000001',           'texto',    'empresa'),
('empresa_direccion',    'Lima, Perú',            'texto',    'empresa'),
('empresa_telefono',     '999999999',             'texto',    'empresa'),
('empresa_email',        'info@techrepair.com',   'texto',    'empresa'),
('empresa_logo',         '',                      'imagen',   'empresa'),
('igv_porcentaje',       '18',                    'numero',   'facturacion'),
('garantia_defecto_dias','30',                    'numero',   'reparaciones'),
('whatsapp_api_token',   '',                      'texto',    'notificaciones'),
('whatsapp_phone_id',    '',                      'texto',    'notificaciones'),
('smtp_host',            '',                      'texto',    'email'),
('smtp_user',            '',                      'texto',    'email'),
('smtp_pass',            '',                      'texto',    'email'),
('smtp_port',            '587',                   'numero',   'email'),
('moneda',               'PEN',                   'texto',    'general'),
('moneda_simbolo',       'S/',                    'texto',    'general');

-- ------------------------------------------------------------
-- USUARIO ADMIN POR DEFECTO
-- IMPORTANTE: Después de importar este SQL, ejecuta:
--   http://localhost/techrepair/reset_password.php
-- Eso establecerá el hash correcto para: Admin123!
-- ------------------------------------------------------------
INSERT INTO `usuarios` (`nombre`,`apellido`,`email`,`password_hash`,`rol`) VALUES
('Administrador','Sistema','admin@techrepair.com',
 'PENDIENTE_RESET','admin');

SET FOREIGN_KEY_CHECKS = 1;
