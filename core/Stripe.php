<?php
/**
 * Stripe — direktni REST API (bez SDK ovisnosti).
 * Koristi hosted Checkout Session: kupac se preusmjerava na Stripeovu stranicu,
 * pa shop ne dira kartične podatke (najjednostavnije i PCI-najsigurnije).
 */

class Stripe
{
    private string $secretKey;
    private string $webhookSecret;
    private string $apiUrl = 'https://api.stripe.com/v1';

    public function __construct(array $config)
    {
        $this->secretKey     = $config['secret_key'] ?? '';
        $this->webhookSecret = $config['webhook_secret'] ?? '';
        if ($this->secretKey === '') {
            throw new RuntimeException('Stripe secret key nije postavljen.');
        }
    }

    /** Kreiraj Checkout Session; vraća ['id' => ..., 'url' => ...]. */
    public function createCheckoutSession(array $order, array $items, string $successUrl, string $cancelUrl): array
    {
        $lineItems = [];
        foreach ($items as $i => $it) {
            $lineItems[$i] = [
                'quantity' => (int) $it['quantity'],
                'price_data' => [
                    'currency'    => 'eur',
                    'unit_amount' => (int) round((float) $it['unit_price'] * 100),
                    'product_data' => ['name' => mb_substr($it['name'], 0, 250)],
                ],
            ];
        }
        $extra = (float) $order['shipping_cost'] + (float) $order['payment_fee'];
        if ($extra > 0) {
            $lineItems[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency'    => 'eur',
                    'unit_amount' => (int) round($extra * 100),
                    'product_data' => ['name' => 'Dostava i naknade'],
                ],
            ];
        }

        $params = [
            'mode'                => 'payment',
            'payment_method_types' => ['card'], // SAMO kartice — bez Bancontact/EPS/iDEAL/Sofort itd.
            'success_url'         => $successUrl,
            'cancel_url'          => $cancelUrl,
            'customer_email'      => $order['customer_email'],
            'client_reference_id' => (string) $order['id'],
            'line_items'          => $lineItems,
            'metadata'            => [
                'order_id'     => (string) $order['id'],
                'order_number' => $order['order_number'],
            ],
            'payment_intent_data' => [
                'description' => 'Narudžba ' . $order['order_number'],
                'metadata'    => ['order_id' => (string) $order['id'], 'order_number' => $order['order_number']],
            ],
        ];

        return $this->apiCall('POST', '/checkout/sessions', $params);
    }

    public function retrieveSession(string $sessionId): array
    {
        return $this->apiCall('GET', '/checkout/sessions/' . rawurlencode($sessionId));
    }

    public function refund(string $paymentIntentId, ?int $amountCents = null): array
    {
        $params = ['payment_intent' => $paymentIntentId];
        if ($amountCents !== null) $params['amount'] = $amountCents;
        return $this->apiCall('POST', '/refunds', $params);
    }

    /** Verifikacija Stripe webhook potpisa; vraća parsirani event. */
    public function verifyWebhookSignature(string $payload, string $sigHeader): array
    {
        if ($this->webhookSecret === '') {
            throw new RuntimeException('Webhook secret nije konfiguriran.');
        }
        $elements = [];
        foreach (explode(',', $sigHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) $elements[$kv[0]] = $kv[1];
        }
        $timestamp = $elements['t'] ?? '';
        $signature = $elements['v1'] ?? '';
        if (!$timestamp || !$signature) {
            throw new RuntimeException('Neispravan Stripe-Signature header.');
        }
        if (abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Stripe webhook timestamp prestar (replay zaštita).');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhookSecret);
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Stripe webhook potpis se ne podudara.');
        }
        return json_decode($payload, true) ?: [];
    }

    public function testConnection(): array
    {
        try {
            $this->apiCall('GET', '/balance');
            return ['success' => true, 'message' => 'Uspješno povezano sa Stripeom.'];
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function apiCall(string $method, string $endpoint, ?array $params = null): array
    {
        $url = $this->apiUrl . $endpoint;
        if ($method === 'GET' && $params) {
            $url .= '?' . http_build_query($params);
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERPWD        => $this->secretKey . ':',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Stripe-Version: 2024-06-20'],
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($this->flattenParams($params ?? []));
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) throw new RuntimeException("Stripe mrežna greška: $error");
        $data = json_decode((string) $response, true) ?: [];
        if ($httpCode >= 400) {
            throw new RuntimeException('Stripe: ' . ($data['error']['message'] ?? "HTTP $httpCode"));
        }
        return $data;
    }

    /** ['metadata' => ['k' => 'v']] → ['metadata[k]' => 'v'] (Stripe form-encoded format). */
    private function flattenParams(array $params, string $prefix = ''): array
    {
        $result = [];
        foreach ($params as $key => $value) {
            $fullKey = $prefix ? "{$prefix}[{$key}]" : (string) $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenParams($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }
}
