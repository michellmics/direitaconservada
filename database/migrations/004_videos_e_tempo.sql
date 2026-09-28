-- =====================================================================
-- Resposta em vídeo nos comentários + índice para o ranking de provocadores
-- =====================================================================

ALTER TABLE comentarios
    MODIFY texto VARCHAR(200) NULL,
    ADD COLUMN video_provider ENUM('youtube','tiktok') NULL AFTER texto,
    ADD COLUMN video_id       VARCHAR(32) NULL AFTER video_provider,
    ADD COLUMN video_vertical TINYINT(1) NOT NULL DEFAULT 0 AFTER video_id,
    ADD CONSTRAINT ck_comentarios_conteudo CHECK (texto IS NOT NULL OR video_id IS NOT NULL),
    ADD KEY idx_comentarios_mes (status, criado_em);
