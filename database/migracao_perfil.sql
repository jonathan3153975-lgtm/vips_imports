-- =====================================================================
-- VIP Imports — Migração: perfil do usuário (nome e logotipo da loja)
-- Acrescenta à configuração do usuário o nome da loja e o arquivo do
-- logotipo exibidos na loja pública (link divulgável).
-- =====================================================================

ALTER TABLE usuario_config
  ADD COLUMN loja_nome VARCHAR(120) NULL DEFAULT NULL AFTER loja_token,
  ADD COLUMN loja_logo VARCHAR(160) NULL DEFAULT NULL AFTER loja_nome;
