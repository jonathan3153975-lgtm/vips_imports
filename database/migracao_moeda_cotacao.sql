-- =====================================================================
-- VIP Imports — Migração: moeda/cotação nos lançamentos
-- Banco: MySQL / MariaDB
-- ---------------------------------------------------------------------
-- Objetivo: permitir lançamentos em BRL e USD, guardando um snapshot da
-- cotação usada no momento do registro. O balanço é sempre feito em BRL
-- (coluna valor_brl), de forma estável mesmo que a cotação mude depois.
-- =====================================================================

USE `VIPS_IMPORTS`;

-- ---------------------------------------------------------------------
-- gastos: moeda, cotação (R$ por US$) e valor convertido em BRL
-- ---------------------------------------------------------------------
ALTER TABLE `gastos`
  ADD COLUMN `moeda`     ENUM('BRL','USD') NOT NULL DEFAULT 'BRL'  AFTER `valor`,
  ADD COLUMN `cotacao`   DECIMAL(10,4)     NOT NULL DEFAULT 1.0000 AFTER `moeda`,
  ADD COLUMN `valor_brl` DECIMAL(12,2)     NOT NULL DEFAULT 0.00   AFTER `cotacao`;

-- Backfill: registros existentes são BRL (cotação 1) => valor_brl = valor
UPDATE `gastos` SET `valor_brl` = `valor` WHERE `moeda` = 'BRL';

-- ---------------------------------------------------------------------
-- cotacoes: cache diário das cotações (evita bater na API a cada request)
--   data_consulta = dia em que a cotação foi obtida/armazenada
--   data_hora_ptax = data/hora oficial informada pela fonte
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cotacoes` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `moeda`           ENUM('USD') NOT NULL DEFAULT 'USD',
  `data_consulta`   DATE NOT NULL,
  `data_hora_ptax`  DATETIME NULL DEFAULT NULL,
  `valor_compra`    DECIMAL(10,4) NOT NULL,
  `valor_venda`     DECIMAL(10,4) NOT NULL,
  `fonte`           VARCHAR(40) NOT NULL DEFAULT 'BCB/PTAX',
  `criado_em`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cotacoes_moeda_data` (`moeda`, `data_consulta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Nota para o futuro módulo de compras: aplicar as mesmas colunas
-- (moeda, cotacao, valor_brl) na tabela de compras.
-- ---------------------------------------------------------------------
