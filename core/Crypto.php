<?php
/**
 * Crypto — AES-256-GCM enkripcija tajni koje se čuvaju u bazi
 * (đurđa API secret, Stripe secret key, webhook secret).
 *
 * Master ključ: ENCRYPTION_KEY u config/config.php (64 hex znamenke),
 * generira ga installer. Format zapisa: "v1:" + base64(iv(12) + tag(16) + ciphertext).
 */

class Crypto
{
    private static function key(): string
    {
        if (!defined('ENCRYPTION_KEY') || !preg_match('/^[0-9a-f]{64}$/i', ENCRYPTION_KEY)) {
            throw new RuntimeException('ENCRYPTION_KEY nije ispravno konfiguriran (64 hex znamenke).');
        }
        return hex2bin(ENCRYPTION_KEY);
    }

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') return null;
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ct === false) {
            throw new RuntimeException('Enkripcija nije uspjela.');
        }
        return 'v1:' . base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null || $stored === '') return null;
        if (strpos($stored, 'v1:') !== 0) return null;
        $raw = base64_decode(substr($stored, 3), true);
        if ($raw === false || strlen($raw) < 29) return null;
        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    /** Maskirani prikaz tajne za UI: "••••1234" (zadnja 4 znaka). */
    public static function hint(?string $stored): ?string
    {
        $plain = self::decrypt($stored);
        if (!$plain) return null;
        return '••••••••' . substr($plain, -4);
    }
}
