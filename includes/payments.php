<?php
declare(strict_types=1);

/**
 * Payment helper utilities (Stripe sandbox).
 *
 * The project avoids composer, so implementations rely on cURL and
 * environment variables configured via `.env`.
 */

if (!function_exists('rb_stripe_available')) {
    function rb_stripe_available(): bool
    {
        $secret = trim((string)($_ENV['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET'] ?? ''));
        $publishable = trim((string)($_ENV['STRIPE_PUBLISHABLE_KEY'] ?? $_ENV['STRIPE_PUBLIC_KEY'] ?? ''));
        return $secret !== '' && $publishable !== '';
    }
}

if (!function_exists('rb_stripe_secret')) {
    function rb_stripe_secret(): string
    {
        return trim((string)($_ENV['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET'] ?? ''));
    }
}

if (!function_exists('rb_stripe_publishable')) {
    function rb_stripe_publishable(): string
    {
        return trim((string)($_ENV['STRIPE_PUBLISHABLE_KEY'] ?? $_ENV['STRIPE_PUBLIC_KEY'] ?? ''));
    }
}

if (!function_exists('rb_stripe_currency')) {
    function rb_stripe_currency(): string
    {
        $default = strtolower((string)($_ENV['STRIPE_DEFAULT_CURRENCY'] ?? 'aud'));
        if (!preg_match('/^[a-z]{3}$/', $default)) {
            $default = 'aud';
        }
        return $default;
    }
}

if (!function_exists('rb_stripe_create_checkout_session')) {
    /**
     * Create a Stripe Checkout session (test mode) and return the redirect URL.
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool, id?:string, url?:string, error?:string}
     */
    function rb_stripe_create_checkout_session(array $payload): array
    {
        if (!rb_stripe_available()) {
            return ['ok' => false, 'error' => 'Stripe sandbox credentials are not configured.'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL is required to contact Stripe.'];
        }

        $secret = rb_stripe_secret();
        $currency = strtolower((string)($payload['currency'] ?? rb_stripe_currency()));
        $successUrl = (string)($payload['success_url'] ?? '');
        $cancelUrl = (string)($payload['cancel_url'] ?? '');
        $customerEmail = (string)($payload['customer_email'] ?? '');
        $lineItems = $payload['line_items'] ?? [];
        $metadata = $payload['metadata'] ?? [];
        $extraFields = $payload['extra_fields'] ?? [];

        if ($successUrl === '' || $cancelUrl === '' || !$lineItems) {
            return ['ok' => false, 'error' => 'Checkout session payload is incomplete.'];
        }

        $fields = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $customerEmail,
            'allow_promotion_codes' => 'true',
            'automatic_tax[enabled]' => 'false',
        ];

        foreach ($lineItems as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string)($item['name'] ?? 'Item'));
            $description = trim((string)($item['description'] ?? ''));
            $unitAmount = (int)round(((float)($item['amount'] ?? 0.0)) * 100);
            $quantity = max(1, (int)($item['quantity'] ?? 1));

            if ($unitAmount <= 0) {
                continue;
            }

            $prefix = "line_items[$index]";
            $fields[sprintf('%s[price_data][currency]', $prefix)] = $currency;
            $fields[sprintf('%s[price_data][product_data][name]', $prefix)] = $name;
            if ($description !== '') {
                $fields[sprintf('%s[price_data][product_data][description]', $prefix)] = mb_substr($description, 0, 400);
            }
            $fields[sprintf('%s[price_data][unit_amount]', $prefix)] = (string)$unitAmount;
            $fields[sprintf('%s[quantity]', $prefix)] = (string)$quantity;
        }

        if (!empty($metadata) && is_array($metadata)) {
            foreach ($metadata as $metaKey => $metaValue) {
                $fields['metadata[' . $metaKey . ']'] = (string)$metaValue;
            }
        }

        if (!empty($extraFields) && is_array($extraFields)) {
            foreach ($extraFields as $fieldKey => $fieldValue) {
                $fields[$fieldKey] = (string)$fieldValue;
            }
        }

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secret,
            ],
            CURLOPT_POSTFIELDS => http_build_query($fields, '', '&'),
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log('[stripe] checkout session curl error: ' . $error);
            return ['ok' => false, 'error' => 'Stripe request failed: ' . $error];
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 0;
        curl_close($ch);

        /** @var array<string,mixed>|null $data */
        $data = json_decode($response, true);
        if (!is_array($data)) {
            error_log('[stripe] checkout session unexpected response: ' . $response);
            return ['ok' => false, 'error' => 'Unexpected response from Stripe.'];
        }
        if ($status < 200 || $status >= 300) {
            $message = (string)($data['error']['message'] ?? 'Unable to create checkout session.');
            error_log('[stripe] checkout session API error: ' . $response);
            return ['ok' => false, 'error' => $message];
        }

        return [
            'ok' => true,
            'id' => (string)($data['id'] ?? ''),
            'url' => (string)($data['url'] ?? ''),
        ];
    }
}

if (!function_exists('rb_stripe_retrieve_session')) {
    /**
     * Retrieve a Stripe Checkout session.
     *
     * @return array{ok:bool, session?:array<string,mixed>, error?:string}
     */
    function rb_stripe_retrieve_session(string $sessionId): array
    {
        if (!rb_stripe_available()) {
            return ['ok' => false, 'error' => 'Stripe sandbox credentials are not configured.'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL is required to contact Stripe.'];
        }

        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return ['ok' => false, 'error' => 'Session ID missing.'];
        }

        $secret = rb_stripe_secret();
        $url = 'https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $secret,
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'error' => 'Stripe request failed: ' . $error];
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 0;
        curl_close($ch);

        /** @var array<string,mixed>|null $data */
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Unexpected response from Stripe.'];
        }
        if ($status < 200 || $status >= 300) {
            $message = (string)($data['error']['message'] ?? 'Unable to retrieve checkout session.');
            return ['ok' => false, 'error' => $message];
        }

        return ['ok' => true, 'session' => $data];
    }
}
