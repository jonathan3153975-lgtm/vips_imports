-- =====================================================================
-- VIP Imports — Módulo de Compras
-- Banco: MySQL / MariaDB
-- ---------------------------------------------------------------------
-- compras: cada lançamento é UM item comprado (produto). A leitura de
-- uma nota fiscal por IA gera vários lançamentos de uma só vez ("bloco").
-- Balanço sempre em BRL (valor_brl), como em gastos.
-- =====================================================================

USE `VIPS_IMPORTS`;

-- ---------------------------------------------------------------------
-- Tabela: compras (item a item, vinculado à viagem)
--   categorias: calca, casaco, jaqueta, tenis, outros
--   valor = total pago pelo item (quantidade × custo unitário)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compras` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `viagem_id`     INT UNSIGNED NOT NULL,
  `categoria`     ENUM('calca','casaco','jaqueta','tenis','outros') NOT NULL,
  `produto`       VARCHAR(160) NOT NULL,
  `marca`         VARCHAR(80)  NULL DEFAULT NULL,
  `quantidade`    INT UNSIGNED NOT NULL DEFAULT 1,
  `valor`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `moeda`         ENUM('BRL','USD') NOT NULL DEFAULT 'BRL',
  `cotacao`       DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
  `valor_brl`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `loja`          VARCHAR(160) NULL DEFAULT NULL,
  `data_compra`   DATE NOT NULL,
  `observacoes`   VARCHAR(500) NULL DEFAULT NULL,
  `criado_em`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_compras_viagem` (`viagem_id`),
  KEY `idx_compras_categoria` (`categoria`),
  KEY `idx_compras_marca` (`marca`),
  CONSTRAINT `fk_compras_viagem`
    FOREIGN KEY (`viagem_id`) REFERENCES `viagens` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: compra_fotos (galeria de fotos por item)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `compra_fotos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `compra_id`  INT UNSIGNED NOT NULL,
  `arquivo`    VARCHAR(255) NOT NULL,
  `criado_em`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_compra_fotos_compra` (`compra_id`),
  CONSTRAINT `fk_compra_fotos_compra`
    FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;