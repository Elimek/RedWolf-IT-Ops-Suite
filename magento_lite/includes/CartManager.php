<?php

declare(strict_types=1);

/**
 * CartManager.php - Session-Based Shopping Cart Manager
 *
 * Manages shopping cart operations using PHP sessions. Stores cart items
 * in the session with full product details fetched from the database.
 * Supports multi-currency totals and quantity management.
 *
 * @package RedWolf\MagentoLite\Cart
 * @version 1.0.0
 */

namespace RedWolf\MagentoLite\Cart;

use RedWolf\MagentoLite\Database\Database;
use RedWolf\MagentoLite\Models\ProductModel;
use RuntimeException;

class CartManager
{
    /** @var string Session key for storing cart items */
    private const CART_SESSION_KEY = 'redwolf_cart';

    /** @var ProductModel Product model for stock/price lookups */
    private ProductModel $productModel;

    /**
     * Initializes the cart manager. Starts session if not active.
     */
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->productModel = new ProductModel();
    }

    /**
     * Adds an item to the shopping cart.
     * Validates stock availability before adding. If the item already exists
     * in the cart, the quantity is increased.
     *
     * @param int $productId The product ID to add
     * @param int $quantity  The quantity to add (must be 1-99)
     * @return array{success: bool, message: string, cart_count: int} Operation result
     */
    public function addItem(int $productId, int $quantity): array
    {
        if ($quantity < 1 || $quantity > 99) {
            return [
                'success'    => false,
                'message'    => 'Quantity must be between 1 and 99.',
                'cart_count' => $this->getCartCount(),
            ];
        }

        $product = $this->productModel->getProductById($productId);

        if ($product === null) {
            return [
                'success'    => false,
                'message'    => 'Product not found.',
                'cart_count' => $this->getCartCount(),
            ];
        }

        $currentQty = $this->getItemQuantity($productId);
        $availableStock = (int) $product['stock_qty'];
        $requestedTotal = $currentQty + $quantity;

        if ($requestedTotal > $availableStock) {
            return [
                'success'    => false,
                'message'    => "Cannot add {$quantity} item(s). Only {$availableStock} in stock, "
                              . "{$currentQty} already in cart.",
                'cart_count' => $this->getCartCount(),
            ];
        }

        $cart = $this->getCart();

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += $quantity;
        } else {
            $cart[$productId] = [
                'product_id' => $productId,
                'quantity'   => $quantity,
                'added_at'   => time(),
            ];
        }

        $this->saveCart($cart);

        return [
            'success'    => true,
            'message'    => "\"{$product['name']}\" added to cart.",
            'cart_count' => $this->getCartCount(),
        ];
    }

    /**
     * Removes an item from the shopping cart.
     *
     * @param int $productId The product ID to remove
     * @return array{success: bool, message: string, cart_count: int} Operation result
     */
    public function removeItem(int $productId): array
    {
        $cart = $this->getCart();

        if (!isset($cart[$productId])) {
            return [
                'success'    => false,
                'message'    => 'Item not found in cart.',
                'cart_count' => $this->getCartCount(),
            ];
        }

        unset($cart[$productId]);
        $this->saveCart($cart);

        return [
            'success'    => true,
            'message'    => 'Item removed from cart.',
            'cart_count' => $this->getCartCount(),
        ];
    }

    /**
     * Updates the quantity of an item in the cart.
     * Setting quantity to 0 removes the item.
     *
     * @param int $productId The product ID to update
     * @param int $quantity  The new quantity (0 to remove)
     * @return array{success: bool, message: string, cart_count: int} Operation result
     */
    public function updateQuantity(int $productId, int $quantity): array
    {
        if ($quantity < 0 || $quantity > 99) {
            return [
                'success'    => false,
                'message'    => 'Quantity must be between 0 and 99.',
                'cart_count' => $this->getCartCount(),
            ];
        }

        if ($quantity === 0) {
            return $this->removeItem($productId);
        }

        $cart = $this->getCart();

        if (!isset($cart[$productId])) {
            return [
                'success'    => false,
                'message'    => 'Item not found in cart.',
                'cart_count' => $this->getCartCount(),
            ];
        }

        $product = $this->productModel->getProductById($productId);

        if ($product !== null && $quantity > (int) $product['stock_qty']) {
            return [
                'success'    => false,
                'message'    => "Only {$product['stock_qty']} available in stock.",
                'cart_count' => $this->getCartCount(),
            ];
        }

        $cart[$productId]['quantity'] = $quantity;
        $this->saveCart($cart);

        return [
            'success'    => true,
            'message'    => 'Quantity updated.',
            'cart_count' => $this->getCartCount(),
        ];
    }

    /**
     * Retrieves all cart items with full product details.
     * Joins session cart data with live product data from the database.
     *
     * @return array<int, array<string, mixed>> Cart items with product details
     */
    public function getCartItems(): array
    {
        $cart = $this->getCart();
        $items = [];

        foreach ($cart as $productId => $cartItem) {
            $product = $this->productModel->getProductById((int) $productId);

            if ($product === null) {
                continue;
            }

            $items[] = [
                'product_id'    => (int) $productId,
                'product_name'  => $product['name'],
                'sku'           => $product['sku'],
                'quantity'      => $cartItem['quantity'],
                'price_hkd'     => (float) $product['price_hkd'],
                'price_usd'     => (float) $product['price_usd'],
                'price_cny'     => (float) $product['price_cny'],
                'stock_qty'     => (int) $product['stock_qty'],
                'line_total_hkd' => (float) $product['price_hkd'] * $cartItem['quantity'],
                'line_total_usd' => (float) $product['price_usd'] * $cartItem['quantity'],
                'line_total_cny' => (float) $product['price_cny'] * $cartItem['quantity'],
            ];
        }

        return $items;
    }

    /**
     * Calculates the cart total in the specified currency.
     * Currency conversion rates are read from environment variables.
     *
     * @param string $currency Target currency: 'HKD', 'USD', or 'CNY'
     * @return array{total: float, currency: string, item_count: int} Cart summary
     */
    public function getCartTotal(string $currency = 'HKD'): array
    {
        $items = $this->getCartItems();
        $totalHkd = 0.0;

        foreach ($items as $item) {
            $totalHkd += $item['line_total_hkd'];
        }

        $convertedTotal = match (strtoupper($currency)) {
            'USD' => round($totalHkd / (float) (getenv('USD_RATE') ?: 7.82), 2),
            'CNY' => round($totalHkd * (float) (getenv('CNY_RATE') ?: 1.08), 2),
            default => round($totalHkd, 2),
        };

        return [
            'total'      => $convertedTotal,
            'currency'   => strtoupper($currency),
            'item_count' => count($items),
        ];
    }

    /**
     * Empties the entire shopping cart.
     *
     * @return void
     */
    public function clearCart(): void
    {
        $_SESSION[self::CART_SESSION_KEY] = [];
    }

    /**
     * Returns the total number of items in the cart.
     *
     * @return int Total item count
     */
    public function getCartCount(): int
    {
        return array_sum(array_column($this->getCart(), 'quantity'));
    }

    /**
     * Returns the raw cart data from the session.
     *
     * @return array<int, array<string, mixed>> Raw cart array
     */
    private function getCart(): array
    {
        return $_SESSION[self::CART_SESSION_KEY] ?? [];
    }

    /**
     * Saves the cart data to the session.
     *
     * @param array<int, array<string, mixed>> $cart Cart data to save
     * @return void
     */
    private function saveCart(array $cart): void
    {
        $_SESSION[self::CART_SESSION_KEY] = $cart;
    }

    /**
     * Gets the quantity of a specific item currently in the cart.
     *
     * @param int $productId The product ID to check
     * @return int Current quantity in cart (0 if not present)
     */
    private function getItemQuantity(int $productId): int
    {
        $cart = $this->getCart();

        return $cart[$productId]['quantity'] ?? 0;
    }
}
