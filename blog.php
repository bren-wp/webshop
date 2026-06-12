<?php
/** Blog — listing (izdvojeni najnoviji članak + grid kartica). Plaćeni plan. */
require_once __DIR__ . '/core/bootstrap.php';

if (!Djurdja::blogActive()) { http_response_code(404); require __DIR__ . '/404.php'; exit; }

$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 9;
$total = (int) $db->fetchColumn('SELECT COUNT(*) FROM blog_posts WHERE is_published = 1');
$pages = max(1, (int) ceil($total / $per));
$page = min($page, $pages);
$posts = $db->fetchAll(
    'SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per)
);
$hero = ($page === 1 && $posts) ? array_shift($posts) : null;

$pageTitle = 'Blog';
$pageDesc = 'Savjeti, novosti i priče — ' . shop_name();
$pageCanonical = SITE_URL . '/blog' . ($page > 1 ? '?page=' . $page : '');
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="section-head" style="margin-top:26px"><h1 class="section-title">Blog</h1></div>

  <?php if (!$hero && !$posts): ?>
    <div class="alert alert-info" style="max-width:560px">Prvi članci stižu uskoro — vratite se brzo! ✍️</div>
  <?php endif; ?>

  <?php if ($hero): ?>
    <a href="<?= e(url('blog/' . $hero['slug'])) ?>" class="blog-hero fade-up">
      <?php if ($hero['cover_image']): ?>
        <img src="<?= e(upload_url('blog/' . $hero['cover_image'])) ?>" alt="<?= e($hero['title']) ?>" loading="lazy">
      <?php endif; ?>
      <div class="bh-body">
        <span class="bh-date"><?= date('d.m.Y', strtotime($hero['published_at'])) ?> · Najnovije</span>
        <h2><?= e($hero['title']) ?></h2>
        <?php if ($hero['excerpt']): ?><p><?= e($hero['excerpt']) ?></p><?php endif; ?>
        <span class="bh-more">Pročitaj članak →</span>
      </div>
    </a>
  <?php endif; ?>

  <?php if ($posts): ?>
    <div class="blog-grid">
      <?php foreach ($posts as $p): ?>
        <a class="blog-card fade-up" href="<?= e(url('blog/' . $p['slug'])) ?>">
          <div class="bc-media">
            <?php if ($p['cover_image']): ?>
              <img src="<?= e(upload_url('blog/' . Images::thumbOr($p['cover_image'], SHOP_ROOT . '/uploads/blog/'))) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
            <?php else: ?>
              <div class="noimg">📰</div>
            <?php endif; ?>
          </div>
          <div class="bc-body">
            <span class="bc-date"><?= date('d.m.Y', strtotime($p['published_at'])) ?></span>
            <h3><?= e($p['title']) ?></h3>
            <?php if ($p['excerpt']): ?><p><?= e(mb_substr($p['excerpt'], 0, 140)) ?><?= mb_strlen($p['excerpt']) > 140 ? '…' : '' ?></p><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
    <nav class="pagination">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="<?= e(url('blog' . ($i > 1 ? '?page=' . $i : ''))) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
