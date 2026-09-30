-- =====================================================================
-- Mural aberto a quem tem conta: publicar e comentar não exige mais azeitona/pimenta.
--   posts.usuario_id / comentarios.usuario_id  quem escreveu (a conta), sempre preenchido
--   posts.item_id / comentarios.item_id        o item com que escreveu (NULL = conta sem item: aparece com o nome da conta)
-- O Tretódromo continua exigindo item ativo (duelos.item_a / item_b).
-- =====================================================================

ALTER TABLE posts ADD COLUMN usuario_id BIGINT UNSIGNED NULL AFTER item_id;
UPDATE posts p JOIN itens i ON i.id = p.item_id SET p.usuario_id = i.usuario_id;
ALTER TABLE posts
    MODIFY item_id BIGINT UNSIGNED NULL,
    MODIFY usuario_id BIGINT UNSIGNED NOT NULL,
    ADD KEY idx_posts_usuario (usuario_id, criado_em),
    ADD CONSTRAINT fk_posts_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id);

ALTER TABLE comentarios ADD COLUMN usuario_id BIGINT UNSIGNED NULL AFTER item_id;
UPDATE comentarios c JOIN itens i ON i.id = c.item_id SET c.usuario_id = i.usuario_id;
ALTER TABLE comentarios
    MODIFY item_id BIGINT UNSIGNED NULL,
    MODIFY usuario_id BIGINT UNSIGNED NOT NULL,
    ADD KEY idx_comentarios_usuario (usuario_id, criado_em),
    ADD CONSTRAINT fk_comentarios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id);
