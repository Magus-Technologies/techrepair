-- Migration: Add 'devolucion' to ordenes_trabajo estado ENUM
-- Date: 2026-04-30

ALTER TABLE ordenes_trabajo
  MODIFY COLUMN estado ENUM('ingresado','en_revision','en_reparacion','listo','entregado','cancelado','devolucion') NOT NULL DEFAULT 'ingresado';
