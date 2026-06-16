<?php
/** Prijava / registracija kupca (jedna stranica, dva stupca). */
require_once __DIR__ . '/core/bootstrap.php';

if (Customer::isLoggedIn()) redirect('moj-racun.php');
$next = preg_match('#^[a-z0-9/._-]*$#i', (string) ($_GET['next'] ?? '')) ? (string) ($_GET['next'] ?? '') : '';

$errLogin = $errReg = $okReg = null;
$showResend = false;
$resendEmail = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form = $_POST['form'] ?? '';
    if ($form === 'login') {
        $r = Customer::login((string) $_POST['email'], (string) $_POST['password']);
        if ($r['ok']) redirect($next !== '' ? $next : 'moj-racun.php');
        $errLogin = $r['error'];
        if (($r['code'] ?? '') === 'unverified') { $showResend = true; $resendEmail = (string) $_POST['email']; }
    } elseif ($form === 'resend') {
        // Ponovno slanje potvrde — uvijek ista poruka (bez otkrivanja postoji li račun), rate-limit
        $rk = 'resend:' . client_ip();
        if (Security::rateLimit($rk, 5, 600)) {
            Security::recordAttempt($rk);
            Customer::resendVerification((string) $_POST['email']);
        }
        flash('success', 'Ako račun postoji i nije potvrđen, poslali smo novi link za potvrdu. Provjerite e-mail (i spam).');
        redirect('prijava.php');
    } else {
        if (!Security::honeypotOk()) {
            $errReg = 'Provjera nije uspjela. Osvježite stranicu i pokušajte ponovno.';
        } else {
            $r = Customer::register((string) $_POST['email'], (string) $_POST['password'], (string) $_POST['name']);
            if ($r['ok']) {
                $okReg = 'Račun je kreiran. Poslali smo vam e-mail s linkom za potvrdu — kliknite ga da aktivirate račun (provjerite i spam).';
            } else {
                $errReg = $r['error'];
            }
        }
    }
}

$pageTitle = 'Prijava';
$pageDesc = 'Prijava i registracija kupaca — ' . shop_name();
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="section-head" style="margin-top:26px"><h1 class="section-title">Moj račun</h1></div>
  <div class="checkout-grid" style="max-width:980px;margin:0 auto">
    <div class="card">
      <h3>Prijava</h3>
      <?php if ($errLogin): ?><div class="alert alert-error"><?= e($errLogin) ?></div><?php endif; ?>
      <?php if ($showResend): ?>
        <form method="post" style="margin:0 0 14px">
          <?= csrf_field() ?><input type="hidden" name="form" value="resend">
          <input type="hidden" name="email" value="<?= e($resendEmail) ?>">
          <button class="btn btn-ghost" style="width:100%">Pošalji novi link za potvrdu</button>
        </form>
      <?php endif; ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="form" value="login">
        <div class="form-grid">
          <div class="full"><label class="f-label">E-mail</label><input class="f-input" type="email" name="email" required autocomplete="email"></div>
          <div class="full"><label class="f-label">Lozinka</label><input class="f-input" type="password" name="password" required autocomplete="current-password"></div>
        </div>
        <button class="btn" style="width:100%;margin-top:16px">Prijavi se</button>
        <p style="font-size:13px;margin:12px 0 0;text-align:center"><a href="<?= e(url('zaboravljena-lozinka.php')) ?>">Zaboravljena lozinka?</a></p>
      </form>
    </div>
    <div class="card">
      <h3>Novi kupac? Registrirajte se</h3>
      <p style="font-size:13.5px;color:var(--c-muted);margin:0 0 12px">Pregled svih narudžbi i računa na jednom mjestu + brži checkout (podaci se sami popune).</p>
      <?php if ($okReg): ?><div class="alert alert-success"><?= e($okReg) ?></div><?php endif; ?>
      <?php if ($errReg): ?><div class="alert alert-error"><?= e($errReg) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?><?= hp_fields() ?><input type="hidden" name="form" value="register">
        <div class="form-grid">
          <div class="full"><label class="f-label">Ime i prezime *</label><input class="f-input" name="name" required maxlength="200" autocomplete="name"></div>
          <div class="full"><label class="f-label">E-mail *</label><input class="f-input" type="email" name="email" required maxlength="190" autocomplete="email"></div>
          <div class="full"><label class="f-label">Lozinka (min 8 znakova) *</label><input class="f-input" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
        </div>
        <button class="btn" style="width:100%;margin-top:16px">Kreiraj račun</button>
        <p style="font-size:12px;color:var(--c-muted);margin:10px 0 0">Registracijom prihvaćate <a href="<?= e(url('s/uvjeti-koristenja')) ?>">uvjete korištenja</a> i <a href="<?= e(url('s/zastita-privatnosti')) ?>">politiku privatnosti</a>.</p>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
