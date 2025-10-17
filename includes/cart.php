<?php
declare(strict_types=1);

if (!function_exists('rb_cart_session_token')) {
    function rb_cart_session_token(): string
    {
        if (empty($_SESSION['cart_token']) || !is_string($_SESSION['cart_token'])) {
            $_SESSION['cart_token'] = bin2hex(random_bytes(20));
        }
        return $_SESSION['cart_token'];
    }
}

if (!function_exists('rb_cart_generate_order_number')) {
    function rb_cart_generate_order_number(PDO $pdo): string
    {
        do {
            $number = 'RB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare('SELECT 1 FROM orders WHERE order_number = :num LIMIT 1');
            $stmt->execute(['num' => $number]);
        } while ($stmt->fetchColumn());
        return $number;
    }
}

if (!function_exists('rb_cart_place_order')) {
    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{order_id:int, order_number:string, subtotal:float, shipping:float, tax:float, grand_total:float, discount:float, promo_code:?string}
     * @throws \Throwable
     */
    function rb_cart_place_order(
        PDO $pdo,
        int $userId,
        int $cartId,
        array $items,
        float $subtotal,
        float $shipping,
        float $discount = 0.0,
        float $tax = 0.0,
        ?int $addressId = null,
        ?string $promoCode = null,
        bool $promoFreeShipping = false,
        ?int $promoId = null
    ): array {
        if (!$items) {
            throw new \RuntimeException('Cannot place an order with an empty cart.');
        }

        $orderNumber = rb_cart_generate_order_number($pdo);
        $discount = max(0.0, $discount);
        $subtotalAdjusted = max(0.0, $subtotal - $discount);
        $grandTotal = max(0.0, $subtotalAdjusted + $shipping + $tax);
        $promoCodeNormalized = $promoCode !== null ? rb_promo_normalize_code($promoCode) : '';
        $promoCodePersisted = $promoCodeNormalized !== '' ? $promoCodeNormalized : null;
        $promoFreeShippingFlag = $promoFreeShipping ? 1 : 0;

        $pdo->beginTransaction();
        try {
            error_log('[checkout] placing order for user ' . $userId . ' cart ' . $cartId . ' items ' . json_encode($items));

            $stmt = $pdo->prepare('
                INSERT INTO orders (
                    user_id,
                    address_id,
                    order_number,
                    status,
                    subtotal,
                    shipping,
                    tax,
                    grand_total,
                    promo_code,
                    promo_discount,
                    promo_free_shipping,
                    placed_at
                ) VALUES (
                    :user_id,
                    :address_id,
                    :order_number,
                    :status,
                    :subtotal,
                    :shipping,
                    :tax,
                    :grand_total,
                    :promo_code,
                    :promo_discount,
                    :promo_free_shipping,
                    NOW()
                )
            ');
            $stmt->execute([
                'user_id' => $userId,
                'address_id' => $addressId,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'subtotal' => $subtotalAdjusted,
                'shipping' => $shipping,
                'tax' => $tax,
                'grand_total' => $grandTotal,
                'promo_code' => $promoCodePersisted,
                'promo_discount' => $discount,
                'promo_free_shipping' => $promoFreeShippingFlag,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, cost_price, total_price) VALUES (:order_id, :product_id, :product_name, :quantity, :unit_price, :cost_price, :total_price)');
            $productCostStmt = $pdo->prepare('SELECT cost_price FROM products WHERE id = :id LIMIT 1');
            $stockUpdate = $pdo->prepare('UPDATE products SET stock = stock - :deduct WHERE id = :id AND stock >= :min_stock');
            foreach ($items as $item) {
                $productId = isset($item['product_id']) ? (int)$item['product_id'] : 0;
                if ($productId <= 0) {
                    throw new \RuntimeException('Cart item is missing product data.');
                }
                $quantity = isset($item['quantity']) ? max(1, (int)$item['quantity']) : 1;
                $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0.0;
                $lineTotal = isset($item['line_total']) ? (float)$item['line_total'] : $unitPrice * $quantity;
                $name = (string)($item['name'] ?? 'Product');
                $stockUpdate->execute(['deduct' => $quantity, 'min_stock' => $quantity, 'id' => $productId]);
                if ($stockUpdate->rowCount() !== 1) {
                    throw new \RuntimeException(sprintf('Insufficient stock to fulfil %s.', $name));
                }

                $productCostStmt->execute(['id' => $productId]);
                $costPriceValue = $productCostStmt->fetchColumn();
                $costPrice = $costPriceValue !== false ? max(0.0, (float)$costPriceValue) : 0.0;

                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'product_name' => $name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'cost_price' => $costPrice,
                    'total_price' => $lineTotal,
                ]);
            }

            if ($promoId !== null) {
                rb_promo_claim_for_order($pdo, $promoId);
            }

            rb_cart_clear($pdo, $cartId);
            $pdo->commit();

            return [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'subtotal' => $subtotalAdjusted,
                'shipping' => $shipping,
                'tax' => $tax,
                'grand_total' => $grandTotal,
                'discount' => $discount,
                'promo_code' => $promoCodePersisted,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('rb_cart_find_by_user')) {
    function rb_cart_find_by_user(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM carts WHERE user_id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $cart = $stmt->fetch();
        return $cart ?: null;
    }
}

if (!function_exists('rb_cart_find_by_token')) {
    function rb_cart_find_by_token(PDO $pdo, string $token): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM carts WHERE session_token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        $cart = $stmt->fetch();
        return $cart ?: null;
    }
}

if (!function_exists('rb_cart_create')) {
    function rb_cart_create(PDO $pdo, ?int $userId, string $token): int
    {
        $stmt = $pdo->prepare('INSERT INTO carts (user_id, session_token, created_at, updated_at) VALUES (:user_id, :token, NOW(), NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'token' => $token,
        ]);
        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('rb_cart_merge')) {
    function rb_cart_merge(PDO $pdo, int $sourceCartId, int $targetCartId): void
    {
        if ($sourceCartId === $targetCartId) {
            return;
        }
        $stmt = $pdo->prepare('SELECT product_id, quantity, unit_price FROM cart_items WHERE cart_id = :id');
        $stmt->execute(['id' => $sourceCartId]);
        $items = $stmt->fetchAll();
        foreach ($items as $item) {
            rb_cart_add_item($pdo, $targetCartId, (int)$item['product_id'], (int)$item['quantity'], (float)$item['unit_price']);
        }
        $stmt = $pdo->prepare('DELETE FROM carts WHERE id = :id');
        $stmt->execute(['id' => $sourceCartId]);
    }
}

if (!function_exists('rb_cart_resolve')) {
    /**
     * @return array{id:int,user_id:?int,session_token:string}
     */
    function rb_cart_resolve(PDO $pdo): array
    {
        $token = rb_cart_session_token();
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        if ($userId) {
            $userCart = rb_cart_find_by_user($pdo, $userId);
            $sessionCart = rb_cart_find_by_token($pdo, $token);

            if ($userCart && $sessionCart && (int)$userCart['id'] !== (int)$sessionCart['id']) {
                rb_cart_merge($pdo, (int)$sessionCart['id'], (int)$userCart['id']);
                $cartId = (int)$userCart['id'];
            } elseif ($userCart) {
                $cartId = (int)$userCart['id'];
            } elseif ($sessionCart) {
                $stmt = $pdo->prepare('UPDATE carts SET user_id = :user_id WHERE id = :id');
                $stmt->execute(['user_id' => $userId, 'id' => $sessionCart['id']]);
                $cartId = (int)$sessionCart['id'];
            } else {
                $cartId = rb_cart_create($pdo, $userId, $token);
            }

            // Ensure session token on cart matches current token
            $stmt = $pdo->prepare('UPDATE carts SET session_token = :token, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['token' => $token, 'id' => $cartId]);
        } else {
            $sessionCart = rb_cart_find_by_token($pdo, $token);
            if ($sessionCart) {
                $cartId = (int)$sessionCart['id'];
            } else {
                $cartId = rb_cart_create($pdo, null, $token);
            }
        }

        return [
            'id' => $cartId,
            'user_id' => $userId,
            'session_token' => $token,
        ];
    }
}

if (!function_exists('rb_cart_add_item')) {
    function rb_cart_add_item(PDO $pdo, int $cartId, int $productId, int $quantity, ?float $overridePrice = null): array
    {
        if ($quantity < 1) {
            $quantity = 1;
        }
        $stmt = $pdo->prepare('SELECT id, name, price, status FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch();
        if (!$product || $product['status'] !== 'active') {
            return ['ok' => false, 'error' => 'Product unavailable.'];
        }

        $price = $overridePrice ?? (float)$product['price'];

        $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = :cart AND product_id = :product LIMIT 1');
        $stmt->execute(['cart' => $cartId, 'product' => $productId]);
        $existing = $stmt->fetch();
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE cart_items SET quantity = quantity + :qty, unit_price = :price, added_at = NOW() WHERE id = :id');
            $stmt->execute(['qty' => $quantity, 'price' => $price, 'id' => $existing['id']]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, unit_price, added_at) VALUES (:cart, :product, :qty, :price, NOW())');
            $stmt->execute(['cart' => $cartId, 'product' => $productId, 'qty' => $quantity, 'price' => $price]);
        }

        return ['ok' => true];
    }
}

if (!function_exists('rb_cart_update_quantity')) {
    function rb_cart_update_quantity(PDO $pdo, int $cartId, int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            rb_cart_remove_item($pdo, $cartId, $productId);
            return;
        }
        $stmt = $pdo->prepare('UPDATE cart_items SET quantity = :qty WHERE cart_id = :cart AND product_id = :product');
        $stmt->execute(['qty' => $quantity, 'cart' => $cartId, 'product' => $productId]);
    }
}

if (!function_exists('rb_cart_remove_item')) {
    function rb_cart_remove_item(PDO $pdo, int $cartId, int $productId): void
    {
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart AND product_id = :product');
        $stmt->execute(['cart' => $cartId, 'product' => $productId]);
    }
}

if (!function_exists('rb_cart_clear')) {
    function rb_cart_clear(PDO $pdo, int $cartId): void
    {
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart');
        $stmt->execute(['cart' => $cartId]);
    }
}

if (!function_exists('rb_cart_items')) {
    /**
     * @return array<int, array<string,mixed>>
     */
    function rb_cart_items(PDO $pdo, int $cartId): array
    {
        $stmt = $pdo->prepare("
            SELECT
                ci.product_id,
                ci.quantity,
                ci.unit_price,
                (ci.unit_price * ci.quantity) AS line_total,
                p.name,
                (
                    SELECT image_path
                    FROM product_images pi
                    WHERE pi.product_id = ci.product_id
                    ORDER BY pi.sort_order ASC, pi.id ASC
                    LIMIT 1
                ) AS image_path
            FROM cart_items ci
            INNER JOIN products p ON p.id = ci.product_id
            WHERE ci.cart_id = :cart
            ORDER BY ci.added_at DESC
        ");
        $stmt->execute(['cart' => $cartId]);
        return $stmt->fetchAll() ?: [];
    }
}

if (!function_exists('rb_cart_summary')) {
    function rb_cart_summary(PDO $pdo, int $cartId): array
    {
        $items = rb_cart_items($pdo, $cartId);
        $subtotal = 0.0;
        $count = 0;
        foreach ($items as $item) {
            $line = (float)$item['line_total'];
            $subtotal += $line;
            $count += (int)$item['quantity'];
        }
        $shipping = 0.0;
        $tax = 0.0;
        $total = $subtotal + $shipping + $tax;
        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tax' => $tax,
            'total' => $total,
            'count' => $count,
        ];
    }
}
