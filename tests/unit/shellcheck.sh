#!/bin/bash
# ============================================================
# Unit Test: ShellCheck - Static analysis for shell scripts
# ============================================================
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "============================================"
echo "  ShellCheck Analysis"
echo "============================================"
echo ""

# Check if shellcheck is installed
if ! command -v shellcheck &>/dev/null; then
    echo -e "${YELLOW}[SKIP] ShellCheck not installed. Install with: apt-get install shellcheck${NC}"
    exit 0
fi

PASS=0
FAIL=0
TOTAL=0

while IFS= read -r -d '' file; do
    TOTAL=$((TOTAL + 1))
    if shellcheck -x -s bash "$file" 2>/dev/null; then
        echo -e "  ${GREEN}[OK]${NC} $file"
        PASS=$((PASS + 1))
    else
        echo -e "  ${RED}[ERR]${NC} $file"
        FAIL=$((FAIL + 1))
    fi
done < <(find "$PROJECT_ROOT" -name "*.sh" -print0 2>/dev/null)

echo ""
echo "============================================"
echo -e "  Results: ${GREEN}$PASS passed${NC}, ${RED}$FAIL failed${NC}, $TOTAL total"
echo "============================================"

[ "$FAIL" -eq 0 ] && exit 0 || exit 1
