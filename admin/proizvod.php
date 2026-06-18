<?php
/**
 * Uređivanje proizvoda — LOKALNA obogaćivanja (slike, opis, SEO, vidljivost).
 * Naziv, cijena, PDV, jedinica i zaliha dolaze iz đurđe i tu su read-only.
 */
require_once __DIR__ . '/templates/init.php';

$id = (int) ($_GET['id'] ?? 0);
$p = $db->fetch('SELECT * FROM products WHERE id = :id', [':id' => $id]);
if (!$p) { flash('error', 'Proizvod nije pronađen.'); redirect('admin/proizvodi.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        // Vidljivost se NE sprema ovdje — određuje je đurđa (Web trgovina → Artikli)
        $db->update('products', [
            'description'     => HtmlSanitizer::clean(trim((string) $_POST['description'])) ?: null,
            'seo_title'       => mb_substr(trim((string) $_POST['seo_title']), 0, 190) ?: null,
            'seo_description' => mb_substr(trim((string) $_POST['seo_description']), 0, 300) ?: null,
            'is_featured'     => !empty($_POST['is_featured']) ? 1 : 0,
        ], 'id = :id', [':id' => $id]);
        flash('success', 'Spremljeno.');

        // Upload novih slika (multiple)
        if (!empty($_FILES['images']['name'][0])) {
            $count = 0;
            foreach ($_FILES['images']['name'] as $i => $nm) {
                $file = [
                    'name' => $nm,
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i],
                ];
                if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
                $v = Security::validateImageUpload($file);
                if (!$v['ok']) { flash('error', $nm . ': ' . $v['error']); continue; }
                $fn = Security::randomFileName($v['ext']);
                if (move_uploaded_file($file['tmp_name'], SHOP_ROOT . '/uploads/products/' . $fn)) {
                    Images::optimize(SHOP_ROOT . '/uploads/products/' . $fn); // auto smanjenje + thumb
                    $hasPrimary = (int) $db->fetchColumn('SELECT COUNT(*) FROM product_images WHERE product_id = :p AND is_primary = 1', [':p' => $id]);
                    $db->insert('product_images', [
                        'product_id' => $id, 'filename' => $fn,
                        'alt' => mb_substr($p['name'], 0, 255),
                        'is_primary' => $hasPrimary ? 0 : 1,
                        'sort_order' => $count + 10,
                    ]);
                    $count++;
                }
            }
            if ($count) flash('success', "Učitano slika: $count");
        }
    } elseif ($action === 'img_delete') {
        $img = $db->fetch('SELECT * FROM product_images WHERE id = :i AND product_id = :p', [':i' => (int) $_POST['img_id'], ':p' => $id]);
        if ($img) {
            @unlink(SHOP_ROOT . '/uploads/products/' . $img['filename']);
            @unlink(SHOP_ROOT . '/uploads/products/' . Images::thumbName($img['filename']));
            $db->delete('product_images', 'id = :i', [':i' => $img['id']]);
            flash('success', 'Slika obrisana.');
        }
    } elseif ($action === 'img_primary') {
        $db->query('UPDATE product_images SET is_primary = 0 WHERE product_id = :p', [':p' => $id]);
        $db->query('UPDATE product_images SET is_primary = 1 WHERE id = :i AND product_id = :p', [':i' => (int) $_POST['img_id'], ':p' => $id]);
        flash('success', 'Glavna slika postavljena.');
    }
    redirect('admin/proizvod.php?id=' . $id);
}

$p = $db->fetch('SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = :id', [':id' => $id]);
$images = $db->fetchAll('SELECT * FROM product_images WHERE product_id = :p ORDER BY is_primary DESC, sort_order, id', [':p' => $id]);
$variants = Variants::forProduct($id, false);
$axis1Name = $variants[0]['option1_name'] ?? '';
$axis2Name = '';
foreach ($variants as $v) { if (!empty($v['option2_name'])) { $axis2Name = $v['option2_name']; break; } }

