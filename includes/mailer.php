<?php
// Envio de e-mail pelo SMTP configurado no .env (ENV_SMTP_HOST/PORT/USER/PASS).
require_once __DIR__ . '/env.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

function mailer(): PHPMailer
{
    $port = (int) env('ENV_SMTP_PORT', '465');
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = (string) env('ENV_SMTP_HOST');
    $m->Port       = $port;
    $m->SMTPAuth   = true;
    $m->Username   = (string) env('ENV_SMTP_USER');
    $m->Password   = (string) env('ENV_SMTP_PASS');
    // ENV_SMTP_SECURE: ssl | tls | none (vazio = automático pela porta: 465 → ssl, outras → tls)
    $secure = strtolower((string) env('ENV_SMTP_SECURE', ''));
    if ($secure === 'none') {
        $m->SMTPSecure = '';
        $m->SMTPAutoTLS = false;
    } else {
        $m->SMTPSecure = ($secure === 'ssl' || ($secure === '' && $port === 465)) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    }
    if (smtp_sem_verificacao()) {
        // Só no computador de desenvolvimento: antivírus com proteção de e-mail (ex.: Norton Mail Shield)
        // trocam o certificado do servidor pelo deles e o PHP recusaria a conexão.
        $m->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
    }
    $m->CharSet    = PHPMailer::CHARSET_UTF8;
    $m->Timeout    = 15;
    $m->setFrom((string) env('ENV_SMTP_USER'), (string) env('MAIL_FROM_NAME', 'Direita Conservada × Pimenta da Resistência'));
    return $m;
}

/**
 * ENV_SMTP_VERIFICAR=0 desliga a verificação do certificado do SMTP, mas só vale em desenvolvimento:
 * exige APP_ENV=local e APP_URL em localhost. Em produção é ignorado (a verificação fica sempre ligada).
 */
function smtp_sem_verificacao(): bool
{
    if ((string) env('ENV_SMTP_VERIFICAR', '1') !== '0' || env('APP_ENV') !== 'local') {
        return false;
    }
    $host = strtolower((string) parse_url((string) env('APP_URL', ''), PHP_URL_HOST));
    return in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
}

/** Envia o e-mail. Retorna null se deu certo ou a mensagem de erro. */
function enviar_email(string $para, string $nome, string $assunto, string $html, string $texto): ?string
{
    try {
        $m = mailer();
        $m->addAddress($para, $nome);
        $m->Subject = $assunto;
        $m->isHTML(true);
        $m->Body    = $html;
        $m->AltBody = $texto;
        $m->send();
        return null;
    } catch (MailException $e) {
        error_log('[email] falha ao enviar para ' . $para . ': ' . $e->getMessage());
        return $e->getMessage();
    }
}

// Moldura dos e-mails, nas cores do pote: ícone no topo, nome do site, faixa e o corpo
function email_moldura(array $S, string $icone, string $faixa, string $corpo): string
{
    $t = $S['theme'];
    $nomeSite = e($S['name']);
    $faixa = e($faixa);
    return <<<HTML
<!doctype html>
<html lang="pt-BR"><body style="margin:0;background:{$t['bg']};font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{$t['bg']};padding:32px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#f4ecd8;border-radius:18px;border:3px solid {$t['gold']};">
        <tr><td style="padding:32px 28px 8px;text-align:center;">
          <div style="font-size:40px;line-height:1;">{$icone}</div>
          <h1 style="margin:12px 0 4px;font-family:Georgia,serif;font-size:26px;color:{$t['ink']};">{$nomeSite}</h1>
          <p style="margin:0;color:{$t['dark']};font-size:13px;letter-spacing:.12em;text-transform:uppercase;font-weight:bold;">{$faixa}</p>
        </td></tr>
        <tr><td style="padding:16px 28px;color:{$t['ink']};font-size:16px;line-height:1.5;">
          {$corpo}
        </td></tr>
        <tr><td style="padding:0 28px 28px;"></td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
}

