-- Migration: Add SUNAT columns to techrepair
-- Date: 2026-04-27

ALTER TABLE ventas
  ADD COLUMN sunat_estado ENUM('pendiente','aceptado','rechazado') NULL AFTER num_doc,
  ADD COLUMN sunat_hash VARCHAR(255) NULL AFTER sunat_estado,
  ADD COLUMN sunat_qr TEXT NULL AFTER sunat_hash,
  ADD COLUMN sunat_xml LONGTEXT NULL AFTER sunat_qr,
  ADD COLUMN sunat_cdr LONGTEXT NULL AFTER sunat_xml,
  ADD COLUMN sunat_mensaje VARCHAR(1000) NULL AFTER sunat_cdr,
  ADD COLUMN sunat_fecha DATETIME NULL AFTER sunat_mensaje,
  ADD INDEX idx_ventas_sunat_estado (sunat_estado),
  ADD INDEX idx_ventas_serie_num (serie_doc, num_doc);
