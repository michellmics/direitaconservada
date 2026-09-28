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

// ---------- tempo de assinatura ----------

// Próximo aniversário da data (depois de hoje): até quando o item vale se renovar todo ano
function proximo_aniversario(string $desde, ?string $hoje = null): string
{
    $hoje = $hoje ?? date('Y-m-d');
    $d = new DateTimeImmutable($desde);
    $anos = max(1, (int) $d->diff(new DateTimeImmutable($hoje))->y + 1);
    $v = $d->modify("+$anos year");
    return $v->format('Y-m-d') <= $hoje ? $v->modify('+1 year')->format('Y-m-d') : $v->format('Y-m-d');
}

// Em que ano de assinatura está (1º, 2º, 3º…) e qual anel ganha: '' | 'prata' | 'ouro'
function ano_de_assinatura(string $desde, ?string $hoje = null): int
{
    return (int) (new DateTimeImmutable($desde))->diff(new DateTimeImmutable($hoje ?? date('Y-m-d')))->y + 1;
}

function anel_de_tempo(int $ano): string
{
    return $ano >= 3 ? 'ouro' : ($ano === 2 ? 'prata' : '');
}

function money(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function num(int $n): string
{
    return number_format($n, 0, ',', '.');
}
