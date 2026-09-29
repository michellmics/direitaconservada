-- =====================================================================
-- Citar um comentário ("↩ Respondendo a Fulano"): qual comentário foi citado.
-- Se o citado for apagado, a citação mostra "comentário apagado".
-- =====================================================================

ALTER TABLE comentarios
    ADD COLUMN cita_id BIGINT UNSIGNED NULL AFTER item_id,
    ADD CONSTRAINT fk_comentarios_cita FOREIGN KEY (cita_id) REFERENCES comentarios (id) ON DELETE SET NULL;
