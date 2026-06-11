<?php
/**
 * Settings — key/value postavke shopa (tablica settings), keširane po requestu.
 */

class Settings
{
    private static ?array $cache = null;

    private static function load(): void
    {
        if (self::$cache !== null) return;
        self::$cache = [];
        try {
            foreach (Database::instance()->fetchAll('SELECT k, v FROM settings') as $row) {
                self::$cache[$row['k']] = $row['v'];
            }
        } catch (Throwable $e) {
            // Tablica još ne postoji (instalacija u tijeku) — ponašaj se kao prazno
            self::$cache = [];
        }
    }

    public static function get(string $key, $default = null)
    {
        self::load();
        return array_key_exists($key, self::$cache) && self::$cache[$key] !== null
            ? self::$cache[$key]
            : $default;
    }

    public static function set(string $key, $value): void
    {
        self::load();
        $v = $value === null ? null : (string) $value;
        Database::instance()->query(
            'INSERT INTO settings (k, v) VALUES (:k, :v) ON DUPLICATE KEY UPDATE v = VALUES(v)',
            [':k' => $key, ':v' => $v]
        );
        self::$cache[$key] = $v;
    }

    public static function getJson(string $key, array $default = []): array
    {
        $raw = self::get($key);
        if (!$raw) return $default;
        $d = json_decode($raw, true);
        return is_array($d) ? $d : $default;
    }

    public static function setJson(string $key, array $value): void
    {
        self::set($key, json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    /** Više postavki odjednom. */
    public static function setMany(array $kv): void
    {
        foreach ($kv as $k => $v) self::set($k, $v);
    }
}
