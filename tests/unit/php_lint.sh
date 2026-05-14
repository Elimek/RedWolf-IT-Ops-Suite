#!/bin/bash
# ============================================================
# Unit Test: PHP Lint - Syntax check all PHP files
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
echo "  PHP Syntax Check (php -l)"
echo "============================================"
echo ""

# Find all PHP files
while IFS= read -r -d '' file; do
    TOTAL=$((TOTAL + 1))
    if php -l "$file" 2>/dev/null | grep -q "No syntax errors"; then
        echo -e "  ${GREEN}[OK]${NC} $file"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}[ERR]${NC} $file"
        FAIL=$((FAIL + 1))
    fi
done < <(find "$PROJECT_ROOT" -name "*.php" -not -path "*/vendor/*" -print0 2>/dev/null)

echo ""
echo "============================================"
echo -e "  Results: ${GREEN}$PASS passed${NC}, ${RED}$FAIL failed${NC}, $TOTAL total"
echo "============================================"

[ "$FAIL" -eq 0 ] && exit 0 || exit 1
