-- =====================================================================
-- Tretódromo: duelos 1×1 entre uma azeitona e uma pimenta (includes/duelos.php, /tretodromo, /duelo?n=…).
-- Grátis: basta ter um item ativo. Lados: a = quem desafiou, b = quem foi desafiado (sempre do outro pote).
--   status  aguardando → (aceitou) andamento → (6 argumentos) votacao → encerrado
--           aguardando → recusado | expirado (não aceitou no prazo)
--           andamento  → encerrado com wo = 1 (quem estava na vez não respondeu no prazo)
--   vez / rodada  de quem é a vez de argumentar (rodada 1 a 3: a, depois b)
--   prazo_em      fim do prazo da etapa atual (aceitar, responder ou votação)
-- =====================================================================

CREATE TABLE duelos (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tema         VARCHAR(140)    NOT NULL,
    item_a       BIGINT UNSIGNED NOT NULL,
    item_b       BIGINT UNSIGNED NOT NULL,
    status       ENUM('aguardando','andamento','votacao','encerrado','recusado','expirado') NOT NULL DEFAULT 'aguardando',
    rodada       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    vez          ENUM('a','b')   NULL,
    prazo_em     DATETIME        NOT NULL,
    vencedor     ENUM('a','b','empate') NULL,
    wo           TINYINT(1)      NOT NULL DEFAULT 0,
    votos_a      INT UNSIGNED    NOT NULL DEFAULT 0,
    votos_b      INT UNSIGNED    NOT NULL DEFAULT 0,
    criado_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    encerrado_em DATETIME        NULL,
    PRIMARY KEY (id),
    KEY idx_duelos_status (status, prazo_em),
    KEY idx_duelos_a (item_a, status),
    KEY idx_duelos_b (item_b, status),
    KEY idx_duelos_encerrado (encerrado_em),
    CONSTRAINT fk_duelos_a FOREIGN KEY (item_a) REFERENCES itens (id),
    CONSTRAINT fk_duelos_b FOREIGN KEY (item_b) REFERENCES itens (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE duelo_argumentos (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    duelo_id   BIGINT UNSIGNED NOT NULL,
    rodada     TINYINT UNSIGNED NOT NULL,
    lado       ENUM('a','b')   NOT NULL,
    texto      VARCHAR(500)    NOT NULL,
    criado_em  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_argumento (duelo_id, rodada, lado),
    CONSTRAINT fk_argumentos_duelo FOREIGN KEY (duelo_id) REFERENCES duelos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE duelo_votos (
    duelo_id   BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    voto       ENUM('a','b')   NOT NULL,
    criado_em  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (duelo_id, usuario_id),
    CONSTRAINT fk_votos_duelo   FOREIGN KEY (duelo_id)   REFERENCES duelos (id) ON DELETE CASCADE,
    CONSTRAINT fk_votos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
