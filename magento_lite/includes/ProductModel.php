<?php

declare(strict_types=1);

/**
 * ProductModel.php - Product Data Access Layer
 *
 * Handles all database operations for the products table including
 * retrieval, stock management with row-level locking, and search.
 * Uses the singleton Database connection for all queries.
 *
 * @package RedWolf\MagentoLite\Models
 * @version 1.0.0
 */

namespace RedWolf\MagentoLite\Models;

use RedWolf\MagentoLite\Database\Database;
use PDOException;
use RuntimeException;

class ProductModel
{
    /**
     * Retrieves all active products ordered by creation date descending.
     * Uses the idx_created index for optimal query performance.
     *
     * @return array<int, array<string, mixed>> List of active products
     * @throws RuntimeException On database error
     */
    public function getAllProducts(): array
    {
        $sql = "SELECT id, name, description, price_hkd, price_usd, price_cny,
                       stock_qty, image_url, category, sku, status, created_at, updated_at
                FROM products
                WHERE status = 'active'
                ORDER BY created_at DESC";

        try {
            $stmt = Database::getInstance()->query($sql);

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Failed to fetch products: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Retrieves a single product by its primary key.
     *
     * @param int $id The product ID
     * @return array<string, mixed>|null Product data or null if not found
     * @throws RuntimeException On database error
     */
    public function getProductById(int $id): ?array
    {
        $sql = "SELECT id, name, description, price_hkd, price_usd, price_cny,
                       stock_qty, image_url, category, sku, status, created_at, updated_at
                FROM products
                WHERE id = :id
                LIMIT 1";

        try {
            $stmt = Database::getInstance()->query($sql, ['id' => $id]);
            $product = $stmt->fetch();

            return $product !== false ? $product : null;
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Failed to fetch product #{$id}: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Updates product stock with transaction safety using SELECT FOR UPDATE.
     * This prevents overselling by locking the row during the update operation.
     *
     * @param int $productId The product ID to update
     * @param int $quantity  The quantity to deduct (must be positive)
     * @return array{success: bool, new_stock: int, message: string} Operation result
     */
    public function updateStock(int $productId, int $quantity): array
    {
        if ($quantity <= 0) {
            return [
                'success'   => false,
                'new_stock' => 0,
                'message'   => 'Quantity must be a positive integer.',
            ];
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            // Lock the row for update to prevent concurrent modifications
            $lockSql = "SELECT stock_qty FROM products WHERE id = :id FOR UPDATE";
            $lockStmt = $db->query($lockSql, ['id' => $productId]);
            $row = $lockStmt->fetch();

            if ($row === false) {
                $db->rollback();
                return [
                    'success'   => false,
                    'new_stock' => 0,
                    'message'   => 'Product not found.',
                ];
            }

            $currentStock = (int) $row['stock_qty'];

            if ($currentStock < $quantity) {
                $db->rollback();
                return [
                    'success'   => false,
                    'new_stock' => $currentStock,
                    'message'   => "Insufficient stock. Available: {$currentStock}, Requested: {$quantity}.",
                ];
            }

            $newStock = $currentStock - $quantity;
            $updateSql = "UPDATE products SET stock_qty = :stock WHERE id = :id";
            $db->query($updateSql, ['stock' => $newStock, 'id' => $productId]);

            $db->commit();

            return [
                'success'   => true,
                'new_stock' => $newStock,
                'message'   => 'Stock updated successfully.',
            ];
        } catch (PDOException $e) {
            $db->rollback();
            return [
                'success'   => false,
                'new_stock' => 0,
                'message'   => 'Stock update failed due to a database error.',
            ];
        }
    }

    /**
     * Searches products by name using a LIKE query.
     * Supports partial matching with wildcards on both sides.
     *
     * @param string $query The search query string
     * @return array<int, array<string, mixed>> Matching products
     * @throws RuntimeException On database error
     */
    public function searchProducts(string $query): array
    {
        $searchTerm = '%' . addcslashes($query, '%_') . '%';

        $sql = "SELECT id, name, description, price_hkd, price_usd, price_cny,
                       stock_qty, image_url, category, sku, status, created_at
                FROM products
                WHERE name LIKE :query AND status = 'active'
                ORDER BY created_at DESC";

        try {
            $stmt = Database::getInstance()->query($sql, ['query' => $searchTerm]);

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Product search failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Retrieves all active products with pagination support.
     * Uses the idx_created index for efficient sorting.
     *
     * @param int $page    The page number (1-based)
     * @param int $perPage Number of items per page
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function getProductsPaginated(int $page = 1, int $perPage = 12): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $db = Database::getInstance();

        try {
            $countStmt = $db->query(
                "SELECT COUNT(*) as total FROM products WHERE status = 'active'"
            );
            $total = (int) $countStmt->fetch()['total'];

            $sql = "SELECT id, name, description, price_hkd, price_usd, price_cny,
                           stock_qty, image_url, category, sku, status, created_at
                    FROM products
                    WHERE status = 'active'
                    ORDER BY created_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $db->query($sql, [
                'limit'  => $perPage,
                'offset' => $offset,
            ]);

            return [
                'items'       => $stmt->fetchAll(),
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ];
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Paginated product fetch failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }
}
