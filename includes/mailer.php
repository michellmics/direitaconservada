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
    $m->CharSet    = PHPMailer::CHARSET_UTF8;
    $m->Timeout    = 15;
    $m->setFrom((string) env('ENV_SMTP_USER'), (string) env('MAIL_FROM_NAME', 'Direita Conservada × Pimenta da Resistência'));
    return $m;
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

// E-mail do link mágico, nas cores do pote
function email_link_login(array $S, string $nome, string $link): array
{
    $t = $S['theme'];
    $primeiro = e(explode(' ', $nome)[0]);
    $min = LINK_MINUTOS;
    $linkHtml = e($link);
    $nomeSite = e($S['name']);
    $html = <<<HTML
<!doctype html>
<html lang="pt-BR"><body style="margin:0;background:{$t['bg']};font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{$t['bg']};padding:32px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#f4ecd8;border-radius:18px;border:3px solid {$t['gold']};">
        <tr><td style="padding:32px 28px 8px;text-align:center;">
          <div style="font-size:40px;line-height:1;">{$S['emoji']}</div>
          <h1 style="margin:12px 0 4px;font-family:Georgia,serif;font-size:26px;color:{$t['ink']};">{$nomeSite}</h1>
          <p style="margin:0;color:{$t['dark']};font-size:13px;letter-spacing:.12em;text-transform:uppercase;font-weight:bold;">Seu link para entrar</p>
        </td></tr>
        <tr><td style="padding:16px 28px;color:{$t['ink']};font-size:16px;line-height:1.5;">
          <p style="margin:0 0 12px;">Olá, {$primeiro}!</p>
          <p style="margin:0 0 20px;">Clique no botão para entrar. Não precisa de senha.</p>
          <p style="margin:0 0 24px;text-align:center;">
            <a href="{$linkHtml}" style="display:inline-block;background:{$t['gold']};color:{$t['on-accent']};text-decoration:none;font-weight:bold;padding:14px 28px;border-radius:999px;">Entrar no site</a>
          </p>
          <p style="margin:0 0 8px;font-size:13px;color:#6b6a55;">O link vale por {$min} minutos e só pode ser usado uma vez.</p>
          <p style="margin:0 0 8px;font-size:13px;color:#6b6a55;">Se o botão não funcionar, copie e cole no navegador:<br><span style="word-break:break-all;color:{$t['dark']};">{$linkHtml}</span></p>
          <p style="margin:16px 0 0;font-size:13px;color:#6b6a55;">Não pediu? É só ignorar este e-mail — ninguém entra sem clicar no link.</p>
        </td></tr>
        <tr><td style="padding:0 28px 28px;"></td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
    $texto = "Olá, {$primeiro}!\n\nPara entrar em {$S['name']}, abra o link abaixo (vale por {$min} minutos, uso único):\n\n{$link}\n\nNão pediu? É só ignorar este e-mail.";
    return [$S['emoji'] . ' Seu link para entrar', $html, $texto];
}
