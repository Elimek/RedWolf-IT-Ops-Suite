<?php

declare(strict_types=1);

/**
 * add_to_cart.php - AJAX Add to Cart API Endpoint
 *
 * Receives JSON POST requests to add products to the session-based cart.
 * Validates CSRF token, input parameters, and stock availability
 * before adding the item to the cart.
 *
 * @package RedWolf\MagentoLite\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ProductModel.php';
require_once __DIR__ . '/../includes/CartManager.php';
require_once __DIR__ . '/../includes/CsrfManager.php';

use RedWolf\MagentoLite\Cart\CartManager;
use RedWolf\MagentoLite\Security\CsrfManager;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success'    => false,
        'message'    => 'Method not allowed. Use POST.',
        'cart_count' => 0,
        'cart_total' => '0.00',
    ]);
    exit;
}

// Read and parse JSON input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success'    => false,
        'message'    => 'Invalid request body. Expected JSON.',
        'cart_count' => 0,
        'cart_total' => '0.00',
    ]);
    exit;
}

// Validate CSRF token
$csrfToken = $data['csrf_token'] ?? '';
$csrf = new CsrfManager();

if (!$csrf->validateToken($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success'    => false,
        'message'    => 'Invalid or expired CSRF token. Please refresh the page.',
        'cart_count' => 0,
        'cart_total' => '0.00',
    ]);
    exit;
}

// Validate product_id (must be a positive integer)
$productId = filter_var($data['product_id'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($productId === false) {
    http_response_code(400);
    echo json_encode([
        'success'    => false,
        'message'    => 'Invalid product ID. Must be a positive integer.',
        'cart_count' => 0,
        'cart_total' => '0.00',
    ]);
    exit;
}

// Validate quantity (must be an integer between 1 and 99)
$quantity = filter_var($data['quantity'] ?? 1, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 99],
]);

if ($quantity === false) {
    http_response_code(400);
    echo json_encode([
        'success'    => false,
        'message'    => 'Invalid quantity. Must be between 1 and 99.',
        'cart_count' => 0,
        'cart_total' => '0.00',
    ]);
    exit;
}

// Process the cart addition
try {
    $cartManager = new CartManager();
    $result = $cartManager->addItem((int) $productId, (int) $quantity);

    $cartTotal = $cartManager->getCartTotal('HKD');

    http_response_code($result['success'] ? 200 : 422);
    echo json_encode([
        'success'    => $result['success'],
        'message'    => $result['message'],
        'cart_count' => $result['cart_count'],
        'cart_total' => number_format($cartTotal['total'], 2),
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success'    => false,
        'message'    => 'An internal error occurred. Please try again.',
        'cart_count' => 0,
        'cart_total' => '0.00',
    ]);
}
