<?php
// Os dois setores (potes) do site. Tudo que muda entre direita e esquerda fica aqui:
// textos, tipos e preços, selos, cores e o desenho dos itens do pote.

// Corpo da pimenta (centrado em 0,0; ponta para a esquerda, cabinho à direita)
const PEPPER_BODY = 'M13 -3.5 C8 -8 -5 -7 -12 -2.5 C-15 -.5 -17 3 -19 2.5 C-15 6.5 -2 6.5 9 4.5 C14 3.5 16 0 13 -3.5 Z';
const PEPPER_CAP  = '<ellipse cx="13.6" cy=".3" rx="2.8" ry="4.4" fill="#2f7a2a"/><path d="M15.6 -.4 Q19.5 -1 20.5 -5.5" stroke="#2f7a2a" stroke-width="2" fill="none" stroke-linecap="round"/>';
const PEPPER_SHINE = '<path d="M9 -4.4 Q0 -6.4 -9 -2.6" stroke="#fff" stroke-opacity=".45" stroke-width="1.5" fill="none" stroke-linecap="round"/>';

const SIDES = [
    'direita' => [
        'slug'   => 'direita',
        'name'   => 'Direita Conservada',
        'name_a' => 'Direita',
        'name_b' => 'Conservada',
        'other'  => 'esquerda',
        'emoji'  => '🫒',
        'item'   => 'azeitona',
        'items'  => 'azeitonas',
        'Item'   => 'Azeitona',
        'since'  => 'conservado desde',       // no mural: "Fulano · conservado desde 01/07/2026"
        'cert_title' => 'CERTIFICADO DE CONSERVAÇÃO',
        'cert_since' => 'Direita conservada desde',
        'members'    => 'conservados',          // "só conservados podem publicar"
        'react'      => 'azeitonam',            // "os outros leem e azeitonam"
        'sort_top'   => 'Mais azeitonadas',
        'tag'        => 'Em salmoura desde sempre',
        'h1'         => 'Sem Caroço,<br>mas com <em>cérebro</em>.',
        // texto do topo da página
        'lead'       => 'Enquanto a petralhada faz assembleia para decidir o sabor do pastel, você entra no <b>maior pote conservador do Brasil</b> e solta o verbo no mural para o canhoto chorar. Não deixa comunista do Leblon ocupar o seu lugar: <b>cadastre-se grátis</b> e entre na conversa.',
        'og'         => 'Valores não têm prazo de validade. Garanta sua azeitona no pote.',
        'label_top'  => '★ DESDE SEMPRE ★',
        'stat_uf'    => 'estado mais conservado',
        'ranking'    => 'Estados livres de canhoto',
        'mural_tag'  => 'Mural conservador',
        'success'    => 'Você foi conservado! 🫒',
        'mural_about' => 'ideias, opiniões, notícias e vídeos sobre a direita',
        // mapa da guerra dos potes (partials/mapa.php): o mesmo mapa nos dois lados, textos daqui.
        // {meus}/{deles} = estados de cada lado · {dif} = vantagem · {falta} {itens} = quantos faltam · {uf} = estado
        // apoio partidário (partials/partidos.php): só partidos de direita neste pote
        'partidos' => [
            'titulo' => 'Qual partido conserva o seu voto?',
            'sub'    => 'Escolha até 3. O ranking muda na hora, e o pão com mortadela vai espiar.',
        ],
        'mapa' => [
            'cor'          => '#8fa532',  // cor dos estados conquistados (igual nos dois potes)
            'titulo'       => 'O Brasil em conserva ou na mão da da esquerda?',
            'ganhando'     => 'A azeitona já conserva {meus} estados. Os comuna vão pedir recontagem e botar a culpa no imperialismo.',
            'perdendo'     => 'A petralhada tomou {deles} estados. Vai deixar o churrasco da família virar sarau de comunista do Leblon?',
            'empate'       => 'Empate: {meus} a {deles}. O canhoto já convocou assembleia para decidir quem ganhou.',
            'estado_meu'   => 'Conservado. Vantagem de {dif}: canhoto aqui, só o do talão de cheque.',
            'estado_deles' => 'Petralhada no comando. Faltam {falta} {itens} para devolver à conserva.',
            'estado_empate' => 'Empatado. Uma azeitona decide se o estado vira conserva ou diretório acadêmico.',
            'estado_vazio' => 'Terra de ninguém. Finque a azeitona antes que a canhotada ocupe e chame de reforma agrária.',
            'fio_sub'      => 'Onde falta pouco para tirar o estado das mãos da canhotada.',
            'indicar'      => '🫒 A esquerda está avançando em {uf}! Garanta sua azeitona antes que o estado vire filial do Leblon: ',
            // quem manda no estado (👑). Sem valores em R$: {falta} = itens para tomar a coroa (ex.: "3 azeitonas Verde")
            'rei_eu'       => 'A coroa é sua. Fica de olho: comunista de iPhone adora dividir o que é dos outros.',
            'rei_time'     => 'É do time, mas coroa não é herança: com mais {falta} aqui, ela é sua.',
            'rei_deles'    => 'Tem canhoto sentado no trono, querendo dividir a coroa com a companheirada. Vire o estado.',
        ],
        // nível ao lado do nome (regras e pontuação em includes/tempero.php)
        'tempero' => [
            'titulo'  => 'Nível de conservação',
            'chamada' => 'Quanto mais conservado, mais azeitonas no nome',
            'unidade' => 'pontos',
            'fator'   => 1,        // pontos exibidos = pontos × fator
            'niveis'  => [
                ['nome' => 'Em Salmoura',         'icone' => '🫒'],
                ['nome' => 'Curtida',             'icone' => '🫒🫒'],
                ['nome' => 'Reserva',             'icone' => '🫒🫒🫒'],
                ['nome' => 'Azeite Virgem',       'icone' => '🫒🫒🫒🫒'],
                ['nome' => 'Extra-Virgem',        'icone' => '🫒🫒🫒🫒🫒'],
                ['nome' => 'Oliveira Centenária', 'icone' => '🫒👑'],
            ],
        ],
        // tipos na ordem de preço (o 1º já vem marcado na compra); 'logo' = desenho usado no topo
        'types' => [
            'verde'    => ['label' => 'Verde',    'price' => 2.90],
            'recheada' => ['label' => 'Recheada', 'price' => 4.90],
            'preta'    => ['label' => 'Preta',    'price' => 6.90],
            'grande'   => ['label' => 'Grande',   'price' => 9.90],
        ],
        'logo' => 'verde',
        'scales' => ['grande' => 1.5],
        'selos' => [
            'br' => 'Bandeira do Brasil',
            '✝️' => 'Fé',
            '🙏' => 'Oração',
            '⭐' => 'Estrela',
            '🦅' => 'Águia',
            '🐂' => 'Agro',
            '🌾' => 'Campo',
            '🛡️' => 'Defesa',
        ],
        'theme' => [
            'bg' => '#161c0c',
            'bg-2' => '#1f2811',
            'surface' => '#26311a',
            'surface-2' => '#2f3c20',
            'line' => '#3f4d2a',
            'text' => '#f4ecd8',
            'muted' => '#b9b69a',
            'gold' => '#c9a227',
            'gold-2' => '#e7c54d',
            'olive' => '#7d8c2f',
            'glow' => '#3a4a1a',
            'ink' => '#25300f',
            'dark' => '#3d4a1f',
            'on-accent' => '#231a00',
        ],
        'lid'   => ['#e7c54d', '#b8901c', '#8a6a10'],
        'brine' => ['#d9d98a', '#8c9a3c'],
        // pote largo; itens deitados, 9 por fileira
        'jar'   => ['shape' => 'pot', 'perRow' => 9, 'dx' => 33, 'dy' => 21, 'x0' => 66, 'y0' => 494, 'rows' => 18, 'rot' => 0, 'rotJitter' => 70, 'itemScale' => 1],
        'defs' => '
            <radialGradient id="olive-verde" cx=".35" cy=".35" r=".8"><stop offset="0" stop-color="#b5c25a"/><stop offset=".6" stop-color="#7d8c2f"/><stop offset="1" stop-color="#4f5a18"/></radialGradient>
            <radialGradient id="olive-preta" cx=".35" cy=".35" r=".8"><stop offset="0" stop-color="#6a5a6e"/><stop offset=".6" stop-color="#2e2530"/><stop offset="1" stop-color="#140f15"/></radialGradient>
            <radialGradient id="olive-grande" cx=".35" cy=".35" r=".8"><stop offset="0" stop-color="#d2de6e"/><stop offset=".55" stop-color="#8fa532"/><stop offset="1" stop-color="#55661a"/></radialGradient>',
        'shapes' => [
            'verde'    => '<ellipse rx="16" ry="11.5" fill="url(#olive-verde)"/><ellipse cx="-6" cy="-5" rx="5" ry="2" fill="#fff" opacity=".35"/>',
            'recheada' => '<ellipse rx="16" ry="11.5" fill="url(#olive-verde)"/><ellipse cx="14" rx="3.8" ry="4.6" fill="#c0392b"/><ellipse cx="-6" cy="-5" rx="5" ry="2" fill="#fff" opacity=".35"/>',
            'preta'    => '<ellipse rx="16" ry="11.5" fill="url(#olive-preta)"/><ellipse cx="-6" cy="-5" rx="5" ry="2" fill="#fff" opacity=".3"/>',
            'grande'   => '<ellipse rx="16" ry="11.5" fill="url(#olive-grande)"/><ellipse cx="-6" cy="-5" rx="5" ry="2" fill="#fff" opacity=".4"/>',
        ],
    ],

    'esquerda' => [
        'slug'   => 'esquerda',
        'name'   => 'Pimenta da Resistência',
        'name_a' => 'Pimenta',
        'name_b' => 'da Resistência',
        'other'  => 'direita',
        'emoji'  => '🌶️',
        'item'   => 'pimenta',
        'items'  => 'pimentas',
        'Item'   => 'Pimenta',
        'since'  => 'na resistência desde',
        'cert_title' => 'CERTIFICADO DE RESISTÊNCIA',
        'cert_since' => 'Na resistência desde',
        'members'    => 'resistentes',
        'react'      => 'apimentam',
        'sort_top'   => 'Mais apimentadas',
        'tag'        => 'Ardendo desde sempre',
        'h1'         => 'Coxinha aqui,<br>só na <em>festa</em>.',
        'lead'       => 'Enquanto o tio do pavê manda áudio de 12 minutos no grupo da família, você entra no <b>pote mais ardido do Brasil</b> e solta o verbo no mural para o coxinha engasgar. Não deixa o tio do zap dominar o pedaço: <b>cadastre-se grátis</b> e entre na conversa.',
        'og'         => 'Não se conserva. Resiste. Garanta sua pimenta no pote.',
        'label_top'  => '★ ARDE SEMPRE ★',
        'stat_uf'    => 'estado mais apimentado',
        'ranking'    => 'Estados livres de coxinha',
        'mural_tag'  => 'Mural da resistência',
        'success'    => 'Você entrou na resistência! 🌶️',
        'mural_about' => 'ideias, opiniões, notícias e vídeos sobre a esquerda',
        // apoio partidário (partials/partidos.php): só partidos de esquerda neste pote
        'partidos' => [
            'titulo' => 'Qual partido faz a sua pimenta arder?',
            'sub'    => 'Escolha até 3. O ranking muda na hora, e o tio do pavê vai ficar sabendo.',
        ],
        'mapa' => [
            'cor'          => '#e0402a',
            'titulo'       => 'O Brasil arde ou vira grupo do tio do zap?',
            'ganhando'     => 'A pimenta já arde em {meus} estados. O tio do pavê jura que é fake news e mandou um áudio de 12 minutos provando.',
            'perdendo'     => 'Os coxinhas estão conservando {deles} estados. Vai deixar o Brasil virar almoço de domingo com o tio do pavê?',
            'empate'       => 'Empate técnico: {meus} a {deles}. O tio do zap já está no grupo da família pedindo recontagem com áudio.',
            'estado_meu'   => 'Arde aqui. Vantagem de {dif}: o tio do zap ainda não viu, mas já está revoltado.',
            'estado_deles' => 'Dominado pela turma do tio do pavê. Faltam {falta} {itens} para esquentar o estado.',
            'estado_empate' => 'Empatado. Uma pimenta decide se o estado é da luta ou do grupo "Família Patriota 🇧🇷".',
            'estado_vazio' => 'Terra de ninguém. Ocupa antes que o coxinha cerque e chame de propriedade privada.',
            'fio_sub'      => 'Onde falta pouquinho para tirar o estado das mãos dos coxinhas.',
            'indicar'      => '🌶️ Os coxinhas estão conservando {uf}! Coloca tua pimenta no pote antes que o estado vire carreata de caminhonete: ',
            'rei_eu'       => 'A coroa é sua. O tio do pavê está de olho no trono (e no seu pavê).',
            'rei_time'     => 'É da companheirada, mas coroa não é vitalícia: com mais {falta} aqui, ela é sua.',
            'rei_deles'    => 'Tem coxinha sentado no trono, de camisa da seleção. Vire o estado e derrube a monarquia.',
        ],
        // escala de ardência: a pontuação aparece em SHU (unidade Scoville).
        // Nomes diferentes dos tipos à venda (Vermelha, Biquinho, Malagueta) para não confundir.
        'tempero' => [
            'titulo'  => 'Nível de ardência',
            'chamada' => 'Quanto mais arde, mais pimentas no nome',
            'unidade' => 'SHU',
            'fator'   => 100,
            'niveis'  => [
                ['nome' => 'Pimenta-de-cheiro', 'icone' => '🌶️'],
                ['nome' => 'Jalapeño',          'icone' => '🌶️🌶️'],
                ['nome' => 'Cumari',            'icone' => '🌶️🌶️🌶️'],
                ['nome' => 'Murupi',            'icone' => '🌶️🌶️🌶️🌶️'],
                ['nome' => 'Habanero',          'icone' => '🌶️🌶️🌶️🌶️🌶️'],
                ['nome' => 'Carolina Reaper',   'icone' => '🔥'],
            ],
        ],
        // 'vermelha' era "Dedo-de-moça": nome curto para caber numa linha no botão de compra
        'types' => [
            'biquinho'  => ['label' => 'Biquinho',  'price' => 2.90],
            'vermelha'  => ['label' => 'Vermelha',  'price' => 4.90],
            'malagueta' => ['label' => 'Malagueta', 'price' => 6.90],
            'grande'    => ['label' => 'Grande',    'price' => 9.90],
        ],
        'logo' => 'vermelha', // a pimenta vermelha continua sendo o símbolo do pote
        'scales' => ['grande' => 1.5, 'biquinho' => .85],
        'selos' => [
            'br' => 'Bandeira do Brasil',
            '✊' => 'Luta',
            '🌹' => 'Rosa',
            '❤️' => 'Coração',
            '⭐' => 'Estrela',
            '🌎' => 'Mundo',
            '📚' => 'Educação',
            '🌱' => 'Natureza',
        ],
        'theme' => [
            'bg' => '#1d0c0a',
            'bg-2' => '#27110e',
            'surface' => '#351714',
            'surface-2' => '#441e1a',
            'line' => '#5c2a23',
            'text' => '#fbeee6',
            'muted' => '#caa79c',
            'gold' => '#e04a1f',
            'gold-2' => '#ff8a3d',
            'olive' => '#b3261e',
            'glow' => '#5c1a12',
            'ink' => '#3a120c',
            'dark' => '#8a2216',
            'on-accent' => '#2a0a04',
        ],
        // vidro de pimenta: alto e fino, tampinha vermelha, sem líquido; pimentas em pé
        'lid'   => ['#ff5a4a', '#c8231a', '#7e120c'],
        'brine' => null,
        'jar'   => ['shape' => 'bottle', 'perRow' => 7, 'dx' => 22, 'dy' => 27, 'x0' => 134, 'y0' => 484, 'rows' => 12, 'rot' => -90, 'rotJitter' => 40, 'itemScale' => .85],
        'defs' => '
            <linearGradient id="pepper-vermelha" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff6a50"/><stop offset=".55" stop-color="#d62d20"/><stop offset="1" stop-color="#8e140c"/></linearGradient>
            <linearGradient id="pepper-biquinho" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffc063"/><stop offset=".55" stop-color="#ff7a1a"/><stop offset="1" stop-color="#c2410c"/></linearGradient>
            <linearGradient id="pepper-malagueta" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e0405a"/><stop offset=".55" stop-color="#a80f1f"/><stop offset="1" stop-color="#5a0610"/></linearGradient>
            <linearGradient id="pepper-grande" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff8a70"/><stop offset=".5" stop-color="#f0301f"/><stop offset="1" stop-color="#a3120a"/></linearGradient>',
        'shapes' => [
            'vermelha'  => '<path d="' . PEPPER_BODY . '" fill="url(#pepper-vermelha)"/>' . PEPPER_CAP . PEPPER_SHINE,
            'biquinho'  => '<path d="M10 0 C10 -7 -2 -8 -8 -3.5 C-10.5 -1.5 -12 .5 -14 .2 C-11 5.5 0 8 6.5 5.2 C9 4 10 2 10 0 Z" fill="url(#pepper-biquinho)"/><ellipse cx="10.4" cy=".2" rx="2.4" ry="3.8" fill="#2f7a2a"/><path d="M12 0 Q15 -.5 15.6 -3.8" stroke="#2f7a2a" stroke-width="1.8" fill="none" stroke-linecap="round"/><path d="M6 -4.5 Q0 -6 -5 -3.2" stroke="#fff" stroke-opacity=".5" stroke-width="1.4" fill="none" stroke-linecap="round"/>',
            'malagueta' => '<g transform="scale(1 .62)"><path d="' . PEPPER_BODY . '" fill="url(#pepper-malagueta)"/></g><ellipse cx="13.6" cy=".2" rx="2.2" ry="2.9" fill="#2f7a2a"/><path d="M15.3 -.2 Q19 -.8 20 -4.5" stroke="#2f7a2a" stroke-width="1.7" fill="none" stroke-linecap="round"/><path d="M9 -2.8 Q0 -4.2 -9 -1.8" stroke="#fff" stroke-opacity=".4" stroke-width="1.1" fill="none" stroke-linecap="round"/>',
            'grande'    => '<path d="' . PEPPER_BODY . '" fill="url(#pepper-grande)"/>' . PEPPER_CAP . PEPPER_SHINE,
        ],
    ],
];

