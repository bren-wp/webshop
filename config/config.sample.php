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

// ── Development: true = đurđa API se simulira lokalno (mock), NE zove pravi server ──
define('DJURDJA_MOCK', false);

// ── Prikaz grešaka — UVIJEK false u produkciji ──
define('DEBUG', false);
