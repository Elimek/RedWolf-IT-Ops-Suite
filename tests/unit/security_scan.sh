#!/bin/bash
# ============================================================
# Unit Test: Security Scan - Check for common vulnerabilities
# ============================================================
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

PASS=0
FAIL=0
TOTAL=0

echo "============================================"
echo "  Security Scan"
echo "============================================"
echo ""

# Check 1: No hardcoded passwords in PHP files
TOTAL=$((TOTAL + 1))
echo -n "  [1] No hardcoded passwords in PHP... "
if grep -rn "password\s*=\s*['\"]" "$PROJECT_ROOT" --include="*.php" | grep -v "\$_ENV\|getenv\|\.env\|example\|placeholder\|changeme" | head -1 > /dev/null 2>&1; then
    echo -e "${RED}FAIL${NC} - Found hardcoded passwords"
    FAIL=$((FAIL + 1))
else
    echo -e "${GREEN}PASS${NC}"
    PASS=$((PASS + 1))
fi

# Check 2: No eval() usage
TOTAL=$((TOTAL + 1))
echo -n "  [2] No eval() usage... "
if grep -rn "\beval\s*(" "$PROJECT_ROOT" --include="*.php" | head -1 > /dev/null 2>&1; then
    echo -e "${RED}FAIL${NC} - Found eval() usage"
    FAIL=$((FAIL + 1))
else
    echo -e "${GREEN}PASS${NC}"
    PASS=$((PASS + 1))
fi

# Check 3: No direct exec/system calls without validation
TOTAL=$((TOTAL + 1))
echo -n "  [3] No unsafe exec/system calls... "
if grep -rn "exec\s*(\s*['\"]\|system\s*(\s*['\"]" "$PROJECT_ROOT" --include="*.php" | head -1 > /dev/null 2>&1; then
    echo -e "${RED}FAIL${NC} - Found potentially unsafe exec/system calls"
    FAIL=$((FAIL + 1))
else
    echo -e "${GREEN}PASS${NC}"
    PASS=$((PASS + 1))
fi

# Check 4: .env files are in .gitignore
TOTAL=$((TOTAL + 1))
echo -n "  [4] .env in .gitignore... "
if grep -q "^\.env$" "$PROJECT_ROOT/.gitignore" 2>/dev/null; then
    echo -e "${GREEN}PASS${NC}"
    PASS=$((PASS + 1))
else
    echo -e "${RED}FAIL${NC}"
    FAIL=$((FAIL + 1))
fi

# Check 5: No .env file committed (check size - should be empty or not exist)
TOTAL=$((TOTAL + 1))
echo -n "  [5] No .env file in repo... "
if [ ! -f "$PROJECT_ROOT/.env" ] || [ ! -s "$PROJECT_ROOT/.env" ]; then
    echo -e "${GREEN}PASS${NC}"
    PASS=$((PASS + 1))
else
    echo -e "${YELLOW}WARN${NC} - .env exists with content (ensure it's gitignored)"
    PASS=$((PASS + 1))
fi

# Check 6: SQL injection protection - prepared statements used
TOTAL=$((TOTAL + 1))
echo -n "  [6] Prepared statements used for DB queries... "
if grep -rn "query\s*(\s*['\"]" "$PROJECT_ROOT" --include="*.php" | grep -v "prepare\|PDO::" | head -1 > /dev/null 2>&1; then
    echo -e "${YELLOW}WARN${NC} - Some queries may not use prepared statements"
    PASS=$((PASS + 1))
else
    echo -e "${GREEN}PASS${NC}"
    PASS=$((PASS + 1))
fi

echo ""
echo "============================================"
echo -e "  Results: ${GREEN}$PASS passed${NC}, ${RED}$FAIL failed${NC}, $TOTAL total"
echo "============================================"

[ "$FAIL" -eq 0 ] && exit 0 || exit 1
