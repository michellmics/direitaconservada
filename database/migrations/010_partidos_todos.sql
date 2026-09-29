-- =====================================================================
-- Todos os partidos nos dois potes (o "lado" do partido vira só informação do espectro).
-- Novo: Missão. Ordem única: PT, PL, Missão, PSOL e REDE em destaque na 1ª linha, depois os demais.
-- =====================================================================

INSERT INTO partidos (sigla, nome, lado, cor, cor_texto, ordem) VALUES
    ('MISSÃO', 'Partido Missão', 'direita', '#111111', '#ffd400', 3);

UPDATE partidos SET ordem = CASE sigla
    WHEN 'PT' THEN 1  WHEN 'PL' THEN 2  WHEN 'MISSÃO' THEN 3  WHEN 'PSOL' THEN 4  WHEN 'REDE' THEN 5
    WHEN 'NOVO' THEN 6  WHEN 'PSB' THEN 7  WHEN 'PDT' THEN 8  WHEN 'PCdoB' THEN 9  WHEN 'PP' THEN 10
    WHEN 'REPUBLICANOS' THEN 11  WHEN 'UNIÃO' THEN 12  WHEN 'PSD' THEN 13  WHEN 'MDB' THEN 14  WHEN 'PSDB' THEN 15
    WHEN 'PODEMOS' THEN 16  WHEN 'PV' THEN 17  WHEN 'PCB' THEN 18  WHEN 'UP' THEN 19  WHEN 'PSTU' THEN 20
    WHEN 'PRD' THEN 21  ELSE 99 END;
