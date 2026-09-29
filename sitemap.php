<?php
// /sitemap.xml (o .htaccess e o router.php mandam para cá): as páginas que devem aparecer no Google.
// Só as públicas e fixas: início e os dois potes. Perfis, presentes, entrar e avisos ficam de fora (noindex).
require __DIR__ . '/includes/config.php';
header('Content-Type: application/xml; charset=utf-8');
$paginas = [url_base() => '1.0'];
foreach (array_keys(SIDES) as $slug) {
    $paginas[url_base() . $slug] = '0.9';
}
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n", '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($paginas as $loc => $prioridade) {
    echo '  <url><loc>', htmlspecialchars($loc, ENT_XML1), '</loc><changefreq>daily</changefreq><priority>', $prioridade, "</priority></url>\n";
}
echo "</urlset>\n";
