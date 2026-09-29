-- =====================================================================
-- Log do sistema: tudo o que acontece (sucesso, erro, segurança), para consultar no painel (/cozinha/logs)
-- e para alertas (ex.: login do painel com falha / com sucesso).
--   nivel     → debug | info | aviso | erro | seguranca
--   categoria → painel, conta, pedido, presente, mural, perfil, enquete, partidos, email, limite, sistema…
--   evento    → o que foi, em snake_case (ex.: painel_login_falha, pedido_aprovado, php_erro)
--   dados     → detalhes em JSON (sem senhas)
-- =====================================================================

CREATE TABLE logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criado_em   DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    nivel       ENUM('debug','info','aviso','erro','seguranca') NOT NULL DEFAULT 'info',
    categoria   VARCHAR(40)     NOT NULL,
    evento      VARCHAR(80)     NOT NULL,
    mensagem    VARCHAR(1000)   NULL,
    usuario_id  BIGINT UNSIGNED NULL,            -- quem estava logado (ou a conta envolvida)
    admin       TINYINT(1)      NOT NULL DEFAULT 0, -- ação feita no painel
    ip          VARCHAR(45)     NULL,
    user_agent  VARCHAR(255)    NULL,
    metodo      VARCHAR(10)     NULL,
    rota        VARCHAR(255)    NULL,            -- caminho da requisição (sem a query)
    status_http SMALLINT        NULL,
    dados       JSON            NULL,
    PRIMARY KEY (id),
    KEY idx_logs_data (criado_em),
    KEY idx_logs_nivel (nivel, criado_em),
    KEY idx_logs_categoria (categoria, criado_em),
    KEY idx_logs_evento (evento, criado_em),
    KEY idx_logs_ip (ip, criado_em),
    KEY idx_logs_usuario (usuario_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
