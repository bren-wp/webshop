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

        $db = Database::instance();
        if ($db->fetchColumn('SELECT id FROM customers WHERE email = :e', [':e' => $email])) {
            return ['ok' => false, 'error' => 'Račun s tom e-mail adresom već postoji — prijavite se.'];
        }
        $id = (int) $db->insert('customers', [
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'name' => mb_substr(trim($name), 0, 200),
        ]);
        // Poveži ranije gost-narudžbe s istim e-mailom (povijest odmah vidljiva)
        $db->query('UPDATE orders SET customer_id = :c WHERE customer_email = :e AND customer_id IS NULL', [':c' => $id, ':e' => $email]);

        session_regenerate_id(true);
        $_SESSION['customer_id'] = $id;
        return ['ok' => true, 'error' => null];
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
}
