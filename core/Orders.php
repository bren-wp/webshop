<?php
/**
 * Orders — kreiranje narudžbi (transakcijski), statusi, plaćanje, otkazivanje.
 */

class Orders
{
    /**
     * Kreiraj narudžbu iz košarice. Baca RuntimeException s porukom za kupca.
     * @return array red iz orders tablice
     */
    public static function create(array $customer, string $paymentMethod, array $items): array
    {
        $db = Database::instance();

        if (!Djurdja::checkoutAllowed()) {
            throw new RuntimeException('Trgovina trenutno ne zaprima narudžbe (veza sa sustavom za izdavanje računa nije dostupna). Pokušajte kasnije.');
        }
        // Anti-zloupotreba: kvota potrošena + nakupljeno previše rezerviranih →
        // privremena pauza (PRAVA brana, server-side; JS sloj je samo prikaz)
        if (Djurdja::ordersBlocked()) {
            throw new RuntimeException('Trgovina trenutno ne zaprima nove narudžbe. Molimo pokušajte ponovno kasnije.');
        }
        if (!$items) {
            throw new RuntimeException('Košarica je prazna.');
        }
        $problems = Cart::stockProblems($items);
        if ($problems) {
            throw new RuntimeException(implode(' ', $problems));
        }

        $pm = new PaymentManager();
        $method = $pm->getMethod($paymentMethod);
        if (!$method || !(int) $method['is_active']) {
            throw new RuntimeException('Odabrani način plaćanja nije dostupan.');
        }

        $subtotal = Cart::subtotal($items);
        $allServices = !array_filter($items, fn($i) => (int) $i['is_service'] === 0);
        $freeOver = (float) s('shipping_free_over', 0);
        $shipping = $allServices ? 0.0
            : (($freeOver > 0 && $subtotal >= $freeOver) ? 0.0 : (float) s('shipping_flat', 0));
        $fee = $pm->calculateFee($paymentMethod, $subtotal + $shipping);
        $total = round($subtotal + $shipping + $fee, 2);

        $pdo = $db->pdo();
        $pdo->beginTransaction();
        try {
            $orderId = $db->insert('orders', [
                'order_number'   => 'TMP-' . bin2hex(random_bytes(6)),
                'status'         => 'pending',
                'subtotal'       => $subtotal,
                'shipping_cost'  => $shipping,
                'payment_fee'    => $fee,
                'total'          => $total,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'customer_name'  => mb_substr($customer['name'], 0, 200),
                'customer_email' => mb_substr($customer['email'], 0, 190),
                'customer_phone' => mb_substr($customer['phone'] ?? '', 0, 40) ?: null,
                'address'        => mb_substr($customer['address'], 0, 255),
                'city'           => mb_substr($customer['city'], 0, 100),
                'postal_code'    => mb_substr($customer['postal'], 0, 20),
                'country'        => 'HR',
                'note'           => mb_substr(trim($customer['note'] ?? ''), 0, 2000) ?: null,
                'guest_token'    => bin2hex(random_bytes(24)),
                'customer_id'    => !empty($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : null,
                'ip'             => client_ip(),
            ]);

            $orderNumber = 'WEB-' . date('Y') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);
            $db->update('orders', ['order_number' => $orderNumber], 'id = :id', [':id' => $orderId]);

            foreach ($items as $it) {
                $db->insert('order_items', [
                    'order_id'           => $orderId,
                    'product_id'         => (int) $it['id'],
                    'variant_id'         => $it['variant_id'] ?? null,
                    'djurdja_product_id' => $it['djurdja_id'],
                    'name'               => $it['name'],
                    'variant_label'      => $it['variant_label'] ?? null,
                    'quantity'           => (int) $it['qty'],
                    'unit_price'         => $it['price'],
                    'vat_rate'           => $it['vat_rate'],
                    'total'              => $it['line_total'],
                ]);
                $qty = (int) $it['qty'];
                $hasVariantStock = !empty($it['variant_id']) && $it['variant_stock'] !== null;
                $hasProductStock = (int) $it['track_stock'] === 1;

                // ATOMIČNO skidanje zalihe (uvjet stock_qty >= qty). Kod istovremenih
                // kupnji zadnjeg komada drugi UPDATE zahvati 0 redaka → throw → rollback
                // (sprječava oversell koji provjera prije transakcije ne može uhvatiti).
                // Mjerodavna je varijanta ako se prati, inače artikl; drugi nivo je best-effort.
                // NB: zaseban placeholder za oduzimanje (:q) i za usporedbu (:qmin) —
                // PDO bez emulacije (ATTR_EMULATE_PREPARES=false) ne dopušta isti :q dvaput.
                if ($hasVariantStock) {
                    $n = $db->query(
                        'UPDATE product_variants SET stock_qty = stock_qty - :q WHERE id = :id AND stock_qty IS NOT NULL AND stock_qty >= :qmin',
                        [':q' => $qty, ':qmin' => $qty, ':id' => (int) $it['variant_id']]
                    )->rowCount();
                    if ($n === 0) throw new RuntimeException('OUT_OF_STOCK:' . $it['name']);
                    if ($hasProductStock) {
                        $db->query(
                            'UPDATE products SET stock_qty = GREATEST(stock_qty - :q, 0) WHERE id = :id AND track_stock = 1',
                            [':q' => $qty, ':id' => (int) $it['id']]
                        );
                    }
                } elseif ($hasProductStock) {
                    $n = $db->query(
                        'UPDATE products SET stock_qty = stock_qty - :q WHERE id = :id AND track_stock = 1 AND stock_qty IS NOT NULL AND stock_qty >= :qmin',
                        [':q' => $qty, ':qmin' => $qty, ':id' => (int) $it['id']]
                    )->rowCount();
                    if ($n === 0) throw new RuntimeException('OUT_OF_STOCK:' . $it['name']);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            if (str_starts_with($e->getMessage(), 'OUT_OF_STOCK:')) {
                $soldOut = substr($e->getMessage(), strlen('OUT_OF_STOCK:'));
                error_log('[Orders::create] oversell spriječen: ' . $soldOut);
                throw new RuntimeException('Nažalost, "' . $soldOut . '" je upravo rasprodan u traženoj količini. Osvježite košaricu i pokušajte ponovno.');
            }
            error_log('[Orders::create] ' . $e->getMessage());
            throw new RuntimeException('Spremanje narudžbe nije uspjelo. Pokušajte ponovno.');
        }

        $order = $db->fetch('SELECT * FROM orders WHERE id = :id', [':id' => $orderId]);

        // E-mail potvrda (best-effort, ne ruši checkout)
        try {
            Mailer::orderConfirmation($order, $items);
        } catch (Throwable $e) {
            error_log('[Orders::create] mail: ' . $e->getMessage());
        }

        // Javi đurđi (master) prodaju → skida ukupnu zalihu s web skladišta +
        // varijantu. Đurđa preskače usluge i firme bez vođenja skladišta. Best-effort.
        $saleItems = [];
        foreach ($items as $it) {
            if (empty($it['djurdja_id'])) continue;
            $saleItems[] = [
                'productId' => $it['djurdja_id'],
                'qty'       => (int) $it['qty'],
                'variantId' => $it['djurdja_variant_id'] ?? null,
            ];
        }
        if ($saleItems) {
            try {
                Djurdja::client()?->stockSale($saleItems);
            } catch (Throwable $e) {
                error_log('[Orders::create] stockSale: ' . $e->getMessage());
            }
        }

        return $order;
    }

    /**
     * Označi narudžbu plaćenom (webhook ili admin). Idempotentno.
     * Pokreće automatsku fiskalizaciju ako je metoda tako konfigurirana.
     */
    public static function markPaid(int $orderId, ?string $transactionId = null, ?array $raw = null): array
    {
        $db = Database::instance();
        $order = $db->fetch('SELECT * FROM orders WHERE id = :id', [':id' => $orderId]);
        if (!$order) return ['success' => false, 'error' => 'Narudžba nije pronađena.'];
        if ($order['payment_status'] === 'paid') {
            return ['success' => true, 'idempotent' => true];
        }

        $upd = [
            'payment_status' => 'paid',
            'status'         => $order['status'] === 'pending' ? 'confirmed' : $order['status'],
        ];
        if ($transactionId) $upd['payment_transaction_id'] = mb_substr($transactionId, 0, 200);
        if ($raw) $upd['payment_data'] = json_encode($raw, JSON_UNESCAPED_UNICODE);
        $db->update('orders', $upd, 'id = :id', [':id' => $orderId]);

        $db->query(
            "UPDATE payment_transactions SET status = 'paid', transaction_id = COALESCE(:t, transaction_id) WHERE order_id = :o",
            [':t' => $transactionId, ':o' => $orderId]
        );

        $result = ['success' => true];
        $method = $db->fetch('SELECT fiscal_auto FROM payment_methods WHERE code = :c', [':c' => $order['payment_method']]);
        if ($method && (int) $method['fiscal_auto'] === 1) {
            $result['fiscal'] = Fiscalizer::fiscalizeOrder($db, $orderId);
        }
        return $result;
    }

    public static function setStatus(int $orderId, string $status): void
    {
        $allowed = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
        if (!in_array($status, $allowed, true)) return;
        Database::instance()->update('orders', ['status' => $status], 'id = :id', [':id' => $orderId]);
    }

    /** Otkaži narudžbu i vrati zalihu (ako nije isporučena). */
    public static function cancel(int $orderId): void
    {
        $db = Database::instance();
        $order = $db->fetch('SELECT * FROM orders WHERE id = :id', [':id' => $orderId]);
        if (!$order || in_array($order['status'], ['cancelled', 'refunded'], true)) return;

        foreach ($db->fetchAll('SELECT * FROM order_items WHERE order_id = :o', [':o' => $orderId]) as $it) {
            if ($it['product_id']) {
                $db->query(
                    'UPDATE products SET stock_qty = stock_qty + :q WHERE id = :id AND track_stock = 1',
                    [':q' => (int) $it['quantity'], ':id' => (int) $it['product_id']]
                );
            }
            if (!empty($it['variant_id'])) {
                $db->query(
                    'UPDATE product_variants SET stock_qty = stock_qty + :q WHERE id = :id AND stock_qty IS NOT NULL',
                    [':q' => (int) $it['quantity'], ':id' => (int) $it['variant_id']]
                );
            }
        }
        $db->update('orders', ['status' => 'cancelled'], 'id = :id', [':id' => $orderId]);
    }

    /** Statusi na hrvatskom za prikaz. */
    public static function statusLabel(string $status): string
    {
        return [
            'pending' => 'Zaprimljena', 'confirmed' => 'Potvrđena', 'processing' => 'U obradi',
            'shipped' => 'Poslana', 'delivered' => 'Isporučena', 'cancelled' => 'Otkazana', 'refunded' => 'Refundirana',
        ][$status] ?? $status;
    }

    public static function paymentLabel(string $code): string
    {
        return ['cod' => 'Pouzeće', 'bank_transfer' => 'Virman', 'stripe' => 'Kartica'][$code] ?? $code;
    }

    /**
     * Jedinstveni izračun dijelova računa (artikli + DOSTAVA + naknada).
     * Dostava i naknada plaćanja ULAZE u račun isto kao artikli — u ukupan
     * iznos i u PDV razradu. PDV stopa dostave = stopa artikala ako su svi
     * isti, inače standardnih 25 %. Koriste ga Fiscalizer (payload), ispis
     * računa i mail — da svi pokazuju identičan broj.
     *
     * @param array $items redovi order_items (trebaju vat_rate, total)
     * @return array{byRate: array<string,float>, itemsTotal: float, shipping: float, fee: float, extra: float, grandTotal: float, shipRate: float}
     */
    public static function receiptParts(array $order, array $items): array
    {
        $byRate = [];
        $rates = [];
        $itemsTotal = 0.0;
        foreach ($items as $it) {
            $r = round((float) $it['vat_rate'], 2);
            $rates[(string) $r] = true;
            $byRate[(string) $r] = ($byRate[(string) $r] ?? 0) + (float) $it['total'];
            $itemsTotal += (float) $it['total'];
        }
        $itemsTotal = round($itemsTotal, 2);
        $shipping = round((float) ($order['shipping_cost'] ?? 0), 2);
        $fee      = round((float) ($order['payment_fee'] ?? 0), 2);
        $extra    = round($shipping + $fee, 2);
        // Stopa dostave: jedinstvena stopa artikala, inače standardnih 25 %.
        $shipRate = count($rates) === 1 ? (float) array_key_first($rates) : 25.0;
        if ($extra > 0) {
            $byRate[(string) $shipRate] = ($byRate[(string) $shipRate] ?? 0) + $extra;
        }
        return [
            'byRate'     => $byRate,
            'itemsTotal' => $itemsTotal,
            'shipping'   => $shipping,
            'fee'        => $fee,
            'extra'      => $extra,
            'grandTotal' => round($itemsTotal + $extra, 2),
            'shipRate'   => $shipRate,
        ];
    }
}
