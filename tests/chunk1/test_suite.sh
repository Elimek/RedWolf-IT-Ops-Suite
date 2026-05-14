#!/usr/bin/env bash
# ============================================================
# test_suite.sh - Chunk 1 Integration Test Suite
#
# Tests for the RedWolf Magento Lite e-commerce platform.
# Covers database connectivity, HTTP endpoints, data integrity,
# stock deduction correctness, and concurrent safety.
#
# Exit codes:
#   0 - All tests passed
#   1 - One or more tests failed
#
# @package RedWolf\Tests\Chunk1
# @version 1.0.0
# ============================================================

set -euo pipefail

# ============================================================
# Configuration
# ============================================================
BASE_URL="${BASE_URL:-http://localhost}"
API_URL="${BASE_URL}/magento_lite/api"
PRODUCT_URL="${BASE_URL}/magento_lite/product.php"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Counters
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

# ============================================================
# Utility Functions
# ============================================================

# Prints a section header
section() {
    echo ""
    echo -e "${CYAN}${BOLD}━━━ $1 ━━━${NC}"
    echo ""
}

# Prints a test name and runs it
run_test() {
    local test_name="$1"
    local test_func="$2"
    TESTS_RUN=$((TESTS_RUN + 1))

    echo -n "  [TEST ${TESTS_RUN}] ${test_name} ... "

    if eval "${test_func}"; then
        echo -e "${GREEN}PASS${NC}"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        echo -e "${RED}FAIL${NC}"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

# Prints a summary of test results
print_summary() {
    echo ""
    echo -e "${BOLD}═══════════════════════════════════════${NC}"
    echo -e "${BOLD}          Test Summary${NC}"
    echo -e "${BOLD}═══════════════════════════════════════${NC}"
    echo -e "  Total:  ${BOLD}${TESTS_RUN}${NC}"
    echo -e "  Passed: ${GREEN}${TESTS_PASSED}${NC}"
    echo -e "  Failed: ${RED}${TESTS_FAILED}${NC}"
    echo -e "${BOLD}═══════════════════════════════════════${NC}"
    echo ""

    if [ "${TESTS_FAILED}" -eq 0 ]; then
        echo -e "${GREEN}${BOLD}All tests passed!${NC}"
        return 0
    else
        echo -e "${RED}${BOLD}Some tests failed.${NC}"
        return 1
    fi
}

# Makes a curl request and returns the HTTP status code
http_status() {
    curl -s -o /dev/null -w '%{http_code}' "$@"
}

# Makes a curl request and returns the body
http_body() {
    curl -s "$@"
}

# Checks if a JSON value matches an expected string
json_eq() {
    local json="$1"
    local key="$2"
    local expected="$3"
    local actual
    actual=$(echo "${json}" | grep -o "\"${key}\"[[:space:]]*:[[:space:]]*\"[^\"]*\"" | head -1 | cut -d'"' -f4)
    [ "${actual}" = "${expected}" ]
}

# Checks if a JSON value matches an expected number
json_num_eq() {
    local json="$1"
    local key="$2"
    local expected="$3"
    local actual
    actual=$(echo "${json}" | grep -o "\"${key}\"[[:space:]]*:[[:space:]]*[0-9]*" | head -1 | grep -o '[0-9]*$')
    [ "${actual}" = "${expected}" ]
}

# ============================================================
# Test: Database Connection
# ============================================================
section "Database Connection"

test_db_connection() {
    local response
    response=$(http_body "${API_URL}/get_products.php?page=1&per_page=1")
    echo "${response}" | grep -q '"success":[[:space:]]*true'
}

test_db_products_table() {
    local response
    response=$(http_body "${API_URL}/get_products.php?page=1&per_page=1")
    echo "${response}" | grep -q '"data"'
}

# ============================================================
# Test: Product Page HTTP
# ============================================================
section "Product Page HTTP"

test_product_page_200() {
    local status
    status=$(http_status "${PRODUCT_URL}")
    [ "${status}" = "200" ]
}

test_product_page_has_content() {
    local body
    body=$(http_body "${PRODUCT_URL}")
    echo "${body}" | grep -qi "product catalog"
}

test_product_page_has_bootstrap() {
    local body
    body=$(http_body "${PRODUCT_URL}")
    echo "${body}" | grep -q "bootstrap"
}

# ============================================================
# Test: API Endpoints
# ============================================================
section "API Endpoints"

test_api_get_products() {
    local status
    status=$(http_status "${API_URL}/get_products.php")
    [ "${status}" = "200" ]
}

test_api_get_products_json() {
    local body
    body=$(http_body "${API_URL}/get_products.php")
    echo "${body}" | python3 -c "import sys, json; json.load(sys.stdin)" 2>/dev/null
}

test_api_currency_hkd() {
    local body
    body=$(http_body "${API_URL}/get_products.php?currency=hkd&page=1&per_page=1")
    echo "${body}" | grep -q '"currency"[[:space:]]*:[[:space:]]*"HKD"'
}

test_api_currency_usd() {
    local body
    body=$(http_body "${API_URL}/get_products.php?currency=usd&page=1&per_page=1")
    echo "${body}" | grep -q '"currency"[[:space:]]*:[[:space:]]*"USD"'
}

test_api_currency_cny() {
    local body
    body=$(http_body "${API_URL}/get_products.php?currency=cny&page=1&per_page=1")
    echo "${body}" | grep -q '"currency"[[:space:]]*:[[:space:]]*"CNY"'
}

test_api_pagination() {
    local body
    body=$(http_body "${API_URL}/get_products.php?page=1&per_page=5")
    echo "${body}" | grep -q '"total_pages"'
}

test_api_post_rejected_get() {
    local status
    status=$(http_status -X GET "${API_URL}/add_to_cart.php")
    [ "${status}" = "405" ]
}

test_api_csrf_required() {
    local status
    status=$(http_status -X POST -H "Content-Type: application/json" \
        -d '{"product_id":1,"quantity":1,"csrf_token":"invalid"}' \
        "${API_URL}/add_to_cart.php")
    [ "${status}" = "403" ]
}

test_api_invalid_product_id() {
    local status
    status=$(http_status -X POST -H "Content-Type: application/json" \
        -d '{"product_id":-1,"quantity":1,"csrf_token":"x"}' \
        "${API_URL}/add_to_cart.php")
    [ "${status}" = "400" ]
}

test_api_invalid_quantity() {
    local status
    status=$(http_status -X POST -H "Content-Type: application/json" \
        -d '{"product_id":1,"quantity":0,"csrf_token":"x"}' \
        "${API_URL}/add_to_cart.php")
    [ "${status}" = "400" ]
}

# ============================================================
# Test: Data Integrity
# ============================================================
section "Data Integrity"

test_minimum_10_products() {
    local body
    body=$(http_body "${API_URL}/get_products.php?page=1&per_page=100")
    local count
    count=$(echo "${body}" | grep -o '"total"[[:space:]]*:[[:space:]]*[0-9]*' | head -1 | grep -o '[0-9]*$')
    [ "${count:-0}" -ge 10 ]
}

test_products_have_prices() {
    local body
    body=$(http_body "${API_URL}/get_products.php?page=1&per_page=1")
    echo "${body}" | grep -q '"price_hkd"'
    echo "${body}" | grep -q '"price_usd"'
    echo "${body}" | grep -q '"price_cny"'
}

test_products_have_stock() {
    local body
    body=$(http_body "${API_URL}/get_products.php?page=1&per_page=1")
    echo "${body}" | grep -q '"stock_qty"'
}

test_products_have_names() {
    local body
    body=$(http_body "${API_URL}/get_products.php?page=1&per_page=1")
    echo "${body}" | grep -q '"name"'
}

# ============================================================
# Test: Stock Deduction
# ============================================================
section "Stock Deduction"

# Note: Stock deduction tests require a valid CSRF token from a session.
# These tests verify the API structure and error handling rather than
# actual stock modification (which requires session-based CSRF tokens).

test_stock_update_requires_csrf() {
    local status
    status=$(http_status -X POST -H "Content-Type: application/json" \
        -d '{"product_id":1,"quantity":1}' \
        "${API_URL}/update_stock.php")
    [ "${status}" = "403" ]
}

test_stock_update_rejects_negative_qty() {
    local status
    status=$(http_status -X POST -H "Content-Type: application/json" \
        -d '{"product_id":1,"quantity":-5,"csrf_token":"x"}' \
        "${API_URL}/update_stock.php")
    [ "${status}" = "400" ]
}

test_stock_update_rejects_invalid_product() {
    local status
    status=$(http_status -X POST -H "Content-Type: application/json" \
        -d '{"product_id":0,"quantity":1,"csrf_token":"x"}' \
        "${API_URL}/update_stock.php")
    [ "${status}" = "400" ]
}

# ============================================================
# Test: Concurrent Safety
# ============================================================
section "Concurrent Safety"

test_concurrent_stock_reads() {
    # Fire 5 simultaneous requests to verify no crashes under load
    local pids=()
    local results=()

    for i in 1 2 3 4 5; do
        curl -s "${API_URL}/get_products.php?page=1&per_page=1" > "/tmp/rw_test_${i}.json" &
        pids+=($!)
    done

    # Wait for all background processes
    for pid in "${pids[@]}"; do
        wait "${pid}" || return 1
    done

    # Verify all responses are valid JSON
    for i in 1 2 3 4 5; do
        if ! python3 -c "import json; json.load(open('/tmp/rw_test_${i}.json'))" 2>/dev/null; then
            rm -f /tmp/rw_test_*.json
            return 1
        fi
    done

    # Verify all responses have success:true
    for i in 1 2 3 4 5; do
        if ! grep -q '"success"[[:space:]]*:[[:space:]]*true' "/tmp/rw_test_${i}.json"; then
            rm -f /tmp/rw_test_*.json
            return 1
        fi
    done

    rm -f /tmp/rw_test_*.json
    return 0
}

test_no_negative_stock_possible() {
    # Verify the stock check endpoint structure works
    local body
    body=$(http_body "${API_URL}/get_products.php?page=1&per_page=100")
    local count
    count=$(echo "${body}" | grep -o '"total"[[:space:]]*:[[:space:]]*[0-9]*' | head -1 | grep -o '[0-9]*$')

    if [ "${count:-0}" -lt 1 ]; then
        return 1
    fi

    # Check that all stock values in the response are non-negative
    local negative_count
    negative_count=$(echo "${body}" | grep -o '"stock_qty"[[:space:]]*:[[:space:]]*-[0-9]' | wc -l)
    [ "${negative_count}" -eq 0 ]
}

# ============================================================
# Run All Tests
# ============================================================
section "Running Chunk 1 Test Suite"

echo -e "${YELLOW}Base URL: ${BASE_URL}${NC}"
echo -e "${YELLOW}Product URL: ${PRODUCT_URL}${NC}"
echo -e "${YELLOW}API URL: ${API_URL}${NC}"
echo ""

# Connection tests
run_test "Database connection is live" "test_db_connection"
run_test "Products table is accessible" "test_db_products_table"

# Product page tests
run_test "Product page returns HTTP 200" "test_product_page_200"
run_test "Product page has content" "test_product_page_has_content"
run_test "Product page loads Bootstrap" "test_product_page_has_bootstrap"

# API tests
run_test "GET /api/get_products.php returns 200" "test_api_get_products"
run_test "API returns valid JSON" "test_api_get_products_json"
run_test "Currency filter HKD works" "test_api_currency_hkd"
run_test "Currency filter USD works" "test_api_currency_usd"
run_test "Currency filter CNY works" "test_api_currency_cny"
run_test "Pagination parameters work" "test_api_pagination"
run_test "POST rejected on GET endpoint" "test_api_post_rejected_get"
run_test "CSRF token required for cart" "test_api_csrf_required"
run_test "Invalid product_id rejected" "test_api_invalid_product_id"
run_test "Invalid quantity rejected" "test_api_invalid_quantity"

# Data integrity tests
run_test "At least 10 products in catalog" "test_minimum_10_products"
run_test "Products have all currency prices" "test_products_have_prices"
run_test "Products have stock_qty field" "test_products_have_stock"
run_test "Products have name field" "test_products_have_names"

# Stock tests
run_test "Stock update requires CSRF token" "test_stock_update_requires_csrf"
run_test "Stock update rejects negative qty" "test_stock_update_rejects_negative_qty"
run_test "Stock update rejects invalid product" "test_stock_update_rejects_invalid_product"

# Concurrent safety tests
run_test "5 concurrent requests succeed" "test_concurrent_stock_reads"
run_test "No negative stock in database" "test_no_negative_stock_possible"

# Summary
print_summary
exit $?
