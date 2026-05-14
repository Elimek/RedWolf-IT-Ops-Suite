/**
 * script.js - RedWolf Magento Lite Client Scripts
 *
 * Vanilla JavaScript for currency switching, AJAX add-to-cart,
 * toast notifications, cart sidebar management, and quantity controls.
 * No external dependencies (no jQuery).
 *
 * @package RedWolf\MagentoLite\Assets
 * @version 1.0.0
 */

'use strict';

/* ============================================================
   Configuration
   ============================================================ */
const RW = {
    config: window.RW_CONFIG || {},
    currentCurrency: localStorage.getItem('rw_currency') || 'hkd',
    lastStockCheck: 0,
    STOCK_CHECK_INTERVAL: 300,
};

/* ============================================================
   Toast Notification System
   ============================================================ */
const Toast = {
    /**
     * Displays a toast notification.
     * @param {string} message - The message to display
     * @param {'success'|'error'|'warning'} type - Toast type
     * @param {number} duration - Display duration in ms
     */
    show(message, type = 'success', duration = 3000) {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-exclamation-triangle-fill',
            warning: 'bi-exclamation-circle-fill',
        };

        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-bg-light toast-${type}`;
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi ${icons[type] || icons.success} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
            </div>
        `;

        container.appendChild(toastEl);
        toastEl.classList.add('showing');

        setTimeout(() => {
            toastEl.classList.add('hiding');
            setTimeout(() => toastEl.remove(), 300);
        }, duration);
    },
};

/* ============================================================
   Currency Switcher
   ============================================================ */
const Currency = {
    /**
     * Initializes currency switcher event listeners.
     */
    init() {
        const options = document.querySelectorAll('.currency-option');
        options.forEach((option) => {
            option.addEventListener('click', (e) => {
                e.preventDefault();
                const currency = option.dataset.currency;
                if (!currency) return;

                this.switchTo(currency);

                options.forEach((o) => o.classList.remove('active'));
                option.classList.add('active');
            });
        });

        this.applyCurrency(RW.currentCurrency);
        this.updateLabel(RW.currentCurrency);
    },

    /**
     * Switches display to the specified currency.
     * @param {string} currency - Currency code (hkd/usd/cny)
     */
    switchTo(currency) {
        RW.currentCurrency = currency;
        localStorage.setItem('rw_currency', currency);
        this.applyCurrency(currency);
        this.updateLabel(currency);
        this.updateCartSidebar();
    },

    /**
     * Applies currency highlighting to all price rows.
     * @param {string} currency - Currency code
     */
    applyCurrency(currency) {
        document.querySelectorAll('.price-row[data-currency]').forEach((row) => {
            if (row.dataset.currency === currency) {
                row.classList.add('price-active');
            } else {
                row.classList.remove('price-active');
            }
        });
    },

    /**
     * Updates the currency dropdown label.
     * @param {string} currency - Currency code
     */
    updateLabel(currency) {
        const label = document.getElementById('currentCurrencyLabel');
        if (label) {
            label.textContent = currency.toUpperCase();
        }
    },

    /**
     * Returns the currency symbol for a given code.
     * @param {string} currency - Currency code
     * @return {string} Currency symbol
     */
    getSymbol(currency) {
        return { hkd: '$', usd: '$', cny: '\u00A5' }[currency] || '$';
    },
};

/* ============================================================
   Cart Manager (Client-side)
   ============================================================ */
