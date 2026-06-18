<?php
/** Odjava s newslettera (token iz e-maila). */
require_once __DIR__ . '/core/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$done = false;
if (preg_match('/^[a-f0-9]{48}$/', $token)) {
    $done = $db->delete('newsletter_subscribers', 'token = :t', [':t' => $token]) > 0;
}

$pageTitle = 'Odjava s newslettera';
$pageNoindex = true;
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="card" style="max-width:520px;margin:26px auto">
    <?php if ($done): ?>
      <div class="alert alert-success">Odjavljeni ste s newslettera. Žao nam je što odlazite!</div>
    <?php else: ?>
      <div class="alert alert-error">Link nije važeći ili ste već odjavljeni.</div>
    <?php endif; ?>
    <a class="btn" href="<?= e(url('')) ?>">Natrag na trgovinu</a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
