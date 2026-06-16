<?php
/**
 * Customer — računi kupaca trgovine (registracija, prijava, profil).
 * Sesija: $_SESSION['customer_id']. Potpuno odvojeno od admin sesije.
 */

class Customer
{
    /** Trenutno prijavljeni kupac ili null. */
    public static function current(): ?array
    {
        if (empty($_SESSION['customer_id'])) return null;
        $c = Database::instance()->fetch(
            'SELECT * FROM customers WHERE id = :id AND is_active = 1',
            [':id' => (int) $_SESSION['customer_id']]
        );
        if (!$c) unset($_SESSION['customer_id']);
        return $c ?: null;
    }

    public static function isLoggedIn(): bool
    {
        return self::current() !== null;
    }

    /** @return array{ok: bool, error: ?string} */
    public static function register(string $email, string $password, string $name): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Unesite ispravnu e-mail adresu.'];
        if (strlen($password) < 8) return ['ok' => false, 'error' => 'Lozinka mora imati najmanje 8 znakova.'];
        if (mb_strlen(trim($name)) < 3) return ['ok' => false, 'error' => 'Unesite ime i prezime.'];

        $name = mb_substr(trim($name), 0, 200);
        $db = Database::instance();
        $existing = $db->fetch('SELECT id, email_verified_at FROM customers WHERE email = :e', [':e' => $email]);
        if ($existing) {
            // Nepotvrđen → osvježi podatke zadnjeg pokušaja i pošalji novu potvrdu
            // (aktivacija ionako stiže samo na e-mail vlasnika). Potvrđen → na prijavu.
            if ($existing['email_verified_at'] === null) {
                $db->update('customers', [
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'name' => $name,
                ], 'id = :id', [':id' => (int) $existing['id']]);
                self::sendVerification((int) $existing['id'], $email, $name);
                return ['ok' => true, 'error' => null, 'verify' => true];
            }
            return ['ok' => false, 'error' => 'Račun s tom e-mail adresom već postoji — prijavite se.'];
        }
        $id = (int) $db->insert('customers', [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $name,
        ]);
        // Poveži ranije gost-narudžbe s istim e-mailom (povijest odmah vidljiva)
        $db->query('UPDATE orders SET customer_id = :c WHERE customer_email = :e AND customer_id IS NULL', [':c' => $id, ':e' => $email]);