const Cart = {
    sidebar: null,
    bsSidebar: null,

    /**
     * Initializes the cart sidebar and toggle button.
     */
    init() {
        this.sidebar = document.getElementById('cartSidebar');
        this.bsSidebar = new bootstrap.Offcanvas(this.sidebar);

        document.getElementById('cartToggleBtn')?.addEventListener('click', () => {
            this.refresh();
            this.bsSidebar.show();
        });
    },

    /**
     * Adds a product to the cart via AJAX.
     * @param {number} productId - The product ID
     * @param {string} csrfToken - CSRF token for validation
     */
    async addToCart(productId, csrfToken) {
        try {
            const response = await fetch(`${RW.config.apiBase}/add_to_cart.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: 1,
                    csrf_token: csrfToken,
                }),
            });

            const data = await response.json();

            if (data.success) {
                this.updateBadge(data.cart_count);
                Toast.show(data.message, 'success');
                this.refresh();
            } else {
                Toast.show(data.message, 'warning');
            }

            return data;
        } catch (error) {
            Toast.show('Failed to add item to cart. Please try again.', 'error');
            return { success: false, message: 'Network error' };
        }
    },

    /**
     * Updates the cart badge count with bounce animation.
     * @param {number} count - New cart item count
     */
    updateBadge(count) {
        const badge = document.getElementById('cartBadge');
        if (!badge) return;

        badge.textContent = count;
        badge.classList.remove('bounce');
        void badge.offsetWidth;
        badge.classList.add('bounce');
    },

    /**
     * Refreshes the cart sidebar content via the products API.
     */
    async refresh() {
        try {
            const params = new URLSearchParams({
                action: 'cart',
                currency: RW.currentCurrency,
            });
            const response = await fetch(`${RW.config.apiBase}/get_products.php?${params}`);
            const data = await response.json();

            if (data.success && data.cart) {
                this.renderCart(data.cart);
            }
        } catch (error) {
            // Silently fail on cart refresh
        }
    },

    /**
     * Renders cart items into the sidebar.
     * @param {Object} cart - Cart data from API
     */
    renderCart(cart) {
        const container = document.getElementById('cartItemsContainer');
        const emptyMsg = document.getElementById('cartEmptyMessage');
        const footer = document.getElementById('cartFooter');

        if (!container) return;

        if (!cart.items || cart.items.length === 0) {
            container.classList.add('d-none');
            if (emptyMsg) emptyMsg.classList.remove('d-none');
            if (footer) footer.classList.add('d-none');
            return;
        }

        container.classList.remove('d-none');
        if (emptyMsg) emptyMsg.classList.add('d-none');
        if (footer) {
            footer.classList.remove('d-none');
            document.getElementById('cartSidebarTotal').textContent =
                `${Currency.getSymbol(RW.currentCurrency)}${cart.total}`;
        }

        container.innerHTML = cart.items.map((item) => `
            <div class="cart-item" data-product-id="${item.product_id}">
                <div class="cart-item-info">
                    <div class="cart-item-name">${this.escapeHtml(item.product_name)}</div>
                    <div class="cart-item-price">
                        ${Currency.getSymbol(RW.currentCurrency)}${item.line_total}
                    </div>
                </div>
                <div class="cart-qty-controls">
                    <button class="cart-qty-btn" onclick="Cart.changeQty(${item.product_id}, -1)">&minus;</button>
                    <span class="cart-qty-value">${item.quantity}</span>
                    <button class="cart-qty-btn" onclick="Cart.changeQty(${item.product_id}, 1)">&plus;</button>
                </div>
                <button class="cart-item-remove" onclick="Cart.removeItem(${item.product_id})">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
        `).join('');
    },

    /**
     * Updates cart sidebar currency display.
     */
    updateCartSidebar() {
        this.refresh();
    },

    /**
     * Changes quantity of a cart item.
     * @param {number} productId - Product ID
     * @param {number} delta - Quantity change (-1 or +1)
     */
    async changeQty(productId, delta) {
        // Placeholder - uses full refresh after API call
        await this.refresh();
    },

    /**
     * Removes an item from the cart.
     * @param {number} productId - Product ID
     */
    async removeItem(productId) {
        // Placeholder - uses full refresh after API call
        await this.refresh();
    },

    /**
     * Escapes HTML to prevent XSS.
     * @param {string} text - Raw text
     * @return {string} Escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
};

/* ============================================================
   Debounced Stock Check
   ============================================================ */
const StockCheck = {
    /**
     * Checks stock for a product with debouncing.
     * Minimum interval of STOCK_CHECK_INTERVAL ms between checks.
     * @param {number} productId - Product ID
     */
    async check(productId) {
        const now = Date.now();
        if (now - RW.lastStockCheck < RW.STOCK_CHECK_INTERVAL) {
            return;
        }
        RW.lastStockCheck = now;

        try {
            const csrfToken = RW.config.csrfToken;
            const response = await fetch(`${RW.config.apiBase}/update_stock.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: 0,
                    action: 'check',
                    csrf_token: csrfToken,
                }),
            });

            const data = await response.json();
            return data;
        } catch (error) {
            return null;
        }
    },
};

/* ============================================================
   Event Bindings
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    Currency.init();
    Cart.init();

    // Bind add-to-cart buttons
    document.querySelectorAll('.add-to-cart-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const productId = parseInt(btn.dataset.productId, 10);
            const csrfToken = btn.dataset.csrfToken;

            if (btn.disabled || !productId || !csrfToken) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Adding...';

            Cart.addToCart(productId, csrfToken).finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-cart-plus me-1"></i>Add to Cart';
            });
        });
    });
});
