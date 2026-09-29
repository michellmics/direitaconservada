-- =====================================================================
-- Pix sem gateway: o site gera o código com a chave do .env (PIX_CHAVE) e o administrador
-- confere no extrato e aprova (ou nega) em /admin/pedidos.
--   codigo       → número do pedido que a pessoa vê (e vai no txid do Pix)
--   pagador_nome → nome do titular da conta que vai pagar (é o que aparece no extrato)
--   ip           → freio contra quem gera pedidos sem parar
-- =====================================================================

ALTER TABLE pedidos
    ADD COLUMN codigo        CHAR(8)      NULL AFTER id,
    ADD COLUMN pagador_nome  VARCHAR(100) NULL AFTER metodo,
    ADD COLUMN ip            VARCHAR(45)  NULL AFTER pagador_nome,
    ADD COLUMN resolvido_em  DATETIME     NULL AFTER pago_em,       -- aprovado, negado ou expirado
    ADD UNIQUE KEY uq_pedidos_codigo (codigo),
    ADD KEY idx_pedidos_ip (ip, status);
