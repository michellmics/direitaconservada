<?php
// Atualizar o sistema pelo painel (/cozinha/atualizar): baixa a branch do GitHub e aplica no servidor.
// Não precisa de git nem de terminal no servidor (funciona em hospedagem cPanel): usa a API do GitHub (ZIP) + ZipArchive.
//
//   .env:  DEPLOY_REPO=michellmics/direitaconservada   DEPLOY_BRANCH=main
//          DEPLOY_TOKEN=github_pat_…   (repositório privado: token "fine-grained" só com Contents: Read-only)
//
// O que faz: baixa o ZIP do último commit → confere → copia os arquivos por cima → apaga os que saíram do repositório
// (só os que vieram de um deploy anterior) → roda as migrations pendentes → limpa o cache do PHP → registra no log.
// Nunca mexe em: .env, uploads/, .git/ e no próprio registro do deploy. A vendor/ está no git e vem junto.
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/migracoes.php';

const DEPLOY_ESTADO = 'data/deploy.json';   // último deploy: commit, data e lista de arquivos (data/ não é pública)
const DEPLOY_PROTEGIDOS = '#^(\.env|\.git/|\.github/|uploads/|data/deploy\.(json|lock)$|error_log$)#';

function deploy_raiz(): string
{
    return dirname(__DIR__);
}

function deploy_config(): array
{
    return [
        'repo'   => (string) env('DEPLOY_REPO', 'michellmics/direitaconservada'),
        'branch' => (string) env('DEPLOY_BRANCH', 'main'),
        'token'  => (string) env('DEPLOY_TOKEN', ''),
    ];
}

/** GET na API do GitHub. Retorna [status, corpo]. $arquivo = salva o corpo nele (download do ZIP). */
function deploy_http(string $url, ?string $arquivo = null): array
{
    $c = deploy_config();
    $h = ['User-Agent: potepolitico-deploy', 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
    if ($c['token'] !== '') {
        $h[] = 'Authorization: Bearer ' . $c['token'];
    }
    $ch = curl_init($url);
    $fp = $arquivo ? fopen($arquivo, 'wb') : null;
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $h,
        CURLOPT_FOLLOWLOCATION => true, // o ZIP redireciona para codeload.github.com
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 15,
    ] + ($fp ? [CURLOPT_FILE => $fp] : [CURLOPT_RETURNTRANSFER => true]));
    $corpo = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $erro = curl_error($ch);
    curl_close($ch);
    if ($fp) {
        fclose($fp);
    }
    if ($corpo === false) {
        throw new RuntimeException('Não conectou no GitHub: ' . $erro);
    }
    return [$status, $fp ? '' : (string) $corpo];
}

/** Último commit da branch no GitHub: ['sha', 'mensagem', 'autor', 'data']. */
function deploy_commit_remoto(): array
{
    $c = deploy_config();
    [$status, $corpo] = deploy_http("https://api.github.com/repos/{$c['repo']}/commits/" . rawurlencode($c['branch']));
    $j = json_decode($corpo, true);
    if ($status !== 200 || empty($j['sha'])) {
        $dica = in_array($status, [401, 403, 404], true) ? ' Repositório privado? Confira DEPLOY_REPO e DEPLOY_TOKEN no .env.' : '';
        throw new RuntimeException("GitHub respondeu $status: " . ($j['message'] ?? 'sem detalhes') . '.' . $dica);
    }
    return [
        'sha'      => $j['sha'],
        'mensagem' => strtok((string) ($j['commit']['message'] ?? ''), "\n"),
        'autor'    => $j['commit']['author']['name'] ?? '',
        'data'     => $j['commit']['author']['date'] ?? '',
    ];
}

/** O último deploy feito por aqui (ou null). */
function deploy_estado(): ?array
{
    $f = deploy_raiz() . '/' . DEPLOY_ESTADO;
    return is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
}

