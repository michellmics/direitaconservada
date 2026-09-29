-- =====================================================================
-- Notificações do app (Web Push), includes/push.php.
--   push_inscricoes  cada aparelho que aceitou receber avisos (endpoint = endereço de entrega do navegador)
--                    aviso_id = último aviso enviado a ele (o app busca o texto em api/push → "ver")
--   push_avisos      cada aviso enviado (enquete nova, virada no placar…) e quantos receberam / abriram
--   push_config      chaves VAPID (geradas sozinhas no 1º uso) e estado do placar (quem lidera)
-- =====================================================================

CREATE TABLE push_inscricoes (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    endpoint    VARCHAR(700)    NOT NULL,
    hash        CHAR(64)        NOT NULL,           -- sha256 do endpoint (índice único)
    lado        VARCHAR(20)     NULL,               -- pote de quem se inscreveu (avisos de placar vão para o pote certo)
    usuario_id  BIGINT UNSIGNED NULL,
    criado_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    visto_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aviso_id    BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_hash (hash),
    KEY idx_push_lado (lado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_avisos (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criado_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo        VARCHAR(30)     NOT NULL,           -- enquete | placar | teste
    lado        VARCHAR(20)     NULL,               -- NULL = todos os inscritos
    titulo      VARCHAR(120)    NOT NULL,
    texto       VARCHAR(300)    NOT NULL,
    url         VARCHAR(200)    NOT NULL,
    enviados    INT UNSIGNED    NOT NULL DEFAULT 0,
    falhas      INT UNSIGNED    NOT NULL DEFAULT 0,
    recebidos   INT UNSIGNED    NOT NULL DEFAULT 0,
    cliques     INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_avisos_tipo (tipo, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_config (
    chave       VARCHAR(40)     NOT NULL,
    valor       TEXT            NOT NULL,
    PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
