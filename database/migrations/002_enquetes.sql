-- =====================================================================
-- Enquetes (criadas no painel /admin/)
-- Regra: só UMA enquete ativa por vez no site todo (garantido pelo índice único em "ativa").
-- =====================================================================

CREATE TABLE enquetes (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pergunta        VARCHAR(200) NOT NULL,
    descricao       VARCHAR(500) NULL,
    lado            VARCHAR(20)  NULL,                  -- NULL = aparece nos dois potes (duelo)
    status          ENUM('rascunho','ativa','encerrada') NOT NULL DEFAULT 'rascunho',
    resultado       ENUM('sempre','apos_votar','apos_encerrar') NOT NULL DEFAULT 'apos_votar',
    termina_em      DATETIME     NULL,                  -- opcional: encerra sozinha nesse horário
    publicada_em    DATETIME     NULL,
    encerrada_em    DATETIME     NULL,
    criado_por      BIGINT UNSIGNED NULL,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- 1 quando ativa, NULL nos outros casos: o UNIQUE impede duas ativas ao mesmo tempo
    ativa           TINYINT GENERATED ALWAYS AS (IF(status = 'ativa', 1, NULL)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_enquetes_uma_ativa (ativa),
    KEY idx_enquetes_status (status, criado_em),
    CONSTRAINT fk_enquetes_pote    FOREIGN KEY (lado)       REFERENCES potes (slug),
    CONSTRAINT fk_enquetes_criador FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enquete_opcoes (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquete_id  BIGINT UNSIGNED NOT NULL,
    texto       VARCHAR(120) NOT NULL,
    ordem       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_enquete_opcoes_enquete (enquete_id, ordem),
    CONSTRAINT fk_enquete_opcoes_enquete FOREIGN KEY (enquete_id) REFERENCES enquetes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enquete_votos (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquete_id  BIGINT UNSIGNED NOT NULL,
    opcao_id    BIGINT UNSIGNED NOT NULL,
    lado        VARCHAR(20)  NOT NULL,                  -- de qual pote a pessoa votou
    usuario_id  BIGINT UNSIGNED NULL,                   -- preenchido quando houver login
    votante     CHAR(64)     NOT NULL,                  -- sha256 do cookie do navegador (ou do usuário)
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_enquete_votos_um_por_pessoa (enquete_id, votante),
    KEY idx_enquete_votos_contagem (enquete_id, opcao_id, lado),
    CONSTRAINT fk_enquete_votos_enquete FOREIGN KEY (enquete_id) REFERENCES enquetes (id) ON DELETE CASCADE,
    CONSTRAINT fk_enquete_votos_opcao   FOREIGN KEY (opcao_id)   REFERENCES enquete_opcoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_enquete_votos_pote    FOREIGN KEY (lado)       REFERENCES potes (slug),
    CONSTRAINT fk_enquete_votos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
