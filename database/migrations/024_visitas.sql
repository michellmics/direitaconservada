-- =====================================================================
-- Contador de visitas (painel → Visitas, /cozinha/visitas).
--   visitas         uma linha por página vista (robôs e o painel não contam).
--                   visitante = hash do cookie anônimo do navegador (sem IP, sem dado pessoal)
--                   novo = 1 na primeira página que esse navegador viu
--   visitas_online  quem está com o site aberto: o navegador avisa a cada ~30 s (api/visita.php);
--                   "online" = visto nos últimos 2 minutos
-- =====================================================================

CREATE TABLE visitas (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criado_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    visitante   CHAR(32)        NOT NULL,
    novo        TINYINT(1)      NOT NULL DEFAULT 0,
    pagina      VARCHAR(80)     NOT NULL,
    origem      VARCHAR(80)     NOT NULL DEFAULT 'direto',
    dispositivo ENUM('celular','tablet','computador') NOT NULL DEFAULT 'computador',
    PRIMARY KEY (id),
    KEY idx_visitas_data (criado_em, visitante),
    KEY idx_visitas_visitante (visitante, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE visitas_online (
    visitante   CHAR(32)        NOT NULL,
    visto_em    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    entrou_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pagina      VARCHAR(80)     NOT NULL,
    dispositivo ENUM('celular','tablet','computador') NOT NULL DEFAULT 'computador',
    PRIMARY KEY (visitante),
    KEY idx_online_visto (visto_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
