-- =====================================================================
-- Rotinas da cron (cron/alertas, a cada 15 minutos): alertas ao administrador e avisos de vencimento aos clientes.
--   alertas_estado        chave → valor (último log já olhado, dia do último resumo diário…)
--   alertas_enviados      o que já foi avisado ao administrador (tipo + id): presente não resgatado, usuário que
--                         publica demais… enviado_em permite repetir depois de um tempo
--   item_avisos_vencimento e-mail "sua azeitona vence em X dias" já mandado ao cliente (por item, faixa e validade:
--                         renovou, a validade muda e os avisos recomeçam no próximo vencimento)
-- =====================================================================

CREATE TABLE alertas_estado (
    chave         VARCHAR(60)  NOT NULL,
    valor         VARCHAR(255) NOT NULL,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alertas_enviados (
    tipo        VARCHAR(30)     NOT NULL,
    ref_id      BIGINT UNSIGNED NOT NULL,
    enviado_em  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tipo, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE item_avisos_vencimento (
    item_id     BIGINT UNSIGNED  NOT NULL,
    faixa       TINYINT UNSIGNED NOT NULL,   -- 30, 15, 10, 5 ou 1 dia(s) antes
    valido_ate  DATE             NOT NULL,
    enviado_em  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id, faixa, valido_ate),
    CONSTRAINT fk_item_avisos_item FOREIGN KEY (item_id) REFERENCES itens (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
