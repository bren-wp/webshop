<?php
/**
 * Security — sigurnosni headeri, rate limiting, validacija uploada, honeypot.
 */

class Security
{
    public static function applyHeaders(): void
    {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com; "
            . "script-src 'self' 'unsafe-inline'; "
            . "connect-src 'self'; object-src 'none'; frame-ancestors 'self'; base-uri 'self'; "
            . "form-action 'self' https://checkout.stripe.com");
        if (is_https()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    /**
     * Rate limit po ključu (npr. "login:1.2.3.4"). Vraća true ako je JOŠ dopušteno.
     */
    public static function rateLimit(string $key, int $max, int $windowSec): bool
    {
        $db = Database::instance();
        $since = date('Y-m-d H:i:s', time() - $windowSec);
        $count = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM login_attempts WHERE attempt_key = :k AND created_at > :s',
            [':k' => $key, ':s' => $since]
        );
        return $count < $max;
    }

    public static function recordAttempt(string $key): void
    {
        $db = Database::instance();
        $db->insert('login_attempts', [
            'attempt_key' => substr($key, 0, 190),
            'ip'          => client_ip(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        // Povremeno čišćenje starih zapisa (1% šanse, bez crona)
        if (random_int(1, 100) === 1) {
            $db->query("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
        }
    }

    public static function clearAttempts(string $key): void
    {
        Database::instance()->delete('login_attempts', 'attempt_key = :k', [':k' => $key]);
    }

    /** Honeypot provjera (uz hp_fields() helper u formi). */
    public static function honeypotOk(): bool
    {
        if (!empty($_POST['website'])) return false; // bot je popunio skriveno polje
        $ts = (int) ($_POST['_ts'] ?? 0);
        if ($ts > 0 && (time() - $ts) < 3) return false; // forma poslana < 3 sekunde — bot
        return true;
    }

    /**
     * Validacija slike za upload. Dozvoljeno: jpg, png, webp. Max 5 MB.
     * @return array{ok: bool, error: ?string, ext: ?string}
     */
    public static function validateImageUpload(array $file, int $maxBytes = 5242880): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Datoteka nije ispravno učitana.', 'ext' => null];
        }
        if (($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'error' => 'Datoteka je prevelika (max 5 MB).', 'ext' => null];
        }
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return ['ok' => false, 'error' => 'Datoteka nije valjana slika.', 'ext' => null];
        }
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $mime = $info['mime'] ?? '';
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'error' => 'Dozvoljeni formati: JPG, PNG, WEBP.', 'ext' => null];
        }
        return ['ok' => true, 'error' => null, 'ext' => $allowed[$mime]];
    }

    /** Nasumično ime datoteke za upload. */
    public static function randomFileName(string $ext): string
    {
        return date('Ym') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    }
}
