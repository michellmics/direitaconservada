-- =====================================================================
-- Aviso por e-mail de comentário recebido (includes/avisos.php).
--
--   usuarios.avisos_email  1 = recebe os avisos; 0 = pediu para parar (link no rodapé do e-mail → /avisos)
--   avisos_comentario      último aviso por pessoa e publicação: numa discussão animada, no máximo
--                          1 e-mail a cada 10 minutos da mesma publicação (não vira enxurrada)
--   frases 'email_oposicao' provocações sorteadas quando o comentário vem do outro pote.
--                          lado = pote de QUEM RECEBE o e-mail. Editáveis em /cozinha/frases.
-- =====================================================================

ALTER TABLE usuarios ADD COLUMN avisos_email TINYINT(1) NOT NULL DEFAULT 1 AFTER status;

CREATE TABLE avisos_comentario (
    usuario_id  BIGINT UNSIGNED NOT NULL,
    post_id     BIGINT UNSIGNED NOT NULL,
    enviado_em  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, post_id),
    CONSTRAINT fk_avisos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_avisos_post    FOREIGN KEY (post_id)    REFERENCES posts (id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO frases (lugar, lado, uf, texto) VALUES
    -- quem recebe é azeitona (Direita Conservada), provocada por uma pimenta
    ('email_oposicao', 'direita', NULL, 'Vai deixar uma pimenta falar assim com você? Vai lá e defende o seu pote, bunda mole!'),
    ('email_oposicao', 'direita', NULL, 'Uma pimenta veio cantar de galo no seu post. Vai ficar aí boiando na salmoura?'),
    ('email_oposicao', 'direita', NULL, 'Azeitona que não responde vira petisco de pimenteiro. Reage!'),
    ('email_oposicao', 'direita', NULL, 'O pote inteiro está olhando. Vai amarelar ou vai responder à altura?'),
    ('email_oposicao', 'direita', NULL, 'A pimentada acha que você é azeitona sem caroço. Mostra que tem!'),
    ('email_oposicao', 'direita', NULL, 'Se você não responder, a pimenta vai contar vitória na assembleia de amanhã. E vai ter até moção de aplauso.'),
    ('email_oposicao', 'direita', NULL, 'Levanta desse sofá, bunda mole: tem pimenta ardendo no seu mural!'),
    ('email_oposicao', 'direita', NULL, 'Seu avô não conservou a família por 80 anos para você levar desaforo de pimenta calado.'),
    ('email_oposicao', 'direita', NULL, 'Tá com medo de arder? Responde logo, azeitona!'),
    ('email_oposicao', 'direita', NULL, 'Pimenta no seu post é igual cunhado no churrasco: se ninguém responde, ele toma conta da conversa.'),
    ('email_oposicao', 'direita', NULL, 'Chegou pimenta querendo dividir até o seu post. Vai deixar estatizarem sua publicação?'),
    ('email_oposicao', 'direita', NULL, 'Defende o seu clã, azeitona! O pote não aceita desertor.'),
    -- quem recebe é pimenta (Pimenta da Resistência), provocada por uma azeitona
    ('email_oposicao', 'esquerda', NULL, 'Vai deixar uma azeitona falar assim com você? Vai lá e defende a resistência, bunda mole!'),
    ('email_oposicao', 'esquerda', NULL, 'Uma azeitona saiu da salmoura só para te provocar. Vai ficar em silêncio?'),
    ('email_oposicao', 'esquerda', NULL, 'Pimenta que não arde vira pimentão. Reage!'),
    ('email_oposicao', 'esquerda', NULL, 'Convoca a assembleia: tem azeitona no seu mural achando que manda no pedaço.'),
    ('email_oposicao', 'esquerda', NULL, 'Levanta daí, bunda mole: tem azeitona dando pitaco no seu post!'),
    ('email_oposicao', 'esquerda', NULL, 'Se você não responder, o tio do zap vai printar e mandar no grupo da família com três emojis de risada.'),
    ('email_oposicao', 'esquerda', NULL, 'A azeitona acha que você ficou sem argumento. Vai deixar barato?'),
    ('email_oposicao', 'esquerda', NULL, 'Mostra para esse caroço quem é que arde neste país.'),
    ('email_oposicao', 'esquerda', NULL, 'Tá esperando o quê, a revolução chegar sozinha? Responde!'),
    ('email_oposicao', 'esquerda', NULL, 'Tem azeitona conservando desaforo no seu post. Arde nela!'),
    ('email_oposicao', 'esquerda', NULL, 'A azeitona veio privatizar a sua publicação. Vai entregar sem luta?'),
    ('email_oposicao', 'esquerda', NULL, 'Defende o seu clã, pimenta! Greve de resposta não vale.'),
    -- serve para os dois potes
    ('email_oposicao', 'ambos', NULL, 'Quem cala consente. E você não vai consentir com isso, né?'),
    ('email_oposicao', 'ambos', NULL, 'Não responder também é uma resposta: a de bunda mole.'),
    ('email_oposicao', 'ambos', NULL, 'A turma do outro pote já está rindo. Vai deixar?');
