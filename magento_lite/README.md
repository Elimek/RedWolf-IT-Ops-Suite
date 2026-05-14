# Magento Lite - Product Platform

Lightweight e-commerce product showcase and inventory management system.

## JD Alignment
- Demonstrates **PHP 8.2** proficiency (CRUD, session management, security)
- Shows understanding of **Magento-like** e-commerce architecture
- Proves **MySQL 8.0** skills (transactions, indexing, row-level locking)

## Features
- Product grid with multi-currency display (HKD/USD/CNY)
- AJAX-powered shopping cart with CSRF protection
- Concurrent-safe stock management (SELECT FOR UPDATE)
- Responsive UI with Bootstrap 5

## Files
```
magento_lite/
├── includes/
│   ├── Database.php       # PDO singleton connection
│   ├── ProductModel.php   # Product CRUD operations
│   └── CartManager.php    # Session-based cart
├── api/
│   └── update_stock.php   # Stock update API (AJAX)
├── assets/
│   ├── style.css          # Responsive grid styles
│   └── script.js          # Cart & currency switching
└── product.php            # Main product listing page
```
