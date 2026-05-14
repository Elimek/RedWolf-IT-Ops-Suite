#!/bin/bash
# ============================================================
# Chunk 1 Deployment: Magento Lite E-Commerce Platform
# ============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "=== Chunk 1: Deploying Magento Lite ==="

# Check Docker
if ! command -v docker &>/dev/null; then
    echo "ERROR: Docker is not installed."
    exit 1
fi

if ! docker info &>/dev/null; then
    echo "ERROR: Docker daemon is not running."
    exit 1
fi

echo "[1/5] Starting web and db services..."
cd "$PROJECT_ROOT"
docker-compose up -d web php db

echo "[2/5] Waiting for MySQL to be ready (timeout: 30s)..."
TIMEOUT=30
while ! docker exec redwolf-db mysqladmin ping -h localhost -u root --silent 2>/dev/null; do
    TIMEOUT=$((TIMEOUT - 1))
    if [ "$TIMEOUT" -le 0 ]; then
        echo "ERROR: MySQL did not become ready in 30 seconds."
        exit 1
    fi
    sleep 1
done
echo "  MySQL is ready."

echo "[3/5] Database schema imported via Docker init (auto)."

echo "[4/5] Verifying product page..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/product.php 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo "  product.php returned HTTP $HTTP_CODE - OK"
else
    echo "  WARNING: product.php returned HTTP $HTTP_CODE (expected 200)"
fi

echo "[5/5] Verifying database connectivity..."
docker exec redwolf-db mysql -u redwolf -predwolf_secret redwolf -e "SELECT COUNT(*) AS product_count FROM products;" 2>/dev/null || echo "  WARNING: Could not query database."

echo "=== Chunk 1 Deployment Complete ==="
