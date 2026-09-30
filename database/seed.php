<?php
// Popula um banco de DESENVOLVIMENTO / STAGING com gente de mentira, para testar o site com volume:
// 1.200 cadastros (600 por pote), azeitonas e pimentas, frases, publicações, curtidas, milhares de comentários
// (educados, provocadores, grossos, respostas citando outros), apoio partidário e uma enquete com votos.
//
//   php database/seed.php
//
// Rodar de novo apaga só o que o seed criou (e-mails pessoa-LADO-N@seed.local e tudo deles) e recria.
// Cadastros de verdade (e o que eles fizeram) não são tocados.
// NÃO roda com APP_ENV=production: o banco de produção começa limpo.
if (PHP_SAPI !== 'cli') {
    exit("Rode pelo terminal: php database/seed.php\n");
}
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/data/mock.php';

const SEED_EMAIL = '@seed.local';
const SEED_POR_LADO = 600;

if (env('APP_ENV', 'local') === 'production') {
    exit("APP_ENV=production: o seed é só para desenvolvimento/staging.\n");
}

$pdo = db();
mt_srand(20260928); // sempre os mesmos dados
$agora = time();
$t0 = microtime(true);
$passo = fn(string $t) => printf("%-46s %5.1fs\n", $t, microtime(true) - $t0);

// ---------- utilidades ----------
$um = fn(array $a) => $a[mt_rand(0, count($a) - 1)];
$chance = fn(int $pct) => mt_rand(1, 100) <= $pct;
/** sorteio com peso: ['a' => 3, 'b' => 1] */
$pesado = function (array $pesos) {
    $r = mt_rand(1, array_sum($pesos));
    foreach ($pesos as $k => $p) {
        if (($r -= $p) <= 0) {
            return $k;
        }
    }
    return array_key_first($pesos);
};
/** insere em lotes (o banco pode estar longe: poucas idas e voltas) */
$lotes = function (string $sql, array $linhas, int $tam = 400) use ($pdo) {
    foreach (array_chunk($linhas, $tam) as $lote) {
        $cols = count($lote[0]);
        $marcas = implode(',', array_fill(0, count($lote), '(' . implode(',', array_fill(0, $cols, '?')) . ')'));
        $pdo->prepare($sql . ' VALUES ' . $marcas)->execute(array_merge(...array_map('array_values', $lote)));
    }
};
$data = fn(int $ts) => date('Y-m-d H:i:s', $ts);
/** momento aleatório entre $de e $ate, preferindo o horário "acordado" (8h–23h) */
$momento = function (int $de, int $ate) use ($agora): int {
    $ate = min($ate, $agora - 30);
    $t = mt_rand(min($de, $ate), $ate);
    $h = (int) date('G', $t);
    if ($h < 7 && mt_rand(1, 100) <= 80) {
        $t = min($ate, $t + mt_rand(7, 14) * 3600);
    }
    return $t;
};

// ---------- jeito humano de escrever ----------
$semAcento = fn(string $s) => strtr($s, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c',
    'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ç' => 'C', 'Ã' => 'A', 'Õ' => 'O']);
$abreviar = fn(string $s) => preg_replace_callback('/\b(você|vocês|porque|por que|também|que|não|hoje|muito|beleza|tudo|comigo|mesmo|quando|obrigado|obrigada)\b/iu',
    fn($m) => ['você' => 'vc', 'vocês' => 'vcs', 'porque' => 'pq', 'por que' => 'pq', 'também' => 'tb', 'que' => 'q', 'não' => 'n', 'hoje' => 'hj',
        'muito' => 'mt', 'beleza' => 'blz', 'tudo' => 'td', 'comigo' => 'cmg', 'mesmo' => 'msm', 'quando' => 'qnd', 'obrigado' => 'obg', 'obrigada' => 'obg'][mb_strtolower($m[1])] ?? $m[1], $s);
$errinho = function (string $s): string { // troca/duplica uma letra, como quem digita no celular
    $p = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    if (count($p) < 8) {
        return $s;
    }
    $i = mt_rand(2, count($p) - 3);
    if (!preg_match('/\p{L}/u', $p[$i]) || !preg_match('/\p{L}/u', $p[$i + 1])) {
        return $s;
    }
    mt_rand(0, 1) ? [$p[$i], $p[$i + 1]] = [$p[$i + 1], $p[$i]] : array_splice($p, $i, 0, $p[$i]);
    return implode('', $p);
};
$humanizar = function (string $s, string $lado) use ($chance, $um, $semAcento, $abreviar, $errinho): string {
    if ($chance(28)) {
        $s = $abreviar($s);
    }
    if ($chance(30)) {
        $s = mb_strtolower($s);
    }
    if ($chance(12)) {
        $s = $semAcento($s);
    }
    if ($chance(9)) {
        $s = $errinho($s);
    }
    if ($chance(18)) {
        $s = rtrim($s, '.') . ' ' . $um(['kkkk', 'kkkkkk', 'kkkkkkkkk', 'rsrs', 'hahaha', 'KKKKKK']);
    }
    if ($chance(22)) {
        $s .= ' ' . $um($lado === 'direita' ? ['🫒', '🇧🇷', '👏', '🙏', '💪', '😂', '🤣', '👍', '🦅', '🔥'] : ['🌶️', '✊', '❤️', '🔥', '😂', '🤣', '👏', '🌹', '💪', '👀']);
    }
    if ($chance(6)) {
        $s = rtrim($s, '.!') . '!!!';
    }
    return mb_substr(trim(preg_replace('/\s+/u', ' ', $s)), 0, 200);
};