function deploy_apagar_pasta(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

/**
 * Faz o deploy. Retorna ['ok' => bool, 'linhas' => [passo a passo], 'commit' => sha].
 * Só um por vez (trava em arquivo). Tudo vai para o log (categoria deploy).
 */
function deploy_executar(): array
{
    $raiz = deploy_raiz();
    $linhas = [];
    $diz = function (string $t) use (&$linhas) { $linhas[] = $t; };
    @set_time_limit(600);
    ignore_user_abort(true); // fechar a aba não interrompe no meio

    // na máquina de desenvolvimento, sobrescreveria o que ainda não foi para o git
    if (env('APP_ENV', 'local') === 'local' && env('DEPLOY_PERMITIR_LOCAL') !== '1') {
        return ['ok' => false, 'linhas' => ['Bloqueado: APP_ENV=local. A atualização pelo painel é para o servidor (APP_ENV=production no .env de lá).'], 'commit' => null];
    }
    $trava = fopen("$raiz/data/deploy.lock", 'c');
    if (!$trava || !flock($trava, LOCK_EX | LOCK_NB)) {
        return ['ok' => false, 'linhas' => ['Já tem uma atualização rodando. Espere ela terminar.'], 'commit' => null];
    }
    $tmp = sys_get_temp_dir() . '/potepolitico-deploy-' . bin2hex(random_bytes(6));
    $zip = "$tmp.zip";
    $commit = null;
    try {
        if (!class_exists('ZipArchive') || !function_exists('curl_init')) {
            throw new RuntimeException('O PHP do servidor precisa das extensões zip e curl (ative no cPanel → Select PHP Version).');
        }
        $c = deploy_config();
        $remoto = deploy_commit_remoto();
        $commit = $remoto['sha'];
        $diz("Commit {$c['branch']}: " . substr($commit, 0, 7) . " — {$remoto['mensagem']} ({$remoto['autor']})");
        logar('info', 'deploy', 'deploy_inicio', 'Atualização iniciada: ' . substr($commit, 0, 7), $remoto, null, true);

        // 1. baixa e abre o ZIP
        [$status] = deploy_http("https://api.github.com/repos/{$c['repo']}/zipball/$commit", $zip);
        if ($status !== 200 || filesize($zip) < 1000) {
            throw new RuntimeException("Não deu para baixar o código (GitHub respondeu $status).");
        }
        $diz('Baixado: ' . round(filesize($zip) / 1024) . ' KB');
        $z = new ZipArchive();
        if ($z->open($zip) !== true || !$z->extractTo($tmp)) {
            throw new RuntimeException('O arquivo baixado não abriu (ZIP corrompido?).');
        }
        $z->close();
        $pastas = glob("$tmp/*", GLOB_ONLYDIR); // o GitHub põe tudo dentro de "dono-repo-sha/"
        $origem = $pastas[0] ?? '';
        if (!is_file("$origem/index.php") || !is_file("$origem/includes/config.php")) {
            throw new RuntimeException('O código baixado não parece ser deste site (faltam index.php/config.php). Nada foi alterado.');
        }

        // 2. lista os arquivos novos (sem os protegidos)
        $novos = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origem, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($origem) + 1));
            if (!preg_match(DEPLOY_PROTEGIDOS, $rel)) {
                $novos[] = $rel;
            }
        }
        sort($novos);
        $lockAntes = @md5_file("$raiz/composer.lock");

        // 3. copia por cima (cada arquivo troca de uma vez: grava ao lado e renomeia)
        $copiados = 0;
        foreach ($novos as $rel) {
            $destino = "$raiz/$rel";
            if (!is_dir(dirname($destino)) && !mkdir(dirname($destino), 0755, true) && !is_dir(dirname($destino))) {
                throw new RuntimeException("Não deu para criar a pasta de $rel (permissão?).");
            }
            if (is_file($destino) && md5_file($destino) === md5_file("$origem/$rel")) {
                continue; // igual: não mexe
            }
            if (!copy("$origem/$rel", "$destino.deploy-novo") || !rename("$destino.deploy-novo", $destino)) {
                @unlink("$destino.deploy-novo");
                throw new RuntimeException("Não deu para gravar $rel (permissão?). Parte dos arquivos já foi atualizada: rode de novo.");
            }
            $copiados++;
        }
        $diz("Arquivos: " . count($novos) . " no repositório, $copiados atualizados");

        // 4. apaga o que saiu do repositório (só o que veio de um deploy anterior; nunca os protegidos)
        $anterior = deploy_estado();
        $apagados = 0;
        foreach (array_diff($anterior['arquivos'] ?? [], $novos) as $rel) {
            if (!preg_match(DEPLOY_PROTEGIDOS, $rel) && !str_contains($rel, '..') && is_file("$raiz/$rel")) {
                @unlink("$raiz/$rel") && $apagados++;
            }
        }
        if ($apagados) {
            $diz("Removidos $apagados arquivos que saíram do repositório");
        }
        file_put_contents("$raiz/" . DEPLOY_ESTADO, json_encode([
            'commit' => $commit, 'mensagem' => $remoto['mensagem'], 'autor' => $remoto['autor'],
            'data' => date('c'), 'arquivos' => $novos,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        // 5. migrations
        $m = migracoes_aplicar(db());
        $diz($m['aplicadas'] ? 'Migrations aplicadas: ' . implode(', ', $m['aplicadas']) : 'Migrations: nada pendente');
        if ($m['erro']) {
            throw new RuntimeException('Migration com erro: ' . $m['erro']);
        }

        // 6. cache do PHP e dependências
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if ($lockAntes !== @md5_file("$raiz/composer.lock")) {
            $diz('Dependências (vendor/) atualizadas junto: o composer.lock mudou.');
        }
        $diz('✓ Sistema atualizado.');
        logar('info', 'deploy', 'deploy_ok', 'Atualizado para ' . substr($commit, 0, 7) . ": $copiados arquivos, $apagados removidos", ['linhas' => $linhas], null, true);
        return ['ok' => true, 'linhas' => $linhas, 'commit' => $commit];
    } catch (Throwable $e) {
        $diz('✗ ' . $e->getMessage());
        logar('erro', 'deploy', 'deploy_falha', $e->getMessage(), ['linhas' => $linhas, 'commit' => $commit], null, true, 500);
        return ['ok' => false, 'linhas' => $linhas, 'commit' => $commit];
    } finally {
        @unlink($zip);
        deploy_apagar_pasta($tmp);
        flock($trava, LOCK_UN);
        fclose($trava);
    }
}
