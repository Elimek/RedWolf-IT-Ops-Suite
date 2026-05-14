#!/bin/bash
# ============================================================
# RedWolf IT Ops Suite - Acceptance Verification Script
# Tests all 4 modules and generates a verification report
# Usage: bash verify.sh
# ============================================================
set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# Allow overriding the base URL (for testing from outside)
BASE_URL="${1:-http://localhost}"
PUBLIC_IP=$(curl -s ifconfig.me 2>/dev/null || echo "localhost")

if [ "$BASE_URL" = "http://localhost" ]; then
    BASE_URL="http://$PUBLIC_IP"
fi

PASS=0
FAIL=0
WARN=0
RESULTS=()

echo ""
echo -e "${BOLD}${CYAN}============================================${NC}"
echo -e "${BOLD}  RedWolf IT Ops Suite - Acceptance Test         ${NC}"
echo -e "${BOLD}  Target: $BASE_URL                              ${NC}"
echo -e "${BOLD}${CYAN}============================================${NC}"
echo ""

# Helper function
check_url() {
    local name="$1"
    local url="$2"
    local expected="${3:-200}"
    local timeout_sec="${4:-10}"

    local http_code
    http_code=$(curl -s -o /tmp/verify_body.txt -w "%{http_code}" \
        --max-time "$timeout_sec" "$url" 2>/dev/null || echo "000")

    if [ "$http_code" = "$expected" ]; then
        RESULTS+=("PASS|$name|HTTP $http_code|$url")
        echo -e "  ${GREEN}[PASS]${NC} $name (HTTP $http_code)"
        PASS=$((PASS + 1))
    else
        RESULTS+=("FAIL|$name|Expected HTTP $expected, got $http_code|$url")
        echo -e "  ${RED}[FAIL]${NC} $name (Expected HTTP $expected, got $http_code)"
        FAIL=$((FAIL + 1))
    fi
}

check_content() {
    local name="$1"
    local url="$2"
    local keyword="$3"

    local body
    body=$(curl -s --max-time 10 "$url" 2>/dev/null || echo "")

    if echo "$body" | grep -qi "$keyword"; then
        RESULTS+=("PASS|$name|Contains '$keyword'|$url")
        echo -e "  ${GREEN}[PASS]${NC} $name (contains '$keyword')"
        PASS=$((PASS + 1))
    else
        RESULTS+=("FAIL|$name|Missing '$keyword'|$url")
        echo -e "  ${RED}[FAIL]${NC} $name (missing '$keyword')"
        FAIL=$((FAIL + 1))
    fi
}

check_api() {
    local name="$1"
    local url="$2"
    local method="${3:-GET}"
    local data="${4:-}"

    if [ "$method" = "POST" ]; then
        local response
        response=$(curl -s --max-time 15 -X POST -H "Content-Type: application/json" \
            -d "$data" "$url" 2>/dev/null || echo '{"error":"timeout"}')
    else
        local response
        response=$(curl -s --max-time 15 "$url" 2>/dev/null || echo '{"error":"timeout"}')
    fi

    if echo "$response" | grep -q '"error"' || [ "$response" = '{"error":"timeout"}' ]; then
        RESULTS+=("FAIL|$name|API error|$url")
        echo -e "  ${RED}[FAIL]${NC} $name (API error)"
        FAIL=$((FAIL + 1))
    else
        RESULTS+=("PASS|$name|OK|$url")
        echo -e "  ${GREEN}[PASS]${NC} $name (response OK)"
        PASS=$((PASS + 1))
    fi
}

# -----------------------------------------------------------
# Test 1: Docker Services
# -----------------------------------------------------------
echo -e "${BOLD}[Test 1] Docker Service Health${NC}"

