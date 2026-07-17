<?php
/** Отдаёт товары каталога в формате JSON для catalog.js / product.js.
 *  GET /api/products.php             — все опубликованные товары
 *  GET /api/products.php?slug=sofa-01 — один товар по слагу */
require_once __DIR__ . '/db.php';
$pdo = db();

// Проверка, что таблица существует (на случай, если install ещё не запущен)
try {
    $pdo->query('SELECT 1 FROM products LIMIT 1');
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'not_installed']);
}

$slug = $_GET['slug'] ?? '';
$single = '';
if ($slug !== '') {
    $st = $pdo->prepare('SELECT * FROM products WHERE slug = ? AND is_published = 1 LIMIT 1');
    $st->execute([$slug]);
    $single = $st->fetch();
}

function map_product(array $p): array {
    return [
        'id'          => $p['slug'],
        'category'    => $p['category'],
        'subcategory' => $p['subcategory'],
        'name'        => $p['name'],
        'art'         => $p['art'],
        'desc'        => $p['descr'],
        'material'    => $p['material'],
        'sizes'       => $p['sizes'],
        'color'       => $p['color'],
        'destination' => $p['destination'],
        'packing'     => $p['packing'],
        'custom'      => (bool)$p['custom'],
        'objects'     => array_values(array_filter(explode(',', $p['objects']))),
        'img'         => $p['image'],
    ];
}

if ($slug !== '') {
    if (!$single) json_out(null);
    json_out(map_product($single));
}

$st = $pdo->query('SELECT * FROM products WHERE is_published = 1 ORDER BY sort_order, id');
$out = [];
foreach ($st as $p) $out[] = map_product($p);
json_out($out);
