-- =====================================================================
-- VIP Imports — Loja: controle de produto vendido
-- Banco: MySQL / MariaDB
-- ---------------------------------------------------------------------
-- loja_produtos.vendido   : 1 = produto vendido, 0 = à venda
-- loja_produtos.vendido_em: data da venda (preenchida ao marcar vendido)
-- =====================================================================

USE `VIPS_IMPORTS`;

ALTER TABLE `loja_produtos`
  ADD COLUMN IF NOT EXISTS `vendido` TINYINT(1) NOT NULL DEFAULT 0 AFTER `preco_sugerido`,
  ADD COLUMN IF NOT EXISTS `vendido_em` DATETIME NULL DEFAULT NULL AFTER `vendido`,
  ADD KEY IF NOT EXISTS `idx_loja_produtos_vendido` (`vendido`);