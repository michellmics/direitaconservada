-- =====================================================================
-- Presentes: "🎁 É presente" na compra. Depois do pagamento aprovado, quem comprou recebe um link para mandar
-- (WhatsApp); quem abre o link e toca em "Resgatar" vira dona do item.
--   presente_token        → o link (enquanto ninguém resgatou). O item fica na conta de quem comprou,
--                           mas é outra "pessoa" no pote (perfil próprio, fora do nível/cadastro de quem deu).
--   presente_de           → quem deu (continua depois do resgate)
--   presente_resgatado_em → quando foi resgatado (aí usuario_id vira quem ganhou e o token some)
-- =====================================================================

ALTER TABLE itens
    ADD COLUMN presente_token        CHAR(24)        NULL AFTER pedido_item_id,
    ADD COLUMN presente_de           BIGINT UNSIGNED NULL AFTER presente_token,
    ADD COLUMN presente_resgatado_em DATETIME        NULL AFTER presente_de,
    ADD UNIQUE KEY uq_itens_presente (presente_token),
    ADD CONSTRAINT fk_itens_presente_de FOREIGN KEY (presente_de) REFERENCES usuarios (id) ON DELETE SET NULL;

ALTER TABLE pedido_itens
    ADD COLUMN presente TINYINT(1) NOT NULL DEFAULT 0 AFTER renova_item_id;