$pageTitle = 'Proizvod: ' . $p['name'];
require __DIR__ . '/templates/header.php';
?>
<form method="post" enctype="multipart/form-data">
<?= csrf_field() ?><input type="hidden" name="action" value="save">
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">
  <div style="display:grid;gap:20px">
    <div class="acard">
      <h3>Podaci iz đurđe <span class="badge violet">read-only</span></h3>
      <p class="sub">Naziv, cijenu, PDV i zalihu mijenjate u sustavu MojaĐurđa — ovdje se sinkroniziraju automatski.</p>
      <div class="aform-grid">
        <div class="full"><label class="al">Naziv</label><input class="ainput" readonly value="<?= e($p['name']) ?>"></div>
        <div><label class="al">Cijena (MPC)</label><input class="ainput" readonly value="<?= fmt_price($p['price']) ?>"></div>
        <div><label class="al">PDV</label><input class="ainput" readonly value="<?= e($p['vat_rate']) ?>%"></div>
        <div><label class="al">Jedinica</label><input class="ainput" readonly value="<?= e($p['unit']) ?>"></div>
        <div><label class="al">Zaliha</label><input class="ainput" readonly value="<?= $p['track_stock'] ? (float) $p['stock_qty'] : 'ne prati se' ?>"></div>
        <div><label class="al">Kategorija</label><input class="ainput" readonly value="<?= e($p['cat_name'] ?? '—') ?>"></div>
        <div><label class="al">Kratki opis (iz đurđe)</label><input class="ainput" readonly value="<?= e($p['short_description'] ?? '') ?>"></div>
      </div>
    </div>

    <div class="acard">
      <h3>Web opis (vaš sadržaj)</h3>
      <p class="sub">Dozvoljeni HTML tagovi za formatiranje. Ovaj opis je ključan za SEO — opišite proizvod detaljno.</p>
      <textarea class="ainput" name="description" rows="10" placeholder="<p>Detaljan opis proizvoda…</p>"><?= e($p['description'] ?? '') ?></textarea>
    </div>

    <div class="acard">
      <h3>SEO</h3>
      <div class="aform-grid">
        <div class="full"><label class="al">SEO naslov (prazno = naziv artikla)</label><input class="ainput" name="seo_title" maxlength="190" value="<?= e($p['seo_title'] ?? '') ?>"></div>
        <div class="full"><label class="al">SEO opis (max 300)</label><textarea class="ainput" name="seo_description" rows="2" maxlength="300"><?= e($p['seo_description'] ?? '') ?></textarea></div>
      </div>
      <p class="sub" style="margin-top:8px">URL: <code><?= e(SITE_URL . '/p/' . $p['slug']) ?></code></p>
    </div>
  </div>

  <div style="display:grid;gap:20px">
    <div class="acard">
      <h3>Vidljivost</h3>
      <p style="font-size:13px;margin:0 0 6px">
        <span class="badge <?= $p['is_visible'] ? 'green' : 'gray' ?>"><?= $p['is_visible'] ? '● Vidljiv u trgovini' : '● Skriven' ?></span>
      </p>
      <p class="sub">Vidljivost se određuje u <strong>MojaĐurđa → Web trgovina → Artikli</strong> i automatski sinkronizira (đurđa je izvor istine).</p>
      <label class="acheck"><input type="checkbox" name="is_featured" <?= $p['is_featured'] ? 'checked' : '' ?>> Istaknut na naslovnici ★</label>
      <button class="abtn" style="width:100%;margin-top:10px;justify-content:center">💾 Spremi sve</button>
      <a class="abtn ghost sm" style="width:100%;margin-top:8px;justify-content:center" target="_blank" href="<?= e(url('p/' . $p['slug'])) ?>">Pogledaj na webu ↗</a>
    </div>

    <div class="acard">
      <h3>Slike (<?= count($images) ?>)</h3>
      <p class="sub">JPG/PNG/WEBP do 5 MB. Slike se čuvaju na vašem serveru.</p>
      <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="ainput">
      <p class="sub" style="margin-top:6px">Odaberite datoteke pa kliknite "Spremi sve".</p>
    </div>
  </div>
</div>
</form>

<div class="acard" id="variants" style="margin-top:20px">
  <h3>Varijante — veličine, boje… <span class="badge violet">izvor: đurđa</span></h3>
  <p class="sub">Varijante se uređuju u <strong>MojaĐurđa → Web trgovina → Artikli → Varijante</strong> i automatski
    sinkroniziraju u trgovinu (najkasnije 15 min, ili odmah preko Sinkronizacije). Prodaja u trgovini javlja se
    đurđi i skida zalihu varijante na izvoru.</p>
  <?php if (!$variants): ?>
    <div class="alert alert-info" style="font-size:13px">Ovaj artikl nema varijanti. Dodajte ih u đurđi ako artikl dolazi u više veličina/boja.</div>
  <?php else: ?>
    <table class="atable" style="font-size:13px">
      <thead><tr><th>Varijanta</th><th>SKU</th><th class="num">Cijena</th><th class="num">Zaliha</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($variants as $v): ?>
        <tr>
          <td><strong><?= e($v['label']) ?></strong></td>
          <td><?= e($v['sku'] ?? '—') ?></td>
          <td class="num"><?= $v['price'] !== null ? fmt_price($v['price']) : fmt_price($p['price']) . ' <small style="color:#9ca3af">(osnovna)</small>' ?></td>
          <td class="num"><?= $v['stock_qty'] !== null ? (float) $v['stock_qty'] . ' kom' : '∞ (ne prati se)' ?></td>
          <td><span class="badge <?= $v['is_active'] ? 'green' : 'gray' ?>"><?= $v['is_active'] ? 'aktivna' : 'isključena' ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($images): ?>
<div class="acard">
  <h3>Galerija</h3>
  <div class="img-grid">
    <?php foreach ($images as $img): ?>
      <div class="img-tile <?= $img['is_primary'] ? 'primary' : '' ?>">
        <img src="<?= e(upload_url('products/' . $img['filename'])) ?>" alt="">
        <div class="acts">
          <?php if (!$img['is_primary']): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="img_primary"><input type="hidden" name="img_id" value="<?= (int) $img['id'] ?>"><button class="abtn ghost sm" title="Postavi kao glavnu">★</button></form>
          <?php else: ?><span class="badge violet">glavna</span><?php endif; ?>
          <form method="post" onsubmit="return confirm('Obrisati sliku?')"><?= csrf_field() ?><input type="hidden" name="action" value="img_delete"><input type="hidden" name="img_id" value="<?= (int) $img['id'] ?>"><button class="abtn danger sm">✕</button></form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/templates/footer.php'; ?>
