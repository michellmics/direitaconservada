-- =====================================================================
-- Alertas por e-mail para o administrador (includes/alertas.php; destinatários em ENV_EMAIL_ALERTAS no .env).
--   evento  qr      = abriu a tela de pagamento com o QR Code (ao gerar o pedido ou em "Ver Pix")
--           copiou  = copiou o código Pix copia e cola
--           atraso  = pendente há mais de 15 minutos (cron/alertas, chamada pela cron do cPanel)
--   vezes        quantas vezes o evento aconteceu (o e-mail sai no máximo 1 vez a cada 5 minutos por pedido e evento)
--   enviado_em   último e-mail mandado
-- =====================================================================

CREATE TABLE IF NOT EXISTS pedido_alertas (
    pedido_id   BIGINT UNSIGNED NOT NULL,
    evento      VARCHAR(20)     NOT NULL,
    vezes       INT UNSIGNED    NOT NULL DEFAULT 0,
    primeiro_em DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_em   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enviado_em  DATETIME        NULL,
    PRIMARY KEY (pedido_id, evento),
    CONSTRAINT fk_pedido_alertas_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
