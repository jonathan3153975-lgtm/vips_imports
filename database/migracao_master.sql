-- =====================================================================
-- VIP Imports — Migração: usuário master
-- 1) Acrescenta o papel 'master' ao ENUM de perfis.
-- 2) Cria a conta master padrão (troque a senha após o primeiro acesso).
--    Login: master@vip.app   Senha: master123
-- =====================================================================

ALTER TABLE `usuarios`
  MODIFY COLUMN `perfil` ENUM('master','admin','usuario') NOT NULL DEFAULT 'usuario';

INSERT INTO `usuarios` (`nome`, `email`, `senha`, `perfil`, `ativo`)
SELECT 'Master', 'master@vip.app', '$2y$12$Y85LVI6xrKT7b5wC6SRkG./51EUN3mepe.z2QXzltNvojPU8KU6/G', 'master', 1
WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM `usuarios` WHERE `email` = 'master@vip.app') AS t);
