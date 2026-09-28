<?php
// Configurações gerais do site
const SITE_NAME   = 'Direita Conservada';

date_default_timezone_set('America/Sao_Paulo');

const JAR_CAPACITY = 10000;

// Tipos de azeitona à venda; preço é anual (renovação a cada 12 meses)
const OLIVE_TYPES = [
    'verde'    => ['label' => 'Verde',    'price' => 14.90],
    'recheada' => ['label' => 'Recheada', 'price' => 19.90],
    'preta'    => ['label' => 'Preta',    'price' => 22.90],
    'grande'   => ['label' => 'Grande',   'price' => 29.90],
];

function min_price(): float
{
    return min(array_column(OLIVE_TYPES, 'price'));
}

// Selos prontos que aparecem pequenos na azeitona (a pessoa também pode enviar uma imagem, ex.: bandeira do partido)
const SELO_PRESETS = [
    'br' => 'Bandeira do Brasil',
    '✝️' => 'Fé',
    '🙏' => 'Oração',
    '⭐' => 'Estrela',
    '🦅' => 'Águia',
    '🐂' => 'Agro',
    '🌾' => 'Campo',
    '🛡️' => 'Defesa',
];

const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

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