// ---------- 1. apaga o seed anterior ----------
$usuariosSeed = "SELECT id FROM usuarios WHERE email LIKE '%" . SEED_EMAIL . "'";
$itensSeed = "SELECT id FROM itens WHERE usuario_id IN ($usuariosSeed)";
$postsSeed = "SELECT id FROM posts WHERE item_id IN ($itensSeed)";
$pdo->exec("DELETE FROM enquetes WHERE criado_por IN ($usuariosSeed)");
$pdo->exec("DELETE FROM enquete_votos WHERE usuario_id IN ($usuariosSeed)");
$pdo->exec("DELETE FROM curtidas WHERE usuario_id IN ($usuariosSeed) OR post_id IN ($postsSeed)");
$pdo->exec("UPDATE comentarios SET cita_id = NULL WHERE cita_id IN (SELECT id FROM (SELECT id FROM comentarios WHERE item_id IN ($itensSeed) OR post_id IN ($postsSeed)) t)");
$pdo->exec("DELETE FROM comentarios WHERE item_id IN ($itensSeed) OR post_id IN ($postsSeed)");
$pdo->exec("DELETE FROM posts WHERE item_id IN ($itensSeed)");
$pdo->exec("DELETE FROM itens WHERE usuario_id IN ($usuariosSeed)");
try {
    $pdo->exec("DELETE FROM apoios_partido WHERE usuario_id IN ($usuariosSeed)");
    $pdo->exec("DELETE FROM usuario_niveis WHERE usuario_id IN ($usuariosSeed)");
} catch (PDOException $e) {
}
$pdo->exec("DELETE FROM usuarios WHERE email LIKE '%" . SEED_EMAIL . "'");
$passo('Seed anterior apagado');

// ---------- 2. quem são as pessoas ----------
$nomesF = ['Ana', 'Maria', 'Juliana', 'Fernanda', 'Patrícia', 'Aline', 'Camila', 'Amanda', 'Bruna', 'Letícia', 'Larissa', 'Beatriz', 'Gabriela', 'Mariana',
    'Vanessa', 'Carla', 'Luciana', 'Renata', 'Cristiane', 'Sandra', 'Débora', 'Tatiane', 'Priscila', 'Jéssica', 'Natália', 'Raquel', 'Simone', 'Rosângela',
    'Márcia', 'Adriana', 'Eliane', 'Cláudia', 'Viviane', 'Daniela', 'Luana', 'Isabela', 'Rafaela', 'Tainá', 'Kelly', 'Josiane', 'Sônia', 'Helena', 'Laura',
    'Alice', 'Valentina', 'Cecília', 'Regina', 'Lúcia', 'Aparecida', 'Francisca', 'Rita', 'Socorro', 'Graça', 'Luiza', 'Paula', 'Talita', 'Michele', 'Joyce'];
$nomesM = ['José', 'João', 'Carlos', 'Paulo', 'Pedro', 'Lucas', 'Luiz', 'Marcos', 'Gabriel', 'Rafael', 'Daniel', 'Marcelo', 'Bruno', 'Eduardo', 'Felipe',
    'Rodrigo', 'Gustavo', 'Fernando', 'André', 'Fábio', 'Leandro', 'Thiago', 'Diego', 'Anderson', 'Ricardo', 'Alexandre', 'Márcio', 'Sérgio', 'Roberto',
    'Jorge', 'Antônio', 'Francisco', 'Raimundo', 'Sebastião', 'Valdir', 'Wagner', 'Wellington', 'Cleiton', 'Edson', 'Mateus', 'Vinícius', 'Henrique',
    'Caio', 'Igor', 'Otávio', 'Renan', 'Juliano', 'Adriano', 'Cristiano', 'Hélio', 'Gilberto', 'Osmar', 'Nilton', 'Davi', 'Samuel', 'Arthur', 'Heitor'];
$sobrenomes = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Lima', 'Pereira', 'Costa', 'Rodrigues', 'Almeida', 'Nascimento', 'Carvalho', 'Araújo', 'Ribeiro',
    'Ferreira', 'Gomes', 'Martins', 'Rocha', 'Barbosa', 'Melo', 'Cardoso', 'Teixeira', 'Moreira', 'Mendes', 'Freitas', 'Castro', 'Pinto', 'Duarte',
    'Moraes', 'Vieira', 'Nunes', 'Batista', 'Campos', 'Monteiro', 'Cavalcanti', 'Farias', 'Dias', 'Machado', 'Zanella', 'Schmidt', 'Becker', 'Rossi'];
