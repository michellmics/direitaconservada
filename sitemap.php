<?php
// /sitemap.xml (o .htaccess e o router.php mandam para cá): as páginas que devem aparecer no Google.
// Só as públicas e fixas: início e os dois potes. Perfis, presentes, entrar e avisos ficam de fora (noindex).
require __DIR__ . '/includes/config.php';
header('Content-Type: application/xml; charset=utf-8');
$paginas = [url_base() => '1.0'];
foreach (array_keys(SIDES) as $slug) {
    $paginas[url_base() . $slug] = '0.9';
}
// Tretódromo: a arena e os duelos que aconteceram (os 500 mais recentes; desafio recusado/expirado fica de fora ok)
$paginas[url_base() . 'tretodromo'] = '0.8';
try {
    foreach (db()->query("SELECT id FROM duelos WHERE status IN ('andamento', 'votacao', 'encerrado') ORDER BY id DESC LIMIT 500")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $paginas[url_base() . 'duelo?n=' . $id] = '0.6';
    }
} catch (Throwable $e) {
    // migration 026 ainda não rodou
}
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n", '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($paginas as $loc => $prioridade) {
    echo '  <url><loc>', htmlspecialchars($loc, ENT_XML1), '</loc><changefreq>daily</changefreq><priority>', $prioridade, "</priority></url>\n";
}
echo "</urlset>\n";