SERVICES=("redwolf-web" "redwolf-php" "redwolf-db" "redwolf-phpmyadmin" "redwolf-ollama")
for svc in "${SERVICES[@]}"; do
    if docker ps --format '{{.Names}}' | grep -q "^${svc}$"; then
        STATUS=$(docker inspect --format='{{.State.Status}}' "$svc" 2>/dev/null || echo "unknown")
        if [ "$STATUS" = "running" ]; then
            RESULTS+=("PASS|$svc container|Running|Docker")
            echo -e "  ${GREEN}[PASS]${NC} $svc (running)"
            PASS=$((PASS + 1))
        else
            RESULTS+=("FAIL|$svc container|Status: $STATUS|Docker")
            echo -e "  ${RED}[FAIL]${NC} $svc (status: $STATUS)"
            FAIL=$((FAIL + 1))
        fi
    else
        RESULTS+=("WARN|$svc container|Not found|Docker")
        echo -e "  ${YELLOW}[WARN]${NC} $svc (not found)"
        WARN=$((WARN + 1))
    fi
done

echo ""

# -----------------------------------------------------------
# Test 2: Landing Page & Core Pages
# -----------------------------------------------------------
echo -e "${BOLD}[Test 2] Landing Page & Core Pages${NC}"

check_url "Landing Page (index.php)" "$BASE_URL/"
check_content "Landing - Module Cards" "$BASE_URL/" "Magento"
check_url "Product Page" "$BASE_URL/product.php"
check_content "Product Page - Products" "$BASE_URL/product.php" "product"

echo ""

# -----------------------------------------------------------
# Test 3: Magento Lite (Chunk 1)
# -----------------------------------------------------------
echo -e "${BOLD}[Test 3] Magento Lite E-Commerce${NC}"

check_url "Magento Lite Products" "$BASE_URL/magento_lite/product.php"
check_content "Product Listing" "$BASE_URL/magento_lite/product.php" "airsoft"

# API endpoints
check_api "Get Products API" "$BASE_URL/magento_lite/api/get_products.php"

echo ""

# -----------------------------------------------------------
# Test 4: Server Monitoring (Chunk 2)
# -----------------------------------------------------------
echo -e "${BOLD}[Test 4] Server Monitoring System${NC}"

check_url "Monitoring Dashboard" "$BASE_URL/monitoring/dashboard.php"
check_content "Dashboard - Chart.js" "$BASE_URL/monitoring/dashboard.php" "chart\|Chart\|canvas"

# Check if metrics data exists
if [ -f "/var/log/redwolf/metrics/latest.json" ]; then
    RESULTS+=("PASS|Metrics Data|File exists|/var/log/redwolf/metrics/latest.json")
    echo -e "  ${GREEN}[PASS]${NC} Metrics data collected"
    PASS=$((PASS + 1))
else
    RESULTS+=("WARN|Metrics Data|No data yet - run collector.sh|/var/log/redwolf/metrics/latest.json")
    echo -e "  ${YELLOW}[WARN]${NC} Metrics data not yet collected"
    WARN=$((WARN + 1))
fi

echo ""

# -----------------------------------------------------------
# Test 5: AI Classifier (Chunk 3)
# -----------------------------------------------------------
echo -e "${BOLD}[Test 5] AI Ticket Classifier${NC}"

check_url "Classifier UI" "$BASE_URL/ai_agent/classifier.html"
check_content "Classifier - Form" "$BASE_URL/ai_agent/classifier.html" "ticket\|classify"

# Ollama health
if curl -s --max-time 5 http://localhost:11434/api/tags > /dev/null 2>&1; then
    RESULTS+=("PASS|Ollama Service|Responding|localhost:11434")
    echo -e "  ${GREEN}[PASS]${NC} Ollama API responding"
    PASS=$((PASS + 1))

    # Check model availability
    MODEL=$(grep OLLAMA_MODEL .env 2>/dev/null | head -1 | cut -d= -f2 | tr -d ' "' || echo "qwen2.5:7b")
    if curl -s http://localhost:11434/api/tags | grep -q "$MODEL"; then
        RESULTS+=("PASS|Ollama Model|$MODEL loaded|Ollama")
        echo -e "  ${GREEN}[PASS]${NC} Model '$MODEL' available"
        PASS=$((PASS + 1))
    else
        RESULTS+=("WARN|Ollama Model|$MODEL still downloading|Ollama")
        echo -e "  ${YELLOW}[WARN]${NC} Model '$MODEL' not yet loaded (downloading?)"
        WARN=$((WARN + 1))
    fi
