<?php
/** Postavljanje nove lozinke putem jednokratnog reset tokena iz e-maila. */
require_once __DIR__ . '/core/bootstrap.php';

if (Customer::isLoggedIn()) redirect('moj-racun.php');

$token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));
$valid = Customer::resetTokenValid($token);
$err   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    csrf_check();
    $rk = 'reset-do:' . client_ip();
    if (!Security::rateLimit($rk, 10, 600)) {
        $err = 'Previše pokušaja. Pričekajte nekoliko minuta.';
    } else {
        Security::recordAttempt($rk);
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password2'] ?? '');
        if ($p1 !== $p2) {
            $err = 'Lozinke se ne podudaraju.';
        } else {
            $r = Customer::resetPassword($token, $p1);
            if ($r['ok']) {
                flash('success', 'Lozinka je promijenjena. Prijavite se novom lozinkom.');
                redirect('prijava.php');
            }
            $err   = $r['error'];
            $valid = Customer::resetTokenValid($token); // token je možda potrošen
        }
    }
}

$pageTitle = 'Nova lozinka';
$pageDesc  = 'Postavljanje nove lozinke — ' . shop_name();
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="section-head" style="margin-top:26px"><h1 class="section-title">Postavi novu lozinku</h1></div>
  <div class="card" style="max-width:520px;margin:0 auto">
    <?php if (!$valid): ?>
      <div class="alert alert-error">Link je nevažeći ili je istekao.</div>
      <p style="color:var(--c-muted);font-size:14px;margin:0 0 14px">Zatražite novi link za reset lozinke.</p>
      <a class="btn" href="<?= e(url('zaboravljena-lozinka.php')) ?>">Zatraži novi link</a>
    <?php else: ?>
      <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-grid">
          <div class="full"><label class="f-label">Nova lozinka (min 8 znakova)</label><input class="f-input" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
          <div class="full"><label class="f-label">Ponovi novu lozinku</label><input class="f-input" type="password" name="password2" required minlength="8" autocomplete="new-password"></div>
        </div>
        <button class="btn" style="width:100%;margin-top:16px">Spremi novu lozinku</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
