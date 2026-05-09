-- Migration: Agregar soft delete a órdenes de trabajo
-- Date: 2026-05-05
-- Description: Permite eliminar OTs de forma lógica sin perder datos

ALTER TABLE `ordenes_trabajo` 
ADD COLUMN `deleted_at` DATETIME DEFAULT NULL COMMENT 'Fecha de eliminación lógica (soft delete)';

-- Crear índice para mejorar consultas que filtran por deleted_at
CREATE INDEX `idx_deleted_at` ON `ordenes_trabajo` (`deleted_at`);
