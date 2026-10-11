-- =====================================================================
-- VIP Imports — Módulo de Viagens e Gastos
-- Banco: MySQL / MariaDB
-- ---------------------------------------------------------------------
-- Regra de negócio: apenas UMA viagem em aberto por usuário. Ao
-- registrar uma nova viagem, as demais abertas são encerradas
-- (data_final preenchida, se ainda estiver vazia).
-- =====================================================================

USE `VIPS_IMPORTS`;

-- ---------------------------------------------------------------------
-- Tabela: viagens
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `viagens` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`    INT UNSIGNED NOT NULL,
  `destino`       VARCHAR(160) NOT NULL,
  `data_inicial`  DATE NOT NULL,
  `data_final`    DATE NULL DEFAULT NULL,
  `status`        ENUM('aberta','encerrada') NOT NULL DEFAULT 'aberta',
  `criado_em`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_viagens_usuario` (`usuario_id`),
  KEY `idx_viagens_status` (`status`),
  CONSTRAINT `fk_viagens_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabela: gastos
--   categorias: passagem, estadia, alimentacao, outros
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gastos` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `viagem_id`     INT UNSIGNED NOT NULL,
  `categoria`     ENUM('passagem','estadia','alimentacao','outros') NOT NULL,
  `descricao`     VARCHAR(200) NULL DEFAULT NULL,
  `valor`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `data_gasto`    DATE NOT NULL,
  `criado_em`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gastos_viagem` (`viagem_id`),
  KEY `idx_gastos_categoria` (`categoria`),
  CONSTRAINT `fk_gastos_viagem`
    FOREIGN KEY (`viagem_id`) REFERENCES `viagens` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
