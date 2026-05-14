# Chunk 1: Magento Lite - Architecture Documentation

## 1. Why No Laravel? (Zero Dependency Principle)

### Decision

The entire e-commerce platform is built with vanilla PHP 8.2 and zero external dependencies - no Composer, no NPM packages, no frameworks.

### Rationale

| Factor | Laravel | Vanilla PHP (Our Choice) |
|--------|---------|------------------------|
| Deployment | Requires Composer, vendor dir (~50MB+) | Copy files, done |
| Dependency conflicts | Version lock issues between packages | None |
| Learning signal | "Knows Laravel" | "Understands PHP internals" |
| Attack surface | Third-party package vulnerabilities | Minimal |
| Debugging | Framework magic, deep call stacks | Direct, transparent |

For an IT Officer role, the ability to write production code from scratch demonstrates deeper PHP understanding than framework usage. Every function is visible, every query is explicit, and every security mechanism is intentionally implemented rather than inherited from a framework.

### What We Built Instead

- **Database.php**: Singleton PDO wrapper (~90 lines) replaces Laravel's Eloquent ORM
- **ProductModel.php**: Direct SQL with prepared statements replaces Eloquent models
- **CartManager.php**: Session-based storage replaces Laravel's cart packages
- **CsrfManager.php**: Token generation/validation replaces Laravel's CSRF middleware

---

## 2. Database Design Decisions

### Schema Overview

```
products          - Core product catalog (12 seeded items)
cart_items        - Session-persisted cart data
alerts            - Monitoring alerts (Chunk 2)
ticket_classifications - AI ticket history (Chunk 3)
audit_log         - Office tools audit trail (Chunk 4)
```

### Indexing Strategy

| Index | Purpose | Query Pattern |
|-------|---------|---------------|
| `PRIMARY (id)` | Row lookup by ID | `getProductById()` |
| `idx_created (created_at DESC)` | Chronological listing | `getAllProducts()`, pagination |
| `idx_sku (sku)` | SKU lookup | Inventory management |
| `idx_category (category)` | Category filtering | Future category pages |
| `idx_status (status)` | Active product filtering | Every product query |
| `idx_session (session_id)` | Cart lookup | Cart retrieval |
| `idx_product (product_id)` | Cart product reference | Cart item joins |

### Normalization

The schema follows Third Normal Form (3NF):
- **1NF**: All columns are atomic (no repeating groups)
- **2NF**: All non-key attributes depend on the entire primary key
- **3NF**: No transitive dependencies

Prices are denormalized across `price_hkd`, `price_usd`, and `price_cny` columns. This is an intentional denormalization for read performance - the application reads prices far more often than it writes them, and this avoids runtime currency conversion on every product listing query.

### Data Types

- `DECIMAL(10,2)` for prices: Prevents floating-point rounding errors
- `INT UNSIGNED` for stock and IDs: Enforces non-negative values at the DB level
- `ENUM('active','inactive','discontinued')` for status: Enforces valid states
- `DATETIME` with `DEFAULT CURRENT_TIMESTAMP`: Automatic timestamping

---

## 3. Concurrency Control Implementation

### The Problem

When multiple users add the same product to their carts simultaneously, naive stock deduction can lead to overselling:

```
Time    User A           User B           Actual Stock
T1      Read stock = 5                    5
T2                       Read stock = 5   5
T3      Set stock = 4                     4
T4                       Set stock = 4    4  (should be 3)
```

### The Solution: SELECT FOR UPDATE with Transactions

Our `updateStock()` method in `ProductModel.php` implements row-level locking:

```php
// 1. Begin transaction
$db->beginTransaction();

// 2. Lock the row (other transactions wait)
"SELECT stock_qty FROM products WHERE id = :id FOR UPDATE"

// 3. Read and validate stock
$currentStock = $row['stock_qty'];
if ($currentStock < $quantity) { rollback; }

// 4. Update within the lock
"UPDATE products SET stock_qty = :stock WHERE id = :id"

// 5. Release lock
$db->commit();
```

### How It Works

