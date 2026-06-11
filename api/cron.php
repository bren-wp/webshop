<?php
/**
 * Cron endpoint — pozivati svakih 5–15 min (hosting cron ili vanjski servis):
 *   curl "https://tvoja-domena.hr/api/cron.php?token=CRON_TOKEN"
 *
 * Radi: (1) retry fiskalizacija u 48h prozoru, (2) osvježavanje đurđa keša + heartbeat,
 *       (3) auto-sync kataloga jednom dnevno (?sync=1 za prisilni full sync).
 */
require_once __DIR__ . '/../core/bootstrap.php';

if (!defined('CRON_TOKEN') || CRON_TOKEN === '' || !hash_equals(CRON_TOKEN, (string) ($_GET['token'] ?? ''))) {
    json_out(['ok' => false, 'error' => 'forbidden'], 403);
}

set_time_limit(280);
$out = ['ok' => true, 'ts' => date('c')];

// 1. Fiskalni retry queue
try {
    $results = Fiscalizer::retryDue($db, 10);
    $out['fiscal_retries'] = count($results);
    foreach ($results as $oid => $r) {
        if (!empty($r['success'])) $out['fiscal_ok'][] = $oid;
    }
} catch (Throwable $e) {
    $out['fiscal_error'] = $e->getMessage();
    error_log('[cron] fiscal: ' . $e->getMessage());
}

// 2. Đurđa keš + heartbeat (interno ograničeno na 6h/24h)
try {
    $out['djurdja_refresh'] = Djurdja::refresh(false);
    $out['djurdja_status'] = Djurdja::status();
} catch (Throwable $e) {
    $out['djurdja_error'] = $e->getMessage();
}

// 3. Katalog: prisilno (?sync=1|full) ili automatski jednom u 24 h
try {
    $force = isset($_GET['sync']);
    $last = strtotime((string) s('catalog_synced_at', ''));
    if ($force || !$last || (time() - $last) > 24 * 3600) {
        $res = Sync::run($force && ($_GET['sync'] === 'full'));
        $out['sync'] = $res['ok'] ? $res['message'] : ('GREŠKA: ' . $res['message']);
    } else {
        $out['sync'] = 'preskočeno (svježe)';
    }
} catch (Throwable $e) {
    $out['sync_error'] = $e->getMessage();
}

json_out($out);
