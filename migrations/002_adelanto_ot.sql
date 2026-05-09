ALTER TABLE ordenes_trabajo
  ADD COLUMN adelanto DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio_final,
  ADD COLUMN metodo_adelanto ENUM('efectivo','yape','plin','tarjeta','transferencia') NULL AFTER adelanto,
  ADD COLUMN fecha_adelanto DATETIME NULL AFTER metodo_adelanto;