else
    RESULTS+=("WARN|Ollama Service|Not responding|localhost:11434")
    echo -e "  ${YELLOW}[WARN]${NC} Ollama not responding (keyword fallback active)"
    WARN=$((WARN + 1))
fi

echo ""

# -----------------------------------------------------------
# Test 6: Office Tools (Chunk 4)
# -----------------------------------------------------------
echo -e "${BOLD}[Test 6] Office Support Tools${NC}"

check_url "Network Scanner" "$BASE_URL/office_tools/network_scanner.php"
check_url "VPN Status" "$BASE_URL/office_tools/vpn_status.php"
check_url "Printer Config" "$BASE_URL/office_tools/printer_config.html"

echo ""

# -----------------------------------------------------------
# Test 7: Security Checks
# -----------------------------------------------------------
echo -e "${BOLD}[Test 7] Security Verification${NC}"

# Check .env is not accessible
ENV_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/.env" 2>/dev/null || echo "000")
if [ "$ENV_CODE" = "404" ] || [ "$ENV_CODE" = "403" ]; then
    RESULTS+=("PASS|.env Access|Blocked (HTTP $ENV_CODE)|Security")
    echo -e "  ${GREEN}[PASS]${NC} .env file blocked (HTTP $ENV_CODE)"
    PASS=$((PASS + 1))
else
    RESULTS+=("FAIL|.env Access|Exposed! (HTTP $ENV_CODE)|Security")
    echo -e "  ${RED}[FAIL]${NC} .env file exposed! (HTTP $ENV_CODE)"
    FAIL=$((FAIL + 1))
fi

# Check .git is not accessible
GIT_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/.git/config" 2>/dev/null || echo "000")
if [ "$GIT_CODE" = "404" ] || [ "$GIT_CODE" = "403" ]; then
    RESULTS+=("PASS|.git Access|Blocked (HTTP $GIT_CODE)|Security")
    echo -e "  ${GREEN}[PASS]${NC} .git directory blocked (HTTP $GIT_CODE)"
    PASS=$((PASS + 1))
else
    RESULTS+=("FAIL|.git Access|Exposed! (HTTP $GIT_CODE)|Security")
    echo -e "  ${RED}[FAIL]${NC} .git directory exposed! (HTTP $GIT_CODE)"
    FAIL=$((FAIL + 1))
fi

# Check security headers
HEADERS=$(curl -sI --max-time 5 "$BASE_URL/" 2>/dev/null || echo "")
if echo "$HEADERS" | grep -qi "X-Frame-Options"; then
    RESULTS+=("PASS|X-Frame-Options|Present|Security Headers")
    echo -e "  ${GREEN}[PASS]${NC} X-Frame-Options header present"
    PASS=$((PASS + 1))
else
    RESULTS+=("WARN|X-Frame-Options|Missing|Security Headers")
    echo -e "  ${YELLOW}[WARN]${NC} X-Frame-Options header missing"
    WARN=$((WARN + 1))
fi

if echo "$HEADERS" | grep -qi "X-Content-Type-Options"; then
    RESULTS+=("PASS|X-Content-Type-Options|Present|Security Headers")
    echo -e "  ${GREEN}[PASS]${NC} X-Content-Type-Options header present"
    PASS=$((PASS + 1))
else
    RESULTS+=("WARN|X-Content-Type-Options|Missing|Security Headers")
    echo -e "  ${YELLOW}[WARN]${NC} X-Content-Type-Options header missing"
    WARN=$((WARN + 1))
fi

echo ""

# -----------------------------------------------------------
# Test 8: Database Verification
# -----------------------------------------------------------
echo -e "${BOLD}[Test 8] Database Integrity${NC}"

DB_PASS=$(grep DB_PASS .env 2>/dev/null | head -1 | cut -d= -f2 | tr -d ' "' || echo "redwolf_secret")

# Check products table
P_COUNT=$(docker exec redwolf-db mysql -u redwolf -p"${DB_PASS}" redwolf -N -e "SELECT COUNT(*) FROM products;" 2>/dev/null || echo "ERR")
if [ "$P_COUNT" != "ERR" ] && [ "$P_COUNT" -gt 0 ] 2>/dev/null; then
    RESULTS+=("PASS|Products Table|$P_COUNT records|Database")
    echo -e "  ${GREEN}[PASS]${NC} Products table: $P_COUNT records"
    PASS=$((PASS + 1))
