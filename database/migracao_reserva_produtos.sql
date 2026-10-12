-- =====================================================================
-- VIP Imports — Migração: reserva de itens do catálogo
-- Quando um pedido é registrado, os itens do catálogo ficam RESERVADOS
-- (somem da loja) até o vendedor confirmar a venda (-> vendido) ou
-- cancelar o pedido (-> volta a ficar disponível).
-- =====================================================================

ALTER TABLE loja_produtos
  ADD COLUMN reservado TINYINT(1) NOT NULL DEFAULT 0 AFTER vendido_em,
  ADD COLUMN reservado_em DATETIME NULL DEFAULT NULL AFTER reservado,
  ADD COLUMN reservado_pedido_id INT(10) UNSIGNED NULL DEFAULT NULL AFTER reservado_em;

ALTER TABLE loja_produtos
  ADD INDEX idx_reservado_pedido (reservado_pedido_id);