| Time | User A | User B | Stock |
|------|--------|--------|-------|
| T1 | `BEGIN` + `SELECT FOR UPDATE` (lock acquired) | | 5 |
| T2 | | `BEGIN` + `SELECT FOR UPDATE` (blocked, waiting) | 5 |
| T3 | Check stock OK, `UPDATE` to 4, `COMMIT` (lock released) | | 4 |
| T4 | | Lock acquired, reads stock = 4, check OK, `UPDATE` to 3, `COMMIT` | 3 |

### Why This Works

- **InnoDB row-level locks**: MySQL's InnoDB engine locks only the affected row, allowing concurrent operations on different products
- **Transaction isolation**: READ COMMITTED ensures each transaction sees the latest committed data
- **Rollback on failure**: If stock is insufficient, the transaction is rolled back cleanly without side effects
- **Single-use CSRF tokens**: Prevents replay attacks that could trigger duplicate stock deductions

---

## 4. Security Measures

### CSRF Protection (CsrfManager.php)

**Threat**: An attacker tricks a logged-in user into submitting a form to our application from a malicious site.

**Defense**: 
- Cryptographically secure tokens using `random_bytes(32)` (256 bits of entropy)
- Single-use tokens: validated once then destroyed from the session
- Time-based expiry: tokens expire after 1 hour
- `hash_equals()` for timing-safe comparison (prevents timing attacks)
- Tokens embedded in every form and AJAX request

### SQL Injection Prevention

**Threat**: Malicious SQL injected through user input.

**Defense**:
- All queries use PDO prepared statements with parameterized binding
- `PDO::ATTR_EMULATE_PREPARES = false` ensures real prepared statements at the database level
- `PDO::ATTR_ERRMODE = PDO::ERRMODE_EXCEPTION` catches all database errors
- No string concatenation of user input in SQL queries

### XSS Protection

**Threat**: Malicious JavaScript injected through product names or descriptions.

**Defense**:
- All user-facing output uses `htmlspecialchars()` with `ENT_QUOTES` flag
- Content-Type header explicitly set: `application/json` for API endpoints
- `X-Content-Type-Options: nosniff` header prevents MIME sniffing

### Input Validation

All API endpoints validate inputs using PHP's `filter_var()`:
- `product_id`: `FILTER_VALIDATE_INT` with `min_range: 1`
- `quantity`: `FILTER_VALIDATE_INT` with `min_range: 1, max_range: 99`
- JSON body: validated with `json_decode()` and type checking
- HTTP method: explicitly checked against allowed methods

### Session Security

- Sessions initialized with `session_start()` in CartManager and CsrfManager
- Cart data stored server-side (not in cookies)
- No sensitive data exposed to the client beyond cart count and total

---

## 5. Currency Conversion Architecture

### Design

The system supports three currencies: **HKD** (base), **USD**, and **CNY**.

### Storage Strategy

Prices are stored directly in the database as three separate columns:
```sql
price_hkd DECIMAL(10,2)  -- Base currency
price_usd DECIMAL(10,2)  -- Pre-calculated USD price
price_cny DECIMAL(10,2)  -- Pre-calculated CNY price
```

### Why Pre-Stored Prices?

| Approach | Pros | Cons |
|----------|------|------|
| Store base price only, convert at runtime | Always up-to-date | Computation on every request, rounding errors |
| **Pre-store all prices (our choice)** | Zero runtime conversion, consistent display | Requires price updates in all currencies |

For a product catalog that updates infrequently, pre-stored prices provide:
1. **Zero computation** on product listing (the hottest path)
2. **Exact prices** - no rounding discrepancies between display and checkout
3. **Simplicity** - no conversion logic in the product listing loop

### Environment Variables

Conversion rates are configured via environment variables for cart total calculations:
```
USD_RATE=7.82   # 1 USD = 7.82 HKD
CNY_RATE=1.08   # 1 CNY = 1.08 HKD
```

### Cart Total Conversion

The cart manager converts HKD totals when displaying in other currencies:
```php
$convertedTotal = match (strtoupper($currency)) {
    'USD' => round($totalHkd / $usdRate, 2),
    'CNY' => round($totalHkd * $cnyRate, 2),
    default => round($totalHkd, 2),
};
```

### Frontend Currency Switching

The client-side script persists the selected currency in `localStorage` and applies CSS highlighting to the active price row. No page reload required - the visual switch is instant.
