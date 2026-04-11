<?php
declare(strict_types=1);

namespace Kronos\API\Endpoints;

use Kronos\API\KronosAPIRouter;
use Kronos\Core\KronosApp;
use Kronos\Core\KronosDB;

/**
 * CommerceEndpoint — Products, Cart, Orders API.
 * Only active in E-Commerce mode.
 */
class CommerceEndpoint extends ApiEndpoint
{
    private const ALLOWED_STATUSES = ['draft', 'published', 'archived'];

    private KronosAPIRouter $api;
    private KronosDB $db;

    public function __construct(KronosAPIRouter $api)
    {
        $this->api = $api;
        $this->db  = KronosApp::getInstance()->db();
    }

    public function handle(array $params): void
    {
        if (!kronos_is_ecommerce()) {
            kronos_abort(503, 'E-Commerce mode is not active.');
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $parts  = explode('/api/kronos/v1/commerce/', $uri);
        $sub    = trim($parts[1] ?? '', '/');

        // Dispatch to sub-resource
        if (str_starts_with($sub, 'products')) {
            $this->dispatchProducts($method, $params);
        } elseif (str_starts_with($sub, 'cart')) {
            $this->dispatchCart($method, $params);
        } elseif (str_starts_with($sub, 'orders')) {
            $this->dispatchOrders($method, $params, $sub);
        } else {
            kronos_abort(404, 'Unknown commerce resource.');
        }
    }

    // ------------------------------------------------------------------
    // Products
    // ------------------------------------------------------------------

    private function dispatchProducts(string $method, array $params): void
    {
        match (true) {
            $method === 'GET' && isset($params['id']) => $this->getProduct((int) $params['id']),
            $method === 'GET'   => $this->listProducts(),
            $method === 'POST'  => $this->createProduct(),
            $method === 'PUT'   => $this->updateProduct((int) ($params['id'] ?? 0)),
            $method === 'DELETE' => $this->deleteProduct((int) ($params['id'] ?? 0)),
            default             => kronos_abort(405, 'Method not allowed'),
        };
    }

    private function listProducts(): void
    {
        $status = isset($_GET['status']) ? trim((string) $_GET['status']) : 'published';
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = 'published';
        }

        $rows = $this->db->getResults(
            'SELECT id, name, slug, price, sale_price, stock, status, created_at FROM kronos_products WHERE status = ? ORDER BY created_at DESC',
            [$status]
        );
        kronos_json(['data' => $rows]);
    }

    private function getProduct(int $id): void
    {
        $row = $this->db->getRow('SELECT * FROM kronos_products WHERE id = ? LIMIT 1', [$id]);
        if ($row === null) {
            kronos_abort(404, 'Product not found.');
        }
        $row['images'] = json_decode($row['images'] ?? '[]', true) ?? [];
        $row['meta']   = json_decode($row['meta'] ?? '{}', true) ?? [];
        kronos_json(['data' => $row]);
    }

    private function createProduct(): void
    {
        $body = $this->getJsonBody();
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            kronos_abort(422, 'Product name is required.');
        }

        $slug = kronos_sanitize_slug($name);

        $id = $this->db->insert('kronos_products', [
            'name'        => $name,
            'slug'        => $slug,
            'description' => (string) ($body['description'] ?? ''),
            'short_desc'  => (string) ($body['short_desc'] ?? ''),
            'price'       => (float)  ($body['price'] ?? 0),
            'sale_price'  => isset($body['sale_price']) ? (float) $body['sale_price'] : null,
            'sku'         => (string) ($body['sku'] ?? ''),
            'stock'       => (int)    ($body['stock'] ?? 0),
            'status'      => in_array($body['status'] ?? '', self::ALLOWED_STATUSES, true)
                                ? $body['status'] : 'draft',
            'images'      => json_encode($body['images'] ?? [], JSON_THROW_ON_ERROR),
            'meta'        => json_encode($body['meta'] ?? [], JSON_THROW_ON_ERROR),
        ]);

