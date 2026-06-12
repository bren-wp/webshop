<?php
/**
 * Jednostrani raskid ugovora — zakonska obveza od 19. 6. 2026. (ZZP).
 * Online obrazac jednako jednostavan kao kupnja: broj narudžbe + e-mail.
 * Automatska potvrda s točnim datumom i vremenom šalje se kupcu e-mailom
 * (zakonski "trajni medij") i bilježi na narudžbi. BEZ dark patterna.
 */
require_once __DIR__ . '/core/bootstrap.php';

$done = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!Security::honeypotOk()) {
        $error = 'Provjera nije uspjela. Osvježite stranicu i pokušajte ponovno.';
    } elseif (!Security::rateLimit('raskid:' . client_ip(), 5, 600)) {
        $error = 'Previše pokušaja. Pričekajte nekoliko minuta.';
    } else {
        Security::recordAttempt('raskid:' . client_ip());
        $orderNo = mb_substr(trim((string) $_POST['order_number']), 0, 20);
        $email = mb_strtolower(trim((string) $_POST['email']));
        $reason = mb_substr(trim((string) $_POST['reason']), 0, 500);

        $order = $db->fetch(
            'SELECT * FROM orders WHERE order_number = :n AND customer_email = :e',
            [':n' => $orderNo, ':e' => $email]
        );
        if (!$order) {
            $error = 'Narudžba s tim brojem i e-mail adresom nije pronađena. Provjerite podatke iz potvrde narudžbe.';
        } elseif ($order['withdrawal_requested_at']) {
            $done = ['ts' => $order['withdrawal_requested_at'], 'number' => $order['order_number'], 'repeat' => true];
        } else {
            $ts = date('Y-m-d H:i:s');
            $db->update('orders', [
                'withdrawal_requested_at' => $ts,
                'withdrawal_reason'       => $reason ?: null,
            ], 'id = :id', [':id' => $order['id']]);

            $tsHr = date('d.m.Y. \u\ H:i:s', strtotime($ts));
            // Potvrda kupcu — zakonski trajni medij s točnim datumom i vremenom
            Mailer::send(
                $order['customer_email'],
                'Potvrda primitka zahtjeva za raskid ugovora — narudžba ' . $order['order_number'],
                '<h2 style="margin:0 0 8px">Zahtjev za raskid ugovora je zaprimljen ✓</h2>'
                . '<p>Potvrđujemo primitak vašeg zahtjeva za jednostrani raskid ugovora za narudžbu '
                . '<strong>' . e($order['order_number']) . '</strong>.</p>'
                . '<p style="font-size:16px"><strong>Datum i vrijeme zaprimanja: ' . e($tsHr) . '</strong></p>'
                . ($reason ? '<p>Vaš navedeni razlog: ' . e($reason) . '</p>' : '')
                . '<p>Trgovac će vas kontaktirati radi povrata robe i sredstava sukladno Zakonu o zaštiti potrošača. '
                . 'Sačuvajte ovu poruku kao dokaz o pravovremenom raskidu.</p>'
            );
            // Obavijest vlasniku
            @Mailer::send(
                s('shop_email', ''),
                '⚠ Zahtjev za RASKID ugovora — ' . $order['order_number'],
                '<p>Kupac <strong>' . e($order['customer_name']) . '</strong> (' . e($order['customer_email']) . ') zatražio je '
                . 'jednostrani raskid ugovora za narudžbu <strong>' . e($order['order_number']) . '</strong> ('
                . fmt_price($order['total']) . ') dana ' . e($tsHr) . '.</p>'
                . ($reason ? '<p>Razlog: ' . e($reason) . '</p>' : '')
                . '<p>Zakonska obveza: vratite kupcu sredstva u roku 14 dana od primitka robe/dokaza o slanju. '
                . 'Detalji narudžbe u administraciji trgovine.</p>'
            );
            $done = ['ts' => $ts, 'number' => $order['order_number'], 'repeat' => false];
        }
    }
}

$pageTitle = 'Jednostrani raskid ugovora';
$pageDesc = 'Online obrazac za jednostrani raskid ugovora sklopljenog na daljinu — ' . shop_name();
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="section-head" style="margin-top:26px"><h1 class="section-title">Jednostrani raskid ugovora</h1></div>

  <div class="checkout-grid" style="max-width:980px;margin:0 auto">
    <div class="card">
      <?php if ($done): ?>
        <div class="alert alert-success" style="font-size:15px">
          <strong>✓ Zahtjev za raskid je <?= $done['repeat'] ? 'već ranije zaprimljen' : 'zaprimljen' ?>.</strong><br><br>
          Narudžba: <strong><?= e($done['number']) ?></strong><br>
          Datum i vrijeme zaprimanja: <strong><?= e(date('d.m.Y. \u\ H:i:s', strtotime($done['ts']))) ?></strong><br><br>
          Potvrdu s ovim podacima poslali smo i na vašu e-mail adresu — ona je vaš dokaz o pravovremenom raskidu.
          Kontaktirat ćemo vas radi povrata robe i sredstava.
        </div>
        <a href="<?= e(url('')) ?>" class="btn btn-ghost" style="width:100%">← Natrag na početnu</a>
      <?php else: ?>
        <h3>Obrazac za raskid</h3>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <?= csrf_field() ?><?= hp_fields() ?>
          <div class="form-grid">
            <div class="full"><label class="f-label">Broj narudžbe * (npr. WEB-2026-00001 — piše u potvrdi narudžbe)</label>
              <input class="f-input" name="order_number" required maxlength="20" placeholder="WEB-2026-00001"></div>
            <div class="full"><label class="f-label">E-mail korišten pri narudžbi *</label>
              <input class="f-input" type="email" name="email" required maxlength="190"></div>
            <div class="full"><label class="f-label">Razlog (nije obavezan — raskid ne morate obrazlagati)</label>
              <textarea class="f-input" name="reason" rows="2" maxlength="500"></textarea></div>
          </div>
          <button class="btn" style="width:100%;margin-top:16px">Pošalji zahtjev za raskid</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Vaša prava</h3>
      <div style="font-size:14px;line-height:1.75;color:var(--c-muted)">
        <p>Ugovor sklopljen na daljinu (online kupnju) imate pravo jednostrano raskinuti
        <strong>u roku 14 dana od primitka robe</strong>, bez navođenja razloga
        (Zakon o zaštiti potrošača).</p>
        <p>Nakon slanja zahtjeva odmah dobivate <strong>automatsku potvrdu s točnim datumom i
        vremenom</strong> na e-mail — to je vaš dokaz o pravovremenom raskidu.</p>
        <p>Robu vraćate trgovcu bez nepotrebnog odgađanja, a trgovac vam vraća uplaćena
        sredstva u zakonskom roku.</p>
        <p>Više informacija: <a href="<?= e(url('s/uvjeti-koristenja')) ?>">uvjeti korištenja</a> ·
        <a href="<?= e(url('s/dostava-i-povrat')) ?>">dostava i povrat</a></p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
