<?php
// Popula o banco com gente de mentira, para o site parecer movimentado:
// cadastros, pimentas e azeitonas, a frase de cada um (post), publicações, curtidas e 100 comentários.
//
//   php database/seed.php
//
// Rodar de novo apaga só o que o seed criou (cadastros com e-mail @seed.local e tudo deles) e recria.
// Cadastros de verdade (e o que eles fizeram) não são tocados.
if (PHP_SAPI !== 'cli') {
    exit("Rode pelo terminal: php database/seed.php\n");
}
require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/data/mock.php';

const SEED_EMAIL = '@seed.local';
const SEED_COMENTARIOS = 100;

$pdo = db();
mt_srand(20260928); // sempre os mesmos dados

// ---------- 1. apaga o seed anterior ----------
$usuariosSeed = "SELECT id FROM usuarios WHERE email LIKE '%" . SEED_EMAIL . "'";
$itensSeed = "SELECT id FROM itens WHERE usuario_id IN ($usuariosSeed)";
$postsSeed = "SELECT id FROM posts WHERE item_id IN ($itensSeed)";
$pdo->exec("DELETE FROM curtidas WHERE usuario_id IN ($usuariosSeed) OR post_id IN ($postsSeed)");
$pdo->exec("DELETE FROM comentarios WHERE item_id IN ($itensSeed) OR post_id IN ($postsSeed)");
$pdo->exec("DELETE FROM posts WHERE item_id IN ($itensSeed)");
$pdo->exec("DELETE FROM itens WHERE usuario_id IN ($usuariosSeed)");
$pdo->exec("DELETE FROM usuarios WHERE email LIKE '%" . SEED_EMAIL . "'");
echo "Seed anterior apagado.\n";

$tipos = [];
foreach ($pdo->query('SELECT id, lado, slug FROM item_tipos') as $t) {
    $tipos[$t['lado']][$t['slug']] = (int) $t['id'];
}
$agora = time();
$hoje = date('Y-m-d');
// data/hora aleatória no dia (nunca no futuro)
$momento = function (string $dia) use ($agora): string {
    $t = strtotime($dia) + mt_rand(7 * 3600, 23 * 3600);
    return date('Y-m-d H:i:s', min($t, $agora - mt_rand(60, 3600)));
};