$apelidos = [
    'direita' => ['Tio do Churrasco', 'Patriota Raiz', 'Zé do Agro', 'Caminhoneiro 88', 'Coronel Aposentado', 'Galo do Sul', 'Vó Nair', 'Seu Valdir',
        'Dona Cida', 'Pr. Márcio', 'Dr. Almeida', 'Sargento Moraes', 'Tiozão da Hilux', 'Peão de Rodeio', 'Mãe de Pet Conservadora', 'Gaúcho Raiz',
        'Empresário Cansado', 'Contador de Impostos', 'Dona Zefa', 'Motorista de App', 'Produtor Rural', 'Seu Nestor', 'Vovó Patriota', 'Cabo Silva',
        'Pastor Ednaldo', 'Tia do Zap', 'Síndico do Prédio', 'Dono da Padaria', 'Jiujiteiro Cristão', 'Coach de Finanças'],
    'esquerda' => ['Companheira Rita', 'Tati do Coletivo', 'Zé da Feira', 'Metalúrgico 13', 'Bancário Raiz', 'Vó Benedita', 'Profa. Helena', 'Seu Raimundo',
        'Dona Socorro', 'Prof. Sérgio', 'Estudante de Humanas', 'Enfermeira do SUS', 'Motoboy Consciente', 'Artesã de Olinda', 'Poeta de Bar',
        'Sindicalista Aposentado', 'Mãe Atípica', 'Mano da Quebrada', 'Bibliotecária Brava', 'Cozinheira de Marmita', 'Tia do Sarau', 'Garçom Revoltado',
        'Doutoranda Cansada', 'Assistente Social', 'Professor de História', 'Pescador do Norte', 'Agricultora Familiar', 'Rapper de Quebrada', 'Fotógrafa Ativista', 'DJ de Forró'],
];
// onde moram: cada pote puxa para as regiões onde o lado é mais forte, mas tem gente de todo lugar
$cidades = [
    'direita' => [['Chapecó', 'SC', 5], ['Joinville', 'SC', 5], ['Blumenau', 'SC', 5], ['Balneário Camboriú', 'SC', 4], ['Florianópolis', 'SC', 4], ['Curitiba', 'PR', 8],
        ['Londrina', 'PR', 5], ['Maringá', 'PR', 5], ['Cascavel', 'PR', 4], ['Porto Alegre', 'RS', 6], ['Caxias do Sul', 'RS', 4], ['Passo Fundo', 'RS', 3],
        ['São Paulo', 'SP', 12], ['Ribeirão Preto', 'SP', 5], ['Sorocaba', 'SP', 4], ['Campinas', 'SP', 5], ['São José do Rio Preto', 'SP', 3], ['Goiânia', 'GO', 7],
        ['Rio Verde', 'GO', 3], ['Anápolis', 'GO', 2], ['Campo Grande', 'MS', 5], ['Dourados', 'MS', 2], ['Cuiabá', 'MT', 5], ['Sinop', 'MT', 3],
        ['Rondonópolis', 'MT', 2], ['Uberlândia', 'MG', 5], ['Belo Horizonte', 'MG', 6], ['Montes Claros', 'MG', 2], ['Brasília', 'DF', 6], ['Vitória', 'ES', 3],
        ['Vila Velha', 'ES', 2], ['Rio de Janeiro', 'RJ', 8], ['Niterói', 'RJ', 2], ['Porto Velho', 'RO', 3], ['Ji-Paraná', 'RO', 1], ['Boa Vista', 'RR', 2],
        ['Palmas', 'TO', 2], ['Rio Branco', 'AC', 2], ['Manaus', 'AM', 3], ['Belém', 'PA', 2], ['Recife', 'PE', 2], ['Fortaleza', 'CE', 2], ['Salvador', 'BA', 2], ['Macapá', 'AP', 1]],
    'esquerda' => [['Recife', 'PE', 8], ['Olinda', 'PE', 3], ['Caruaru', 'PE', 2], ['Salvador', 'BA', 9], ['Feira de Santana', 'BA', 3], ['Vitória da Conquista', 'BA', 2],
        ['Fortaleza', 'CE', 8], ['Juazeiro do Norte', 'CE', 2], ['São Luís', 'MA', 5], ['Imperatriz', 'MA', 2], ['Teresina', 'PI', 5], ['Natal', 'RN', 4],
        ['Mossoró', 'RN', 2], ['João Pessoa', 'PB', 4], ['Campina Grande', 'PB', 3], ['Maceió', 'AL', 4], ['Aracaju', 'SE', 3], ['Belém', 'PA', 5],
        ['Santarém', 'PA', 2], ['Manaus', 'AM', 4], ['Macapá', 'AP', 2], ['São Paulo', 'SP', 12], ['Campinas', 'SP', 3], ['Santo André', 'SP', 3],
        ['São Bernardo do Campo', 'SP', 3], ['Rio de Janeiro', 'RJ', 9], ['Niterói', 'RJ', 3], ['Belo Horizonte', 'MG', 6], ['Juiz de Fora', 'MG', 2],
        ['Porto Alegre', 'RS', 6], ['Pelotas', 'RS', 2], ['Florianópolis', 'SC', 2], ['Curitiba', 'PR', 3], ['Brasília', 'DF', 4], ['Goiânia', 'GO', 2],
        ['Vitória', 'ES', 2], ['Palmas', 'TO', 1], ['Cuiabá', 'MT', 1], ['Porto Velho', 'RO', 1], ['Rio Branco', 'AC', 1], ['Boa Vista', 'RR', 1], ['Campo Grande', 'MS', 1]],
];
$tiposPeso = ['direita' => ['verde' => 46, 'recheada' => 26, 'preta' => 18, 'grande' => 10], 'esquerda' => ['biquinho' => 44, 'vermelha' => 27, 'malagueta' => 19, 'grande' => 10]];
$selos = ['direita' => ['br', 'br', 'br', 'br', '✝️', '🙏', '⭐', '🦅', '🐂', '🌾', '🛡️'], 'esquerda' => ['br', 'br', '✊', '✊', '✊', '🌹', '❤️', '⭐', '🌎', '📚', '🌱']];

// ---------- 3. as frases da compra (o 1º post de cada um) ----------
$frases = [
    'direita' => array_merge(mock_phrases('direita'), [
        'Conservado desde sempre, e com orgulho.', 'Aqui é trabalho, família e fé.', 'Meu voto não se vende, se conserva.', 'Brasil acima de tudo, azeitona acima da pizza.',
        'Criado na roça, conservado na cidade.', 'Pago imposto demais pra ficar calado.', 'Não sou radical, sou conservado.', 'Liberdade econômica e empada de azeitona.',
        'Deus no comando e azeitona no pote.', 'Minha família é meu partido.', 'Quem planta colhe. Quem conserva, dura.', 'Nasci no interior e o interior mora em mim.',
        'Agro é pop, agro é tudo, agro é azeitona.', 'Conservador sim, chato jamais.', 'Menos Brasília, mais Brasil.', 'Pátria amada, pote lotado.',
        'Aqui a gente acorda cedo.', 'Mais trabalho, menos lacração.', 'Meu avô era conservador. Meu pai também. Eu sou azeitona.', 'Ordem e progresso, e azeitona no pastel.',
        'Livre mercado, livre pensamento.', 'Sou do tempo em que respeito se ensinava em casa.', 'Direita raiz, sem modinha.', 'Vim pelo meme, fiquei pelo pote.',
        'Entrei pra ver o mapa ficar verde.', 'Tô aqui pela minha filha, que vai herdar esse país.', 'Conservado e sem glúten. Mentira, com glúten mesmo.',
    ]),
    'esquerda' => array_merge(mock_phrases('esquerda'), [
        'Ninguém solta a mão de ninguém.', 'Educação pública salvou minha vida.', 'SUS na veia, pimenta no pote.', 'Trabalhador unido arde mais.',
        'Nordeste resiste, e resiste arretado.', 'Pão, terra, trabalho e pimenta.', 'Sonho com um Brasil que caiba todo mundo.', 'Direito não é favor.',
        'Filha de empregada, primeira da família na faculdade.', 'Sou do povo e o povo é pimenta.', 'A esperança venceu o medo, e ainda arde.', 'Salário mínimo não é teto, é chão.',
        'Periferia no centro do debate.', 'Arde, mas é pra curar.', 'Minha avó lutou pra eu estar aqui.', 'Diversidade é o melhor tempero.',
        'Professora, mãe e resistência.', 'Cultura é resistência.', 'Vim pelo meme, fiquei pela ardência.', 'Quero o mapa vermelho até o Oiapoque.',
        'Pimenta pouca é bobagem.', 'Ninguém fica pra trás.', 'O povo não é bobo.', 'Bora apimentar esse país.', 'Tô aqui pela minha filha, que vai herdar esse país.',
        'Sindicalizado e apimentado.', 'Moro na quebrada e voto com consciência.',
    ]),
];

