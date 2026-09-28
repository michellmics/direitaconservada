-- =====================================================================
-- Preços novos (R$ 2,90 · 4,90 · 6,90 · 9,90), Biquinho como a pimenta mais barata
-- e "Dedo-de-moça" renomeada para "Vermelha" (nome curto, cabe numa linha na compra).
-- Pedidos já feitos guardam o preço antigo em pedido_itens.preco_unit_centavos.
-- =====================================================================

UPDATE item_tipos SET preco_centavos = 290, ordem = 1 WHERE lado = 'direita' AND slug = 'verde';
UPDATE item_tipos SET preco_centavos = 490, ordem = 2 WHERE lado = 'direita' AND slug = 'recheada';
UPDATE item_tipos SET preco_centavos = 690, ordem = 3 WHERE lado = 'direita' AND slug = 'preta';
UPDATE item_tipos SET preco_centavos = 990, ordem = 4 WHERE lado = 'direita' AND slug = 'grande';

UPDATE item_tipos SET preco_centavos = 290, ordem = 1 WHERE lado = 'esquerda' AND slug = 'biquinho';
UPDATE item_tipos SET preco_centavos = 490, ordem = 2, nome = 'Vermelha' WHERE lado = 'esquerda' AND slug = 'vermelha';
UPDATE item_tipos SET preco_centavos = 690, ordem = 3 WHERE lado = 'esquerda' AND slug = 'malagueta';
UPDATE item_tipos SET preco_centavos = 990, ordem = 4 WHERE lado = 'esquerda' AND slug = 'grande';
