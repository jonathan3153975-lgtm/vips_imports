-- =====================================================================
-- VIP Imports — Script da tabela de usuários
-- Banco: MySQL / MariaDB
-- =====================================================================

-- Cria o schema caso não exista (ajuste o nome se necessário)
CREATE DATABASE IF NOT EXISTS `VIPS_IMPORTS`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `VIPS_IMPORTS`;

-- ---------------------------------------------------------------------
-- Tabela: usuarios
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`          VARCHAR(120) NOT NULL,
  `email`         VARCHAR(160) NOT NULL,
  `senha`         VARCHAR(255) NOT NULL,
  `perfil`        ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
  `ativo`         TINYINT(1) NOT NULL DEFAULT 1,
  `ultimo_acesso` DATETIME NULL DEFAULT NULL,
  `criado_em`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Usuário fictício
--   Login: maria@vip.app
--   Senha: senha123
-- (hash gerado com password_hash('senha123', PASSWORD_DEFAULT))
-- ---------------------------------------------------------------------
INSERT INTO `usuarios` (`nome`, `email`, `senha`, `perfil`, `ativo`)
VALUES (
  'Maria Souza',
  'maria@vip.app',
  '$2y$12$L9w0C9PEL7DWnXOFhIhDVObqYUUJwZbcHPsXIuz1frwdKcone0Czq',
  'admin',
  1
)
ON DUPLICATE KEY UPDATE
  `nome`   = VALUES(`nome`),
  `senha`  = VALUES(`senha`),
  `perfil` = VALUES(`perfil`),
  `ativo`  = VALUES(`ativo`);