        // NE prijavljujemo automatski — račun se aktivira tek potvrdom e-maila
        self::sendVerification($id, $email, $name);
        return ['ok' => true, 'error' => null, 'verify' => true];
    }

    /** @return array{ok: bool, error: ?string} */
    public static function login(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $rlKey = 'clogin:' . client_ip();
        if (!Security::rateLimit($rlKey, 8, 600)) {
            return ['ok' => false, 'error' => 'Previše pokušaja prijave. Pričekajte nekoliko minuta.'];
        }
        $c = Database::instance()->fetch('SELECT * FROM customers WHERE email = :e AND is_active = 1', [':e' => $email]);
        if (!$c || !password_verify($password, $c['password_hash'])) {
            Security::recordAttempt($rlKey);
            return ['ok' => false, 'error' => 'Pogrešna e-mail adresa ili lozinka.'];
        }
        if (empty($c['email_verified_at'])) {
            // Lozinka ispravna, ali e-mail nije potvrđen — ne prijavljujemo
            return ['ok' => false, 'error' => 'Potvrdite e-mail adresu prije prijave (provjerite inbox i spam).', 'code' => 'unverified'];
        }
        Security::clearAttempts($rlKey);
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) $c['id'];
        Database::instance()->update('customers', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $c['id']]);
        return ['ok' => true, 'error' => null];
    }

    public static function logout(): void
    {
        unset($_SESSION['customer_id']);
        session_regenerate_id(true);
    }

    /**
     * GDPR brisanje računa: osobni podaci se ANONIMIZIRAJU, a porezni/financijski
     * zapisi (narudžbe + fiskalni računi) se ZADRŽAVAJU (zakonska obveza čuvanja) —
     * samo im se uklone osobni podaci. Račun se trajno deaktivira (nema više prijave).
     */
    public static function anonymize(int $id): void
    {
        $db = Database::instance();
        $c = $db->fetch('SELECT email FROM customers WHERE id = :id', [':id' => $id]);
        if (!$c) return;
        $anonEmail = 'obrisano+' . $id . '@anonimizirano.invalid';

        // Narudžbe: ukloni osobne podatke, ZADRŽI broj/iznose/fiskalne podatke (zakon)
        $db->query(
            "UPDATE orders SET customer_name = 'Obrisani korisnik', customer_email = :ae,
                 customer_phone = NULL, address = '—', city = '—', postal_code = '—', note = NULL
             WHERE customer_id = :id",
            [':ae' => $anonEmail, ':id' => $id]
        );
        // Newsletter: ukloni e-mail
        try { $db->query('DELETE FROM newsletter_subscribers WHERE email = :e', [':e' => $c['email']]); } catch (Throwable $e) {}
        // Profil: anonimiziraj, poništi lozinku, deaktiviraj
        $db->update('customers', [
            'name' => 'Obrisani korisnik',
            'email' => $anonEmail,
            'phone' => null, 'address' => null, 'city' => null, 'postal_code' => null,
            'password_hash' => password_hash(bin2hex(random_bytes(18)), PASSWORD_DEFAULT),
            'is_active' => 0,
            'deleted_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', [':id' => $id]);
    }

    // ── Potvrda e-maila + reset lozinke (sigurni jednokratni tokeni) ──────

    /** Kreiraj token; u bazu ide SAMO SHA-256 hash, plaintext se vraća za link. */
    private static function createToken(int $customerId, string $type, int $ttlMinutes): string
    {
        $db = Database::instance();
        $db->query("UPDATE customer_tokens SET used_at = NOW() WHERE customer_id = :c AND type = :t AND used_at IS NULL", [':c' => $customerId, ':t' => $type]);
        $token = bin2hex(random_bytes(32));
        $db->insert('customer_tokens', [
            'customer_id' => $customerId,
            'type'        => $type,
            'token_hash'  => hash('sha256', $token),
            'expires_at'  => date('Y-m-d H:i:s', time() + $ttlMinutes * 60),
        ]);
        return $token;
    }

    /** Provjeri + potroši token (jednokratno). Vrati customer_id ili null. */
    private static function consumeToken(string $token, string $type): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $db = Database::instance();
        $row = $db->fetch(
            "SELECT id, customer_id FROM customer_tokens
             WHERE token_hash = :h AND type = :t AND used_at IS NULL AND expires_at > NOW() LIMIT 1",
            [':h' => hash('sha256', $token), ':t' => $type]
        );
        if (!$row) return null;
        $db->update('customer_tokens', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => (int) $row['id']]);
        return (int) $row['customer_id'];
    }

    private static function sendVerification(int $id, string $email, string $name): void
    {
        $url = SITE_URL . '/potvrda-maila.php?token=' . self::createToken($id, 'verify', 1440);
        $html = '<h2 style="margin:0 0 10px">Potvrdite e-mail adresu</h2>'
            . '<p>Pozdrav ' . e($name) . ', hvala na registraciji u trgovini <strong>' . e(shop_name()) . '</strong>.</p>'
            . '<p>Kliknite za aktivaciju računa:</p>'
            . '<p><a href="' . e($url) . '" style="background:#7c3aed;color:#fff;padding:12px 24px;border-radius:9px;text-decoration:none;font-weight:bold;display:inline-block">Potvrdi e-mail</a></p>'
            . '<p style="color:#6b7280;font-size:12px">Link vrijedi 24 sata. Ako se niste registrirali, zanemarite ovu poruku.</p>';
        // Slanje ne smije srušiti registraciju (npr. SMTP pad) — tiho logiraj
        try { Mailer::send($email, 'Potvrda registracije — ' . shop_name(), $html); }
        catch (Throwable $e) { error_log('[Customer] verify mail: ' . $e->getMessage()); }
    }

    /** Potvrdi e-mail tokenom; na uspjeh aktivira i prijavi kupca. */
    public static function verifyEmail(string $token): bool
    {
        $cid = self::consumeToken($token, 'verify');
        if (!$cid) return false;
        Database::instance()->update('customers', ['email_verified_at' => date('Y-m-d H:i:s')], 'id = :id AND email_verified_at IS NULL', [':id' => $cid]);
        session_regenerate_id(true);
        $_SESSION['customer_id'] = $cid;
        return true;
    }

    /** Ponovno pošalji potvrdu (samo ako račun postoji i nije potvrđen) — tiho. */
    public static function resendVerification(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $c = Database::instance()->fetch('SELECT id, name FROM customers WHERE email = :e AND is_active = 1 AND email_verified_at IS NULL', [':e' => $email]);
        if ($c) self::sendVerification((int) $c['id'], $email, (string) $c['name']);
    }

    /** Zatraži reset lozinke — uvijek tiho (bez otkrivanja postoji li račun). */
    public static function requestReset(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $c = Database::instance()->fetch('SELECT id, name FROM customers WHERE email = :e AND is_active = 1 AND email_verified_at IS NOT NULL', [':e' => $email]);
        if (!$c) return;
        $url = SITE_URL . '/reset-lozinke.php?token=' . self::createToken((int) $c['id'], 'reset', 60);
        $html = '<h2 style="margin:0 0 10px">Reset lozinke</h2>'
            . '<p>Pozdrav ' . e((string) $c['name']) . ', zatražen je reset lozinke za vaš račun u trgovini <strong>' . e(shop_name()) . '</strong>.</p>'
            . '<p><a href="' . e($url) . '" style="background:#7c3aed;color:#fff;padding:12px 24px;border-radius:9px;text-decoration:none;font-weight:bold;display:inline-block">Postavi novu lozinku</a></p>'
            . '<p style="color:#6b7280;font-size:12px">Link vrijedi 1 sat. Ako niste vi tražili, zanemarite — lozinka ostaje nepromijenjena.</p>';
        try { Mailer::send($email, 'Reset lozinke — ' . shop_name(), $html); }
        catch (Throwable $e) { error_log('[Customer] reset mail: ' . $e->getMessage()); }
    }

    /** Je li reset token još valjan (bez trošenja) — za prikaz forme na GET-u. */
    public static function resetTokenValid(string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return false;
        return (bool) Database::instance()->fetchColumn(
            "SELECT 1 FROM customer_tokens WHERE token_hash = :h AND type = 'reset' AND used_at IS NULL AND expires_at > NOW() LIMIT 1",
            [':h' => hash('sha256', $token)]
        );
    }

    /** Postavi novu lozinku tokenom (jednokratno); poništi ostale tokene. */
    public static function resetPassword(string $token, string $newPassword): array
    {
        if (strlen($newPassword) < 8) return ['ok' => false, 'error' => 'Lozinka mora imati najmanje 8 znakova.'];
        $cid = self::consumeToken($token, 'reset');
        if (!$cid) return ['ok' => false, 'error' => 'Link je nevažeći ili je istekao. Zatražite novi.'];
        $db = Database::instance();
        $db->update('customers', ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)], 'id = :id', [':id' => $cid]);
        $db->query("UPDATE customer_tokens SET used_at = NOW() WHERE customer_id = :c AND used_at IS NULL", [':c' => $cid]);
        return ['ok' => true, 'error' => null];
    }
}
