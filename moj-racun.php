<?php
/** Moj račun — korisnički profil kupca sa sekcijama (nav + sadržaj). */
require_once __DIR__ . '/core/bootstrap.php';

$customer = Customer::current();
if (!$customer) redirect('prijava.php?next=moj-racun.php');

$view = (string) ($_GET['v'] ?? 'dashboard');
$allowed = ['dashboard', 'narudzbe', 'racuni', 'adrese', 'detalji'];
if (!in_array($view, $allowed, true)) $view = 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'logout') {
        Customer::logout();
        flash('success', 'Odjavljeni ste. Vidimo se! 👋');
        redirect('');
    } elseif ($action === 'address') {
        $db->update('customers', [
            'phone'       => mb_substr(trim((string) $_POST['phone']), 0, 40) ?: null,
            'address'     => mb_substr(trim((string) $_POST['address']), 0, 255) ?: null,
            'city'        => mb_substr(trim((string) $_POST['city']), 0, 100) ?: null,
            'postal_code' => mb_substr(trim((string) $_POST['postal_code']), 0, 20) ?: null,
        ], 'id = :id', [':id' => $customer['id']]);
        flash('success', 'Adresa spremljena — popunit će se sama pri sljedećoj kupnji.');
        redirect('moj-racun.php?v=adrese');
    } elseif ($action === 'details') {
        $name = mb_substr(trim((string) $_POST['name']), 0, 200);
        if (mb_strlen($name) >= 3) {
            $db->update('customers', ['name' => $name], 'id = :id', [':id' => $customer['id']]);
            flash('success', 'Podaci spremljeni.');
        } else {
            flash('error', 'Ime mora imati najmanje 3 znaka.');
        }
        redirect('moj-racun.php?v=detalji');
    } elseif ($action === 'password') {
        if (!password_verify((string) $_POST['current'], $customer['password_hash'])) {
            flash('error', 'Trenutna lozinka nije ispravna.');
        } elseif (strlen((string) $_POST['new']) < 8) {
            flash('error', 'Nova lozinka mora imati najmanje 8 znakova.');
        } else {
            $db->update('customers', ['password_hash' => password_hash((string) $_POST['new'], PASSWORD_DEFAULT)], 'id = :id', [':id' => $customer['id']]);
            flash('success', 'Lozinka promijenjena.');
        }
        redirect('moj-racun.php?v=detalji');
    } elseif ($action === 'delete_account') {
        if (!password_verify((string) ($_POST['confirm_password'] ?? ''), $customer['password_hash'])) {
            flash('error', 'Lozinka nije ispravna — račun NIJE obrisan.');
            redirect('moj-racun.php?v=detalji');
        }
        Customer::anonymize((int) $customer['id']);
        Customer::logout();
        flash('success', 'Vaš račun je obrisan, a osobni podaci anonimizirani. Računi koje zakon nalaže čuvati ostaju bez vaših osobnih podataka. Hvala što ste bili s nama.');
        redirect('');
    }
}

$orders = $db->fetchAll('SELECT * FROM orders WHERE customer_id = :c ORDER BY id DESC LIMIT 100', [':c' => $customer['id']]);
$ordersCount = count($orders);
$invoices = array_values(array_filter($orders, fn($o) => in_array($o['fiscal_status'], ['fiscalized', 'stornoed'], true)));

$nav = [
    'dashboard' => ['🏠', 'Nadzorna ploča'],
    'narudzbe'  => ['📦', 'Narudžbe'],
    'racuni'    => ['🧾', 'Računi'],
    'adrese'    => ['📍', 'Adrese'],
    'detalji'   => ['👤', 'Detalji računa'],
];

