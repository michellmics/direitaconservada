-- =====================================================================
-- Apoio partidário: cada pessoa escolhe até 3 partidos do seu lado (pimenta = esquerda, azeitona = direita)
-- e o pote mostra o ranking. Partidos editáveis aqui (ativo = 0 esconde; ordem = posição dos quadrados).
-- =====================================================================

CREATE TABLE partidos (
    sigla       VARCHAR(20)  NOT NULL,
    nome        VARCHAR(80)  NOT NULL,
    lado        VARCHAR(20)  NOT NULL,                   -- esquerda | direita (o pote onde aparece)
    cor         CHAR(7)      NOT NULL,                   -- fundo do quadrado
    cor_texto   CHAR(7)      NOT NULL DEFAULT '#ffffff', -- sigla
    ordem       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ativo       TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (sigla),
    KEY idx_partidos_lado (lado, ativo, ordem),
    CONSTRAINT fk_partidos_pote FOREIGN KEY (lado) REFERENCES potes (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE apoios_partido (
    usuario_id  BIGINT UNSIGNED NOT NULL,
    sigla       VARCHAR(20)     NOT NULL,
    criado_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, sigla),
    KEY idx_apoios_sigla (sigla),
    CONSTRAINT fk_apoios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_apoios_partido FOREIGN KEY (sigla) REFERENCES partidos (sigla) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO partidos (sigla, nome, lado, cor, cor_texto, ordem) VALUES
    ('PT',           'Partido dos Trabalhadores',              'esquerda', '#c4161c', '#ffffff', 1),
    ('PSOL',         'Partido Socialismo e Liberdade',         'esquerda', '#ffcc00', '#6a1b9a', 2),
    ('PCdoB',        'Partido Comunista do Brasil',            'esquerda', '#b3001b', '#ffe14d', 3),
    ('PSB',          'Partido Socialista Brasileiro',          'esquerda', '#f39200', '#ffffff', 4),
    ('PDT',          'Partido Democrático Trabalhista',        'esquerda', '#1d3f8f', '#ffffff', 5),
    ('REDE',         'Rede Sustentabilidade',                  'esquerda', '#00a4a6', '#ffffff', 6),
    ('PV',           'Partido Verde',                          'esquerda', '#1b9e3e', '#ffffff', 7),
    ('PCB',          'Partido Comunista Brasileiro',           'esquerda', '#7a0010', '#ffd000', 8),
    ('UP',           'Unidade Popular',                        'esquerda', '#e4002b', '#111111', 9),
    ('PSTU',         'Partido Socialista dos Trabalhadores Unificado', 'esquerda', '#231f20', '#ff3b3b', 10),
    ('PL',           'Partido Liberal',                        'direita',  '#0033a0', '#ffd100', 1),
    ('NOVO',         'Partido Novo',                           'direita',  '#f58220', '#ffffff', 2),
    ('PP',           'Progressistas',                          'direita',  '#0057a8', '#ffffff', 3),
    ('REPUBLICANOS', 'Republicanos',                           'direita',  '#1f4e9c', '#7fd3ff', 4),
    ('UNIÃO',        'União Brasil',                           'direita',  '#f9c80e', '#0a3d91', 5),
    ('PSD',          'Partido Social Democrático',             'direita',  '#ffd100', '#004b93', 6),
    ('PODEMOS',      'Podemos',                                'direita',  '#00a651', '#ffffff', 7),
    ('MDB',          'Movimento Democrático Brasileiro',       'direita',  '#008d36', '#ffdd00', 8),
    ('PSDB',         'Partido da Social Democracia Brasileira', 'direita', '#0080c6', '#ffd200', 9),
    ('PRD',          'Partido Renovação Democrática',          'direita',  '#1e2a78', '#ffffff', 10);
