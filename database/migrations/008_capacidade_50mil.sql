-- =====================================================================
-- Capacidade de cada pote: 10.000 → 50.000 itens (igual a JAR_CAPACITY em includes/config.php)
-- =====================================================================

ALTER TABLE potes ALTER capacidade SET DEFAULT 50000;
UPDATE potes SET capacidade = 50000;