// Botão dos e-mails
function email_botao(array $S, string $link, string $texto): string
{
    $t = $S['theme'];
    return '<p style="margin:0 0 24px;text-align:center;"><a href="' . e($link) . '" style="display:inline-block;background:' . $t['gold']
        . ';color:' . $t['on-accent'] . ';text-decoration:none;font-weight:bold;padding:14px 28px;border-radius:999px;">' . e($texto) . '</a></p>';
}

// E-mail do link mágico
function email_link_login(array $S, string $nome, string $link): array
{
    $t = $S['theme'];
    $primeiro = e(explode(' ', $nome)[0]);
    $min = LINK_MINUTOS;
    $linkHtml = e($link);
    $corpo = "<p style=\"margin:0 0 12px;\">Olá, {$primeiro}!</p>
          <p style=\"margin:0 0 20px;\">Clique no botão para entrar. Não precisa de senha.</p>
          " . email_botao($S, $link, 'Entrar no site') . "
          <p style=\"margin:0 0 8px;font-size:13px;color:#6b6a55;\">O link vale por {$min} minutos e só pode ser usado uma vez.</p>
          <p style=\"margin:0 0 8px;font-size:13px;color:#6b6a55;\">Se o botão não funcionar, copie e cole no navegador:<br><span style=\"word-break:break-all;color:{$t['dark']};\">{$linkHtml}</span></p>
          <p style=\"margin:16px 0 0;font-size:13px;color:#6b6a55;\">Não pediu? É só ignorar este e-mail — ninguém entra sem clicar no link.</p>";
    $html = email_moldura($S, $S['emoji'], 'Seu link para entrar', $corpo);
    $texto = "Olá, {$primeiro}!\n\nPara entrar em {$S['name']}, abra o link abaixo (vale por {$min} minutos, uso único):\n\n{$link}\n\nNão pediu? É só ignorar este e-mail.";
    return [$S['emoji'] . ' Seu link para entrar', $html, $texto];
}

// E-mail "você subiu de nível" (níveis e regras em includes/sides.php e includes/tempero.php)
function email_nivel_subiu(array $S, string $nome, int $nivel, array $p, string $link): array
{
    $T = $S['tempero'];
    $nv = $T['niveis'][$nivel - 1];
    $primeiro = explode(' ', $nome)[0];
    $fmt = fn(int $pts) => num($pts * $T['fator']) . ' ' . $T['unidade'];
    $pontos = $p['total'];
    $frase = $nivel === 1 ? "Você entrou no nível {$nv['nome']}" : "Você subiu para o nível {$nv['nome']}";

    $proximo = $T['niveis'][$nivel] ?? null;
    if ($proximo) {
        $falta = max(1, tempero_falta($p, $nivel + 1));
        $dica = "Faltam {$fmt($falta)} para o nível {$proximo['nome']} {$proximo['icone']}. Mais {$S['items']} no pote, publicações e comentários no mural fazem subir.";
    } else {
        $dica = 'Este é o nível máximo do pote. Mantenha suas ' . $S['items'] . ' em dia para continuar no topo!';
    }

    $corpo = '<p style="margin:0 0 12px;">Olá, ' . e($primeiro) . '!</p>
          <p style="margin:0 0 6px;text-align:center;font-size:34px;line-height:1.2;">' . $nv['icone'] . '</p>
          <p style="margin:0 0 4px;text-align:center;font-family:Georgia,serif;font-size:22px;font-weight:bold;">' . e($frase) . '!</p>
          <p style="margin:0 0 20px;text-align:center;font-size:14px;color:' . $S['theme']['dark'] . ';font-weight:bold;">' . e($fmt($pontos)) . '</p>
          <p style="margin:0 0 20px;">Agora seu nome aparece com ' . $nv['icone'] . ' no pote, no mural e nos comentários.</p>
          <p style="margin:0 0 20px;">' . e($dica) . '</p>
          ' . email_botao($S, $link, 'Ver no pote');
    $html = email_moldura($S, $nv['icone'], $T['titulo'], $corpo);
    $texto = "Olá, {$primeiro}!\n\n{$frase} ({$fmt($pontos)}) em {$S['name']}.\n"
        . "Agora seu nome aparece com {$nv['icone']} no pote, no mural e nos comentários.\n\n{$dica}\n\n{$link}";
    return ["{$nv['icone']} {$frase}!", $html, $texto];
}
