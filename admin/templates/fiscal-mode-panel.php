<?php
/**
 * Panel statusa fiskalnog moda (DEMO/PROD) + uvjeti — "prekidač s uvjetima".
 * Mod određuje đurđa certifikat firme; shop ga ne mijenja, samo prikazuje i
 * provjerava da API ključ i Stripe odgovaraju. Koriste ga admin/djurdja.php i
 * admin/placanja.php. Očekuje da je klasa Djurdja dostupna.
 */
$fs = Djurdja::fiscalStatus();
$isProd = $fs['mode'] === 'PROD';
?>
<div class="acard">
  <h3>🧾 Mod fiskalizacije</h3>
  <p style="margin:0 0 8px">Vaša firma je u đurđi u
    <span class="badge <?= $isProd ? 'green' : 'amber' ?>"><?= $isProd ? 'PRODUKCIJSKOM' : 'DEMO (testnom)' ?> modu</span>
    <?= $fs['certKnown']
        ? 'prema vašem FINA certifikatu u đurđi.'
        : '— privremeno prema API ključu (za točan status deployajte đurđa /shop/account).' ?>
  </p>
  <p class="sub" style="margin:0 0 4px">Uvjeti za <?= $isProd ? 'produkcijski (stvarni)' : 'demo (testni)' ?> rad:</p>
  <ul style="margin:0 0 10px;padding-left:18px;line-height:1.85">
    <?php foreach ($fs['checks'] as $c): ?>
      <li><span style="color:<?= $c['ok'] ? '#16a34a' : '#dc2626' ?>;font-weight:700"><?= $c['ok'] ? '✓' : '✗' ?></span>
        <?= e($c['label']) ?> <span class="sub">(<?= e($c['note']) ?>)</span></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($fs['aligned']): ?>
    <div class="alert alert-success" style="margin:0">Sve je usklađeno — fiskalizacija radi u <strong><?= e($fs['mode'] ?? '—') ?></strong> modu.</div>
  <?php else: ?>
    <div class="alert alert-error" style="margin:0">Nije usklađeno — fiskalizacija je <strong>blokirana</strong> dok ne ispravite ✗ stavke. Mod (DEMO/PROD) se mijenja u <strong>đurđi</strong> (upload odgovarajućeg FINA certifikata), a ovdje upišite njemu odgovarajuće (test/live) ključeve. Tako se lažni i pravi računi nikad ne miješaju.</div>
  <?php endif; ?>
</div>
