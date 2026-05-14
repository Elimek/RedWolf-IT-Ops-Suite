<?php

declare(strict_types=1);

/**
 * get_products.php - Product Listing API Endpoint
 *
 * Returns active products as JSON with optional currency conversion
 * and pagination. Also supports returning cart data when the
 * 'action' parameter is set to 'cart'.
 *
 * @package RedWolf\MagentoLite\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ProductModel.php';
require_once __DIR__ . '/../includes/CartManager.php';

use RedWolf\MagentoLite\Models\ProductModel;
use RedWolf\MagentoLite\Cart\CartManager;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.',
        'data'    => [],
    ]);
    exit;
}

try {
    $productModel = new ProductModel();
    $action = $_GET['action'] ?? 'list';

    // Handle cart data request
    if ($action === 'cart') {
        $cartManager = new CartManager();
        $currency = $_GET['currency'] ?? 'hkd';

        $cartTotal = $cartManager->getCartTotal($currency);
        $cartItems = $cartManager->getCartItems();

        // Format cart items with currency-specific line totals
        $items = array_map(function (array $item) use ($currency): array {
            $field = match ($currency) {
                'usd' => 'line_total_usd',
                'cny' => 'line_total_cny',
                default => 'line_total_hkd',
            };

            return [
                'product_id'   => $item['product_id'],
                'product_name' => $item['product_name'],
                'quantity'     => $item['quantity'],
                'line_total'   => number_format($item[$field], 2),
                'stock_qty'    => $item['stock_qty'],
            ];
        }, $cartItems);

        echo json_encode([
            'success' => true,
            'cart'    => [
                'items'    => $items,
                'total'    => number_format($cartTotal['total'], 2),
                'currency' => $cartTotal['currency'],
                'count'    => $cartTotal['item_count'],
            ],
        ]);
        exit;
    }

    // Handle product listing with pagination
    $page = (int) ($_GET['page'] ?? 1);
    $perPage = (int) ($_GET['per_page'] ?? 12);
    $currency = strtolower($_GET['currency'] ?? 'hkd');

    // Clamp pagination values
    $page = max(1, $page);
    $perPage = min(100, max(1, $perPage));

    $result = $productModel->getProductsPaginated($page, $perPage);

    // Map products to include currency-specific price
    $products = array_map(function (array $product) use ($currency): array {
        $priceField = match ($currency) {
            'usd' => 'price_usd',
            'cny' => 'price_cny',
            default => 'price_hkd',
        };

        return [
            'id'          => (int) $product['id'],
            'name'        => $product['name'],
            'description' => $product['description'],
            'price'       => number_format((float) $product[$priceField], 2),
            'price_hkd'   => number_format((float) $product['price_hkd'], 2),
            'price_usd'   => number_format((float) $product['price_usd'], 2),
            'price_cny'   => number_format((float) $product['price_cny'], 2),
            'currency'    => strtoupper($currency),
            'stock_qty'   => (int) $product['stock_qty'],
            'category'    => $product['category'],
            'sku'         => $product['sku'],
            'status'      => $product['status'],
            'image_url'   => $product['image_url'],
            'created_at'  => $product['created_at'],
        ];
    }, $result['items']);

    echo json_encode([
        'success' => true,
        'data'    => $products,
        'pagination' => [
            'total'       => $result['total'],
            'page'        => $result['page'],
            'per_page'    => $result['per_page'],
            'total_pages' => $result['total_pages'],
        ],
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch products.',
        'data'    => [],
    ]);
}