// ---------- 4. gera as pessoas e as azeitonas/pimentas ----------
$pessoas = [];   // [] = ['lado','n','nome','genero','email','criado','cidade','uf','foto','frase','selo','itens' => [[tipo, criado]]]
foreach (array_keys(SIDES) as $lado) {
    $pesoCidade = [];
    foreach ($cidades[$lado] as $i => $c) {
        $pesoCidade[$i] = $c[2];
    }
    for ($n = 1; $n <= SEED_POR_LADO; $n++) {
        $f = $chance(50);
        if ($chance(9)) {
            $nome = $um($apelidos[$lado]);
            $f = (bool) preg_match('/^(Dona|Vó|Vovó|Tia|Mãe|Companheira|Tati|Profa|Enfermeira|Artesã|Bibliotecária|Cozinheira|Doutoranda|Assistente|Agricultora|Fotógrafa)/u', $nome);
        } else {
            $primeiro = $um($f ? $nomesF : $nomesM);
            $nome = match (mt_rand(1, 10)) {
                1, 2, 3, 4 => $primeiro . ' ' . $um($sobrenomes),
                5, 6, 7 => $primeiro . ' ' . mb_substr($um($sobrenomes), 0, 1) . '.',
                8 => $primeiro . ' ' . $um($sobrenomes) . ' ' . $um($sobrenomes),
                default => $primeiro,
            };
        }
        [$cidade, $uf] = $cidades[$lado][$pesado($pesoCidade)];
        // o site cresce: mais gente chegando nos últimos meses (até ~14 meses atrás)
        $diasAtras = (int) floor(420 * pow(mt_rand(0, 1000) / 1000, 1.9));
        $criado = $momento($agora - ($diasAtras + 1) * 86400, $agora - $diasAtras * 86400);
        $foto = null;
        $r = mt_rand(1, 100);
        if ($r <= 80) {
            $foto = 'https://randomuser.me/api/portraits/' . ($f ? 'women' : 'men') . '/' . mt_rand(0, 99) . '.jpg';
        } elseif ($r <= 83) {
            $foto = 'https://randomuser.me/api/portraits/lego/' . mt_rand(0, 9) . '.jpg';
        }
        $qtd = $pesado([1 => 64, 2 => 20, 3 => 9, 4 => 4, 5 => 2, 7 => 1]);
        $itensPessoa = [];
        for ($k = 0; $k < $qtd; $k++) { // as seguintes vêm depois da primeira
            $itensPessoa[] = [$pesado($tiposPeso[$lado]), $k === 0 ? $criado : $momento($criado, $agora)];
        }
        $pessoas[] = [
            'lado' => $lado, 'n' => $n, 'nome' => $nome, 'email' => "pessoa-$lado-$n" . SEED_EMAIL, 'criado' => $criado,
            'cidade' => $cidade, 'uf' => $uf, 'foto' => $foto, 'frase' => $humanizar($um($frases[$lado]), $lado),
            'selo' => $chance(42) ? $um($selos[$lado]) : null, 'itens' => $itensPessoa,
        ];
    }
}

// cadastros (em lotes) e os ids que o banco deu
$lotes('INSERT INTO usuarios (nome, email, email_verificado_em, criado_em)',
    array_map(fn($p) => [$p['nome'], $p['email'], $data($p['criado']), $data($p['criado'])], $pessoas));
$ids = $pdo->query("SELECT email, id FROM usuarios WHERE email LIKE '%" . SEED_EMAIL . "'")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($pessoas as &$p) {
    $p['id'] = (int) $ids[$p['email']];
}
unset($p);
$passo(count($pessoas) . ' cadastros');

// azeitonas/pimentas: numeradas na ordem em que entraram no pote
$tipos = [];
foreach ($pdo->query('SELECT id, lado, slug FROM item_tipos') as $t) {
    $tipos[$t['lado']][$t['slug']] = (int) $t['id'];
}
$linhasItens = [];
foreach (array_keys(SIDES) as $lado) {
    $todos = [];
    foreach ($pessoas as $i => $p) {
        if ($p['lado'] === $lado) {
            foreach ($p['itens'] as $k => [$tipo, $criado]) {
                $todos[] = [$i, $k, $tipo, $criado];
            }
        }
    }
    usort($todos, fn($a, $b) => $a[3] <=> $b[3]);
    $numero = (int) $pdo->query('SELECT COALESCE(MAX(numero), 0) FROM itens WHERE lado = ' . $pdo->quote($lado))->fetchColumn();
    foreach ($todos as [$i, $k, $tipo, $criado]) {
        $p = &$pessoas[$i];
        $desde = date('Y-m-d', $criado);
        // quem entrou há mais de 1 ano renovou (continua "desde" a data antiga)
        $vale = date('Y-m-d', strtotime("$desde +" . ($criado < $agora - 360 * 86400 ? 2 : 1) . ' year'));
        $p['numeros'][$k] = ++$numero;
        $linhasItens[] = [$lado, $numero, $p['id'], $tipos[$lado][$tipo], $p['nome'], $p['cidade'], $p['uf'], $p['frase'],
            $p['foto'], $p['selo'] ? 'preset' : null, $p['selo'], $desde, $vale, 'ativo', $data($criado)];
        unset($p);
    }
    $pdo->prepare('UPDATE potes SET proximo_numero = ? WHERE slug = ?')->execute([$numero + 1, $lado]);
}
$lotes('INSERT INTO itens (lado, numero, usuario_id, item_tipo_id, nome, cidade, uf, frase, foto_path, selo_tipo, selo_valor, desde, valido_ate, status, criado_em)', $linhasItens);
$itemId = $itemDono = []; // [lado][numero] = id; [id] = usuario_id
foreach ($pdo->query("SELECT lado, numero, id, usuario_id FROM itens WHERE usuario_id IN ($usuariosSeed)") as $r) {
    $itemId[$r['lado']][(int) $r['numero']] = (int) $r['id'];
    $itemDono[(int) $r['id']] = (int) $r['usuario_id'];
}
foreach ($pessoas as &$p) {
    $p['item'] = $itemId[$p['lado']][$p['numeros'][0]]; // a 1ª azeitona: é com ela que a pessoa publica e comenta
    $p['uid'] = $itemDono[$p['item']];
}
unset($p);
$passo(count($linhasItens) . ' azeitonas e pimentas');

