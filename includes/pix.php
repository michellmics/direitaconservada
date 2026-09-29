<?php
// Pix "copia e cola" (BR Code do Banco Central) gerado aqui mesmo, sem gateway: a cobrança vai direto para a
// chave do .env. Quem confirma o pagamento é o administrador, conferindo o extrato (/cozinha/pedidos).
//   PIX_CHAVE  = e-mail, CPF/CNPJ (só números), celular (+5511999999999) ou chave aleatória
//   PIX_NOME   = nome do recebedor como está no banco (até 25 letras)
//   PIX_CIDADE = cidade do recebedor (até 15 letras)
require_once __DIR__ . '/env.php';

function pix_configurado(): bool
{
    return trim((string) env('PIX_CHAVE', '')) !== '';
}

/** Texto em maiúsculas, sem acento e cortado (o BR Code só aceita ASCII nos campos de nome e cidade). */
function pix_ascii(string $s, int $max): string
{
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ç' => 'C', 'Ñ' => 'N']);
    return substr(strtoupper(trim(preg_replace('/[^A-Za-z0-9 ]+/', '', $s))), 0, $max);
}

/** Campo do BR Code: id (2 dígitos) + tamanho (2 dígitos) + valor. */
function pix_campo(string $id, string $valor): string
{
    return $id . str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;
}

/** CRC16-CCITT (polinômio 0x1021, início 0xFFFF), exigido no fim do código. */
function pix_crc16(string $s): string
{
    $crc = 0xFFFF;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $crc ^= ord($s[$i]) << 8;
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
        }
    }
    return sprintf('%04X', $crc);
}

/** Código "copia e cola" com valor fixo. $txid = número do pedido (letras e números, até 25). */
function pix_copia_cola(int $centavos, string $txid): string
{
    $chave = trim((string) env('PIX_CHAVE', ''));
    $txid = substr(preg_replace('/[^A-Za-z0-9]/', '', $txid), 0, 25) ?: '***';
    $payload = pix_campo('00', '01')
        . pix_campo('26', pix_campo('00', 'br.gov.bcb.pix') . pix_campo('01', $chave))
        . pix_campo('52', '0000')
        . pix_campo('53', '986') // real
        . pix_campo('54', number_format($centavos / 100, 2, '.', ''))
        . pix_campo('58', 'BR')
        . pix_campo('59', pix_ascii((string) env('PIX_NOME', SITE_NAME), 25) ?: 'RECEBEDOR')
        . pix_campo('60', pix_ascii((string) env('PIX_CIDADE', 'SAO PAULO'), 15) ?: 'SAO PAULO')
        . pix_campo('62', pix_campo('05', $txid))
        . '6304';
    return $payload . pix_crc16($payload);
}

/** QR code do código, em SVG (sem imagem externa). */
function pix_qr_svg(string $copiaCola): string
{
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $opcoes = new chillerlan\QRCode\QROptions([
        'outputType'      => chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
        'outputBase64'    => false,
        'eccLevel'        => chillerlan\QRCode\Common\EccLevel::M,
        'addQuietzone'    => true,
        'quietzoneSize'   => 2,
        'svgAddXmlHeader' => false,
        'drawLightModules' => false,
        'connectPaths'     => true, // um caminho só: SVG bem menor
    ]);
    return (new chillerlan\QRCode\QRCode($opcoes))->render($copiaCola);
}
