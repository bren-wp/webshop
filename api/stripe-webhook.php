<?php
/**
 * Stripe webhook — checkout.session.completed → markPaid → auto-fiskalizacija.
 * Endpoint se registrira u Stripe dashboardu: https://tvoja-domena.hr/api/stripe-webhook.php
 * Uvijek vraćamo 200 za uspješno verificirane evente (Stripe inače retry-a).
 */
require_once __DIR__ . '/../core/bootstrap.php';

$payload = file_get_contents('php://input') ?: '';
$sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $pm = new PaymentManager();
    $event = $pm->stripe()->verifyWebhookSignature($payload, $sig);
} catch (Throwable $e) {
    error_log('[stripe-webhook] verifikacija: ' . $e->getMessage());
    http_response_code(400);
    echo 'invalid';
    exit;
}

$type = $event['type'] ?? '';
$object = $event['data']['object'] ?? [];

try {
    if ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') {
        $paid = ($object['payment_status'] ?? '') === 'paid';
        $orderId = (int) ($object['metadata']['order_id'] ?? $object['client_reference_id'] ?? 0);
        if ($paid && $orderId > 0) {
            Orders::markPaid($orderId, (string) ($object['payment_intent'] ?? $object['id'] ?? ''), [
                'stripe_event' => $type,
                'session_id'   => $object['id'] ?? null,
                'amount_total' => $object['amount_total'] ?? null,
            ]);
        }
    } elseif ($type === 'checkout.session.async_payment_failed' || $type === 'checkout.session.expired') {
        $orderId = (int) ($object['metadata']['order_id'] ?? $object['client_reference_id'] ?? 0);
        if ($orderId > 0) {
            $db = Database::instance();
            $order = $db->fetch('SELECT payment_status FROM orders WHERE id = :id', [':id' => $orderId]);
            if ($order && $order['payment_status'] === 'pending') {
                $db->update('orders', ['payment_status' => 'failed'], 'id = :id', [':id' => $orderId]);
                $db->query("UPDATE payment_transactions SET status = 'failed' WHERE order_id = :o", [':o' => $orderId]);
            }
        }
    }
} catch (Throwable $e) {
    // Logiraj, ali vrati 200 — fiskalizacija ima vlastiti retry mehanizam
    error_log('[stripe-webhook] obrada: ' . $e->getMessage());
}

http_response_code(200);
echo 'ok';
