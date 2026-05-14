#!/bin/bash
# ============================================================
# RedWolf IT Officer Demo - Master Test Runner
# Runs all test suites across all modules
# ============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'
BOLD='\033[1m'

# Counters
TOTAL_PASS=0
TOTAL_FAIL=0
TOTAL_TESTS=0
RESULTS=()

# Parse arguments
SUITE="${1:-all}"
BASE_URL="${BASE_URL:-http://localhost}"

usage() {
    echo "Usage: $0 [all|unit|integration|e2e|performance|chunk1|chunk2|chunk3|chunk4]"
    echo ""
    echo "Options:"
    echo "  all          Run all test suites (default)"
    echo "  unit         Run unit tests (lint, shellcheck, security)"
    echo "  integration  Run integration tests (API tests)"
    echo "  e2e          Run end-to-end tests (browser tests)"
    echo "  performance  Run performance benchmarks"
    echo "  chunk1       Run Chunk 1 tests (Magento Lite)"
    echo "  chunk2       Run Chunk 2 tests (Monitoring)"
    echo "  chunk3       Run chunk 3 tests (AI Classifier)"
    echo "  chunk4       Run Chunk 4 tests (Office Tools)"
    exit 0
}

run_suite() {
    local name="$1"
    local script="$2"

    echo ""
    echo -e "${BOLD}${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BOLD}  Running: $name${NC}"
    echo -e "${BOLD}${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    if [ -f "$script" ]; then
        if bash "$script" 2>&1; then
            RESULTS+=("${GREEN}PASS${NC} - $name")
            TOTAL_PASS=$((TOTAL_PASS + 1))
        else
            RESULTS+=("${RED}FAIL${NC} - $name")
            TOTAL_FAIL=$((TOTAL_FAIL + 1))
        fi
    else
        echo -e "${YELLOW}[SKIP] Test script not found: $script${NC}"
        RESULTS+=("${YELLOW}SKIP${NC} - $name (not found)")
    fi

    TOTAL_TESTS=$((TOTAL_TESTS + 1))
}

# Unit Tests
run_unit_tests() {
    echo ""
    echo -e "${BOLD}${CYAN}╔══════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}${CYAN}║        UNIT TESTS                       ║${NC}"
    echo -e "${BOLD}${CYAN}╚══════════════════════════════════════════╝${NC}"

    run_suite "PHP Syntax Check" "$SCRIPT_DIR/unit/php_lint.sh"
    run_suite "ShellCheck Analysis" "$SCRIPT_DIR/unit/shellcheck.sh"
    run_suite "Security Scan" "$SCRIPT_DIR/unit/security_scan.sh"
}

# Integration Tests
run_integration_tests() {
    echo ""
    echo -e "${BOLD}${CYAN}╔══════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}${CYAN}║        INTEGRATION TESTS                ║${NC}"
    echo -e "${BOLD}${CYAN}╚══════════════════════════════════════════╝${NC}"

    run_suite "API Integration Tests" "$SCRIPT_DIR/integration/test_product_api.py"
}

# End-to-End Tests
run_e2e_tests() {
    echo ""
    echo -e "${BOLD}${CYAN}╔══════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}${CYAN}║        END-TO-END TESTS                 ║${NC}"
    echo -e "${BOLD}${CYAN}╚══════════════════════════════════════════╝${NC}"

    run_suite "Browser E2E Tests" "$SCRIPT_DIR/e2e/run_e2e.sh"
}

# Performance Tests
run_performance_tests() {
    echo ""
    echo -e "${BOLD}${CYAN}╔══════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}${CYAN}║        PERFORMANCE TESTS                ║${NC}"
    echo -e "${BOLD}${CYAN}╚══════════════════════════════════════════╝${NC}"

    run_suite "Load Benchmark" "$SCRIPT_DIR/performance/run_benchmark.sh"
}

# Chunk Tests
run_chunk_tests() {
    run_suite "Chunk 1: Magento Lite" "$SCRIPT_DIR/chunk1/test_suite.sh"
    run_suite "Chunk 2: Monitoring" "$SCRIPT_DIR/chunk2/test_suite.sh"
    run_suite "Chunk 3: AI Classifier" "$SCRIPT_DIR/chunk3/test_suite.sh"
    run_suite "Chunk 4: Office Tools" "$SCRIPT_DIR/chunk4/test_suite.sh"
}

# Print Summary
print_summary() {
    echo ""
    echo -e "${BOLD}${BLUE}══════════════════════════════════════════${NC}"
    echo -e "${BOLD}           TEST SUMMARY                      ${NC}"
    echo -e "${BOLD}${BLUE}══════════════════════════════════════════${NC}"
    echo ""

    for result in "${RESULTS[@]}"; do
        echo -e "  $result"
    done

    echo ""
    echo -e "  ${BOLD}Total: $TOTAL_TESTS suites | ${GREEN}Passed: $TOTAL_PASS${NC} | ${RED}Failed: $TOTAL_FAIL${NC}"
    echo ""
    echo -e "${BOLD}${BLUE}══════════════════════════════════════════${NC}"

    if [ "$TOTAL_FAIL" -gt 0 ]; then
        echo -e "${RED}${BOLD}  SOME TESTS FAILED - Review output above${NC}"
        echo -e "${BOLD}${BLUE}══════════════════════════════════════════${NC}"
        return 1
    else
        echo -e "${GREEN}${BOLD}  ALL TESTS PASSED${NC}"
        echo -e "${BOLD}${BLUE}══════════════════════════════════════════${NC}"
        return 0
    fi
}

# Main
case "$SUITE" in
    all)
        run_unit_tests
        run_chunk_tests
        run_integration_tests
        run_e2e_tests
        run_performance_tests
        ;;
    unit)
        run_unit_tests
        ;;
    integration)
        run_integration_tests
        ;;
    e2e)
        run_e2e_tests
        ;;
    performance)
        run_performance_tests
        ;;
    chunk1)
        run_suite "Chunk 1: Magento Lite" "$SCRIPT_DIR/chunk1/test_suite.sh"
        ;;
    chunk2)
        run_suite "Chunk 2: Monitoring" "$SCRIPT_DIR/chunk2/test_suite.sh"
        ;;
    chunk3)
        run_suite "Chunk 3: AI Classifier" "$SCRIPT_DIR/chunk3/test_suite.sh"
        ;;
    chunk4)
        run_suite "Chunk 4: Office Tools" "$SCRIPT_DIR/chunk4/test_suite.sh"
        ;;
    -h|--help|help)
        usage
        ;;
    *)
        echo -e "${RED}Unknown suite: $SUITE${NC}"
        usage
        ;;
esac

print_summary
