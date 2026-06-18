<?php
/**
 * ĐurđaShop — konfiguracija (PREDLOŽAK)
 *
 * Instalacijski čarobnjak (install/) automatski generira config/config.php.
 * Ovu datoteku NE uređuj — služi samo kao referenca formata.
 * config.php NIKAD ne ide u git (sadrži tajne).
 */

// ── Baza podataka ──
define('DB_HOST', 'localhost');
define('DB_NAME', 'djurdjashop');
define('DB_USER', 'root');
define('DB_PASS', '');

// ── Master ključ za AES-256-GCM enkripciju tajni u bazi (đurđa secret, Stripe secret key) ──
// TOČNO 64 hex znamenke (32 bajta). Installer generira: bin2hex(random_bytes(32))
// VAŽNO: ako se ključ izgubi/promijeni, tajne u bazi postaju nečitljive i moraju se ponovno unijeti.
define('ENCRYPTION_KEY', '');

// ── Tajni token za cron endpoint (api/cron.php?token=...) ──
define('CRON_TOKEN', '');

// ── Bazna putanja instalacije ('' za root domene, '/shop' za poddirektorij) ──
define('BASE_URL', '');

// ── Fiksni adresa trgovine (npr. 'https://trgovina.hr' ili 'https://trgovina.hr/shop') ──
// Installer ju upiše automatski. Sprječava "Host header poisoning": svi linkovi u
// e-mailovima (reset lozinke, potvrda), canonical i sitemap koriste OVU adresu, a ne
// ono što stigne u HTTP zaglavlju. Ostavite prazno samo ako stvarno ne znate adresu.
define('APP_URL', '');

// ── Pouzdani proxy/load-balancer IP-ovi (CSV) — npr. '10.0.0.1,10.0.0.2' ──
// SAMO ako je trgovina iza Cloudflarea/reverse-proxyja čita se X-Forwarded-For za
// pravu IP adresu kupca. Prazno (default) = koristi se samo REMOTE_ADDR (sigurno na
// klasičnom shared hostingu; sprječava lažiranje IP-a i zaobilaženje rate-limita).
define('TRUSTED_PROXIES', '');

// ── Development: true = đurđa API se simulira lokalno (mock), NE zove pravi server ──
define('DJURDJA_MOCK', false);

// ── Prikaz grešaka — UVIJEK false u produkciji ──
define('DEBUG', false);