else
    RESULTS+=("FAIL|Products Table|Query failed or empty|Database")
    echo -e "  ${RED}[FAIL]${NC} Products table check failed"
    FAIL=$((FAIL + 1))
fi

# Check audit_log table
A_COUNT=$(docker exec redwolf-db mysql -u redwolf -p"${DB_PASS}" redwolf -N -e "SELECT COUNT(*) FROM audit_log;" 2>/dev/null || echo "ERR")
if [ "$A_COUNT" != "ERR" ]; then
    RESULTS+=("PASS|Audit Log Table|$A_COUNT records|Database")
    echo -e "  ${GREEN}[PASS]${NC} Audit log table: $A_COUNT records"
    PASS=$((PASS + 1))
else
    RESULTS+=("WARN|Audit Log Table|Not accessible|Database")
    echo -e "  ${YELLOW}[WARN]${NC} Audit log table not accessible"
    WARN=$((WARN + 1))
fi

echo ""

# -----------------------------------------------------------
# Summary Report
# -----------------------------------------------------------
TOTAL=$((PASS + FAIL + WARN))

echo -e "${BOLD}${CYAN}============================================${NC}"
echo -e "${BOLD}  Acceptance Test Summary                      ${NC}"
echo -e "${BOLD}${CYAN}============================================${NC}"
echo ""
echo -e "  Total checks:  $TOTAL"
echo -e "  ${GREEN}Passed:        $PASS${NC}"
echo -e "  ${RED}Failed:        $FAIL${NC}"
echo -e "  ${YELLOW}Warnings:      $WARN${NC}"
echo ""

if [ "$FAIL" -eq 0 ]; then
    echo -e "  ${GREEN}${BOLD}RESULT: ALL CRITICAL TESTS PASSED${NC}"
    echo ""
else
    echo -e "  ${RED}${BOLD}RESULT: $FAIL TEST(S) FAILED - FIX BEFORE DEMO${NC}"
    echo ""
fi

# Generate report file
REPORT_FILE="deployment/oracle-cloud/VERIFICATION_REPORT.md"
cat > "$REPORT_FILE" << EOF
# RedWolf IT Ops Suite - Verification Report

**Date:** $(date -u '+%Y-%m-%d %H:%M:%S UTC')
**Target:** $BASE_URL
**Public IP:** $PUBLIC_IP

## Summary

| Metric | Count |
|--------|-------|
| Total Checks | $TOTAL |
| Passed | $PASS |
| Failed | $FAIL |
| Warnings | $WARN |

## Results

| Status | Test | Details | Source |
|--------|------|---------|--------|
EOF

for result in "${RESULTS[@]}"; do
    IFS='|' read -r status name detail source <<< "$result"
    case "$status" in
        PASS) status_str=":white_check_mark: PASS" ;;
        FAIL) status_str=":x: FAIL" ;;
        WARN) status_str=":warning: WARN" ;;
    esac
    echo "| $status_str | $name | $detail | $source |" >> "$REPORT_FILE"
done

cat >> "$REPORT_FILE" << EOF

## Access URLs

- Main Site: $BASE_URL
- Product Page: $BASE_URL/product.php
- Monitoring: $BASE_URL/monitoring/dashboard.php
- AI Classifier: $BASE_URL/ai_agent/classifier.html
- Network Scanner: $BASE_URL/office_tools/network_scanner.php
- VPN Status: $BASE_URL/office_tools/vpn_status.php
- phpMyAdmin: http://$PUBLIC_IP:8080

## Docker Containers

EOF

docker ps --format "| {{.Names}} | {{.Image}} | {{.Status}} | {{.Ports}} |" >> "$REPORT_FILE"

echo -e "  Report saved to: $REPORT_FILE"
echo ""

# Exit with error if any failures
if [ "$FAIL" -gt 0 ]; then
    exit 1
fi
exit 0
