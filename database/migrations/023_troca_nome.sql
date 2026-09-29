-- =====================================================================
-- Troca de nome no perfil (api/perfil.php): 1 troca a cada 30 dias.
--   usuarios.nome_trocado_em  quando a pessoa trocou o nome pela última vez (NULL = nunca trocou)
-- A troca muda o nome da conta e o das azeitonas/pimentas dela que tinham o nome antigo (nos dois potes).
-- O histórico do pedido (pedido_itens.nome_certificado) e o titular do Pix não mudam.
-- =====================================================================

ALTER TABLE usuarios ADD COLUMN nome_trocado_em DATETIME NULL;
