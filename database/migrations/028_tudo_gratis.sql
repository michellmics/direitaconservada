-- =====================================================================
-- O site ficou grátis (a receita vem do Google AdSense): pegar e renovar azeitona/pimenta não custa nada.
-- Os pedidos que estavam aguardando o Pix são liberados agora, como se o pagamento tivesse sido aprovado:
--   compra     → itens 'ativo', 1 ano a partir de hoje (igual a pedido_itens_status(…, 'ativo'))
--   renovação  → mesma regra de renovar_item(): em dia soma 1 ano e mantém o "desde"; vencido recomeça hoje
-- Daqui para a frente os pedidos já nascem 'pago' com total 0 (includes/pedidos.php).
-- =====================================================================

UPDATE itens i
    JOIN pedido_itens pi ON pi.id = i.pedido_item_id
    JOIN pedidos p ON p.id = pi.pedido_id AND p.status = 'pendente'
SET i.status = 'ativo', i.desde = CURDATE(), i.valido_ate = CURDATE() + INTERVAL 1 YEAR
WHERE i.status = 'pendente';

UPDATE itens i
    JOIN pedido_itens pi ON pi.renova_item_id = i.id
    JOIN pedidos p ON p.id = pi.pedido_id AND p.status = 'pendente'
SET i.desde      = IF(i.valido_ate >= CURDATE(), i.desde, CURDATE()),
    i.valido_ate = IF(i.valido_ate >= CURDATE(), i.valido_ate + INTERVAL 1 YEAR, CURDATE() + INTERVAL 1 YEAR),
    i.status     = 'ativo'
WHERE i.status IN ('ativo', 'vencido');

-- avisado_em NULL: na próxima visita a pessoa vê "liberado" (mostrarAvisos() em app.js)
UPDATE pedidos SET status = 'pago', pago_em = NOW(), resolvido_em = NOW(), avisado_em = NULL
WHERE status = 'pendente';
