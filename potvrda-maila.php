<?php
/** Potvrda e-mail adrese nakon registracije (jednokratni token iz e-maila). */
require_once __DIR__ . '/core/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
if ($token !== '' && Customer::verifyEmail($token)) {
    flash('success', 'E-mail je potvrđen — dobrodošli! Vaš račun je aktivan.');
    redirect('moj-racun.php');
}

$pageTitle = 'Potvrda e-maila';
$pageDesc  = 'Potvrda e-mail adrese — ' . shop_name();
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="section-head" style="margin-top:26px"><h1 class="section-title">Potvrda e-maila</h1></div>
  <div class="card" style="max-width:520px;margin:0 auto">
    <div class="alert alert-error">Link za potvrdu je nevažeći ili je istekao.</div>
    <p style="color:var(--c-muted);font-size:14px;margin:0 0 14px">Ako ste se tek registrirali, na stranici za prijavu pokušajte ponovno — poslat ćemo vam novi link za potvrdu.</p>
    <a class="btn" href="<?= e(url('prijava.php')) ?>">Idi na prijavu</a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
