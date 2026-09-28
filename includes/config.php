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

function money(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function num(int $n): string
{
    return number_format($n, 0, ',', '.');
}
