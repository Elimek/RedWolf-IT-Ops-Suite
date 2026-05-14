<?php

declare(strict_types=1);

/**
 * product.php - Product Listing Page
 *
 * Displays all active products in a responsive grid layout with
 * multi-currency pricing, stock status, and AJAX add-to-cart.
 * Uses Bootstrap 5 for styling and vanilla JS for interactivity.
 *
 * @package RedWolf\MagentoLite\Pages
 * @version 1.0.0
 */

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/ProductModel.php';
require_once __DIR__ . '/includes/CartManager.php';
require_once __DIR__ . '/includes/CsrfManager.php';

use RedWolf\MagentoLite\Models\ProductModel;
use RedWolf\MagentoLite\Cart\CartManager;
use RedWolf\MagentoLite\Security\CsrfManager;

$csrf = new CsrfManager();
$csrfToken = $csrf->getActiveToken();

$productModel = new ProductModel();
$products = $productModel->getAllProducts();

$cartManager = new CartManager();
$cartCount = $cartManager->getCartCount();
$cartTotal = $cartManager->getCartTotal('HKD');

$currencyRates = [
    'USD_RATE' => getenv('USD_RATE') ?: '7.82',
    'CNY_RATE' => getenv('CNY_RATE') ?: '1.08',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RedWolf Airsoft - Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
          crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
          rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>

    <!-- Navigation Header -->
    <header class="rw-header">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand fw-bold" href="/">
                    <i class="bi bi-shield-fill-check me-2"></i>RedWolf Airsoft
                </a>
                <div class="d-flex align-items-center gap-3">
                    <!-- Currency Switcher -->
                    <div class="dropdown">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button"
                                id="currencyDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-currency-exchange me-1"></i>
                            <span id="currentCurrencyLabel">HKD</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="currencyDropdown">
                            <li><a class="dropdown-item currency-option active" href="#" data-currency="hkd">HKD ($)</a></li>
                            <li><a class="dropdown-item currency-option" href="#" data-currency="usd">USD ($)</a></li>
                            <li><a class="dropdown-item currency-option" href="#" data-currency="cny">CNY (&yen;)</a></li>
                        </ul>
                    </div>

                    <!-- Cart Button -->
                    <button class="btn btn-outline-light btn-sm position-relative" id="cartToggleBtn">
                        <i class="bi bi-cart3"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                              id="cartBadge">
                            <?= $cartCount ?>
                            <span class="visually-hidden">items in cart</span>
                        </span>
                    </button>
                </div>
            </div>
        </nav>
    </header>

    <!-- Page Content -->
    <main class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 fw-bold">Product Catalog</h1>
                <p class="text-muted mb-0">
                    <?= count($products) ?> products available
                </p>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="productGrid">
            <?php foreach ($products as $product): ?>
            <div class="col">
                <div class="card h-100 product-card" data-product-id="<?= (int) $product['id'] ?>">
                    <!-- Product Image Placeholder -->
                    <div class="product-image-wrapper">
                        <svg class="product-image-placeholder" viewBox="0 0 300 200"
                             xmlns="http://www.w3.org/2000/svg">
                            <rect width="300" height="200" fill="#e9ecef"/>
                            <text x="150" y="100" text-anchor="middle" fill="#6c757d"
                                  font-size="14" font-family="sans-serif">
                                <?= htmlspecialchars($product['name'] ?? 'No Image') ?>
                            </text>
                            <text x="150" y="120" text-anchor="middle" fill="#adb5bd"
                                  font-size="11" font-family="sans-serif">
                                SKU: <?= htmlspecialchars($product['sku'] ?? 'N/A') ?>
                            </text>
                        </svg>
                    </div>

                    <div class="card-body d-flex flex-column">
                        <!-- Product Info -->
                        <span class="badge bg-secondary mb-2 align-self-start">
                            <?= htmlspecialchars($product['category'] ?? 'General') ?>
                        </span>
                        <h5 class="card-title mb-1">
                            <?= htmlspecialchars($product['name']) ?>
                        </h5>
                        <p class="card-text text-muted small mb-2">
                            <?= htmlspecialchars(mb_substr($product['description'] ?? '', 0, 80)) ?>
                        </p>

                        <!-- Prices in all currencies -->
                        <div class="price-group mb-2">
                            <div class="price-row" data-currency="hkd">
                                <span class="price-label">HKD</span>
                                <span class="price-value">$<?= number_format((float) $product['price_hkd'], 2) ?></span>
                            </div>
                            <div class="price-row" data-currency="usd">
                                <span class="price-label">USD</span>
                                <span class="price-value">$<?= number_format((float) $product['price_usd'], 2) ?></span>
                            </div>
                            <div class="price-row" data-currency="cny">
                                <span class="price-label">CNY</span>
                                <span class="price-value">&yen;<?= number_format((float) $product['price_cny'], 2) ?></span>
                            </div>
                        </div>

                        <!-- Stock Status -->
                        <div class="mt-auto">
                            <?php
                            $stock = (int) $product['stock_qty'];
                            $stockClass = match (true) {
                                $stock === 0 => 'text-danger',
                                $stock <= 10 => 'text-warning',
                                default      => 'text-success',
                            };
                            $stockLabel = match (true) {
                                $stock === 0 => 'Out of Stock',
                                $stock <= 10 => "Low Stock ({$stock})",
                                default      => "In Stock ({$stock})",
                            };
                            ?>
                            <span class="small <?= $stockClass ?>">
                                <i class="bi bi-box-seam me-1"></i><?= $stockLabel ?>
                            </span>
                        </div>
                    </div>

                    <!-- Add to Cart -->
                    <div class="card-footer bg-transparent border-top-0 pb-3">
                        <button class="btn btn-primary w-100 add-to-cart-btn"
                                data-product-id="<?= (int) $product['id'] ?>"
                                data-product-name="<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>"
                                data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>"
                                <?= $stock === 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-cart-plus me-1"></i>
                            <?= $stock === 0 ? 'Out of Stock' : 'Add to Cart' ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Cart Sidebar -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="cartSidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title"><i class="bi bi-cart3 me-2"></i>Shopping Cart</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body" id="cartSidebarBody">
            <div class="text-center text-muted py-5" id="cartEmptyMessage">
                <i class="bi bi-cart-x display-4 d-block mb-2"></i>
                <p>Your cart is empty</p>
            </div>
            <div id="cartItemsContainer" class="d-none"></div>
        </div>
        <div class="offcanvas-footer border-top p-3 d-none" id="cartFooter">
            <div class="d-flex justify-content-between mb-2">
                <strong>Total:</strong>
                <strong id="cartSidebarTotal">$0.00</strong>
            </div>
            <button class="btn btn-danger w-100">Proceed to Checkout</button>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"></script>
    <script src="assets/script.js"></script>
    <script>
        window.RW_CONFIG = {
            csrfToken: <?= json_encode($csrfToken) ?>,
            currencyRates: <?= json_encode($currencyRates) ?>,
            apiBase: 'api'
        };
    </script>
</body>
</html>
