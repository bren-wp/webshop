<?php
/**
 * API: košarica (AJAX). Akcije: add, update, remove, summary.
 */
require_once __DIR__ . '/../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Samo POST.'], 405);
}
csrf_check();

$action = $_POST['action'] ?? 'summary';
$productId = (int) ($_POST['product_id'] ?? 0);
$qty = (int) ($_POST['qty'] ?? 1);

switch ($action) {
    case 'add':
        $exists = Database::instance()->fetchColumn(
            'SELECT id FROM products WHERE id = :id AND is_visible = 1 AND is_orphaned = 0',
            [':id' => $productId]
        );
        if (!$exists) json_out(['ok' => false, 'error' => 'Proizvod nije dostupan.'], 404);
        Cart::add($productId, max(1, $qty));
        break;
    case 'update':
        Cart::update($productId, $qty);
        break;
    case 'remove':
        Cart::remove($productId);
        break;
    case 'summary':
        break;
    default:
        json_out(['ok' => false, 'error' => 'Nepoznata akcija.'], 400);
}

$items = Cart::detailed();
$out = [];
foreach ($items as $it) {
    $out[] = [
        'id'        => (int) $it['id'],
        'name'      => $it['name'],
        'slug'      => $it['slug'],
        'qty'       => (int) $it['qty'],
        'price'     => (float) $it['price'],
        'lineTotal' => (float) $it['line_total'],
        'image'     => $it['image'] ? upload_url('products/' . $it['image']) : null,
        'url'       => url('p/' . $it['slug']),
    ];
}
json_out([
    'ok'       => true,
    'count'    => Cart::count(),
    'subtotal' => Cart::subtotal($items),
    'subtotalFmt' => fmt_price(Cart::subtotal($items)),
    'items'    => $out,
    'problems' => Cart::stockProblems($items),
]);
