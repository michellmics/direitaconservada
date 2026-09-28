<?php
// Sair: POST com CSRF (um link GET permitiria deslogar alguém por um <img> de outro site)
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/auth.php';

$volta = destino_seguro($_POST['r'] ?? 'index.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_publico_ok($_POST['csrf'] ?? null)) {
    encerrar_sessao();
}
header('Location: ' . $volta);
