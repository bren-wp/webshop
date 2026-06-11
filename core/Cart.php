<?php
/**
 * Cart — košarica u sesiji. Cijene se UVIJEK ponovno čitaju iz baze
 * (sesija drži samo productId => qty), tako da manipulacija klijenta nije moguća.
 */

class Cart
{
    private const KEY = 'cart';
    private const MAX_QTY = 999;

    public static function items(): array
    {
        $c = $_SESSION[self::KEY] ?? [];
        return is_array($c) ? $c : [];
    }

    public static function add(int $productId, int $qty = 1): void
    {
        if ($productId < 1) return;
        $qty = max(1, min(self::MAX_QTY, $qty));
        $c = self::items();
        $c[$productId] = min(self::MAX_QTY, ($c[$productId] ?? 0) + $qty);
        $_SESSION[self::KEY] = $c;
    }

    public static function update(int $productId, int $qty): void
    {
        $c = self::items();
        if ($qty <= 0) {
            unset($c[$productId]);
        } else {
            $c[$productId] = min(self::MAX_QTY, $qty);
        }
        $_SESSION[self::KEY] = $c;
    }

    public static function remove(int $productId): void
    {
        self::update($productId, 0);
    }

    public static function clear(): void
    {
        unset($_SESSION[self::KEY]);
    }

    /**
     * Stavke s podacima iz baze (samo vidljivi, ne-orphan proizvodi).
     * Nestale proizvode tiho uklanja iz košarice.
     * @return array niz redaka: product polja + qty + line_total + image
     */
    public static function detailed(): array
    {
        $c = self::items();
        if (!$c) return [];
        $db = Database::instance();
        $ids = array_map('intval', array_keys($c));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = $db->fetchAll(
            "SELECT p.*, (SELECT filename FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order ASC LIMIT 1) AS image
             FROM products p WHERE p.id IN ($ph) AND p.is_visible = 1 AND p.is_orphaned = 0",
            $ids
        );
        $byId = [];
        foreach ($rows as $r) $byId[(int) $r['id']] = $r;

        $out = [];
        $changed = false;
        foreach ($c as $pid => $qty) {
            $pid = (int) $pid;
            if (!isset($byId[$pid])) {
                unset($c[$pid]);
                $changed = true;
                continue;
            }
            $row = $byId[$pid];
            $row['qty'] = (int) $qty;
            $row['line_total'] = round((float) $row['price'] * (int) $qty, 2);
            $out[] = $row;
        }
        if ($changed) $_SESSION[self::KEY] = $c;
        return $out;
    }

    public static function count(): int
    {
        return array_sum(self::items());
    }

    public static function subtotal(?array $detailed = null): float
    {
        $detailed = $detailed ?? self::detailed();
        return round(array_sum(array_column($detailed, 'line_total')), 2);
    }

    /**
     * Provjera zaliha za artikle koji prate zalihu.
     * @return string[] poruke o problemima (prazno = sve ok)
     */
    public static function stockProblems(?array $detailed = null): array
    {
        $problems = [];
        foreach ($detailed ?? self::detailed() as $it) {
            if ((int) $it['track_stock'] === 1 && $it['stock_qty'] !== null) {
                $available = (float) $it['stock_qty'];
                if ($available < $it['qty']) {
                    $problems[] = $available <= 0
                        ? "\"{$it['name']}\" trenutno nije dostupan."
                        : "\"{$it['name']}\" — dostupno samo " . (int) $available . " kom.";
                }
            }
        }
        return $problems;
    }
}
