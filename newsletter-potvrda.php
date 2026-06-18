<?php
/** Potvrda prijave na newsletter (double opt-in token iz e-maila). */
require_once __DIR__ . '/core/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$ok = false;
if (preg_match('/^[a-f0-9]{48}$/', $token)) {
    $n = $db->query('UPDATE newsletter_subscribers SET is_confirmed = 1 WHERE token = :t AND is_confirmed = 0', [':t' => $token])->rowCount();
    $ok = $n > 0 || (bool) $db->fetchColumn('SELECT id FROM newsletter_subscribers WHERE token = :t AND is_confirmed = 1', [':t' => $token]);
}

$pageTitle = 'Newsletter';
$pageNoindex = true;
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="card" style="max-width:520px;margin:26px auto">
    <?php if ($ok): ?>
      <div class="alert alert-success">Prijava na newsletter je potvrđena — hvala! 💌</div>
    <?php else: ?>
      <div class="alert alert-error">Link nije važeći ili je prijava već obrađena.</div>
    <?php endif; ?>
    <a class="btn" href="<?= e(url('')) ?>">Natrag na trgovinu</a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
