-- =====================================================================
-- Nível ao lado do nome (tempero): o maior nível já alcançado por pote,
-- para mandar o e-mail "você subiu de nível" uma única vez por nível
-- =====================================================================

CREATE TABLE usuario_niveis (
    usuario_id      BIGINT UNSIGNED  NOT NULL,
    lado            VARCHAR(20)      NOT NULL,
    nivel_maximo    TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- cair de nível não reduz (não repete o e-mail)
    pontos          INT UNSIGNED     NOT NULL DEFAULT 0,   -- pontos na última subida
    avisado_em      DATETIME         NULL,                 -- último e-mail de subida
    atualizado_em   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, lado),
    CONSTRAINT fk_usuario_niveis_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_usuario_niveis_pote    FOREIGN KEY (lado)       REFERENCES potes (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
