<?php

declare(strict_types=1);

/**
 * update_stock.php - Stock Update API Endpoint
 *
 * Handles stock deduction and read operations with transaction safety.
 * Uses SELECT FOR UPDATE row-level locking to prevent concurrent
 * stock overselling. Supports both update and read-only check actions.
 *
 * @package RedWolf\MagentoLite\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ProductModel.php';
require_once __DIR__ . '/../includes/CsrfManager.php';

use RedWolf\MagentoLite\Models\ProductModel;
use RedWolf\MagentoLite\Security\CsrfManager;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success'   => false,
        'message'   => 'Method not allowed. Use POST.',
        'new_stock' => 0,
    ]);
    exit;
}

// Read and parse JSON input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success'   => false,
        'message'   => 'Invalid request body. Expected JSON.',
        'new_stock' => 0,
    ]);
    exit;
}

// Validate CSRF token
$csrfToken = $data['csrf_token'] ?? '';
$csrf = new CsrfManager();

if (!$csrf->validateToken($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success'   => false,
        'message'   => 'Invalid or expired CSRF token.',
        'new_stock' => 0,
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
        'success'   => false,
        'message'   => 'Invalid product ID. Must be a positive integer.',
        'new_stock' => 0,
    ]);
    exit;
}

// Validate quantity (must be a positive integer)
$quantity = filter_var($data['quantity'] ?? 0, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0],
]);

if ($quantity === false) {
    http_response_code(400);
    echo json_encode([
        'success'   => false,
        'message'   => 'Invalid quantity. Must be a positive integer.',
        'new_stock' => 0,
    ]);
    exit;
}

$action = $data['action'] ?? 'update';

try {
    $productModel = new ProductModel();

    // For "check" action, return current stock without modification
    if ($action === 'check') {
        $product = $productModel->getProductById((int) $productId);

        if ($product === null) {
            http_response_code(404);
            echo json_encode([
                'success'   => false,
                'message'   => 'Product not found.',
                'new_stock' => 0,
            ]);
            exit;
        }

        echo json_encode([
            'success'   => true,
            'message'   => 'Stock check complete.',
            'new_stock' => (int) $product['stock_qty'],
        ]);
        exit;
    }

    // Perform the stock update with transaction safety
    $result = $productModel->updateStock((int) $productId, (int) $quantity);

    http_response_code($result['success'] ? 200 : 422);
    echo json_encode($result);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success'   => false,
        'message'   => 'An internal error occurred during stock update.',
        'new_stock' => 0,
    ]);
}
