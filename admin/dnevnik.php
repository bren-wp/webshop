<?php
/** Dnevnik (audit log) — sigurnosne i administratorske akcije. */
require_once __DIR__ . '/templates/init.php';

$rows = Audit::recent(300);
$pageTitle = 'Dnevnik (audit)';
require __DIR__ . '/templates/header.php';

$labels = [
    'admin_login' => ['green', 'Prijava'],
    'admin_login_failed' => ['red', 'Neuspjela prijava'],
    'admin_password_changed' => ['amber', 'Promjena lozinke'],
    'settings_updated' => ['blue', 'Postavke'],
    'shop_reset' => ['red', 'Reset trgovine'],
    'cron_token_rotated' => ['amber', 'Rotacija cron tokena'],
    'order_marked_paid' => ['blue', 'Ručno plaćeno'],
    'fiscalize' => ['blue', 'Fiskalizacija'],
    'storno' => ['amber', 'Storno'],
];
?>
<div class="acard">
  <h3>Dnevnik akcija <span class="sub">(zadnjih <?= count($rows) ?>)</span></h3>
  <p class="sub">Bilježe se prijave u administraciju (uspješne i neuspjele), promjene postavki i lozinke, reset trgovine, rotacija cron tokena te fiskalne akcije.</p>
  <table class="atable" style="font-size:13px">
    <thead><tr><th>Vrijeme</th><th>Admin</th><th>Akcija</th><th>Detalj</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): [$bc, $bt] = $labels[$r['action']] ?? ['gray', $r['action']]; ?>
      <tr>
        <td style="white-space:nowrap"><?= e(date('d.m.Y H:i:s', strtotime((string) $r['created_at']))) ?></td>
        <td><?= e($r['admin_name'] ?? '—') ?></td>
        <td><span class="badge <?= e($bc) ?>"><?= e($bt) ?></span></td>
        <td><?= e($r['detail'] ?? ($r['entity_type'] ? $r['entity_type'] . ' #' . $r['entity_id'] : '—')) ?></td>
        <td style="color:#9ca3af;font-size:12px"><?= e($r['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" style="color:#8b90a0">Još nema zapisa.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
