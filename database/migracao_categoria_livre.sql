-- =====================================================================
-- VIP Imports — Categoria como texto livre
-- ---------------------------------------------------------------------
-- Permite digitar novas categorias além do padrão
-- (calca, casaco, jaqueta, tenis, outros).
-- =====================================================================

USE `VIPS_IMPORTS`;

ALTER TABLE `compras`
  MODIFY COLUMN `categoria` VARCHAR(40) NOT NULL DEFAULT 'outros';