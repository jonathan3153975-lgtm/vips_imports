-- =====================================================================
-- VIP Imports — Módulo Loja
-- Banco: MySQL / MariaDB
-- ---------------------------------------------------------------------
-- compras.publicada: marca o item como já publicado na loja. O item NÃO
-- é excluído de compras — apenas deixa de aparecer nas listagens de compra
-- (o custo permanece nos totais de gastos/compras da viagem).
--
-- loja_produtos: um registro por item publicado, com o preço de venda
-- definido no formulário e a descrição (opcional, gerada por IA).
-- =====================================================================

USE `VIPS_IMPORTS`;

-- ---------------------------------------------------------------------
-- Coluna de controle em compras
-- ---------------------------------------------------------------------
ALTER TABLE `compras`
  ADD COLUMN IF NOT EXISTS `publicada` TINYINT(1) NOT NULL DEFAULT 0 AFTER `observacoes`,
  ADD KEY IF NOT EXISTS `idx_compras_publicada` (`publicada`);

-- ---------------------------------------------------------------------
-- Tabela: loja_produtos (produto publicado na loja)
--   preco        = preço de venda (R$)
--   preco_sugerido = custo do item com +20% de lucro (sugestão)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `loja_produtos` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`     INT UNSIGNED NOT NULL,
  `compra_id`      INT UNSIGNED NOT NULL,
  `produto`        VARCHAR(160) NOT NULL,
  `marca`          VARCHAR(80)  NULL DEFAULT NULL,
  `categoria`      VARCHAR(40)  NOT NULL,
  `descricao`      TEXT         NULL,
  `preco`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `preco_sugerido` DECIMAL(12,2) NULL DEFAULT NULL,
  `publicado_em`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loja_produtos_compra` (`compra_id`),
  KEY `idx_loja_produtos_usuario` (`usuario_id`),
  CONSTRAINT `fk_loja_produtos_compra`
    FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_loja_produtos_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;