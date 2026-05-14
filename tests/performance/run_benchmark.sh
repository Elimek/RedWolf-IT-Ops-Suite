#!/bin/bash
# ============================================================
# Performance Test - Load Benchmark
# ============================================================
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost}"
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "============================================"
echo "  Performance Benchmark"
echo "============================================"
echo ""

# Test 1: Response time for product page
echo "[1] Product Page Response Time"
TOTAL=0
for i in $(seq 1 10); do
    TIME=$(curl -s -o /dev/null -w "%{time_total}" "$BASE_URL/magento_lite/product.php" 2>/dev/null || echo "0")
    TOTAL=$(echo "$TOTAL + $TIME" | bc 2>/dev/null || echo "0")
    echo -n "."
done
AVG=$(echo "scale=3; $TOTAL / 10" | bc 2>/dev/null || echo "N/A")
echo ""
echo "  Average: ${AVG}s (target: < 0.5s)"
if [ "$(echo "$AVG < 0.5" | bc 2>/dev/null)" = "1" ]; then
    echo -e "  ${GREEN}PASS${NC}"
else
    echo -e "  ${YELLOW}SLOW${NC}"
fi

# Test 2: Response time for monitoring dashboard
echo ""
echo "[2] Monitoring Dashboard Response Time"
TOTAL=0
for i in $(seq 1 10); do
    TIME=$(curl -s -o /dev/null -w "%{time_total}" "$BASE_URL/monitoring/dashboard.php" 2>/dev/null || echo "0")
    TOTAL=$(echo "$TOTAL + $TIME" | bc 2>/dev/null || echo "0")
    echo -n "."
done
AVG=$(echo "scale=3; $TOTAL / 10" | bc 2>/dev/null || echo "N/A")
echo ""
echo "  Average: ${AVG}s (target: < 0.5s)"

# Test 3: Concurrent connections
echo ""
echo "[3] Concurrent Connections (20 simultaneous)"
START=$(date +%s%N)
for i in $(seq 1 20); do
    curl -s -o /dev/null "$BASE_URL/magento_lite/product.php" &
done
wait
END=$(date +%s%N)
ELAPSED=$(( (END - START) / 1000000 ))
echo "  20 concurrent requests: ${ELAPSED}ms"
if [ "$ELAPSED" -lt 5000 ]; then
    echo -e "  ${GREEN}PASS${NC}"
else
    echo -e "  ${YELLOW}SLOW${NC}"
fi

echo ""
echo "============================================"
echo "  Benchmark Complete"
echo "============================================"
