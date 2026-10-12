-- =====================================================================
-- VIP Imports — Loja Virtual (catálogo público + pedidos + Telegram)
-- Banco: MySQL / MariaDB
-- ---------------------------------------------------------------------
-- usuario_config : link público da loja (loja_token) e credenciais do
--                  bot do Telegram por usuário.
-- pedidos        : pedido recebido (da loja pública ou do Telegram).
-- pedido_itens   : itens de cada pedido (snapshot do nome/preço).
-- =====================================================================

USE `VIPS_IMPORTS`;

-- ---------------------------------------------------------------------
-- Tabela: usuario_config (1 linha por usuário)
--   loja_token             = código público divulgável (loja.php?c=...)
--   telegram_bot_token     = token do bot (BotFather)
--   telegram_webhook_secret= segredo validado no webhook
--   telegram_group_chat_id = id do grupo onde o catálogo é divulgado
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuario_config` (
  `usuario_id`              INT UNSIGNED NOT NULL,
  `loja_token`              VARCHAR(40)  NOT NULL,
  `telegram_bot_token`      VARCHAR(160) NULL DEFAULT NULL,
  `telegram_webhook_secret` VARCHAR(80)  NULL DEFAULT NULL,
  `telegram_group_chat_id`  VARCHAR(40)  NULL DEFAULT NULL,
  `criado_em`               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_id`),
  UNIQUE KEY `uq_usuario_config_token` (`loja_token`),
  CONSTRAINT `fk_usuario_config_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: pedidos
--   origem: loja (página pública) | telegram
--   status: novo | confirmado | concluido | cancelado
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pedidos` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`      INT UNSIGNED NOT NULL,
  `cliente_nome`    VARCHAR(120) NOT NULL,
  `cliente_contato` VARCHAR(160) NOT NULL,
  `observacao`      VARCHAR(500) NULL DEFAULT NULL,
  `total`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`          ENUM('novo','confirmado','concluido','cancelado') NOT NULL DEFAULT 'novo',
  `origem`          ENUM('loja','telegram') NOT NULL DEFAULT 'loja',
  `criado_em`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pedidos_usuario` (`usuario_id`),
  KEY `idx_pedidos_status` (`status`),
  CONSTRAINT `fk_pedidos_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: pedido_itens
--   produto_id é um snapshot "solto" (sem FK) para preservar o histórico
--   mesmo que o produto seja removido da loja.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pedido_itens` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_id`  INT UNSIGNED NOT NULL,
  `produto_id` INT UNSIGNED NULL DEFAULT NULL,
  `produto`    VARCHAR(160) NOT NULL,
  `preco`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `quantidade` INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_pedido_itens_pedido` (`pedido_id`),
  CONSTRAINT `fk_pedido_itens_pedido`
    FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
