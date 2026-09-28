<?php
// Dados fictícios enquanto não há banco de dados.
// Quando o banco existir, basta trocar estas funções por consultas.

function mock_phrases(): array
{
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

function mock_olives(): array
{
    // [nome, gênero da foto de exemplo]
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
    $types  = ['verde', 'verde', 'verde', 'preta', 'preta', 'recheada', 'recheada', 'grande'];
    $phrases = mock_phrases();

    mt_srand(2026); // sempre os mesmos dados
    $olives = [];
    $start  = strtotime('2026-06-01');
    $total  = 118;
    for ($i = 1; $i <= $total; $i++) {
        [$city, $uf]     = $cities[mt_rand(0, count($cities) - 1)];
        [$name, $gender] = $names[mt_rand(0, count($names) - 1)];
        $olives[] = [
            'id'     => $i,
            'nome'   => $name,
            // fotos de exemplo; ~15% sem foto para testar o fallback com iniciais
            'foto'   => mt_rand(1, 100) <= 85 ? "https://randomuser.me/api/portraits/$gender/" . mt_rand(1, 99) . '.jpg' : null,
            'cidade' => $city,
            'uf'     => $uf,
            'tipo'   => $types[mt_rand(0, count($types) - 1)],
            'frase'  => $phrases[mt_rand(0, count($phrases) - 1)],
            'desde'  => date('Y-m-d', $start + (int) (($i / $total) * (time() - $start))),
            'likes'  => mt_rand(0, 420),
            // ~40% com selo; a bandeira do Brasil é a mais comum
            'selo'   => mt_rand(1, 100) <= 40 ? ['br', 'br', 'br', 'br', '✝️', '🙏', '⭐', '🦅', '🐂', '🌾', '🛡️'][mt_rand(0, 10)] : null,
        ];
    }
    mt_srand();

    // um post com vídeo para demonstrar o player no mural
    $olives[$total - 1]['video'] = ['provider' => 'youtube', 'id' => 'jNQXAC9IVRw', 'vertical' => false];
    $olives[$total - 1]['frase'] = 'Olha esse vídeo raiz, de quando a internet ainda era conservada.';

    return $olives;
}

function ranking_uf(array $olives, int $limit = 8): array
{
    $count = [];
    foreach ($olives as $o) {
        $count[$o['uf']] = ($count[$o['uf']] ?? 0) + 1;
    }
    arsort($count);
    return array_slice($count, 0, $limit, true);
}
