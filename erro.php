<?php
// Endereço que não existe (e pastas internas bloqueadas): o .htaccess e o router.php mandam para cá.
// ?codigo=403 mostra a de acesso proibido; o padrão é a 404. Ver includes/erro.php.
require __DIR__ . '/includes/erro.php';
pagina_erro(($_GET['codigo'] ?? '') === '403' ? 403 : 404);
