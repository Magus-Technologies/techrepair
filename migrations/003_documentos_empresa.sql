-- Migration: Tabla de series y correlativos para facturación electrónica
-- Date: 2026-04-30

CREATE TABLE IF NOT EXISTS `documentos_empresa` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id`  INT NOT NULL DEFAULT 1,
  `tipo`        ENUM('boleta','factura','nota_venta','nota_credito','ticket') NOT NULL,
  `serie`       VARCHAR(4) NOT NULL,
  `numero`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Último número emitido',
  `descripcion` VARCHAR(100) DEFAULT NULL,
  `activo`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_empresa_tipo_serie` (`empresa_id`, `tipo`, `serie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `documentos_empresa` (`empresa_id`, `tipo`, `serie`, `numero`, `descripcion`, `activo`) VALUES
(1, 'boleta',     'B001', 0, 'Boleta de venta',  1),
(1, 'factura',    'F001', 0, 'Factura',           1),
(1, 'nota_venta', 'NV01', 0, 'Nota de venta',     1)
ON DUPLICATE KEY UPDATE activo=activo;
