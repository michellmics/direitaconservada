-- =====================================================================
-- Tudo no banco (nada mais só no navegador):
--   itens.status 'pendente' → comprado, pagamento Pix ainda não conferido. Só a dona vê (com os posts e
--                             comentários que fizer com ele). Aprovado vira 'ativo'; negado/expirado, 'removido'.
--   pedidos.avisado_em      → a pessoa já viu o aviso "pagamento confirmado" / "pedido cancelado".
-- =====================================================================

ALTER TABLE itens
    MODIFY status ENUM('pendente','ativo','vencido','removido') NOT NULL DEFAULT 'ativo';

ALTER TABLE pedidos
    ADD COLUMN avisado_em DATETIME NULL AFTER resolvido_em;
