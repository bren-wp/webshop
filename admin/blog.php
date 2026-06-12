<?php
/**
 * Blog — SEO sadržaj trgovine (kartice s naslovnicom, izdvojeni članak,
 * Article JSON-LD, sitemap). Pogodnost PLAĆENOG plana: na besplatnom se
 * uređivanje zaključava, a objavljeni članci ne prikazuju na izlogu
 * (sadržaj se čuva i vraća čim se plan aktivira).
 */
require_once __DIR__ . '/templates/init.php';

$paid = Djurdja::customizationAllowed();
$editId = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$paid) { flash('error', 'Blog je dostupan u plaćenim paketima.'); redirect('admin/blog.php'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = mb_substr(trim((string) $_POST['title']), 0, 255);
        if ($title === '') { flash('error', 'Naslov je obavezan.'); redirect('admin/blog.php' . ($id ? '?id=' . $id : '')); }

        $data = [
            'title'           => $title,
            'excerpt'         => mb_substr(trim((string) $_POST['excerpt']), 0, 500) ?: null,
            'content'         => trim((string) $_POST['content']) ?: null,
            'is_published'    => !empty($_POST['is_published']) ? 1 : 0,
            'seo_title'       => mb_substr(trim((string) $_POST['seo_title']), 0, 190) ?: null,
            'seo_description' => mb_substr(trim((string) $_POST['seo_description']), 0, 300) ?: null,
        ];

        if ($id) {
            $post = $db->fetch('SELECT * FROM blog_posts WHERE id = :id', [':id' => $id]);
            if (!$post) { flash('error', 'Članak nije pronađen.'); redirect('admin/blog.php'); }
            if ($data['is_published'] && !$post['published_at']) $data['published_at'] = date('Y-m-d H:i:s');
            $db->update('blog_posts', $data, 'id = :id', [':id' => $id]);
        } else {
            $base = slugify($title);
            $slug = $base; $i = 2;
            while ($db->fetchColumn('SELECT id FROM blog_posts WHERE slug = :s', [':s' => $slug])) { $slug = $base . '-' . $i++; }
            $data['slug'] = $slug;
            if ($data['is_published']) $data['published_at'] = date('Y-m-d H:i:s');
            $id = (int) $db->insert('blog_posts', $data);
        }

        if (!empty($_FILES['cover']['name'])) {
            $v = Security::validateImageUpload($_FILES['cover']);
            if ($v['ok']) {
                $fn = 'blog-' . Security::randomFileName($v['ext']);
                if (move_uploaded_file($_FILES['cover']['tmp_name'], SHOP_ROOT . '/uploads/blog/' . $fn)) {
                    Images::optimize(SHOP_ROOT . '/uploads/blog/' . $fn);
                    $old = $db->fetchColumn('SELECT cover_image FROM blog_posts WHERE id = :id', [':id' => $id]);
                    if ($old) { @unlink(SHOP_ROOT . '/uploads/blog/' . $old); @unlink(SHOP_ROOT . '/uploads/blog/' . Images::thumbName($old)); }
                    $db->update('blog_posts', ['cover_image' => $fn], 'id = :id', [':id' => $id]);
                }
            } else {
                flash('error', 'Naslovnica: ' . $v['error']);
            }
        }
        flash('success', 'Članak spremljen.');
        redirect('admin/blog.php?id=' . $id);
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        $old = $db->fetchColumn('SELECT cover_image FROM blog_posts WHERE id = :id', [':id' => $id]);
        if ($old) { @unlink(SHOP_ROOT . '/uploads/blog/' . $old); @unlink(SHOP_ROOT . '/uploads/blog/' . Images::thumbName($old)); }
        $db->delete('blog_posts', 'id = :id', [':id' => $id]);
        flash('success', 'Članak obrisan.');
        redirect('admin/blog.php');
    }
    redirect('admin/blog.php');
}

$post = $editId ? $db->fetch('SELECT * FROM blog_posts WHERE id = :id', [':id' => $editId]) : null;
$posts = $db->fetchAll('SELECT id, title, slug, is_published, published_at, updated_at FROM blog_posts ORDER BY id DESC LIMIT 200');

