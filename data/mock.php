<?php
// Dados fictícios enquanto não há banco de dados.
// Quando o banco existir, basta trocar estas funções por consultas.

function mock_phrases(string $side): array
{
    if ($side === 'esquerda') {
        return [
            'Não se conserva. Resiste.',
            'Direitos não se conservam, se conquistam.',
            'Picante desde a primeira greve.',
            'Mais tempero, menos privilégio.',
            'Justiça social com pimenta a gosto.',
            'Sem ardor não tem mudança.',
            'A ardência é coletiva.',
            'Educação, saúde e uma pimentinha.',
            'Quem arde por justiça não esfria.',
            'Minha pimenta, nossas pautas.',
            'Apimentada sim, amarga nunca.',
            'Menos muro, mais tempero.',
            'Tempero é diversidade.',
            'Pimenta no pote dos outros é refresco.',
        ];
    }
    return [
        'Princípios não estragam. Se conservam.',
        'Deus, pátria, família e azeitona na pizza.',
        'Meu pote, minhas regras.',
        'Quem é conservado não estraga.',
        'Azeitona com caroço: pelo menos tem princípios.',
        'Na dúvida, conserve.',
        'Azeitona não muda de pote a cada eleição.',
        'Salmoura é tradição, não modinha.',
        'Enquanto uns amadurecem, eu me conservo.',
        'Trabalho, ordem e um bom pote de azeitona.',
        'Fui conservado antes de virar moda.',
        'Pode me chamar de retrógrado. Eu chamo de tempero.',
        'Nem toda verde é esquerdista.',
        'Não me tire do pote, que eu não saio.',
        'Tradição é a azeitona que nunca sai da empada.',
        'Menos Estado, mais salmoura.',
    ];
}

function mock_items(string $side): array
{
    static $cache = [];
    if (isset($cache[$side])) {
        return $cache[$side];
    }
    $S = side($side);

    if ($side === 'esquerda') {
        $names = [['Companheira Rita', 'women'], ['Prof. Sérgio', 'men'], ['Mariana L.', 'women'], ['Seu Raimundo', 'men'],
            ['Tati do Coletivo', 'women'], ['João Pedro', 'men'], ['Dona Socorro', 'women'], ['Lucas M.', 'men'],
            ['Beatriz A.', 'women'], ['Metalúrgico 13', 'men'], ['Carla S.', 'women'], ['Zé da Feira', 'men'],
            ['Profa. Helena', 'women'], ['André R.', 'men'], ['Vó Benedita', 'women'], ['Diego F.', 'men'],
            ['Larissa P.', 'women'], ['Bancário Raiz', 'men'], ['Paulo H.', 'men'], ['Aline C.', 'women'],
            ['Sr. Josué', 'men'], ['Rodrigo N.', 'men'], ['Júlia T.', 'women'], ['Marcelo B.', 'men']];
        $cities = [['Recife','PE'],['Salvador','BA'],['Fortaleza','CE'],['São Luís','MA'],['Teresina','PI'],['Belém','PA'],
            ['Natal','RN'],['João Pessoa','PB'],['Maceió','AL'],['São Paulo','SP'],['Porto Alegre','RS'],['Rio de Janeiro','RJ'],
            ['Belo Horizonte','MG'],['Aracaju','SE'],['Manaus','AM'],['Campinas','SP'],['Niterói','RJ'],['Olinda','PE']];
        $types = ['vermelha', 'vermelha', 'vermelha', 'biquinho', 'biquinho', 'malagueta', 'malagueta', 'grande'];
        $selos = ['br', 'br', '✊', '✊', '🌹', '❤️', '⭐', '🌎', '📚', '🌱'];
        $seed  = 1917;
        $total = 104;
        $videoFrase = 'Vídeo antigo, mas a luta continua atual.';
    } else {
        $names = [['Carlos M.', 'men'], ['Tio do Churrasco', 'men'], ['Ana Paula', 'women'], ['Seu Valdir', 'men'],
            ['Pr. Márcio', 'men'], ['Juliana R.', 'women'], ['Coronel Aposentado', 'men'], ['Dona Cida', 'women'],
            ['Rafael B.', 'men'], ['Patriota Raiz', 'men'], ['Luiza F.', 'women'], ['Zé do Agro', 'men'],
            ['Fernanda K.', 'women'], ['Gustavo T.', 'men'], ['Vó Nair', 'women'], ['Marcos Vinícius', 'men'],
            ['Bruna S.', 'women'], ['Caminhoneiro 88', 'men'], ['Eduardo L.', 'men'], ['Priscila A.', 'women'],
            ['Sr. Hélio', 'men'], ['Thiago P.', 'men'], ['Renata C.', 'women'], ['Galo do Sul', 'men'],
            ['Dr. Almeida', 'men'], ['Camila V.', 'women'], ['Jorge N.', 'men'], ['Cristiane D.', 'women'],
            ['Leandro G.', 'men'], ['Sônia M.', 'women']];
        $cities = [['Chapecó','SC'],['Ribeirão Preto','SP'],['Goiânia','GO'],['Londrina','PR'],['Campo Grande','MS'],
            ['Uberlândia','MG'],['Joinville','SC'],['Cuiabá','MT'],['Porto Alegre','RS'],['Sorocaba','SP'],['Maringá','PR'],
            ['Blumenau','SC'],['Rio Verde','GO'],['Brasília','DF'],['Vitória','ES'],['Curitiba','PR'],['Campinas','SP'],
            ['Balneário Camboriú','SC'],['Belo Horizonte','MG'],['Rio de Janeiro','RJ'],['Sinop','MT'],['Cascavel','PR']];
        $types = ['verde', 'verde', 'verde', 'preta', 'preta', 'recheada', 'recheada', 'grande'];
        $selos = ['br', 'br', 'br', 'br', '✝️', '🙏', '⭐', '🦅', '🐂', '🌾', '🛡️'];
        $seed  = 2026;
        $total = 118;
        $videoFrase = 'Olha esse vídeo raiz, de quando a internet ainda era conservada.';
    }
    $phrases = mock_phrases($side);

    mt_srand($seed); // sempre os mesmos dados
    $items = [];
    $start = strtotime('2026-06-01');
    for ($i = 1; $i <= $total; $i++) {
        [$city, $uf]     = $cities[mt_rand(0, count($cities) - 1)];
        [$name, $gender] = $names[mt_rand(0, count($names) - 1)];
        $items[] = [
            'id'     => $i,
            'side'   => $side,
            'nome'   => $name,
            // fotos de exemplo; ~15% sem foto para testar o fallback com iniciais
            'foto'   => mt_rand(1, 100) <= 85 ? "https://randomuser.me/api/portraits/$gender/" . mt_rand(1, 99) . '.jpg' : null,
            'cidade' => $city,
            'uf'     => $uf,
            'tipo'   => $types[mt_rand(0, count($types) - 1)],
            'frase'  => $phrases[mt_rand(0, count($phrases) - 1)],
            'desde'  => date('Y-m-d', $start + (int) (($i / $total) * (time() - $start))),
            'likes'  => mt_rand(0, 420),
            'selo'   => mt_rand(1, 100) <= 40 ? $selos[mt_rand(0, count($selos) - 1)] : null,
        ];
    }
    mt_srand();

    // um post com vídeo para demonstrar o player no mural
    $items[$total - 1]['video'] = ['provider' => 'youtube', 'id' => 'jNQXAC9IVRw', 'vertical' => false];
    $items[$total - 1]['frase'] = $videoFrase;

    return $cache[$side] = $items;
}

