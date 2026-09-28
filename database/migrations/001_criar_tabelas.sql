-- =====================================================================
-- Direita Conservada × Pimenta da Resistência — estrutura completa
-- MySQL 8+ / MariaDB 10.6+ · utf8mb4 (emojis nos selos e textos)
--
-- Rodar:  php database/migrate.php        (usa as credenciais do .env)
--   ou:   mysql -u USUARIO -p NOME_DO_BANCO < database/migrations/001_criar_tabelas.sql
--
-- Convenções:
--   * dinheiro em centavos (INT)       → R$ 14,90 = 1490
--   * "lado" = slug do pote            → 'direita' | 'esquerda'
--   * "item" = uma azeitona ou pimenta no pote
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- ---------------------------------------------------------------------
-- Potes (os dois lados do site)
-- ---------------------------------------------------------------------
CREATE TABLE potes (
    slug            VARCHAR(20)  NOT NULL,
    nome            VARCHAR(60)  NOT NULL,
    item_singular   VARCHAR(20)  NOT NULL,              -- azeitona / pimenta
    item_plural     VARCHAR(20)  NOT NULL,
    capacidade      INT UNSIGNED NOT NULL DEFAULT 10000,
    proximo_numero  INT UNSIGNED NOT NULL DEFAULT 1,    -- numeração sequencial dos itens (#0001…)
    PRIMARY KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tipos de item e preço anual (Verde, Recheada, Dedo-de-moça…)
-- ---------------------------------------------------------------------
CREATE TABLE item_tipos (
    id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lado            VARCHAR(20)  NOT NULL,
    slug            VARCHAR(20)  NOT NULL,              -- verde, recheada, vermelha, grande…
    nome            VARCHAR(40)  NOT NULL,
    preco_centavos  INT UNSIGNED NOT NULL,              -- preço por ano
    escala          DECIMAL(3,2) NOT NULL DEFAULT 1.00, -- tamanho no pote (Grande = 1.50)
    ordem           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_item_tipos_lado_slug (lado, slug),
    CONSTRAINT fk_item_tipos_pote FOREIGN KEY (lado) REFERENCES potes (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Usuários (quem compra, publica e comenta)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome                VARCHAR(80)  NOT NULL,
    email               VARCHAR(190) NOT NULL,
    telefone            VARCHAR(20)  NULL,
    cpf                 CHAR(11)     NULL,              -- exigido por alguns gateways de Pix
    senha_hash          VARCHAR(255) NULL,              -- NULL = entra só por link mágico
    email_verificado_em DATETIME     NULL,
    is_admin            TINYINT(1)   NOT NULL DEFAULT 0,
    status              ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
    criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    UNIQUE KEY uq_usuarios_cpf (cpf)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login sem senha (link mágico por e-mail/WhatsApp) e "esqueci a senha"
CREATE TABLE login_tokens (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  BIGINT UNSIGNED NOT NULL,
    token_hash  CHAR(64)     NOT NULL,                  -- sha256 do token; o token puro só vai no link
    finalidade  ENUM('login','reset_senha','verificar_email') NOT NULL DEFAULT 'login',
    expira_em   DATETIME     NOT NULL,
    usado_em    DATETIME     NULL,
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_login_tokens_hash (token_hash),
    KEY idx_login_tokens_usuario (usuario_id),
    CONSTRAINT fk_login_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pedidos (carrinho pago) e itens do pedido
-- ---------------------------------------------------------------------
CREATE TABLE pedidos (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id          BIGINT UNSIGNED NOT NULL,
    lado                VARCHAR(20)  NOT NULL,
    status              ENUM('pendente','pago','expirado','cancelado','estornado') NOT NULL DEFAULT 'pendente',
    subtotal_centavos   INT UNSIGNED NOT NULL,
    desconto_centavos   INT UNSIGNED NOT NULL DEFAULT 0,
    total_centavos      INT UNSIGNED NOT NULL,
    metodo              ENUM('pix','cartao') NOT NULL DEFAULT 'pix',
    gateway             VARCHAR(30)  NULL,              -- mercadopago, asaas, pagarme…
    gateway_id          VARCHAR(100) NULL,              -- id da cobrança no gateway
    pix_copia_cola      TEXT         NULL,
    pix_qr_base64       MEDIUMTEXT   NULL,
    expira_em           DATETIME     NULL,              -- validade da cobrança Pix
    pago_em             DATETIME     NULL,
    criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pedidos_gateway (gateway, gateway_id),
    KEY idx_pedidos_usuario (usuario_id),
    KEY idx_pedidos_status (status, criado_em),
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_pedidos_pote    FOREIGN KEY (lado) REFERENCES potes (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada linha do carrinho: tipo + quantidade + dados que vão no certificado.
-- Ao confirmar o pagamento, cada unidade vira uma linha em "itens".
CREATE TABLE pedido_itens (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id           BIGINT UNSIGNED NOT NULL,
    item_tipo_id        SMALLINT UNSIGNED NOT NULL,
    quantidade          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    preco_unit_centavos INT UNSIGNED NOT NULL,          -- preço congelado no momento da compra
    nome_certificado    VARCHAR(40)  NOT NULL,
    cidade              VARCHAR(60)  NOT NULL,
    uf                  CHAR(2)      NOT NULL,
    frase               VARCHAR(140) NOT NULL,
    foto_path           VARCHAR(255) NULL,
    selo_tipo           ENUM('preset','imagem') NULL,
    selo_valor          VARCHAR(255) NULL,              -- 'br', emoji, ou caminho da imagem enviada
    renova_item_id      BIGINT UNSIGNED NULL,           -- preenchido quando é renovação de um item existente
    presente_email      VARCHAR(190) NULL,              -- compra de presente: a quem entregar
    PRIMARY KEY (id),
    KEY idx_pedido_itens_pedido (pedido_id),
    CONSTRAINT fk_pedido_itens_pedido FOREIGN KEY (pedido_id)    REFERENCES pedidos (id) ON DELETE CASCADE,
    CONSTRAINT fk_pedido_itens_tipo   FOREIGN KEY (item_tipo_id) REFERENCES item_tipos (id),
    CONSTRAINT ck_pedido_itens_qtd    CHECK (quantidade BETWEEN 1 AND 50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Itens no pote (cada azeitona/pimenta)
-- ---------------------------------------------------------------------
CREATE TABLE itens (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lado            VARCHAR(20)  NOT NULL,
    numero          INT UNSIGNED NOT NULL,              -- #0042, único por pote
    usuario_id      BIGINT UNSIGNED NOT NULL,           -- dono
    pedido_item_id  BIGINT UNSIGNED NULL,               -- de qual compra veio
    item_tipo_id    SMALLINT UNSIGNED NOT NULL,
    nome            VARCHAR(40)  NOT NULL,              -- nome no certificado
    cidade          VARCHAR(60)  NOT NULL,
    uf              CHAR(2)      NOT NULL,
    frase           VARCHAR(140) NOT NULL,
    foto_path       VARCHAR(255) NULL,
    selo_tipo       ENUM('preset','imagem') NULL,
    selo_valor      VARCHAR(255) NULL,
    desde           DATE         NOT NULL,              -- "Conservado desde": mantida nas renovações
    valido_ate      DATE         NOT NULL,              -- vence 1 ano após o pagamento; renovação estende
    status          ENUM('ativo','vencido','removido') NOT NULL DEFAULT 'ativo',
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_itens_lado_numero (lado, numero),
    KEY idx_itens_pote (lado, status, numero),          -- desenhar o pote
    KEY idx_itens_usuario (usuario_id),
    KEY idx_itens_vencimento (status, valido_ate),      -- rotina que marca vencidos
    KEY idx_itens_uf (lado, uf),                        -- ranking por estado
    CONSTRAINT fk_itens_pote        FOREIGN KEY (lado)           REFERENCES potes (slug),
    CONSTRAINT fk_itens_usuario     FOREIGN KEY (usuario_id)     REFERENCES usuarios (id),
    CONSTRAINT fk_itens_pedido_item FOREIGN KEY (pedido_item_id) REFERENCES pedido_itens (id) ON DELETE SET NULL,
    CONSTRAINT fk_itens_tipo        FOREIGN KEY (item_tipo_id)   REFERENCES item_tipos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pedido_itens
    ADD CONSTRAINT fk_pedido_itens_renova FOREIGN KEY (renova_item_id) REFERENCES itens (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- Mural: posts, curtidas e comentários (comentário pode vir do outro pote)
-- ---------------------------------------------------------------------
CREATE TABLE posts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lado            VARCHAR(20)  NOT NULL,              -- mural onde aparece
    item_id         BIGINT UNSIGNED NOT NULL,           -- autor (o item que publica)
    texto           VARCHAR(400) NULL,
    video_provider  ENUM('youtube','tiktok') NULL,
    video_id        VARCHAR(32)  NULL,
    video_vertical  TINYINT(1)   NOT NULL DEFAULT 0,
    is_frase_compra TINYINT(1)   NOT NULL DEFAULT 0,    -- a frase escrita na compra
    fixado_ate      DATETIME     NULL,                  -- vantagem de assinante: post fixado
    curtidas_count    INT UNSIGNED NOT NULL DEFAULT 0,  -- contadores para ordenar sem COUNT(*)
    comentarios_count INT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('publicado','oculto','removido') NOT NULL DEFAULT 'publicado',
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_posts_recentes (lado, status, criado_em),
    KEY idx_posts_top (lado, status, curtidas_count),
    KEY idx_posts_debate (lado, status, comentarios_count),
    KEY idx_posts_item (item_id),
    CONSTRAINT fk_posts_pote FOREIGN KEY (lado)    REFERENCES potes (slug),
    CONSTRAINT fk_posts_item FOREIGN KEY (item_id) REFERENCES itens (id),
    CONSTRAINT ck_posts_conteudo CHECK (texto IS NOT NULL OR video_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE curtidas (
    post_id     BIGINT UNSIGNED NOT NULL,
    usuario_id  BIGINT UNSIGNED NOT NULL,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, usuario_id),
    KEY idx_curtidas_usuario (usuario_id),
    CONSTRAINT fk_curtidas_post    FOREIGN KEY (post_id)    REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_curtidas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comentarios (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id     BIGINT UNSIGNED NOT NULL,
    item_id     BIGINT UNSIGNED NOT NULL,               -- quem comenta (item de qualquer um dos potes)
    texto       VARCHAR(200) NOT NULL,
    status      ENUM('publicado','oculto','removido') NOT NULL DEFAULT 'publicado',
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comentarios_post (post_id, status, criado_em),
    KEY idx_comentarios_item (item_id),
    CONSTRAINT fk_comentarios_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_comentarios_item FOREIGN KEY (item_id) REFERENCES itens (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Moderação
-- ---------------------------------------------------------------------
CREATE TABLE denuncias (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    alvo_tipo       ENUM('post','comentario','item') NOT NULL,   -- item = foto/selo/frase no pote
    alvo_id         BIGINT UNSIGNED NOT NULL,
    usuario_id      BIGINT UNSIGNED NULL,               -- quem denunciou (NULL = visitante)
    motivo          ENUM('ofensivo','odio','spam','foto_indevida','selo_indevido','outro') NOT NULL,
    detalhes        VARCHAR(500) NULL,
    status          ENUM('aberta','procedente','improcedente') NOT NULL DEFAULT 'aberta',
    resolvido_por   BIGINT UNSIGNED NULL,
    resolvido_em    DATETIME NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_denuncias_fila (status, criado_em),
    KEY idx_denuncias_alvo (alvo_tipo, alvo_id),
    CONSTRAINT fk_denuncias_usuario   FOREIGN KEY (usuario_id)    REFERENCES usuarios (id) ON DELETE SET NULL,
    CONSTRAINT fk_denuncias_moderador FOREIGN KEY (resolvido_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Webhooks do gateway de pagamento (idempotência e auditoria)
-- ---------------------------------------------------------------------
CREATE TABLE pagamento_eventos (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gateway         VARCHAR(30)  NOT NULL,
    evento_id       VARCHAR(100) NOT NULL,              -- id do evento no gateway (evita processar 2x)
    tipo            VARCHAR(60)  NOT NULL,
    pedido_id       BIGINT UNSIGNED NULL,
    payload         JSON         NOT NULL,
    recebido_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processado_em   DATETIME     NULL,
    erro            VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pagamento_eventos (gateway, evento_id),
    KEY idx_pagamento_eventos_pedido (pedido_id),
    CONSTRAINT fk_pagamento_eventos_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Dados iniciais: os dois potes e os tipos com os preços atuais
-- =====================================================================
INSERT INTO potes (slug, nome, item_singular, item_plural, capacidade) VALUES
    ('direita',  'Direita Conservada',     'azeitona', 'azeitonas', 10000),
    ('esquerda', 'Pimenta da Resistência', 'pimenta',  'pimentas',  10000);

INSERT INTO item_tipos (lado, slug, nome, preco_centavos, escala, ordem) VALUES
    ('direita',  'verde',     'Verde',        1490, 1.00, 1),
    ('direita',  'recheada',  'Recheada',     1990, 1.00, 2),
    ('direita',  'preta',     'Preta',        2290, 1.00, 3),
    ('direita',  'grande',    'Grande',       2990, 1.50, 4),
    ('esquerda', 'vermelha',  'Dedo-de-moça', 1490, 1.00, 1),
    ('esquerda', 'biquinho',  'Biquinho',     1990, 0.85, 2),
    ('esquerda', 'malagueta', 'Malagueta',    2290, 1.00, 3),
    ('esquerda', 'grande',    'Grande',       2990, 1.50, 4);