$pageTitle = 'Blog';
require __DIR__ . '/templates/header.php';
?>
<?php if (!$paid): ?>
  <div class="acard" style="color:#fff;background:linear-gradient(135deg,#6d28d9,#a855f7 60%,#ec4899);border:0;box-shadow:0 8px 24px rgba(124,58,237,.35)">
    <h3 style="color:#fff">📝 Blog — vaš najjači SEO alat <span class="badge" style="background:rgba(255,255,255,.25);color:#fff">plaćeni plan</span></h3>
    <p style="font-size:13.5px;line-height:1.7;opacity:.95;margin:8px 0 12px">
      Trgovine s blogom dobivaju <strong>višestruko više posjeta s Googlea</strong> — svaki članak
      ("Kako odabrati…", "5 savjeta za…") je nova ulaznica za kupce koji vas još ne poznaju.
      Blog dolazi s naprednim prikazom (naslovnice, izdvojeni članak), automatskim SEO-om
      (Article schema, sitemap, AI sažetak) i dijeljenjem na društvene mreže.
    </p>
    <a class="abtn sm" style="background:#fff;color:#6d28d9;font-weight:800" target="_blank"
       href="https://mojadjurdja.com/cjenik?utm_source=webshop&utm_medium=blog&utm_campaign=upsell">⚡ Otključaj blog — pogledaj pakete ↗</a>
  </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start;<?= $paid ? '' : 'opacity:.45;pointer-events:none' ?>">
  <div class="acard">
    <h3>Članci (<?= count($posts) ?>)</h3>
    <a class="abtn sm" style="width:100%;justify-content:center;margin-bottom:12px" href="<?= e(adminUrl('blog.php')) ?>">+ Novi članak</a>
    <?php foreach ($posts as $p): ?>
      <a href="<?= e(adminUrl('blog.php?id=' . $p['id'])) ?>" style="display:block;padding:10px 12px;border-radius:10px;margin-bottom:6px;text-decoration:none;<?= $editId === (int) $p['id'] ? 'background:#ede9fe' : 'background:#f9fafb' ?>">
        <strong style="font-size:13.5px;color:#1f2937"><?= e($p['title']) ?></strong><br>
        <span class="badge <?= $p['is_published'] ? 'green' : 'gray' ?>" style="margin-top:4px"><?= $p['is_published'] ? 'objavljen' : 'skica' ?></span>
        <small style="color:#9ca3af"> · <?= e(date('d.m.Y', strtotime($p['published_at'] ?: $p['updated_at']))) ?></small>
      </a>
    <?php endforeach; ?>
    <?php if (!$posts): ?><p class="sub">Još nema članaka. Napišite prvi — npr. "Kako odabrati savršen poklon"?</p><?php endif; ?>
  </div>

  <div class="acard">
    <h3><?= $post ? 'Uredi: ' . e($post['title']) : 'Novi članak' ?></h3>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($post['id'] ?? 0) ?>">
      <div class="aform-grid">
        <div class="full"><label class="al">Naslov *</label><input class="ainput" name="title" required maxlength="255" value="<?= e($post['title'] ?? '') ?>"></div>
        <div class="full"><label class="al">Kratki uvod (prikazuje se na kartici i u SEO)</label><textarea class="ainput" name="excerpt" rows="2" maxlength="500"><?= e($post['excerpt'] ?? '') ?></textarea></div>
        <div class="full"><label class="al">Sadržaj (HTML dozvoljen: &lt;h2&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;img&gt;, &lt;a&gt;…)</label>
          <textarea class="ainput" name="content" rows="14" placeholder="<h2>Podnaslov</h2>&#10;<p>Tekst članka…</p>"><?= e($post['content'] ?? '') ?></textarea></div>
        <div><label class="al">Naslovna slika (JPG/PNG/WEBP — automatski se optimizira)</label><input class="ainput" type="file" name="cover" accept="image/jpeg,image/png,image/webp"></div>
        <div style="display:flex;align-items:end">
          <?php if (!empty($post['cover_image'])): ?>
            <img src="<?= e(upload_url('blog/' . $post['cover_image'])) ?>" alt="" style="max-height:64px;border-radius:8px">
          <?php endif; ?>
        </div>
        <div class="full"><label class="al">SEO naslov (prazno = naslov)</label><input class="ainput" name="seo_title" maxlength="190" value="<?= e($post['seo_title'] ?? '') ?>"></div>
        <div class="full"><label class="al">SEO opis (prazno = uvod)</label><input class="ainput" name="seo_description" maxlength="300" value="<?= e($post['seo_description'] ?? '') ?>"></div>
      </div>
      <div style="display:flex;gap:14px;align-items:center;margin-top:14px;flex-wrap:wrap">
        <label class="acheck" style="margin:0"><input type="checkbox" name="is_published" <?= !empty($post['is_published']) ? 'checked' : '' ?>> Objavljen (vidljiv na blogu)</label>
        <button class="abtn">💾 Spremi članak</button>
        <?php if ($post): ?>
          <a class="abtn ghost sm" target="_blank" href="<?= e(url('blog/' . $post['slug'])) ?>">Pogledaj ↗</a>
        <?php endif; ?>
      </div>
    </form>
    <?php if ($post): ?>
      <form method="post" style="margin-top:14px" onsubmit="return confirm('Obrisati članak? Nepovratno.')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
        <button class="abtn danger sm">Obriši članak</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
