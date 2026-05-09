-- Migration: Estados de orden administrables
-- Date: 2026-05-05
-- Description: Permite administrar los estados de las órdenes de trabajo desde el sistema

CREATE TABLE IF NOT EXISTS `estados_orden` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Código interno del estado (ej: ingresado, en_revision)',
  `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre visible del estado',
  `color` VARCHAR(20) NOT NULL DEFAULT 'secondary' COMMENT 'Color Bootstrap (primary, success, warning, etc)',
  `icono` VARCHAR(50) DEFAULT 'circle' COMMENT 'Icono Feather',
  `orden` INT NOT NULL DEFAULT 0 COMMENT 'Orden de visualización',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `sistema` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = No se puede eliminar (estados críticos del sistema)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar estados iniciales (migrar desde ESTADOS_OT en config/app.php)
INSERT INTO `estados_orden` (`codigo`, `nombre`, `color`, `icono`, `orden`, `sistema`) VALUES
('ingresado',          'Ingresado',           'secondary', 'inbox',            1, 1),
('en_revision',        'En revisión',         'info',      'search',           2, 0),
('proceso_importacion','Proceso de Importación', 'warning', 'package',       3, 0),
('en_reparacion',      'En reparación',       'warning',   'tool',             4, 0),
('listo',              'Listo',               'success',   'check-circle',     5, 1),
('entregado',          'Entregado',           'primary',   'package',          6, 1),
('cancelado',          'Cancelado',           'danger',    'x-circle',         7, 0),
('devolucion',         'Devolución',          'dark',      'corner-down-left', 8, 0);

-- Nota: Los estados marcados con sistema=1 no se pueden eliminar desde el CRUD
