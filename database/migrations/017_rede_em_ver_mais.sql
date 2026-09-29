-- =====================================================================
-- Apoio partidário: a 1ª linha passa a ter 4 partidos (PT, PL, Missão, PSOL — botões retangulares)
-- e a REDE vai para o "Exibir mais" (12ª posição; os 11 primeiros aparecem sem clicar).
-- =====================================================================

UPDATE partidos SET ordem = ordem - 1 WHERE ordem BETWEEN 6 AND 12;
UPDATE partidos SET ordem = 12 WHERE sigla = 'REDE';
