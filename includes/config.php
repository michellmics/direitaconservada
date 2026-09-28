<?php
// Configurações gerais do site
const SITE_NAME = 'Direita Conservada × Pimenta da Resistência';

date_default_timezone_set('America/Sao_Paulo');

const JAR_CAPACITY = 10000;

const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

require __DIR__ . '/sides.php';

// Escapa texto para HTML
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// Nome próprio: "JOÃO DA SILVA" / "joão da silva" → "João da Silva" (igual a nomeProprio() no JS).
// Use também ao salvar no banco.
function nome_proprio(?string $s): string
{
    $s = mb_convert_case(trim(preg_replace('/\s+/u', ' ', (string) $s)), MB_CASE_TITLE, 'UTF-8');
    // maiúscula também depois de apóstrofo e hífen: D'Ávila, Ana-Clara
    $s = preg_replace_callback("/(['’-])(\p{Ll})/u", fn($m) => $m[1] . mb_strtoupper($m[2], 'UTF-8'), $s);
    $words = explode(' ', $s);
    foreach ($words as $i => $w) {
        $lower = mb_strtolower($w, 'UTF-8');
        if ($i > 0 && in_array($lower, ['da', 'de', 'do', 'das', 'dos', 'e', 'di', 'du'], true)) {
            $words[$i] = $lower;
        }
    }
    return implode(' ', $words);
}

function money(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function num(int $n): string
{
    return number_format($n, 0, ',', '.');
}