// ---------- 5. publicações no mural ----------
$posts = [
    'direita' => [
        'Churrasco de domingo sem discussão política é churrasco de canhoto.', 'Meu avô já dizia: o que é bom a gente conserva.',
        'Hoje briguei no grupo da família e ganhei. Azeitona 1 × 0 pimenta.', 'Quem trabalha não tem tempo pra assembleia.',
        'Acordei 5h, trabalhei, paguei imposto. Rotina de conservado.', 'O mapa tá ficando verde. Segura, petralhada!',
        'Comunista de iPhone reclamando do capitalismo pelo Wi-Fi do shopping.', 'Tradição não é moda. Moda é o que a canhotada inventa toda semana.',
        'Coloquei mais três azeitonas no pote. Estado conservado é estado protegido.', 'Petralha fala em dividir o bolo, mas nunca trouxe a farinha.',
        'Mandei esse site pro grupo da família. Metade entrou no pote, metade saiu do grupo.', 'Enquanto a pimentada arde no Twitter, a gente trabalha.',
        'Alguém mais aqui de Santa Catarina? Bora pintar o estado de verde.', 'Meu filho de 16 anos perguntou o que é conservador. Mostrei o pote.',
        'Gasolina subiu de novo e o pessoal do outro pote comemorando não sei o quê.', 'Primeira vez que compro algo político e não me arrependo kkkk',
        'Dei uma azeitona de presente pro meu pai de aniversário. O velho amou, printou o certificado e mandou pra todo mundo.',
        'Quem manda no Paraná é azeitona, só avisando.', 'O pessoal do pote vermelho tá muito nervoso hoje, o que aconteceu?',
        'Imposto é roubo? Não sei, mas que dói, dói.', 'Bom dia pra quem acorda cedo e não depende do governo.',
        'A pimentada ficou brava porque o mapa virou. Chorem.', 'Minha esposa entrou no pote também. Família conservada é outra coisa.',
        'Comprei a Grande só pra ficar maior que o comunista da firma.', 'Tem gente no outro pote que nunca pagou um boleto na vida.',
        'Quem mais tá aqui só pra zoar a pimentada? 🙋‍♂️', 'Esse site é o melhor investimento de R$ 2,90 que fiz no ano.',
        'Sou conservador mas respeito quem pensa diferente. Só não concordo kkkk', 'Culto de manhã, churrasco à tarde, pote à noite.',
        'Fiz as contas: se cada um trouxer mais um, a gente fecha o Sul inteiro.', 'Alguém sabe se dá pra colocar a bandeira do meu estado como selo?',
        'Ontem um petista comentou no meu post. Respondi com educação. Ele não voltou mais.', 'Hoje é dia de trabalhar e deixar a militância pra quem não trabalha.',
        'Vi o ranking e Goiás tá subindo. Orgulho!', 'Meu pai tem 78 anos e pediu ajuda pra comprar a azeitona dele. Ele tá mais feliz que criança.',
        'Pimentada fala muito de amor mas vem aqui xingar a gente kkkkk', 'Economia livre, família forte. Simples assim.',
        'Galera, bora chegar em mil azeitonas até o fim do mês?', 'Não sou de política, mas esse pote me pegou.', 'Nunca vi tanta pimenta ardida por causa de azeitona.',
    ],
    'esquerda' => [
        'Tio do zap mandou áudio de 11 minutos. Ouvi em 2x e continuei discordando.', 'Saúde e educação pública: o tempero que o país precisa.',
        'O coxinha reclama do imposto, mas adora estrada asfaltada.', 'Hoje o mapa ficou mais vermelho. Arde, azeitona!',
        'Sobrevivi ao almoço com o tio do pavê e ainda defendi o SUS.', 'Pimenta biquinho é porta de entrada. Depois vem a malagueta.',
        'Salário digno não é luxo, é tempero básico.', 'Azeitoneiro diz que é apolítico e passa o dia no grupo "Patriotas Unidos 🇧🇷".',
        'Feira de domingo, pastel de queijo e debate com o vizinho coxinha. Programa completo.', 'Coloquei mais uma pimenta no pote. O Nordeste agradece.',
        'O tio do pavê descobriu esse site. Vai ter áudio.', 'Carreata de caminhonete não enche o pote. Pimenta sim.',
        'Meu pote tem mais diversidade que a bancada inteira do outro lado.', 'O coxinha comentou no meu post. Tô me sentindo famosa.',
        'Bora deixar o mapa vermelho até o Chuí, gente!', 'Professora aqui. Salário atrasado, mas a pimenta tá em dia kkkk',
        'Minha mãe fez 70 anos e ganhou uma pimenta de presente. Ela disse que é a primeira coisa política que ela não briga kkkk',
        'Bahia é vermelha e não tem conversa.', 'Tem azeitona aqui que nunca pegou um ônibus lotado na vida.', 'Enfermeira de plantão, passando pra dar oi pro pote 🌶️',
        'Quem mais tá aqui só pra provocar as azeitonas? 🙋‍♀️', 'Melhor R$ 2,90 que gastei esse ano.', 'Pernambuco segura o Nordeste inteiro, confia.',
        'Vi uma azeitona dizendo que trabalha desde os 14. Eu também, amigo. É por isso que sou pimenta.', 'Direita conservada é aquela que conserva até o preconceito na geladeira.',
        'Dei pimenta de presente pra minha namorada. Ela é azeitona. Agora tá o caos em casa kkkkk', 'Ardência coletiva > salmoura individual.',
        'Hoje é dia de apimentar o grupo da família.', 'Galera do Ceará, bora encher esse pote!', 'Sou de esquerda e acho esse site muito engraçado, parabéns a quem fez.',
        'O povo do pote verde fica bravo com qualquer coisa, impressionante.', 'Estudei em escola pública, faculdade pública e hoje sou médica. Pimenta com orgulho.',
        'Entrei pra zoar e acabei comprando três. Vício.', 'Sabe o que arde mais que pimenta? Aluguel.', 'Tem gente do outro pote que ainda acha que vacina tem chip.',
        'Minas tá dividido, bora virar isso!', 'Não sou petista, sou pimenta. Tem diferença.', 'Respeito todo mundo, mas o mapa vermelho é mais bonito.',
        'Quem inventou esse site merece um prêmio.', 'Sexta-feira, cerveja gelada e pote cheio.',
    ],
];
$linhasPosts = [];
$autoresPost = [];
foreach ($pessoas as $i => $p) { // a frase da compra de cada um
    $linhasPosts[] = [$p['lado'], $p['item'], $p['uid'], $p['frase'], 1, $data($p['criado'])];
}
foreach (array_keys(SIDES) as $lado) { // e as publicações: poucos publicam muito, muitos publicam pouco
    $doLado = array_values(array_filter(array_keys($pessoas), fn($i) => $pessoas[$i]['lado'] === $lado));
    $ativos = array_slice($doLado, 0, 0);
    foreach ($doLado as $i) {
        $n = $pesado([0 => 52, 1 => 22, 2 => 11, 3 => 6, 5 => 4, 8 => 3, 14 => 2]);
        for ($k = 0; $k < $n; $k++) {
            $linhasPosts[] = [$lado, $pessoas[$i]['item'], $pessoas[$i]['uid'], $humanizar($um($posts[$lado]), $lado), 0, $data($momento($pessoas[$i]['criado'], $agora))];
        }
    }
}
$lotes('INSERT INTO posts (lado, item_id, usuario_id, texto, is_frase_compra, criado_em)', $linhasPosts);
$todosPosts = $pdo->query("SELECT p.id, p.lado, p.item_id, p.is_frase_compra, UNIX_TIMESTAMP(p.criado_em) AS t
                           FROM posts p WHERE p.item_id IN ($itensSeed)")->fetchAll();
$pessoaDoItem = [];
foreach ($pessoas as $i => $p) {
    $pessoaDoItem[$p['item']] = $i;
}
$passo(count($todosPosts) . ' posts no mural');

// ---------- 6. curtidas: uns bombam, a maioria tem poucas ----------
$porLado = [];
foreach ($pessoas as $p) {
    $porLado[$p['lado']][] = $p['id'];
}
$linhasCurtidas = [];
$popularidade = [];
foreach ($todosPosts as $post) {
    $base = $post['is_frase_compra'] ? 4 : 12;
    $pop = (int) round($base * exp(mt_rand(0, 1000) / 1000 * 2.6)); // cauda longa
    $popularidade[$post['id']] = $pop;
    $doLado = $porLado[$post['lado']];
    $doOutro = $porLado[side($post['lado'])['other']];
    $quem = [];
    for ($k = 0; $k < $pop; $k++) { // curte mais quem é do mesmo pote; às vezes alguém do outro
        $quem[$chance(88) ? $um($doLado) : $um($doOutro)] = true;
    }
    foreach (array_keys($quem) as $u) {
        $linhasCurtidas[] = [(int) $post['id'], $u, $data($momento((int) $post['t'], $agora))];
    }
}
$lotes('INSERT IGNORE INTO curtidas (post_id, usuario_id, criado_em)', $linhasCurtidas, 800);
$passo(count($linhasCurtidas) . ' curtidas');

// ---------- 7. comentários: os dois potes se provocam, se apoiam e se xingam (sem passar do ponto) ----------
$falas = [
    'direita' => [
        'apoio' => ['Falou tudo!', 'Assino embaixo.', 'Isso aí, conservado de raiz!', 'Mandei no grupo da família e o tio aplaudiu.', 'Tradição é isso.',
            'Perfeito, irmão.', 'Melhor post da semana.', 'Deus abençoe esse pote.', 'Concordo 100%.', 'Exatamente o que eu penso.', 'Tamo junto, patriota!',
            'Isso mesmo! Tem que falar mesmo.', 'Aqui é Brasil!', 'Me representa demais.', 'Amém!', 'Verdade pura.', 'Kkkkk perfeito', 'Salvei pra mandar pro meu cunhado.',
            'Boa! Bem-vindo ao pote.', 'Seja bem-vinda, vizinha de pote!', 'Top demais.', 'Falou pouco mas falou bonito.', 'Esse é dos meus.', 'Sem mais.'],
        'provoca' => ['Arde, mas não convence.', 'Muita pimenta e pouca proposta.', 'Petralhada chorando no próprio pote kkkk', 'Vem pro pote de azeitona que é mais tranquilo.',
            'Tempero demais estraga o prato.', 'Quem paga essa pimenta toda? Nós, os pagadores de imposto.', 'Canhotada em pânico com o mapa verde',
            'Muito texto pra quem nunca trabalhou.', 'Vai estudar economia antes de falar isso.', 'Isso é discurso de DCE.', 'Coitado, acredita mesmo nisso.',
            'O pote de vocês arde de inveja.', 'Fala isso quando pagar um boleto sozinho.', 'Lindo discurso, agora mostra a conta.', 'Quanto custou essa pimenta? Foi no cartão do governo?',
            'Chora mais que o mapa tá ficando verde.', 'Mortadela detectada.', 'Tá precisando de um choque de realidade.', 'Engraçado que todo comunista tem iPhone.'],
        'grosso' => ['Vai trabalhar, vagabundo.', 'Que comentário burro, meu Deus.', 'Você é muito sem noção.', 'Nem vou perder meu tempo com você.', 'Gado vermelho falando besteira de novo.',
            'Cala a boca, mortadela.', 'Isso é a coisa mais idiota que li hoje.', 'Volta pro seu pote e fica quietinho.', 'Some daqui, militante de sofá.', 'Que preguiça de gente como você.'],
        'educado' => ['Respeito sua opinião, mas discordo.', 'Entendo seu ponto, mas vejo diferente.', 'Discordo, mas gosto do debate. Boa sorte aí no seu pote.',
            'Sem briga: cada um no seu pote e o Brasil no coração.', 'Boa noite, vizinho do outro pote. Hoje não vou discutir kkkk', 'Pelo menos a gente concorda que o site é bom.',
            'Tenho amigos pimenta e a gente se dá bem. Só não fala de política no churrasco.', 'Argumento justo, mas a conta não fecha.'],
    ],
    'esquerda' => [
        'apoio' => ['Resiste!', 'É isso, companheira!', 'Arde de verdade.', 'Mandei pro grupo do coletivo.', 'Perfeito, sem mais.', 'A luta continua!', 'Que texto! Arrepiei.',
            'Coxinha nenhum passa daqui.', 'Isso é tempero de verdade.', 'Tô contigo!', 'Falou tudo, parceiro.', 'Me representa.', 'Chorei aqui.', 'Bem-vinda ao pote! 🌶️',
            'Bem-vindo, companheiro!', 'Ninguém solta a mão de ninguém.', 'Kkkkkk morri', 'Salvei pra mandar pro tio.', 'Arrasou!', 'Tamo junto sempre.', 'Que orgulho desse pote.',
            'Isso aí, Nordeste resiste!', 'Perfeito demais.', 'Boa!'],
        'provoca' => ['Conservado demais, faltou tempero.', 'Tá precisando de uma pimentinha nesse pote.', 'Tio do pavê detectado', 'Conservar o quê, exatamente? O atraso?',
            'Azeitona não arde, né?', 'Lá vem mais um áudio de 12 minutos.', 'Esse post tem cheiro de grupo do zap.', 'Coxinha raiz, hein?', 'Salmoura demais dá pressão alta, cuidado.',
            'Fala isso de dentro da Hilux financiada?', 'Patriota que nunca leu a Constituição.', 'Isso aí é fake news, amigo.', 'Vai ver o mapa e chora.',
            'O importante é que você acredita.', 'Pesquisa no Google antes, custa nada.', 'Minion detectado.', 'Tá com medo da pimenta?', 'Que discurso de 1964, hein.'],
        'grosso' => ['Que comentário ridículo.', 'Você é muito sem noção, sério.', 'Vai ler um livro, pelo amor de Deus.', 'Gado demais pra um pote só.', 'Nem perco meu tempo com bolsominion.',
            'Cala a boca, coxinha.', 'Que coisa mais burra de se dizer.', 'Volta pro zap, tiozão.', 'Some daqui com esse discurso.', 'Preguiça infinita de gente assim.'],
        'educado' => ['Respeito, mas discordo totalmente.', 'Entendo o que você quis dizer, mas não concordo.', 'Discordo, mas foi um bom ponto.',
            'Sem briga, gente. Cada um no seu pote kkkk', 'Boa noite, vizinho azeitona. Hoje tô sem energia pra debate.', 'Pelo menos o site é bom, isso a gente concorda.',
            'Meu pai é azeitona e a gente se ama. Só não fala de política no almoço.', 'Justo, mas acho que falta olhar pro outro lado.'],
    ],
];
$respostas = [
    'direita' => ['Exatamente o que eu ia dizer.', 'Não foi isso que ele falou.', 'Olha quem apareceu kkkk', 'Discordo de você.', 'Boa resposta!', 'Isso mesmo, mandou bem.',
        'Você nem leu o post né?', 'Kkkkk calou o comunista', 'Nossa, que exagero.', 'Tá certo, mas não precisava ofender.', 'Falou pouco e falou bonito.', 'Aí complicou.'],
    'esquerda' => ['Exatamente isso!', 'Não foi o que ela disse.', 'Olha o coxinha de novo kkkk', 'Discordo de você.', 'Boa resposta!', 'Isso, mandou bem.',
        'Você nem leu o post né?', 'Kkkkk calou o tiozão', 'Menos, gente.', 'Concordo, mas sem ofender.', 'Falou tudo.', 'Aí pesou.'],
];
$tomOutro = ['provoca' => 46, 'grosso' => 16, 'educado' => 24, 'apoio' => 14];   // quem vem do outro pote
$tomMesmo = ['apoio' => 82, 'educado' => 10, 'provoca' => 8];                   // quem é do mesmo pote
$linhasComent = [];
$comentPosts = []; // [post_id] = [[pessoa, t]] para as respostas depois
foreach ($todosPosts as $post) {
    $media = ($post['is_frase_compra'] ? 0.35 : 2.2) * (0.5 + $popularidade[$post['id']] / 40);
    $n = (int) floor($media * pow(mt_rand(1, 1000) / 1000, 0.9) * 2);
    if ($n === 0) {
        continue;
    }
    $dono = $pessoaDoItem[$post['item_id']];
    $t = (int) $post['t'];
    for ($k = 0; $k < $n; $k++) {
        $outro = $chance(52);
        $ladoAutor = $outro ? side($post['lado'])['other'] : $post['lado'];
        do {
            $i = mt_rand(0, count($pessoas) - 1);
        } while ($pessoas[$i]['lado'] !== $ladoAutor || $i === $dono);
        $tom = $pesado($outro ? $tomOutro : $tomMesmo);
        $texto = $um($falas[$ladoAutor][$tom]);
        if ($chance(14)) { // chama o autor do post pelo nome
            $texto = explode(' ', $pessoas[$dono]['nome'])[0] . ', ' . mb_strtolower(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
        }
        $t = $momento($t, min($agora, $t + mt_rand(60, 3 * 86400))); // a conversa anda no tempo
        $linhasComent[] = [(int) $post['id'], $pessoas[$i]['item'], $pessoas[$i]['uid'], $humanizar($texto, $ladoAutor), $chance(1) ? 'removido' : 'publicado', $data($t)];
        $comentPosts[$post['id']][] = [$i, $t];
    }
}
$lotes('INSERT INTO comentarios (post_id, item_id, usuario_id, texto, status, criado_em)', $linhasComent, 500);
// respostas citando um comentário anterior do mesmo post
$doPost = [];
foreach ($pdo->query("SELECT c.id, c.post_id, c.item_id, UNIX_TIMESTAMP(c.criado_em) AS t FROM comentarios c WHERE c.item_id IN ($itensSeed) AND c.status = 'publicado'") as $c) {
    $doPost[$c['post_id']][] = $c;
}
$linhasResp = [];
foreach ($doPost as $postId => $lista) {
    foreach ($lista as $c) {
        if (!$chance(20)) {
            continue;
        }
        $citado = $pessoaDoItem[$c['item_id']] ?? null;
        do {
            $i = mt_rand(0, count($pessoas) - 1);
        } while ($i === $citado);
        $lado = $pessoas[$i]['lado'];
        $linhasResp[] = [(int) $postId, $pessoas[$i]['item'], $pessoas[$i]['uid'], (int) $c['id'], $humanizar($um($respostas[$lado]), $lado), 'publicado',
            $data($momento((int) $c['t'], min($agora, (int) $c['t'] + 2 * 86400)))];
    }
}
if ($linhasResp) {
    $lotes('INSERT INTO comentarios (post_id, item_id, usuario_id, cita_id, texto, status, criado_em)', $linhasResp, 500);
}
$passo((count($linhasComent) + count($linhasResp)) . ' comentários (' . count($linhasResp) . ' respostas citando)');

// ---------- 8. apoio partidário: 1 a 3 partidos do seu lado ----------
$pesos = [
    'PT' => 30, 'PSOL' => 18, 'PSB' => 9, 'PDT' => 8, 'PCdoB' => 8, 'REDE' => 5, 'PV' => 5, 'PCB' => 3, 'UP' => 2, 'PSTU' => 2,
    'PL' => 32, 'MISSÃO' => 16, 'NOVO' => 18, 'REPUBLICANOS' => 10, 'PP' => 9, 'UNIÃO' => 8, 'PSD' => 6, 'MDB' => 6, 'PODEMOS' => 5, 'PSDB' => 4, 'PRD' => 2,
];
try {
    $linhasApoio = [];
    foreach (array_keys(SIDES) as $lado) {
        $st = $pdo->prepare('SELECT sigla FROM partidos WHERE lado = ? AND ativo = 1');
        $st->execute([$lado]);
        $doLado = array_intersect_key($pesos, array_flip($st->fetchAll(PDO::FETCH_COLUMN)));
        if (!$doLado) {
            continue;
        }
        foreach ($pessoas as $p) {
            if ($p['lado'] !== $lado || $chance(35)) { // nem todo mundo escolhe partido
                continue;
            }
            $escolhas = [];
            $quantos = min($pesado([1 => 50, 2 => 32, 3 => 18]), count($doLado));
            while (count($escolhas) < $quantos) {
                $escolhas[$pesado($doLado)] = true;
            }
            foreach (array_keys($escolhas) as $sigla) {
                $linhasApoio[] = [$p['id'], $sigla, $data($momento($p['criado'], $agora))];
            }
        }
    }
    $lotes('INSERT INTO apoios_partido (usuario_id, sigla, criado_em)', $linhasApoio);
    $passo(count($linhasApoio) . ' apoios partidários');
} catch (PDOException $e) {
    echo "(apoio partidário pulado: {$e->getMessage()})\n";
}

// ---------- 9. uma enquete de duelo no ar, com votos ----------
try {
    $pdo->exec("UPDATE enquetes SET status = 'encerrada', encerrada_em = NOW() WHERE status = 'ativa'");
    $pdo->prepare("INSERT INTO enquetes (pergunta, descricao, lado, status, resultado, publicada_em, criado_por) VALUES (?, ?, NULL, 'ativa', 'sempre', NOW() - INTERVAL 5 DAY, ?)")
        ->execute(['Qual o maior problema do Brasil hoje?', 'Duelo entre os potes: cada voto conta para o seu time.', $pessoas[0]['id']]);
    $enq = (int) $pdo->lastInsertId();
    $opcoes = ['Impostos altos' => [34, 9], 'Saúde pública' => [8, 30], 'Segurança' => [30, 14], 'Educação' => [10, 27], 'Corrupção' => [18, 20]];
    $opIds = [];
    $ordem = 0;
    foreach ($opcoes as $texto => $w) {
        $pdo->prepare('INSERT INTO enquete_opcoes (enquete_id, texto, ordem) VALUES (?, ?, ?)')->execute([$enq, $texto, ++$ordem]);
        $opIds[$texto] = (int) $pdo->lastInsertId();
    }
    $linhasVoto = [];
    foreach ($pessoas as $p) {
        if (!$chance(58)) {
            continue;
        }
        $col = $p['lado'] === 'direita' ? 0 : 1;
        $escolha = $pesado(array_map(fn($w) => $w[$col], $opcoes));
        $linhasVoto[] = [$enq, $opIds[$escolha], $p['lado'], $p['id'], hash('sha256', 'u|' . $p['id']), $data($momento($agora - 5 * 86400, $agora))];
    }
    $lotes('INSERT INTO enquete_votos (enquete_id, opcao_id, lado, usuario_id, votante, criado_em)', $linhasVoto);
    $passo(count($linhasVoto) . ' votos na enquete');
} catch (PDOException $e) {
    echo "(enquete pulada: {$e->getMessage()})\n";
}

// ---------- 10. contadores ----------
$pdo->exec("UPDATE posts p SET curtidas_count = (SELECT COUNT(*) FROM curtidas c WHERE c.post_id = p.id),
                               comentarios_count = (SELECT COUNT(*) FROM comentarios c WHERE c.post_id = p.id AND c.status = 'publicado')");
$passo('Contadores atualizados. Pronto');
