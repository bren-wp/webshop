<?php
/**
 * Audit — bilježi osjetljive admin akcije (prijava, promjena postavki, reset,
 * fiskalizacija/storno…) u tablicu audit_log. Pregled: admin/dnevnik.php.
 * Nikad ne baca grešku (logiranje ne smije srušiti akciju).
 */

class Audit
{
    public static function log(string $action, array $opts = []): void
    {
        try {
            $adminId = !empty($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
            $name = $opts['admin_name'] ?? null;
            if ($name === null && $adminId) {
                $name = Database::instance()->fetchColumn('SELECT username FROM admin_users WHERE id = :id', [':id' => $adminId]);
            }
            Database::instance()->insert('audit_log', [
                'admin_id'    => $adminId,
                'admin_name'  => $name !== null ? mb_substr((string) $name, 0, 60) : null,
                'action'      => mb_substr($action, 0, 60),
                'entity_type' => isset($opts['entity_type']) ? mb_substr((string) $opts['entity_type'], 0, 40) : null,
                'entity_id'   => isset($opts['entity_id']) ? mb_substr((string) $opts['entity_id'], 0, 40) : null,
                'detail'      => isset($opts['detail']) ? mb_substr((string) $opts['detail'], 0, 255) : null,
                'ip'          => client_ip(),
            ]);
        } catch (Throwable $e) {
            error_log('[Audit] ' . $e->getMessage());
        }
    }

    public static function recent(int $limit = 300): array
    {
        try {
            return Database::instance()->fetchAll('SELECT * FROM audit_log ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit)));
        } catch (Throwable $e) {
            return [];
        }
    }
}
