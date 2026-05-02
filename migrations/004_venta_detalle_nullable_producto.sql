-- Migration: Permitir producto_id NULL en venta_detalle para servicios de OT
-- Date: 2026-05-02

ALTER TABLE venta_detalle
  MODIFY COLUMN producto_id INT UNSIGNED NULL DEFAULT NULL;
