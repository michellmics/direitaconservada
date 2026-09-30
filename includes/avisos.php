<?php
// Aviso por e-mail de comentário recebido (migration 018).
//   Recebe: o dono da publicação comentada e, numa resposta, o autor do comentário citado (nunca quem comentou).
//   Vai por e-mail e também como notificação do app (push) nos aparelhos em que a pessoa ativou o 🔔.
//   Comentário do outro pote: e-mail provocativo, com uma frase 'email_oposicao' sorteada (edite em /cozinha/frases).
//   Anti-enxurrada: no máximo 1 aviso por pessoa e publicação a cada AVISO_INTERVALO_MIN minutos.
//   Quem clica em "parar de receber" (link no e-mail → /avisos) fica com usuarios.avisos_email = 0.
// O envio roda depois que a resposta já foi para o navegador (quem comenta não espera o SMTP).
require_once __DIR__ . '/frases.php';

const AVISO_INTERVALO_MIN = 10;

/** Agenda os avisos do comentário para depois da resposta da API. */
function aviso_comentario_agendar(int $comentarioId): void
{
    register_shutdown_function(function () use ($comentarioId) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request(); // PHP-FPM: entrega a resposta e segue enviando
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request(); // LiteSpeed (comum em cPanel)
        }
        ignore_user_abort(true);
        try {
            aviso_comentario_enviar($comentarioId);
        } catch (Throwable $e) {
            logar('erro', 'email', 'aviso_comentario_falha', 'Aviso de comentário: ' . $e->getMessage(), ['comentario' => $comentarioId]);
        }
    });
}

/** Manda os avisos de um comentário (e-mail e notificação do app). Retorna quantos e-mails saíram. */
function aviso_comentario_enviar(int $comentarioId): int
{
    $temEmail = is_file(dirname(__DIR__) . '/vendor/autoload.php'); // sem PHPMailer (composer install): só a notificação
    $pdo = db();
    // o comentário, quem comentou e a publicação (com o dono)
    $st = $pdo->prepare("SELECT c.id, c.post_id, c.texto, c.video_id, c.cita_id,
                                c.usuario_id AS autor_uid, COALESCE(ia.nome, ua.nome) AS autor_nome, COALESCE(ia.lado, p.lado) AS autor_lado,
                                p.lado AS post_lado, p.texto AS post_texto, p.usuario_id AS dono_uid, ip.numero AS dono_numero, p.lado AS dono_lado
                         FROM comentarios c
                         JOIN usuarios ua ON ua.id = c.usuario_id
                         LEFT JOIN itens ia ON ia.id = c.item_id
                         JOIN posts p ON p.id = c.post_id
                         LEFT JOIN itens ip ON ip.id = p.item_id
                         WHERE c.id = ? AND c.status = 'publicado'");
    $st->execute([$comentarioId]);
    $c = $st->fetch();
    if (!$c) {
        return 0;
    }

    // quem recebe: [usuario_id => lado do item dele]
    $para = [(int) $c['dono_uid'] => ['lado' => $c['dono_lado'], 'resposta' => false]];
    if ($c['cita_id']) {
        $st = $pdo->prepare('SELECT c.usuario_id, COALESCE(i.lado, p.lado) AS lado FROM comentarios c JOIN posts p ON p.id = c.post_id
                             LEFT JOIN itens i ON i.id = c.item_id WHERE c.id = ?');
        $st->execute([(int) $c['cita_id']]);
        if ($r = $st->fetch()) {
            $para[(int) $r['usuario_id']] ??= ['lado' => $r['lado'], 'resposta' => true];
        }
    }
    unset($para[(int) $c['autor_uid']]); // ninguém é avisado do próprio comentário

    if ($temEmail) {
        require_once __DIR__ . '/mailer.php';
    }
    $enviados = 0;
    foreach ($para as $uid => $info) {
        $st = $pdo->prepare("SELECT nome, email, avisos_email FROM usuarios WHERE id = ? AND status = 'ativo'");
        $st->execute([$uid]);
        $u = $st->fetch();
        if (!$u) {
            continue; // conta bloqueada / apagada
        }
        // anti-enxurrada: 1 = primeiro aviso desta publicação, 2 = passou o intervalo; 0 = avisado há pouco
        $st = $pdo->prepare('INSERT INTO avisos_comentario (usuario_id, post_id) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE enviado_em = IF(enviado_em < NOW() - INTERVAL ' . AVISO_INTERVALO_MIN . ' MINUTE, NOW(), enviado_em)');
        $st->execute([$uid, (int) $c['post_id']]);
        if ($st->rowCount() === 0) {
            continue;
        }

        $S = side($info['lado']);
        $A = side($c['autor_lado']);
        $oposicao = $c['autor_lado'] !== $info['lado'];
        $provocacao = $oposicao ? (frase('email_oposicao', $info['lado']) ?? 'Vai deixar barato? Vai lá e defende o seu pote!') : null;
        $trecho = trim((string) $c['texto']) !== '' ? (string) $c['texto'] : '🎬 (mandou um vídeo)';

        // notificação do app (quem ativou o 🔔 no aparelho; desativa pelo mesmo botão). Falhar não impede o e-mail.
        try {
            require_once __DIR__ . '/push.php';
            $autorNome = nome_proprio($c['autor_nome']);
            push_avisar('comentario',
                $info['resposta'] ? "↩️ $autorNome respondeu seu comentário" : "💬 $autorNome comentou na sua publicação",
                '“' . mb_strimwidth($trecho, 0, 140, '…') . '”' . ($provocacao ? ' ' . $provocacao : ''),
                $c['dono_numero'] ? url('perfil', ['lado' => $c['dono_lado'], 'id' => (int) $c['dono_numero']]) : url('pote', ['lado' => $c['dono_lado']]), null, $uid);
        } catch (Throwable $e) {
            // tabelas do push ainda não criadas (migration 025) ou serviço de push fora do ar
        }

        if (!$temEmail || !$u['avisos_email'] || str_ends_with(strtolower($u['email']), '.local')) {
            continue; // e-mail: pediu para parar ou conta de teste (seed)
        }
        // post de conta sem item não tem perfil: o link leva ao pote
        $link = $c['dono_numero'] ? url_absoluta('perfil', ['lado' => $c['dono_lado'], 'id' => (int) $c['dono_numero']]) : url_absoluta('pote', ['lado' => $c['dono_lado']]);
        $linkParar = url_absoluta('avisos', ['u' => $uid]);
        [$assunto, $html, $texto] = email_comentario_recebido($S, nome_proprio($u['nome']), nome_proprio($c['autor_nome']), $A,
            $trecho, $info['resposta'], $link, $provocacao, $linkParar);
        if (enviar_email($u['email'], nome_proprio($u['nome']), $assunto, $html, $texto, ['List-Unsubscribe' => '<' . $linkParar . '>']) === null) {
            $enviados++;
        }
    }
    return $enviados;
}

/** Liga/desliga os avisos (página /avisos). */
function avisos_definir(int $usuarioId, bool $receber): void
{
    db()->prepare('UPDATE usuarios SET avisos_email = ? WHERE id = ?')->execute([(int) $receber, $usuarioId]);
    logar('info', 'conta', $receber ? 'avisos_ligados' : 'avisos_desligados', 'Avisos de comentário ' . ($receber ? 'ligados' : 'desligados'), [], $usuarioId);
}
