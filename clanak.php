<?php
/** Blog članak — Article JSON-LD, share, povezani članci. Plaćeni plan. */
require_once __DIR__ . '/core/bootstrap.php';

if (!Djurdja::blogActive()) { http_response_code(404); require __DIR__ . '/404.php'; exit; }

$slug = trim((string) ($_GET['slug'] ?? ''));
$post = $slug !== '' ? $db->fetch('SELECT * FROM blog_posts WHERE slug = :s AND is_published = 1', [':s' => $slug]) : null;
if (!$post) { http_response_code(404); require __DIR__ . '/404.php'; exit; }

$related = $db->fetchAll(
    'SELECT title, slug, cover_image, published_at FROM blog_posts
     WHERE is_published = 1 AND id != :id ORDER BY published_at DESC LIMIT 3',
    [':id' => $post['id']]
);

$postUrl = SITE_URL . '/blog/' . $post['slug'];
$cover = $post['cover_image'] ? SITE_URL . '/uploads/blog/' . $post['cover_image'] : null;
$company = Djurdja::company();

$pageTitle = $post['seo_title'] ?: $post['title'];
$pageDesc = $post['seo_description'] ?: ($post['excerpt'] ?: mb_substr(strip_tags((string) $post['content']), 0, 300));
$pageCanonical = $postUrl;
$pageType = 'article';
$pageOgImage = $cover;
$articleLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post['title'],
    'description' => $pageDesc,
    'datePublished' => date('c', strtotime($post['published_at'])),
    'dateModified' => date('c', strtotime($post['updated_at'])),
    'mainEntityOfPage' => $postUrl,
    'author' => ['@type' => 'Organization', 'name' => $company['companyName'] ?? shop_name()],
    'publisher' => ['@type' => 'Organization', 'name' => $company['companyName'] ?? shop_name()],
];
if ($cover) $articleLd['image'] = [$cover];
$pageJsonLd = [
    '<script type="application/ld+json">' . json_encode($articleLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>',
    Seo::breadcrumbJsonLd([['Početna', SITE_URL . '/'], ['Blog', SITE_URL . '/blog'], [$post['title'], null]]),
];
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <article class="blog-article">
    <nav class="breadcrumbs" style="margin-top:18px">
      <a href="<?= e(url('')) ?>">Početna</a><span class="sep">›</span>
      <a href="<?= e(url('blog')) ?>">Blog</a><span class="sep">›</span><?= e($post['title']) ?>
    </nav>
    <h1><?= e($post['title']) ?></h1>
    <div class="ba-meta">Objavljeno <?= date('d.m.Y', strtotime($post['published_at'])) ?> · <?= e(shop_name()) ?></div>
    <?php if ($cover): ?><img class="ba-cover" src="<?= e($cover) ?>" alt="<?= e($post['title']) ?>"><?php endif; ?>
    <?php if ($post['excerpt']): ?><p class="ba-lead"><?= e($post['excerpt']) ?></p><?php endif; ?>
    <div class="content ba-content"><?= $post['content'] /* HTML iz admina (vlasnik je trusted) */ ?></div>

    <div class="share-row" style="margin:26px 0 8px">
      <span class="lbl">Podijeli članak:</span>
      <button class="share-btn" type="button" data-share="native" data-url="<?= e($postUrl) ?>" data-title="<?= e($post['title']) ?>" aria-label="Podijeli">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
      </button>
      <a class="share-btn" href="https://wa.me/?text=<?= rawurlencode($post['title'] . ' ' . $postUrl) ?>" target="_blank" rel="noopener" aria-label="WhatsApp">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.5 0 1.47 1.07 2.89 1.22 3.09.15.2 2.11 3.22 5.1 4.51.71.31 1.27.49 1.7.63.72.23 1.37.2 1.88.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12.05 21.8h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 1 1 8.38 4.63zM12.05 0C5.5 0 .16 5.34.16 11.9c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.68 1.45c6.56 0 11.9-5.34 11.9-11.9C23.94 5.34 18.6 0 12.05 0z"/></svg>
      </a>
      <a class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($postUrl) ?>" target="_blank" rel="noopener" aria-label="Facebook">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.09 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.8-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.5c-1.5 0-1.96.93-1.96 1.89v2.26h3.32l-.53 3.49h-2.8V24C19.62 23.09 24 18.1 24 12.07z"/></svg>
      </a>
      <button class="share-btn" type="button" data-share="copy" data-url="<?= e($postUrl) ?>" aria-label="Kopiraj link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
      </button>
    </div>
  </article>

  <?php if ($related): ?>
    <section class="section">
      <div class="section-head"><h2 class="section-title">Pročitajte i ovo</h2></div>
      <div class="blog-grid">
        <?php foreach ($related as $r): ?>
          <a class="blog-card" href="<?= e(url('blog/' . $r['slug'])) ?>">
            <div class="bc-media">
              <?php if ($r['cover_image']): ?>
                <img src="<?= e(upload_url('blog/' . Images::thumbOr($r['cover_image'], SHOP_ROOT . '/uploads/blog/'))) ?>" alt="" loading="lazy">
              <?php else: ?><div class="noimg">📰</div><?php endif; ?>
            </div>
            <div class="bc-body">
              <span class="bc-date"><?= date('d.m.Y', strtotime($r['published_at'])) ?></span>
              <h3><?= e($r['title']) ?></h3>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