// ---------- 2. cadastros e itens (as pessoas do gerador de data/mock.php) ----------
$insUsuario = $pdo->prepare('INSERT INTO usuarios (nome, email, email_verificado_em, criado_em) VALUES (?, ?, ?, ?)');
$insItem = $pdo->prepare('INSERT INTO itens (lado, numero, usuario_id, item_tipo_id, nome, cidade, uf, frase, foto_path, selo_tipo, selo_valor, desde, valido_ate, status, criado_em)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insPost = $pdo->prepare('INSERT INTO posts (lado, item_id, texto, video_provider, video_id, video_vertical, is_frase_compra, criado_em)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$itens = [];     // [lado][] = ['db' => id, 'numero', 'usuario', 'desde']
$usuarios = [];  // ids de todos os cadastros do seed
$posts = [];     // [] = ['db' => id, 'lado', 'item' => db id do autor, 'criado']
foreach (array_keys(SIDES) as $lado) {
    $base = (int) $pdo->query("SELECT COALESCE(MAX(numero), 0) FROM itens WHERE lado = " . $pdo->quote($lado))->fetchColumn();
    $donos = [];
    foreach (mock_items_gerados($lado) as $o) {
        if (!isset($donos[$o['dono']])) {
            $insUsuario->execute([$o['nome'], "pessoa-$lado-{$o['dono']}" . SEED_EMAIL, $o['desde'] . ' 12:00:00', $o['desde'] . ' 12:00:00']);
            $donos[$o['dono']] = (int) $pdo->lastInsertId();
            $usuarios[] = $donos[$o['dono']];
        }
        $criado = $momento($o['desde']);
        $insItem->execute([$lado, $base + $o['id'], $donos[$o['dono']], $tipos[$lado][$o['tipo']], $o['nome'], $o['cidade'], $o['uf'], $o['frase'],
            $o['foto'], $o['selo'] ? 'preset' : null, $o['selo'], $o['desde'], $o['valido_ate'], 'ativo', $criado]);
        $itemId = (int) $pdo->lastInsertId();
        $itens[$lado][] = ['db' => $itemId, 'numero' => $base + $o['id'], 'usuario' => $donos[$o['dono']], 'desde' => $o['desde']];
        // a frase da compra é o 1º post de cada item
        $v = $o['video'] ?? null;
        $insPost->execute([$lado, $itemId, $o['frase'], $v['provider'] ?? null, $v['id'] ?? null, (int) ($v['vertical'] ?? 0), 1, $criado]);
        $posts[] = ['db' => (int) $pdo->lastInsertId(), 'lado' => $lado, 'item' => $itemId, 'criado' => $criado, 'frase' => true];
    }
    $usuariosDoLado[$lado] = array_values($donos);
    $pdo->prepare('UPDATE potes SET proximo_numero = (SELECT COALESCE(MAX(numero), 0) + 1 FROM itens WHERE lado = ?) WHERE slug = ?')->execute([$lado, $lado]);
    echo "$lado: " . count($donos) . ' cadastros, ' . count($itens[$lado]) . " itens.\n";
}

// ---------- 2b. apoio partidário: cada cadastro apoia 1 a 3 partidos DO SEU LADO (pimenta = esquerda, azeitona = direita) ----------
// Pesos para o ranking parecer real (os maiores partidos de cada lado na frente). Precisa da migration 009.
$pesos = [
    'PT' => 30, 'PSOL' => 18, 'PSB' => 9, 'PDT' => 8, 'PCdoB' => 8, 'REDE' => 5, 'PV' => 5, 'PCB' => 3, 'UP' => 2, 'PSTU' => 2,
    'PL' => 32, 'MISSÃO' => 16, 'NOVO' => 18, 'REPUBLICANOS' => 10, 'PP' => 9, 'UNIÃO' => 8, 'PSD' => 6, 'MDB' => 6, 'PODEMOS' => 5, 'PSDB' => 4, 'PRD' => 2,
];
try {
    $insApoio = $pdo->prepare('INSERT INTO apoios_partido (usuario_id, sigla, criado_em) VALUES (?, ?, NOW() - INTERVAL ? MINUTE)');
    $totalApoios = 0;
    foreach ($usuariosDoLado as $lado => $ids) {
        $st = $pdo->prepare('SELECT sigla FROM partidos WHERE lado = ? AND ativo = 1');
        $st->execute([$lado]);
        $doLado = array_intersect_key($pesos, array_flip($st->fetchAll(PDO::FETCH_COLUMN)));
        foreach ($ids as $u) {
            $escolhas = [];
            $quantos = [1, 1, 2, 2, 2, 3][mt_rand(0, 5)];
            while (count($escolhas) < min($quantos, count($doLado))) {
                $sorteio = mt_rand(1, array_sum($doLado));
                foreach ($doLado as $sigla => $peso) {
                    if (($sorteio -= $peso) <= 0) {
                        $escolhas[$sigla] = true;
                        break;
                    }
                }
            }
            foreach (array_keys($escolhas) as $sigla) {
                $insApoio->execute([$u, $sigla, mt_rand(1, 60 * 24 * 30)]);
                $totalApoios++;
            }
        }
    }
    echo "$totalApoios apoios partidários.\n";
} catch (PDOException $e) {
    echo "(apoio partidário pulado: rode php database/migrate.php para criar as tabelas)\n";
}

// ---------- 3. publicações no mural (além da frase da compra) ----------
$opinioes = [
    'direita' => [
        'Churrasco de domingo sem discussão política é churrasco de canhoto.',
        'Meu avô já dizia: o que é bom a gente conserva.',
        'Hoje briguei no grupo da família e ganhei. Azeitona 1 × 0 pimenta.',
        'Quem trabalha não tem tempo pra assembleia.',
        'Deus, pátria, família e uma boa empada de azeitona.',
        'Acordei cedo, trabalhei, paguei imposto. Rotina de conservado.',
        'O mapa tá ficando verde. Segura, petralhada!',
        'Comunista de iPhone reclamando do capitalismo pelo Wi-Fi do shopping.',
        'Minha opinião tem caroço: dura de engolir pra quem é do outro pote.',
        'Tradição não é moda. Moda é o que a canhotada inventa toda semana.',
        'Coloquei mais três azeitonas no pote. Estado conservado é estado protegido.',
        'Petralha fala em dividir o bolo, mas nunca trouxe a farinha.',
        'Salmoura forte, família unida, conta paga. Simples assim.',
        'Se reclamar do preço da gasolina desse likes, a pimentada tava rica.',
        'Aqui a gente não pede, a gente conserva.',
        'Vi um comunista do Leblon pedindo menos luxo. De dentro do SUV.',
        'Conservado desde que nasci. E não pretendo estragar.',
        'Não é teimosia, é princípio. Azeitona não muda de pote.',
        'Mandei esse site pro grupo da família. Metade entrou no pote, metade saiu do grupo.',
        'Enquanto a pimentada arde no Twitter, a gente trabalha.',
    ],
    'esquerda' => [
        'Tio do zap mandou áudio de 11 minutos. Ouvi em 2x e continuei discordando.',
        'Saúde e educação pública: o tempero que o país precisa.',
        'O coxinha reclama do imposto, mas adora estrada asfaltada.',
        'Hoje o mapa ficou mais vermelho. Arde, azeitona!',
        'Sobrevivi ao almoço com o tio do pavê e ainda defendi o SUS.',
        'Pimenta biquinho é porta de entrada. Depois vem a malagueta.',
        'Quem arde por justiça não esfria no inverno.',
        'Salário digno não é luxo, é tempero básico.',
        'Azeitoneiro diz que é apolítico e passa o dia no grupo "Patriotas Unidos 🇧🇷".',
        'Feira de domingo, pastel de queijo e debate com o vizinho coxinha. Programa completo.',
        'Resistir cansa. Mas conservar só azeitona dá sono.',
        'Coxinha acha que tempero é coisa de comunista.',
        'Coloquei mais uma pimenta no pote. O estado agradece.',
        'O tio do pavê descobriu esse site. Vai ter áudio.',
        'Direita conservada é aquela que conserva até o preconceito na geladeira.',
        'Carreata de caminhonete não enche o pote. Pimenta sim.',
        'Meu pote tem mais diversidade que a bancada inteira do outro lado.',
        'Ardência coletiva > salmoura individual.',
        'Hoje é dia de apimentar o grupo da família.',
        'O coxinha comentou no meu post. Tô me sentindo famosa.',
    ],
];
foreach ($opinioes as $lado => $textos) {
    foreach ($textos as $texto) {
        $autor = $itens[$lado][mt_rand(0, count($itens[$lado]) - 1)];
        $dia = date('Y-m-d', max(strtotime($autor['desde']), $agora - mt_rand(0, 25) * 86400));
        $criado = $momento($dia);
        $insPost->execute([$lado, $autor['db'], $texto, null, null, 0, 0, $criado]);
        $posts[] = ['db' => (int) $pdo->lastInsertId(), 'lado' => $lado, 'item' => $autor['db'], 'criado' => $criado, 'frase' => false];
    }
}
echo count($posts) . " posts.\n";

// ---------- 4. curtidas (cada uma de um cadastro diferente) ----------
$lote = [];
$totalCurtidas = 0;
foreach ($posts as $p) {
    $k = $p['frase'] ? mt_rand(0, 40) : mt_rand(8, min(90, count($usuarios)));
    $quem = (array) array_rand(array_flip($usuarios), max(1, $k));
    foreach (array_slice($quem, 0, $k) as $u) {
        $lote[] = sprintf('(%d, %d, %s)', $p['db'], $u, $pdo->quote(date('Y-m-d H:i:s', mt_rand(strtotime($p['criado']), $agora))));
        $totalCurtidas++;
    }
    if (count($lote) > 800) {
        $pdo->exec('INSERT IGNORE INTO curtidas (post_id, usuario_id, criado_em) VALUES ' . implode(',', $lote));
        $lote = [];
    }
}
if ($lote) {
    $pdo->exec('INSERT IGNORE INTO curtidas (post_id, usuario_id, criado_em) VALUES ' . implode(',', $lote));
}
$pdo->exec('UPDATE posts p SET curtidas_count = (SELECT COUNT(*) FROM curtidas c WHERE c.post_id = p.id)');
echo "$totalCurtidas curtidas.\n";

// ---------- 5. 100 comentários (os dois potes comentam; dois posts "pegando fogo") ----------
$falas = [
    'direita' => [
        'mesmo' => ['Falou tudo! 🫒', 'Assino embaixo, patriota.', 'Isso aí, conservado de raiz!', 'Mandei no grupo da família e o tio aplaudiu.',
            'Tradição é isso.', 'Perfeito. Salmoura neles!', 'Melhor post da semana.', 'Canhoto aqui só o do cheque 😂', 'Deus abençoe esse pote.', 'Concordo 100%.'],
        'outro' => ['Arde, mas não convence. 🫒', 'Muita pimenta e pouca proposta.', 'Petralhada chorando no próprio pote kkkk',
            'Vem pro pote de azeitona que é mais tranquilo.', 'Isso aí é conversa de comunista do Leblon.', 'Tempero demais estraga o prato.',
            'Quem paga essa pimenta toda? Nós, os pagadores de imposto.', 'Respeito, mas discordo. Educadamente conservado.',
            'Assembleia de novo? Vai trabalhar, companheiro.', 'Canhotada em pânico com o mapa verde 😂'],
    ],
    'esquerda' => [
        'mesmo' => ['Resiste! 🌶️', 'É isso, companheira!', 'Arde de verdade.', 'Mandei pro grupo do coletivo.', 'Perfeito, sem mais.',
            'A luta continua! ✊', 'Que texto! Arrepiei.', 'Coxinha nenhum passa daqui.', 'Isso é tempero de verdade.', 'Tô contigo!'],
        'outro' => ['Conservado demais, faltou tempero. 🌶️', 'Tá precisando de uma pimentinha nesse pote.', 'Tio do pavê detectado 😂',
            'Conservar o quê, exatamente? O atraso?', 'Azeitona não arde, né?', 'Lá vem mais um áudio de 12 minutos.',
            'Esse post tem cheiro de grupo do zap.', 'Respeito, mas discordo. Com ardência.', 'Coxinha raiz, hein?',
            'Salmoura demais dá pressão alta, cuidado.'],
    ],
];
$inicioMes = strtotime(date('Y-m-01'));
// os dois posts mais recentes (um de cada pote) ganham um debate maior, para ter "Ver mais comentários"
$alvos = [];
foreach (array_keys(SIDES) as $lado) {
    $doLado = array_values(array_filter($posts, fn($p) => $p['lado'] === $lado && !$p['frase']));
    usort($doLado, fn($a, $b) => strcmp($b['criado'], $a['criado']));
    $alvos = array_merge($alvos, array_fill(0, 22, $doLado[0]));
}
while (count($alvos) < SEED_COMENTARIOS) {
    $alvos[] = $posts[mt_rand(0, count($posts) - 1)];
}
$insComentario = $pdo->prepare('INSERT INTO comentarios (post_id, item_id, texto, status, criado_em) VALUES (?, ?, ?, ?, ?)');
$apagados = array_flip((array) array_rand(range(0, SEED_COMENTARIOS - 1), 2)); // dois aparecem como "comentário apagado"
foreach ($alvos as $n => $p) {
    $outroLado = mt_rand(1, 100) <= 55; // o debate é com o outro pote
    $ladoAutor = $outroLado ? side($p['lado'])['other'] : $p['lado'];
    do {
        $autor = $itens[$ladoAutor][mt_rand(0, count($itens[$ladoAutor]) - 1)];
    } while ($autor['db'] === $p['item']);
    $textos = $falas[$ladoAutor][$outroLado ? 'outro' : 'mesmo'];
    $de = max(strtotime($p['criado']), $inicioMes);
    $quando = date('Y-m-d H:i:s', mt_rand($de, max($de, $agora - 60)));
    $insComentario->execute([$p['db'], $autor['db'], $textos[mt_rand(0, count($textos) - 1)], isset($apagados[$n]) ? 'removido' : 'publicado', $quando]);
}
$pdo->exec("UPDATE posts p SET comentarios_count = (SELECT COUNT(*) FROM comentarios c WHERE c.post_id = p.id AND c.status = 'publicado')");
echo SEED_COMENTARIOS . " comentários (2 apagados).\n";
echo "Pronto. Abra o site: agora tudo vem do banco.\n";