$pageTitle = 'Moj račun';
$pageDesc = 'Korisnički profil — ' . shop_name();
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="section-head" style="margin-top:26px"><h1 class="section-title">Moj račun</h1></div>

  <div class="account-layout">
    <aside class="account-nav">
      <?php foreach ($nav as $key => [$ico, $label]): ?>
        <a href="<?= e(url('moj-racun.php?v=' . $key)) ?>" class="<?= $view === $key ? 'active' : '' ?>"><span class="ic"><?= $ico ?></span> <?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('raskid-ugovora.php')) ?>"><span class="ic">↩️</span> Raskid ugovora</a>
      <form method="post" style="margin-top:6px"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="acc-logout"><span class="ic">🚪</span> Odjava</button></form>
    </aside>

    <div class="account-content">
      <?php if ($view === 'dashboard'): ?>
        <div class="card">
          <h3 style="margin-top:0">Pozdrav, <?= e(explode(' ', trim($customer['name']))[0]) ?>! 👋</h3>
          <p style="color:var(--c-muted)">Iz nadzorne ploče upravljate narudžbama, računima i podacima računa.</p>
          <div class="acc-stats">
            <a href="<?= e(url('moj-racun.php?v=narudzbe')) ?>" class="acc-stat"><span class="n"><?= $ordersCount ?></span><span class="l">narudžbi</span></a>
            <a href="<?= e(url('moj-racun.php?v=racuni')) ?>" class="acc-stat"><span class="n"><?= count($invoices) ?></span><span class="l">računa</span></a>
            <a href="<?= e(url('proizvodi.php')) ?>" class="acc-stat"><span class="n">🛍️</span><span class="l">nova kupnja</span></a>
          </div>
          <?php if ($orders): $last = $orders[0]; ?>
            <p style="font-size:13.5px;color:var(--c-muted);margin:16px 0 0">Zadnja narudžba: <strong><?= e($last['order_number']) ?></strong> · <?= e(Orders::statusLabel($last['status'])) ?> · <?= fmt_price($last['total']) ?>
              <a href="<?= e(url('moj-racun.php?v=narudzbe')) ?>">sve narudžbe →</a></p>
          <?php endif; ?>
        </div>

      <?php elseif ($view === 'narudzbe'): ?>
        <div class="card" style="padding:18px 22px">
          <h3 style="margin-top:0">Moje narudžbe</h3>
          <?php if (!$orders): ?>
            <div class="alert alert-info">Još nemate narudžbi. <a href="<?= e(url('proizvodi.php')) ?>">Razgledajte ponudu →</a></div>
          <?php else: ?>
            <table class="cart-table">
              <thead><tr><th>Narudžba</th><th>Datum</th><th>Status</th><th>Iznos</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($orders as $o): ?>
                <tr>
                  <td><strong><?= e($o['order_number']) ?></strong></td>
                  <td style="white-space:nowrap"><?= date('d.m.Y', strtotime($o['created_at'])) ?></td>
                  <td><?= e(Orders::statusLabel($o['status'])) ?><?= $o['withdrawal_requested_at'] ? '<br><small style="color:#b45309">zatražen raskid</small>' : '' ?></td>
                  <td><strong><?= fmt_price($o['total']) ?></strong></td>
                  <td><a href="<?= e(url('narudzba-potvrda.php?t=' . urlencode($o['guest_token']))) ?>">Detalji →</a></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

      <?php elseif ($view === 'racuni'): ?>
        <div class="card" style="padding:18px 22px">
          <h3 style="margin-top:0">Moji računi</h3>
          <p style="font-size:13.5px;color:var(--c-muted);margin:0 0 12px">Fiskalizirani računi za vaše narudžbe — kliknite za ispis ili spremanje u PDF.</p>
          <?php if (!$invoices): ?>
            <div class="alert alert-info">Još nemate fiskaliziranih računa. Pojavit će se ovdje nakon što narudžba bude plaćena i fiskalizirana.</div>
          <?php else: ?>
            <table class="cart-table">
              <thead><tr><th>Račun br.</th><th>Narudžba</th><th>Datum</th><th>Iznos</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($invoices as $o): ?>
                <tr>
                  <td><strong><?= e($o['fiscal_receipt_number']) ?></strong><?= $o['fiscal_status'] === 'stornoed' ? ' <small style="color:#b91c1c">(stornirano)</small>' : '' ?></td>
                  <td><?= e($o['order_number']) ?></td>
                  <td style="white-space:nowrap"><?= date('d.m.Y', strtotime($o['fiscalized_at'] ?: $o['created_at'])) ?></td>
                  <td><strong><?= fmt_price($o['total']) ?></strong></td>
                  <td><a href="<?= e(url('racun.php?id=' . (int) $o['id'])) ?>" target="_blank">🧾 Preuzmi / ispiši →</a></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

      <?php elseif ($view === 'adrese'): ?>
        <div class="card">
          <h3 style="margin-top:0">Adresa za dostavu</h3>
          <p style="font-size:13.5px;color:var(--c-muted);margin:0 0 14px">Ovi podaci se automatski popunjavaju pri sljedećoj kupnji.</p>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="address">
            <div class="form-grid">
              <div class="full"><label class="f-label">Adresa</label><input class="f-input" name="address" value="<?= e($customer['address'] ?? '') ?>" maxlength="255" autocomplete="street-address"></div>
              <div><label class="f-label">Grad</label><input class="f-input" name="city" value="<?= e($customer['city'] ?? '') ?>" maxlength="100" autocomplete="address-level2"></div>
              <div><label class="f-label">Poštanski broj</label><input class="f-input" name="postal_code" value="<?= e($customer['postal_code'] ?? '') ?>" maxlength="20" autocomplete="postal-code"></div>
              <div class="full"><label class="f-label">Telefon</label><input class="f-input" name="phone" value="<?= e($customer['phone'] ?? '') ?>" maxlength="40" autocomplete="tel"></div>
            </div>
            <button class="btn" style="margin-top:14px">💾 Spremi adresu</button>
          </form>
        </div>

      <?php elseif ($view === 'detalji'): ?>
        <div style="display:grid;gap:20px">
          <div class="card">
            <h3 style="margin-top:0">Detalji računa</h3>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="action" value="details">
              <div class="form-grid">
                <div class="full"><label class="f-label">Ime i prezime</label><input class="f-input" name="name" value="<?= e($customer['name']) ?>" maxlength="200" required></div>
                <div class="full"><label class="f-label">E-mail (prijava)</label><input class="f-input" type="email" value="<?= e($customer['email']) ?>" disabled style="opacity:.7"><small style="color:var(--c-muted)">E-mail je vezan uz prijavu i račune — za promjenu nas kontaktirajte.</small></div>
              </div>
              <button class="btn btn-sm" style="margin-top:14px">💾 Spremi</button>
            </form>
          </div>
          <div class="card">
            <h3 style="margin-top:0">Promjena lozinke</h3>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="action" value="password">
              <div class="form-grid">
                <div class="full"><label class="f-label">Trenutna lozinka</label><input class="f-input" type="password" name="current" required autocomplete="current-password"></div>
                <div class="full"><label class="f-label">Nova lozinka (min 8)</label><input class="f-input" type="password" name="new" required minlength="8" autocomplete="new-password"></div>
              </div>
              <button class="btn btn-ghost btn-sm" style="margin-top:14px">Promijeni lozinku</button>
            </form>
          </div>
          <div class="card" style="border:1px solid #fecaca">
            <h3 style="margin-top:0;color:#b91c1c">Brisanje računa</h3>
            <p style="font-size:13px;color:var(--c-muted);margin:0 0 12px">Trajno zatvaramo vaš račun i <strong>anonimiziramo osobne podatke</strong> (ime, e-mail, telefon, adresa). Računi koje zakon nalaže čuvati ostaju u sustavu, ali bez vaših osobnih podataka. Radnja je nepovratna.</p>
            <form method="post" onsubmit="return confirm('Sigurno obrisati račun? Radnja je nepovratna.')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete_account">
              <div class="form-grid">
                <div class="full"><label class="f-label">Potvrdite svojom lozinkom</label><input class="f-input" type="password" name="confirm_password" required autocomplete="current-password"></div>
              </div>
              <button class="btn btn-sm" style="margin-top:14px;background:#dc2626;border-color:#dc2626;color:#fff">Obriši moj račun</button>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
