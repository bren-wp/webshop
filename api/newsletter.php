<?php
/**
 * API: prijava na newsletter (double opt-in token spremljen za buduću potvrdu).
 */
require_once __DIR__ . '/../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Samo POST.'], 405);
csrf_check();

$rl = 'newsletter:' . client_ip();
if (!Security::rateLimit($rl, 5, 600)) json_out(['ok' => false, 'error' => 'Previše pokušaja.'], 429);
Security::recordAttempt($rl);

$email = trim((string) ($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Unesite ispravnu e-mail adresu.'], 422);
}

$email = mb_strtolower(mb_substr($email, 0, 190));
try {
    $existing = $db->fetch('SELECT id, is_confirmed FROM newsletter_subscribers WHERE email = :e', [':e' => $email]);
    if ($existing && (int) $existing['is_confirmed'] === 1) {
        json_out(['ok' => true, 'message' => 'Već ste prijavljeni na newsletter. 💌']);
    }
    $token = bin2hex(random_bytes(24));
    if ($existing) {
        $db->update('newsletter_subscribers', ['token' => $token], 'id = :id', [':id' => (int) $existing['id']]);
    } else {
        $db->insert('newsletter_subscribers', ['email' => $email, 'token' => $token, 'is_confirmed' => 0]);
    }
} catch (Throwable $e) {
    error_log('[newsletter] ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Greška, pokušajte kasnije.'], 500);
}

// Double opt-in (GDPR): bez potvrde e-mailom NE šaljemo newsletter.
$confirmUrl = SITE_URL . '/newsletter-potvrda.php?token=' . $token;
$unsubUrl   = SITE_URL . '/newsletter-odjava.php?token=' . $token;
$html = '<h2 style="margin:0 0 10px">Potvrdite prijavu na newsletter</h2>'
    . '<p>Ova je adresa prijavljena na newsletter trgovine <strong>' . e(shop_name()) . '</strong>. Kliknite za potvrdu:</p>'
    . '<p><a href="' . e($confirmUrl) . '" style="background:#7c3aed;color:#fff;padding:12px 24px;border-radius:9px;text-decoration:none;font-weight:bold;display:inline-block">Potvrdi prijavu</a></p>'
    . '<p style="color:#6b7280;font-size:12px">Ako se niste prijavili, zanemarite ovu poruku — bez potvrde ne šaljemo ništa. Odjava: <a href="' . e($unsubUrl) . '">ovdje</a>.</p>';
try { Mailer::send($email, 'Potvrdite prijavu na newsletter — ' . shop_name(), $html); } catch (Throwable $e) {}

json_out(['ok' => true, 'message' => 'Gotovo! Provjerite e-mail i kliknite link za potvrdu prijave.']);
