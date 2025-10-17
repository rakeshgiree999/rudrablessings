<?php
declare(strict_types=1);

if (!function_exists('rb_promo_normalize_code')) {
    function rb_promo_normalize_code(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/\s+/', '', $code);
        return $code;
    }
}

if (!function_exists('rb_promo_is_currently_active')) {
    /**
     * Determine if a promo is currently active based on flags, schedule, and usage limits.
     *
     * @param array<string, mixed> $promo
     */
    function rb_promo_is_currently_active(array $promo, ?int $nowTs = null): bool
    {
        $now = $nowTs ?? time();
        if ((int)($promo['is_active'] ?? 0) !== 1) {
            return false;
        }

        $startsAtRaw = trim((string)($promo['starts_at'] ?? ''));
        if ($startsAtRaw !== '') {
            $startTs = strtotime($startsAtRaw);
            if ($startTs !== false && $now < $startTs) {
                return false;
            }
        }

        $endsAtRaw = trim((string)($promo['ends_at'] ?? ''));
        if ($endsAtRaw !== '') {
            $endTs = strtotime($endsAtRaw);
            if ($endTs !== false && $now > $endTs) {
                return false;
            }
        }

        $maxUsage = $promo['max_usage'] ?? null;
        if ($maxUsage !== null && $maxUsage !== '') {
            $maxUsage = (int)$maxUsage;
            if ($maxUsage >= 0 && (int)($promo['usage_count'] ?? 0) >= $maxUsage) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('rb_promo_default_message')) {
    /**
     * @param array<string, mixed> $promo
     */
    function rb_promo_default_message(array $promo): string
    {
        $code = rb_promo_normalize_code((string)($promo['code'] ?? ''));
        $type = (string)($promo['type'] ?? 'percent');
        $value = (float)($promo['value'] ?? 0.0);

        switch ($type) {
            case 'amount':
                return sprintf('%s applied - %s off subtotal', $code, rb_format_price($value));
            case 'shipping':
                return $code !== '' ? sprintf('%s applied - free shipping unlocked', $code) : 'Free shipping applied';
            case 'percent':
            default:
                $percent = rtrim(rtrim(number_format(max(0.0, $value), 2, '.', ''), '0'), '.');
                if ($percent === '') {
                    $percent = '0';
                }
                return sprintf('%s applied - %s%% off subtotal', $code, $percent);
        }
    }
}

if (!function_exists('rb_promo_apply_row')) {
    /**
     * Evaluate a promo row against the provided cart summary.
     *
     * @param array<string, mixed>|null $promoRow
     * @return array{valid:bool,code:string,discount:float,shipping:float,message:string,free_ship:bool,promo_id:?int}
     */
    function rb_promo_apply_row(?array $promoRow, string $inputCode, float $subtotal, float $shipping, ?int $nowTs = null): array
    {
        $normalizedCode = rb_promo_normalize_code($inputCode);
        $shipping = max(0.0, $shipping);

        $result = [
            'valid' => $normalizedCode === '',
            'code' => $normalizedCode,
            'discount' => 0.0,
            'shipping' => $shipping,
            'message' => '',
            'free_ship' => false,
            'promo_id' => null,
        ];

        if ($normalizedCode === '') {
            return $result;
        }

        if ($promoRow === null) {
            $result['valid'] = false;
            return $result;
        }

        $rowCode = rb_promo_normalize_code((string)($promoRow['code'] ?? ''));
        if ($rowCode !== $normalizedCode) {
            $result['valid'] = false;
            return $result;
        }

        $now = $nowTs ?? time();

        if ((int)($promoRow['is_active'] ?? 0) !== 1) {
            $result['valid'] = false;
            $result['message'] = 'Promo code inactive.';
            return $result;
        }

        $startsAtRaw = trim((string)($promoRow['starts_at'] ?? ''));
        if ($startsAtRaw !== '') {
            $startTs = strtotime($startsAtRaw);
            if ($startTs !== false && $now < $startTs) {
                $result['valid'] = false;
                $result['message'] = 'Promo code not active yet.';
                return $result;
            }
        }

        $endsAtRaw = trim((string)($promoRow['ends_at'] ?? ''));
        if ($endsAtRaw !== '') {
            $endTs = strtotime($endsAtRaw);
            if ($endTs !== false && $now > $endTs) {
                $result['valid'] = false;
                $result['message'] = 'Promo code has expired.';
                return $result;
            }
        }

        $maxUsageRaw = $promoRow['max_usage'] ?? null;
        if ($maxUsageRaw !== null && $maxUsageRaw !== '') {
            $maxUsageLimit = (int)$maxUsageRaw;
            if ($maxUsageLimit >= 0 && (int)($promoRow['usage_count'] ?? 0) >= $maxUsageLimit) {
                $result['valid'] = false;
                $result['message'] = 'Promo code usage limit reached.';
                return $result;
            }
        }

        $minSubtotal = max(0.0, (float)($promoRow['min_subtotal'] ?? 0.0));
        if ($minSubtotal > 0 && ($subtotal + 0.00001) < $minSubtotal) {
            $result['valid'] = false;
            $result['message'] = 'Subtotal must be at least ' . rb_format_price($minSubtotal);
            return $result;
        }

        $type = (string)($promoRow['type'] ?? 'percent');
        $value = max(0.0, (float)($promoRow['value'] ?? 0.0));
        $message = trim((string)($promoRow['label'] ?? ''));

        if ($type === 'percent') {
            $discount = round($subtotal * ($value / 100), 2);
            $result['discount'] = min($subtotal, max(0.0, $discount));
        } elseif ($type === 'amount') {
            $result['discount'] = min($subtotal, round($value, 2));
        } elseif ($type === 'shipping') {
            $result['free_ship'] = true;
            $result['shipping'] = 0.0;
        }

        if ($message === '') {
            $message = rb_promo_default_message($promoRow);
        }

        $result['valid'] = true;
        $result['message'] = $message;
        $result['promo_id'] = isset($promoRow['id']) ? (int)$promoRow['id'] : null;

        return $result;
    }
}

if (!function_exists('rb_promo_apply')) {
    /**
     * Apply a promo code by looking it up in the database.
     *
     * @return array{valid:bool,code:string,discount:float,shipping:float,message:string,free_ship:bool,promo_id:?int}
     */
    function rb_promo_apply(PDO $pdo, string $code, float $subtotal, float $shipping): array
    {
        $normalized = rb_promo_normalize_code($code);
        $promo = null;
        if ($normalized !== '') {
            $stmt = $pdo->prepare('SELECT id, code, type, value, label, min_subtotal, max_usage, usage_count, is_active, starts_at, ends_at FROM promos WHERE UPPER(code) = :code LIMIT 1');
            $stmt->execute(['code' => $normalized]);
            $promo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        return rb_promo_apply_row($promo, $code, $subtotal, $shipping);
    }
}

if (!function_exists('rb_promo_apply_cached')) {
    /**
     * Apply a promo code using a pre-fetched cache.
     *
     * @param array<string, array<string, mixed>> $cache map of normalized promo code to promo row
     * @return array{valid:bool,code:string,discount:float,shipping:float,message:string,free_ship:bool,promo_id:?int}
     */
    function rb_promo_apply_cached(string $code, float $subtotal, float $shipping, array $cache, ?int $nowTs = null): array
    {
        $normalized = rb_promo_normalize_code($code);
        $promo = $cache[$normalized] ?? null;
        return rb_promo_apply_row(is_array($promo) ? $promo : null, $code, $subtotal, $shipping, $nowTs);
    }
}

if (!function_exists('rb_promos_fetch')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function rb_promos_fetch(PDO $pdo, bool $onlyActive = false): array
    {
        $sql = 'SELECT id, code, type, value, label, min_subtotal, max_usage, usage_count, is_active, starts_at, ends_at FROM promos';
        if ($onlyActive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY code ASC';
        $stmt = $pdo->query($sql);
        if (!$stmt) {
            return [];
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('rb_promos_active')) {
    /**
     * Return promos that are active and currently usable.
     *
     * @return array<int, array<string, mixed>>
     */
    function rb_promos_active(PDO $pdo): array
    {
        $rows = rb_promos_fetch($pdo, true);
        $now = time();
        return array_values(array_filter($rows, static function (array $promo) use ($now): bool {
            return rb_promo_is_currently_active($promo, $now);
        }));
    }
}

if (!function_exists('rb_promo_claim_for_order')) {
    /**
     * Increment promo usage within an open transaction, enforcing usage limits.
     */
    function rb_promo_claim_for_order(PDO $pdo, int $promoId): void
    {
        $stmt = $pdo->prepare('SELECT id, max_usage, usage_count FROM promos WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $promoId]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$promo) {
            throw new \RuntimeException('Promo code no longer exists.');
        }

        $maxUsage = $promo['max_usage'] ?? null;
        if ($maxUsage !== null && $maxUsage !== '') {
            $maxUsage = (int)$maxUsage;
            if ($maxUsage >= 0 && (int)($promo['usage_count'] ?? 0) >= $maxUsage) {
                throw new \RuntimeException('Promo code usage limit reached.');
            }
        }

        $update = $pdo->prepare('UPDATE promos SET usage_count = usage_count + 1, updated_at = NOW() WHERE id = :id');
        $update->execute(['id' => $promoId]);
    }
}