        kronos_json(['success' => true, 'id' => $id], 201);
    }

    private function updateProduct(int $id): void
    {
        $existing = $this->db->getRow('SELECT id FROM kronos_products WHERE id = ? LIMIT 1', [$id]);
        if ($existing === null) {
            kronos_abort(404, 'Product not found.');
        }

        $body = $this->getJsonBody();
        $data = [];

        $textFields = ['name', 'description', 'short_desc', 'sku'];
        foreach ($textFields as $field) {
            if (isset($body[$field])) {
                $data[$field] = trim((string) $body[$field]);
            }
        }
        if (isset($body['price'])) {
            $data['price'] = (float) $body['price'];
        }
        if (isset($body['sale_price'])) {
            $data['sale_price'] = (float) $body['sale_price'];
        }
        if (isset($body['stock'])) {
            $data['stock'] = (int) $body['stock'];
        }
        if (isset($body['status']) && in_array($body['status'], self::ALLOWED_STATUSES, true)) {
            $data['status'] = $body['status'];
        }
        if (isset($body['images'])) {
            $data['images'] = json_encode($body['images'], JSON_THROW_ON_ERROR);
        }

        if (empty($data)) {
            kronos_abort(422, 'No valid fields to update.');
        }

        $this->db->update('kronos_products', $data, ['id' => $id]);
        kronos_json(['success' => true]);
    }

    private function deleteProduct(int $id): void
    {
        $existing = $this->db->getRow('SELECT id FROM kronos_products WHERE id = ? LIMIT 1', [$id]);
        if ($existing === null) {
            kronos_abort(404, 'Product not found.');
        }
        $this->db->delete('kronos_products', ['id' => $id]);
        kronos_json(['success' => true]);
    }

    // ------------------------------------------------------------------
    // Cart (session-based)
    // ------------------------------------------------------------------

    private function dispatchCart(string $method, array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        match ($method) {
            'GET'    => $this->getCart(),
            'POST'   => $this->addToCart(),
            'DELETE' => $this->removeFromCart((int) ($params['item_id'] ?? 0)),
            default  => kronos_abort(405, 'Method not allowed'),
        };
    }

    private function getCart(): void
    {
        kronos_json(['data' => $_SESSION['kronos_cart'] ?? []]);
    }

    private function addToCart(): void
    {
        $body      = $this->getJsonBody();
        $productId = (int) ($body['product_id'] ?? 0);
        $qty       = max(1, (int) ($body['qty'] ?? 1));

        if ($productId <= 0) {
            kronos_abort(422, 'product_id is required.');
        }

        $product = $this->db->getRow(
            'SELECT id, name, price, sale_price, stock, manage_stock FROM kronos_products WHERE id = ? AND status = "published" LIMIT 1',
            [$productId]
        );

        if ($product === null) {
            kronos_abort(404, 'Product not found or not available.');
        }
        if ($product['manage_stock'] && $product['stock'] < $qty) {
            kronos_abort(409, 'Insufficient stock.');
        }

        $cart = &$_SESSION['kronos_cart'];
        if (!is_array($cart)) {
            $cart = [];
        }

        $price = (float) ($product['sale_price'] ?? $product['price'] ?? 0);
        $key   = 'product_' . $productId;

        if (isset($cart[$key])) {
            $cart[$key]['qty'] += $qty;
        } else {
            $cart[$key] = [
                'product_id'   => $productId,
                'product_name' => $product['name'],
                'unit_price'   => $price,
                'qty'          => $qty,
            ];
        }

        kronos_json(['success' => true, 'cart' => $cart]);
    }

    private function removeFromCart(int $itemId): void
    {
        $key = 'product_' . $itemId;
        if (isset($_SESSION['kronos_cart'][$key])) {
            unset($_SESSION['kronos_cart'][$key]);
        }
        kronos_json(['success' => true, 'cart' => $_SESSION['kronos_cart'] ?? []]);
    }

    // ------------------------------------------------------------------
    // Orders
    // ------------------------------------------------------------------

    private function dispatchOrders(string $method, array $params, string $sub): void
    {
        if ($method === 'POST' && !isset($params['id'])) {
            $this->createOrder();
        } elseif ($method === 'GET' && isset($params['id'])) {
            $this->getOrder((int) $params['id']);
        } elseif ($method === 'GET') {
            $this->listOrders();
        } elseif ($method === 'PUT' && isset($params['id']) && str_ends_with($sub, '/status')) {
            $this->updateOrderStatus((int) $params['id']);
        } else {
            kronos_abort(405, 'Method not allowed');
        }
    }

    private function listOrders(): void
    {
        $user = kronos_current_user();
        $isManager = kronos_user_can('app_manager');

        if ($isManager) {
            $rows = $this->db->getResults(
                'SELECT id, order_number, customer_id, status, total, payment_method, created_at FROM kronos_orders ORDER BY created_at DESC LIMIT 50'
            );
        } else {
            $customerId = (int) ($user['id'] ?? 0);
            $rows = $this->db->getResults(
                'SELECT id, order_number, status, total, payment_method, created_at FROM kronos_orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 50',
                [$customerId]
            );
        }

        kronos_json(['data' => $rows]);
    }

    private function getOrder(int $id): void
    {
        $order = $this->db->getRow('SELECT * FROM kronos_orders WHERE id = ? LIMIT 1', [$id]);
        if ($order === null) {
            kronos_abort(404, 'Order not found.');
        }

        $items   = $this->db->getResults('SELECT * FROM kronos_order_items WHERE order_id = ?', [$id]);
        $addresses = $this->db->getResults('SELECT * FROM kronos_order_addresses WHERE order_id = ?', [$id]);

        kronos_json(['data' => array_merge($order, ['items' => $items, 'addresses' => $addresses])]);
    }

    private function createOrder(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $cart = $_SESSION['kronos_cart'] ?? [];
        if (empty($cart)) {
            kronos_abort(422, 'Cart is empty.');
        }

        $body = $this->getJsonBody();

        // Calculate totals
        $subtotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['qty'], $cart));
        $tax      = round($subtotal * 0.10, 2); // 10% tax — configurable via option later
        $shipping = (float) ($body['shipping'] ?? 0);
        $total    = $subtotal + $tax + $shipping;

        $orderNumber = 'KR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $orderId = $this->db->insert('kronos_orders', [
            'order_number'   => $orderNumber,
            'customer_id'    => (int) (kronos_current_user()['id'] ?? 0),
            'status'         => 'pending',
            'subtotal'       => $subtotal,
            'tax'            => $tax,
            'shipping'       => $shipping,
            'total'          => $total,
            'payment_method' => (string) ($body['payment_method'] ?? 'stripe'),
            'currency'       => 'USD',
        ]);

        // Insert items
        foreach ($cart as $item) {
            $this->db->insert('kronos_order_items', [
                'order_id'     => $orderId,
                'product_id'   => $item['product_id'],
                'product_name' => $item['product_name'],
                'qty'          => $item['qty'],
                'unit_price'   => $item['unit_price'],
                'total_price'  => $item['unit_price'] * $item['qty'],
            ]);
        }

        // Insert billing address
        if (!empty($body['billing'])) {
            $this->insertAddress((int) $orderId, 'billing', $body['billing']);
        }
        if (!empty($body['shipping_address'])) {
            $this->insertAddress((int) $orderId, 'shipping', $body['shipping_address']);
        }

        // Clear cart
        $_SESSION['kronos_cart'] = [];

        // Fire hook for payment gateway to pick up
        do_action('kronos/commerce/order_created', $orderId, $body);

        kronos_json(['success' => true, 'order_id' => $orderId, 'order_number' => $orderNumber], 201);
    }

    private function updateOrderStatus(int $id): void
    {
        $body   = $this->getJsonBody();
        $status = trim((string) ($body['status'] ?? ''));
        $allowed = ['pending', 'processing', 'completed', 'cancelled', 'refunded'];

        if (!in_array($status, $allowed, true)) {
            kronos_abort(422, 'Invalid status value.');
        }

        $existing = $this->db->getRow('SELECT id FROM kronos_orders WHERE id = ? LIMIT 1', [$id]);
        if ($existing === null) {
            kronos_abort(404, 'Order not found.');
        }

        $this->db->update('kronos_orders', ['status' => $status], ['id' => $id]);

        // SSE hook — stream.php picks this up via polling
        do_action('kronos/commerce/order_status_changed', $id, $status);

        kronos_json(['success' => true]);
    }

    /** @param array<string, mixed> $addressData */
    private function insertAddress(int $orderId, string $type, array $addressData): void
    {
        $allowed = ['billing', 'shipping'];
        if (!in_array($type, $allowed, true)) {
            return;
        }

        $this->db->insert('kronos_order_addresses', [
            'order_id'     => $orderId,
            'address_type' => $type,
            'first_name'   => (string) ($addressData['first_name'] ?? ''),
            'last_name'    => (string) ($addressData['last_name'] ?? ''),
            'company'      => (string) ($addressData['company'] ?? ''),
            'address_1'    => (string) ($addressData['address_1'] ?? ''),
            'address_2'    => (string) ($addressData['address_2'] ?? ''),
            'city'         => (string) ($addressData['city'] ?? ''),
            'state'        => (string) ($addressData['state'] ?? ''),
            'postcode'     => (string) ($addressData['postcode'] ?? ''),
            'country'      => substr((string) ($addressData['country'] ?? ''), 0, 2),
            'phone'        => (string) ($addressData['phone'] ?? ''),
            'email'        => (string) ($addressData['email'] ?? ''),
        ]);
    }

}
