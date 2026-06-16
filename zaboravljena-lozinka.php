<?php
/** Zaboravljena lozinka — zatraži link za reset. Uvijek ista (tiha) potvrda. */
require_once __DIR__ . '/core/bootstrap.php';

if (Customer::isLoggedIn()) redirect('moj-racun.php');

$sent = false;
$err  = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!Security::honeypotOk()) {
        $err = 'Provjera nije uspjela. Osvježite stranicu i pokušajte ponovno.';
    } else {
        $rk = 'reset-req:' . client_ip();
        if (!Security::rateLimit($rk, 5, 900)) {
            $err = 'Previše zahtjeva. Pričekajte nekoliko minuta pa pokušajte ponovno.';
        } else {
            Security::recordAttempt($rk);
            Customer::requestReset((string) ($_POST['email'] ?? ''));
            $sent = true;
        }
    }
}

$pageTitle = 'Zaboravljena lozinka';
$pageDesc  = 'Reset lozinke — ' . shop_name();
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="section-head" style="margin-top:26px"><h1 class="section-title">Zaboravljena lozinka</h1></div>
  <div class="card" style="max-width:520px;margin:0 auto">
    <?php if ($sent): ?>
      <div class="alert alert-success">Ako račun s tom e-mail adresom postoji, poslali smo upute za postavljanje nove lozinke. Provjerite e-mail (i spam).</div>
      <a class="btn btn-ghost" href="<?= e(url('prijava.php')) ?>">Natrag na prijavu</a>
    <?php else: ?>
      <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
      <p style="color:var(--c-muted);font-size:14px;margin:0 0 14px">Upišite e-mail adresu svog računa i poslat ćemo vam link za postavljanje nove lozinke.</p>
      <form method="post">
        <?= csrf_field() ?><?= hp_fields() ?>
        <div class="form-grid">
          <div class="full"><label class="f-label">E-mail</label><input class="f-input" type="email" name="email" required autocomplete="email"></div>
        </div>
        <button class="btn" style="width:100%;margin-top:16px">Pošalji link za reset</button>
        <p style="font-size:13px;margin:12px 0 0;text-align:center"><a href="<?= e(url('prijava.php')) ?>">Natrag na prijavu</a></p>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
