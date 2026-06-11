<?php
/**
 * Mailer — HTML e-mailovi preko PHP mail() (radi na 99% shared hostinga bez konfiguracije).
 */

class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $from = s('shop_email', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromName = mb_encode_mimeheader(shop_name(), 'UTF-8');
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: DjurdjaShop',
        ];
        return @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), self::wrap($htmlBody), implode("\r\n", $headers));
    }

    /** Brendirani omotač za sve mailove. */
    private static function wrap(string $inner): string
    {
        $shop = e(shop_name());
        $url = defined('SITE_URL') ? SITE_URL : '';
        return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:28px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.06)">'
            . '<tr><td style="background:#1f2937;padding:22px 30px"><a href="' . e($url) . '" style="color:#fff;font-size:20px;font-weight:bold;text-decoration:none">' . $shop . '</a></td></tr>'
            . '<tr><td style="padding:30px;color:#374151;font-size:14px;line-height:1.65">' . $inner . '</td></tr>'
            . '<tr><td style="padding:18px 30px;background:#f9fafb;color:#9ca3af;font-size:12px">' . $shop . ' · Ovo je automatska poruka, ne odgovarajte na nju.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    public static function orderConfirmation(array $order, array $items): bool
    {
        $rows = '';
        foreach ($items as $it) {
            $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #f3f4f6">' . e($it['name'])
                . ' <span style="color:#9ca3af">× ' . (int) $it['qty'] . '</span></td>'
                . '<td align="right" style="padding:8px 0;border-bottom:1px solid #f3f4f6;white-space:nowrap">' . fmt_price($it['line_total']) . '</td></tr>';
        }
        if ((float) $order['shipping_cost'] > 0) {
            $rows .= '<tr><td style="padding:8px 0;color:#6b7280">Dostava</td><td align="right" style="padding:8px 0">' . fmt_price($order['shipping_cost']) . '</td></tr>';
        }
        if ((float) $order['payment_fee'] > 0) {
            $rows .= '<tr><td style="padding:8px 0;color:#6b7280">Naknada plaćanja</td><td align="right" style="padding:8px 0">' . fmt_price($order['payment_fee']) . '</td></tr>';
        }

        $statusUrl = SITE_URL . '/narudzba-potvrda.php?t=' . urlencode($order['guest_token']);
        $payInfo = '';
        if ($order['payment_method'] === 'bank_transfer') {
            $cfg = json_decode((string) Database::instance()->fetchColumn(
                "SELECT config FROM payment_methods WHERE code = 'bank_transfer'"
            ), true) ?: [];
            $payInfo = '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:16px;margin:18px 0">'
                . '<strong>Podaci za uplatu:</strong><br>'
                . 'IBAN: <strong>' . e($cfg['iban'] ?? '—') . '</strong><br>'
                . 'Primatelj: ' . e($cfg['recipient'] ?: shop_name()) . '<br>'
                . 'Model i poziv na broj: ' . e($cfg['model'] ?? 'HR00') . ' ' . e(preg_replace('/\D/', '', $order['order_number']))
                . '<br>Opis plaćanja: ' . e($order['order_number'])
                . '<br>Iznos: <strong>' . fmt_price($order['total']) . '</strong></div>'
                . '<p>Narudžbu šaljemo nakon evidentirane uplate.</p>';
        } elseif ($order['payment_method'] === 'cod') {
            $payInfo = '<p>Iznos <strong>' . fmt_price($order['total']) . '</strong> platite dostavljaču prilikom preuzimanja.</p>';
        }

        $html = '<h2 style="margin:0 0 6px;color:#111827">Hvala na narudžbi! 🎉</h2>'
            . '<p>Vaša narudžba <strong>' . e($order['order_number']) . '</strong> je zaprimljena.</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0">' . $rows
            . '<tr><td style="padding:12px 0;font-size:16px"><strong>Ukupno</strong></td><td align="right" style="padding:12px 0;font-size:16px"><strong>' . fmt_price($order['total']) . '</strong></td></tr></table>'
            . $payInfo
            . '<p style="margin-top:22px"><a href="' . e($statusUrl) . '" style="background:#1f2937;color:#fff;padding:11px 22px;border-radius:8px;text-decoration:none;font-weight:bold">Pregled narudžbe</a></p>';

        $ok = self::send($order['customer_email'], 'Potvrda narudžbe ' . $order['order_number'], $html);

        // Obavijest vlasniku trgovine
        $adminHtml = '<h3 style="margin:0 0 8px">Nova narudžba ' . e($order['order_number']) . '</h3>'
            . '<p>' . e($order['customer_name']) . ' · ' . e($order['customer_email']) . ' · ' . e($order['customer_phone'] ?? '') . '<br>'
            . e($order['address']) . ', ' . e($order['postal_code']) . ' ' . e($order['city']) . '<br>'
            . 'Plaćanje: ' . e(Orders::paymentLabel($order['payment_method'])) . ' · Ukupno: <strong>' . fmt_price($order['total']) . '</strong></p>'
            . '<table role="presentation" width="100%">' . $rows . '</table>';
        @self::send(s('shop_email', ''), 'Nova narudžba ' . $order['order_number'], $adminHtml);

        return $ok;
    }
}
