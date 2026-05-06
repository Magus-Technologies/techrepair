-- Migration: Tabla de configuración de empresa
-- Date: 2026-05-05
-- Description: Tabla para almacenar datos fiscales, SUNAT y configuración de la empresa

CREATE TABLE IF NOT EXISTS `empresa` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ruc` VARCHAR(11) NOT NULL DEFAULT '00000000000',
  `razon_social` VARCHAR(255) NOT NULL DEFAULT 'MI EMPRESA',
  `nombre_comercial` VARCHAR(255) DEFAULT NULL,
  `direccion` VARCHAR(500) DEFAULT NULL,
  `ubigeo` VARCHAR(6) DEFAULT NULL,
  `distrito` VARCHAR(100) DEFAULT NULL,
  `provincia` VARCHAR(100) DEFAULT NULL,
  `departamento` VARCHAR(100) DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `telefono2` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `web` VARCHAR(255) DEFAULT NULL,
  `logo` VARCHAR(255) DEFAULT NULL COMMENT 'Ruta relativa del logo',
  `igv` DECIMAL(5,2) NOT NULL DEFAULT 18.00,
  `moneda` VARCHAR(10) NOT NULL DEFAULT 'S/',
  `color_primario` VARCHAR(7) NOT NULL DEFAULT '#4f46e5',
  `propaganda` VARCHAR(500) DEFAULT NULL COMMENT 'Slogan o propaganda',
  `pie_pagina` TEXT DEFAULT NULL COMMENT 'Texto legal del pie de página',
  `modo` ENUM('beta','produccion') NOT NULL DEFAULT 'beta' COMMENT 'Modo SUNAT',
  `sunat_usuario_sol` VARCHAR(50) DEFAULT NULL,
  `sunat_clave_sol` VARCHAR(50) DEFAULT NULL,
  `certificado_subido` TINYINT(1) NOT NULL DEFAULT 0,
  `certificado_fecha` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar registro inicial
INSERT INTO `empresa` (`id`, `ruc`, `razon_social`) VALUES (1, '00000000000', 'MI EMPRESA')
ON DUPLICATE KEY UPDATE id=id;