// Comentários de exemplo: gente do mesmo pote e gente do outro lado
function mock_comments(string $side, array $items): array
{
    $other = side($side)['other'];
    $mine = $items;
    $theirs = mock_items($other);

    $pool = [
        'direita' => [
            'same'  => ['Falou tudo! 🫒', 'Assino embaixo.', 'Isso aí, conservado!', 'Perfeito.', 'Compartilhei no grupo da família.'],
            'other' => ['Arde, mas não convence. 🫒', 'Muita pimenta e pouca proposta.', 'Respeito, mas discordo.', 'Vem pro pote de azeitona que é mais tranquilo.', 'Tempero demais estraga o prato.'],
        ],
        'esquerda' => [
            'same'  => ['Resiste! 🌶️', 'É isso, companheira!', 'Arde de verdade.', 'Perfeito.', 'Mandei pro grupo do coletivo.'],
            'other' => ['Conservado demais, faltou tempero. 🌶️', 'Tá precisando de uma pimentinha nesse pote.', 'Respeito, mas discordo.', 'Azeitona não arde, né?', 'Conservar o quê, exatamente?'],
        ],
    ];

    mt_srand($side === 'direita' ? 77 : 88);
    $out = [];
    foreach ($items as $it) {
        if (mt_rand(1, 100) > 38) {
            continue;
        }
        $n = mt_rand(1, 4);
        for ($k = 0; $k < $n; $k++) {
            $fromOther = mt_rand(0, 1) === 1;
            $author = $fromOther ? $theirs[mt_rand(0, count($theirs) - 1)] : $mine[mt_rand(0, count($mine) - 1)];
            $texts = $pool[$author['side']][$fromOther ? 'other' : 'same'];
            $out[] = [
                'post'  => "$side-o{$it['id']}",
                'autor' => array_intersect_key($author, array_flip(['id', 'side', 'nome', 'foto', 'tipo', 'selo'])),
                'texto' => $texts[mt_rand(0, count($texts) - 1)],
                'data'  => $it['desde'],
            ];
        }
    }
    mt_srand();
    return $out;
}

function ranking_uf(array $items, int $limit = 8): array
{
    $count = [];
    foreach ($items as $o) {
        $count[$o['uf']] = ($count[$o['uf']] ?? 0) + 1;
    }
    arsort($count);
    return array_slice($count, 0, $limit, true);
}
