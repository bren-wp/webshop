<?php
require_once __DIR__ . '/templates/init.php';

$diag = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    set_time_limit(280);

    if (($_POST['action'] ?? '') === 'diag') {
        // ── Dijagnostika veze: testira svaki endpoint zasebno i kaže TOČNO što ne radi ──
        $diag = [];
        $client = DjurdjaClient::fromSettings();
        if (!$client) {
            $diag[] = ['API ključ', false, 'Ključ nije konfiguriran — unesite ga na stranici Đurđa veza.'];
        } else {
            $probe = function (string $label, callable $fn) use (&$diag) {
                try {
                    $r = $fn();
                    $diag[] = [$label, true, $r];
                } catch (DjurdjaApiException $e) {
                    $hint = '';
                    if ($e->httpStatus === 404) $hint = ' → ruta NE POSTOJI na serveru: shop modul nije deployan (docs/DEPLOY-DJURDJA.md)!';
                    elseif (in_array($e->httpStatus, [401, 403], true)) $hint = ' → ključ odbijen: provjerite pk_/sk_ par, status ključa i IP whitelist u đurđi.';
                    elseif ($e->httpStatus === 0) $hint = ' → mrežni problem (server nedostupan / SSL).';
                    $diag[] = [$label, false, 'HTTP ' . $e->httpStatus . ' · ' . ($e->apiErrorCode ?: '—') . ' · ' . $e->getMessage() . $hint];
                } catch (Throwable $e) {
                    $diag[] = [$label, false, $e->getMessage()];
                }
            };
            $probe('1. /health (server živ, bez autentikacije)', function () use ($client) {
                $h = $client->health();
                return 'OK — gateway v' . ($h['version'] ?? '?');
            });
            $probe('2. /me (valjanost ključa + firma)', function () use ($client) {
                $me = $client->me();
                return 'OK — ' . ($me['companyName'] ?? '?') . ', OIB ' . ($me['companyOib'] ?? '?')
                    . ', certifikat: ' . (!empty($me['hasCertificate']) ? 'da' : 'NE') . ', mod ključa: ' . ($me['mode'] ?? '?');
            });
            $probe('3. /shop/account (plan + kvota + features)', function () use ($client) {
                $a = $client->account();
                $u = $a['usage']['DOCUMENT_CREATE'] ?? null;
                return 'OK — plan: ' . ($a['plan']['name'] ?? '?')
                    . ($u ? (', kvota ' . $u['used'] . '/' . $u['limit']) : ', kvota neograničena')
                    . ', shopStatus: ' . ($a['shopStatus'] ?? '?');
            });
            $probe('4. /shop/catalog (artikli za sinkronizaciju)', function () use ($client) {
                $c = $client->catalog();
                $n = count($c['products'] ?? []);
                $k = count($c['categories'] ?? []);
                return $n === 0
                    ? "OK, ali đurđa je vratila 0 artikala ($k kategorija) — u đurđi nemate aktivnih artikala (ili nijedan nije označen za web)."
                    : "OK — $n artikala, $k kategorija (ukupno: " . ($c['total'] ?? '?') . ')';
            });
            $probe('5. /shop/heartbeat (registracija trgovine u đurđi)', function () use ($client) {
                $h = $client->heartbeat([
                    'domain' => strtolower($_SERVER['HTTP_HOST'] ?? ''),
                    'baseUrl' => SITE_URL,
                    'version' => SHOP_VERSION,
                    'shopName' => Settings::get('shop_name', ''),
                ]);
                Settings::set('djurdja_heartbeat_at', date('Y-m-d H:i:s'));
                return 'OK — status: ' . ($h['status'] ?? '?') . ' (trgovina je sada vidljiva u đurđi → kartica Web trgovina)';
            });
        }
    } else {
        $full = !empty($_POST['full']);
        $res = Sync::run($full);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? '✓ ' . $res['message'] : 'Sinkronizacija NIJE uspjela: ' . $res['message']);
        redirect('admin/sync.php');
    }
}

$logs = $db->fetchAll('SELECT * FROM sync_log ORDER BY id DESC LIMIT 12');
$counts = [
    'products' => (int) $db->fetchColumn('SELECT COUNT(*) FROM products WHERE is_orphaned = 0'),
    'visible'  => (int) $db->fetchColumn('SELECT COUNT(*) FROM products WHERE is_visible = 1 AND is_orphaned = 0'),
    'noimg'    => (int) $db->fetchColumn('SELECT COUNT(*) FROM products p WHERE p.is_orphaned = 0 AND NOT EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id)'),
    'cats'     => (int) $db->fetchColumn('SELECT COUNT(*) FROM categories'),
];

$pageTitle = 'Sinkronizacija kataloga';
require __DIR__ . '/templates/header.php';
?>
<div class="kpis">
  <div class="kpi"><div class="l">Artikala</div><div class="v"><?= $counts['products'] ?></div></div>
  <div class="kpi"><div class="l">Vidljivih u trgovini</div><div class="v"><?= $counts['visible'] ?></div></div>
  <div class="kpi <?= $counts['noimg'] > 0 ? 'warn' : '' ?>"><div class="l">Bez slike</div><div class="v"><?= $counts['noimg'] ?></div></div>
  <div class="kpi"><div class="l">Kategorija</div><div class="v"><?= $counts['cats'] ?></div></div>
</div>

<div class="acard">
  <h3>Pokreni sinkronizaciju</h3>
  <p class="sub">Povlači artikle i kategorije iz vašeg MojaĐurđa računa. Lokalna obogaćivanja (slike, opisi, SEO) se NE diraju. Zadnji sync: <strong><?= e(s('catalog_synced_at', 'nikad')) ?></strong></p>
  <form method="post" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <?= csrf_field() ?>
    <button class="abtn">🔄 Sinkroniziraj sada</button>
    <label class="acheck" style="margin:0"><input type="checkbox" name="full" value="1"> Puni sync (sve + označi obrisane artikle)</label>
  </form>
  <form method="post" style="margin-top:10px">
    <?= csrf_field() ?><input type="hidden" name="action" value="diag">
    <button class="abtn ghost sm">🩺 Dijagnostika veze (testira svaki endpoint i kaže točno što ne radi)</button>
  </form>

  <?php if ($diag !== null): ?>
    <div style="margin-top:14px;display:grid;gap:8px">
      <?php foreach ($diag as [$label, $ok, $msg]): ?>
        <div class="alert <?= $ok ? 'alert-success' : 'alert-error' ?>" style="font-size:13px;margin:0">
          <strong><?= $ok ? '✓' : '✗' ?> <?= e($label) ?></strong><br><?= e($msg) ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="alert alert-info" style="margin-top:14px;font-size:12.5px">
    💡 Automatski sync jednom dnevno: postavite hosting cron na<br>
    <code><?= e(SITE_URL . '/api/cron.php?token=' . CRON_TOKEN) ?></code> (svakih 5–15 min — radi i fiskalne retry-e).
  </div>
</div>

<div class="acard">
  <h3>Povijest</h3>
  <table class="atable">
    <thead><tr><th>Početak</th><th>Tip</th><th>Status</th><th>Rezultat</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td style="white-space:nowrap"><?= e($l['started_at']) ?></td>
        <td><?= e($l['type']) ?></td>
        <td><span class="badge <?= $l['status'] === 'done' ? 'green' : ($l['status'] === 'error' ? 'red' : 'amber') ?>"><?= e($l['status']) ?></span></td>
        <td style="font-size:12.5px"><?= e($l['message'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:24px">Još nije bilo sinkronizacija.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