function side(string $slug): array
{
    return SIDES[$slug] ?? SIDES['direita'];
}

function min_price(array $S): float
{
    return min(array_column($S['types'], 'price'));
}

// variáveis CSS do tema, para usar em style="..."
function theme_vars(array $S): string
{
    $out = '';
    foreach ($S['theme'] as $k => $v) {
        $out .= "--$k: $v; ";
    }
    return trim($out);
}

// desenho de um item (azeitona/pimenta) como <svg> avulso
function item_svg(array $S, string $tipo, string $class = '', float $scale = 1): string
{
    return '<svg class="' . e($class) . '" viewBox="-24 -17 48 34" aria-hidden="true"><g transform="scale(' . $scale . ')">'
        . ($S['shapes'][$tipo] ?? '') . '</g></svg>';
}

// mesma posição que o JS usa no pote (mantenha as duas em sincronia)
function jar_rand(float $seed): float
{
    $x = sin($seed * 9301 + 49297) * 233280;
    return $x - floor($x);
}

function jar_position(int $i, array $L): array
{
    $row = intdiv($i, $L['perRow']);
    $col = $i % $L['perRow'];
    return [
        'x' => $L['x0'] + $col * $L['dx'] + ($row % 2 ? $L['dx'] / 2 : 0) + (jar_rand($i) - .5) * 6,
        'y' => $L['y0'] - $row * $L['dy'] + (jar_rand($i + 7) - .5) * 4,
        'r' => $L['rot'] + (jar_rand($i + 3) - .5) * $L['rotJitter'],
    ];
}

// quantos itens cabem visíveis no pote
function jar_slots(array $L): int
{
    return $L['perRow'] * $L['rows'];
}
