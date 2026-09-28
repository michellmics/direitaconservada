-- =====================================================================
-- Login por link mágico: sessões persistentes (30 dias) e destino após entrar
-- =====================================================================

CREATE TABLE sessoes (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id        BIGINT UNSIGNED NOT NULL,
    token_hash        CHAR(64)     NOT NULL,            -- sha256 do cookie; o token puro só fica no navegador
    user_agent        VARCHAR(255) NULL,
    criado_em         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_em         DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sessoes_token (token_hash),
    KEY idx_sessoes_usuario (usuario_id),
    KEY idx_sessoes_expira (expira_em),
    CONSTRAINT fk_sessoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- para onde voltar depois de clicar no link (ex.: /pote.php?lado=esquerda#enquete)
ALTER TABLE login_tokens
    ADD COLUMN redirecionar VARCHAR(255) NULL AFTER finalidade,
    ADD KEY idx_login_tokens_recentes (usuario_id, criado_em);
