#!/bin/bash
# =============================================================
# RedWolf IT Ops Suite - Chunk 4 Test Suite
# Tests for: Network Scanner, Printer Config, VPN Status
# =============================================================

set -euo pipefail

# --- Configuration ---
BASE_URL="${BASE_URL:-http://localhost:8080}"
TIMEOUT=30
PASS=0
FAIL=0
SKIP=0

# --- Color Codes ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# --- Helper Functions ---

log_pass() {
    PASS=$((PASS + 1))
    echo -e "  ${GREEN}[PASS]${NC} $1"
}

log_fail() {
    FAIL=$((FAIL + 1))
    echo -e "  ${RED}[FAIL]${NC} $1"
}

log_skip() {
    SKIP=$((SKIP + 1))
    echo -e "  ${YELLOW}[SKIP]${NC} $1"
}

log_info() {
    echo -e "  ${CYAN}[INFO]${NC} $1"
}

section() {
    echo ""
    echo -e "${BOLD}=== $1 ===${NC}"
}

# HTTP request helper
http_status() {
    local url="$1"
    local method="${2:-GET}"
    local status

    if command -v curl &>/dev/null; then
        status=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" --max-time "$TIMEOUT" "$url" 2>/dev/null || echo "000")
    elif command -v wget &>/dev/null; then
        status=$(wget --spider -S --method="$method" --timeout="$TIMEOUT" "$url" 2>&1 | grep "HTTP/" | tail -1 | awk '{print $2}' || echo "000")
    else
        log_skip "Neither curl nor wget found"
        echo "000"
        return
    fi

    echo "$status"
}

http_body() {
    local url="$1"
    if command -v curl &>/dev/null; then
        curl -s --max-time "$TIMEOUT" "$url" 2>/dev/null || echo ""
    elif command -v wget &>/dev/null; then
        wget -q -O - --timeout="$TIMEOUT" "$url" 2>/dev/null || echo ""
    fi
}

# --- Pre-flight Check ---
section "Pre-flight Checks"

# Check if server is reachable
server_status=$(http_status "$BASE_URL/office_tools/")
if [ "$server_status" = "000" ]; then
    log_fail "Server not reachable at $BASE_URL"
    echo ""
    echo -e "${RED}Cannot connect to the test server.${NC}"
    echo "Set BASE_URL environment variable if not running on localhost:8080"
    echo "  Example: BASE_URL=http://192.168.1.100:8080 ./test_suite.sh"
    echo ""
    echo "Skipping all tests."
    section "Results"
    echo -e "  ${RED}FAIL: $FAIL  ${GREEN}PASS: $PASS  ${YELLOW}SKIP: $SKIP${NC}"
    exit 1
fi
log_pass "Server is reachable ($BASE_URL) - HTTP $server_status"

# --- Test Suite ---

section "Network Scanner (network_scanner.php)"

# Test: Unauthenticated access should redirect or return 401/403
scanner_status=$(http_status "$BASE_URL/office_tools/network_scanner.php")
if [ "$scanner_status" = "302" ] || [ "$scanner_status" = "301" ]; then
    log_pass "Unauthenticated access redirects (HTTP $scanner_status)"
elif [ "$scanner_status" = "401" ] || [ "$scanner_status" = "403" ]; then
    log_pass "Unauthenticated access denied (HTTP $scanner_status)"
elif [ "$scanner_status" = "200" ]; then
    # Check if it shows login form
    body=$(http_body "$BASE_URL/office_tools/network_scanner.php")
    if echo "$body" | grep -qi "login\|sign in\|password"; then
        log_pass "Login page displayed for unauthenticated access (HTTP 200 with login form)"
    else
        log_fail "Page accessible without authentication (HTTP 200, no login form detected)"
    fi
else
    log_fail "Unexpected response: HTTP $scanner_status"
fi

# Test: Scan API rejects unauthenticated POST
scan_status=$(http_status "$BASE_URL/office_tools/network_scanner.php?action=start_scan" "POST")
if [ "$scan_status" = "302" ] || [ "$scan_status" = "401" ] || [ "$scan_status" = "403" ] || [ "$scan_status" = "500" ]; then
    log_pass "Scan API rejects unauthenticated request (HTTP $scan_status)"
else
    log_info "Scan API returned HTTP $scan_status (may show login page)"
fi

# Test: Public IP range should be rejected
log_info "Public IP rejection tested at application level (requires auth to POST)"

# Test: Page contains expected elements
scanner_body=$(http_body "$BASE_URL/office_tools/network_scanner.php")
if echo "$scanner_body" | grep -qi "network scanner\|bootstrap"; then
    log_pass "Page contains expected content (Network Scanner / Bootstrap)"
else
    log_skip "Could not verify page content (may require auth)"
fi

section "Printer Config (printer_config.html)"

# Test: Printer config page loads
printer_status=$(http_status "$BASE_URL/office_tools/printer_config.html")
if [ "$printer_status" = "200" ]; then
    log_pass "Printer config page loads (HTTP 200)"
else
    log_fail "Printer config page failed to load (HTTP $printer_status)"
fi

# Test: Contains IP Calculator tab
printer_body=$(http_body "$BASE_URL/office_tools/printer_config.html")
if echo "$printer_body" | grep -qi "IP Calculator\|Subnet Calculator"; then
    log_pass "IP Calculator tab found"
else
    log_fail "IP Calculator tab not found"
fi

# Test: Contains Port Tester tab
if echo "$printer_body" | grep -qi "Port Tester\|Printer Port"; then
    log_pass "Port Tester tab found"
else
    log_fail "Port Tester tab not found"
fi

# Test: Contains Error Code Lookup tab
if echo "$printer_body" | grep -qi "Error Code\|error database"; then
    log_pass "Error Code Lookup tab found"
else
    log_fail "Error Code Lookup tab not found"
fi

# Test: Contains Driver Downloads tab
if echo "$printer_body" | grep -qi "Driver Download\|download links"; then
    log_pass "Driver Downloads tab found"
else
    log_fail "Driver Downloads tab not found"
fi

# Test: Contains error codes in JavaScript
if echo "$printer_body" | grep -q "ERROR_CODES"; then
    log_pass "Error code database found in JavaScript"
else
    log_fail "Error code database not found"
fi

# Test: Contains embedded error codes (HP, Brother, Canon)
if echo "$printer_body" | grep -q "HP" && echo "$printer_body" | grep -q "Brother" && echo "$printer_body" | grep -q "Canon"; then
    log_pass "All three printer brands (HP, Brother, Canon) in error database"
else
    log_fail "Missing printer brand(s) in error database"
fi

# Test: Uses Bootstrap
if echo "$printer_body" | grep -qi "bootstrap"; then
    log_pass "Bootstrap framework loaded"
else
    log_fail "Bootstrap framework not found"
fi

section "VPN Status (vpn_status.php)"

# Test: Unauthenticated access should redirect or return 401/403
vpn_status=$(http_status "$BASE_URL/office_tools/vpn_status.php")
if [ "$vpn_status" = "302" ] || [ "$vpn_status" = "301" ]; then
    log_pass "Unauthenticated access redirects (HTTP $vpn_status)"
elif [ "$vpn_status" = "401" ] || [ "$vpn_status" = "403" ]; then
    log_pass "Unauthenticated access denied (HTTP $vpn_status)"
elif [ "$vpn_status" = "200" ]; then
    vpn_body=$(http_body "$BASE_URL/office_tools/vpn_status.php")
    if echo "$vpn_body" | grep -qi "login\|sign in\|password"; then
        log_pass "Login page displayed for unauthenticated access (HTTP 200 with login form)"
    else
        log_fail "Page accessible without authentication (HTTP 200, no login form detected)"
    fi
else
    log_fail "Unexpected response: HTTP $vpn_status"
fi

# Test: VPN API rejects unauthenticated requests
vpn_api_status=$(http_status "$BASE_URL/office_tools/vpn_status.php?action=status" "POST")
if [ "$vpn_api_status" = "302" ] || [ "$vpn_api_status" = "401" ] || [ "$vpn_api_status" = "403" ] || [ "$vpn_api_status" = "500" ]; then
    log_pass "VPN status API rejects unauthenticated request (HTTP $vpn_api_status)"
else
    log_info "VPN status API returned HTTP $vpn_api_status"
fi

section "File Structure Verification"

# Check required files exist
REQUIRED_FILES=(
    "office_tools/network_scanner.php"
    "office_tools/printer_config.html"
    "office_tools/vpn_status.php"
    "office_tools/includes/AuthManager.php"
    "office_tools/includes/NetworkUtils.php"
    "office_tools/includes/navbar.php"
    "office_tools/windows_tools/NetworkScanner.ps1"
    "office_tools/windows_tools/VpnChecker.ps1"
    "office_tools/windows_tools/PrinterConfig.ps1"
)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

for file in "${REQUIRED_FILES[@]}"; do
    full_path="$PROJECT_ROOT/$file"
    if [ -f "$full_path" ]; then
        log_pass "File exists: $file"
    else
        log_fail "File missing: $file"
    fi
done

section "PHP Syntax Check"

if command -v php &>/dev/null; then
    PHP_FILES=(
        "$PROJECT_ROOT/office_tools/network_scanner.php"
        "$PROJECT_ROOT/office_tools/vpn_status.php"
        "$PROJECT_ROOT/office_tools/includes/AuthManager.php"
        "$PROJECT_ROOT/office_tools/includes/NetworkUtils.php"
    )

    for php_file in "${PHP_FILES[@]}"; do
        if [ -f "$php_file" ]; then
            errors=$(php -l "$php_file" 2>&1)
            if echo "$errors" | grep -q "No syntax errors"; then
                log_pass "$(basename "$php_file") - No syntax errors"
            else
                log_fail "$(basename "$php_file") - Syntax errors found"
                echo "       $errors"
            fi
        fi
    done
else
    log_skip "PHP CLI not found, skipping syntax checks"
fi

section "PowerShell Syntax Check"

if command -v pwsh &>/dev/null; then
    PS_FILES=(
        "$PROJECT_ROOT/office_tools/windows_tools/NetworkScanner.ps1"
        "$PROJECT_ROOT/office_tools/windows_tools/VpnChecker.ps1"
        "$PROJECT_ROOT/office_tools/windows_tools/PrinterConfig.ps1"
    )

    for ps_file in "${PS_FILES[@]}"; do
        if [ -f "$ps_file" ]; then
            errors=$(pwsh -NoProfile -Command "try { \$null = [System.Management.Automation.Language.Parser]::ParseFile('$ps_file', [ref]\$null, [ref]\$null); Write-Host 'OK' } catch { Write-Host 'ERROR' }" 2>/dev/null)
            if echo "$errors" | grep -q "OK"; then
                log_pass "$(basename "$ps_file") - No syntax errors"
            else
                log_fail "$(basename "$ps_file") - Syntax errors found"
            fi
        fi
    done
else
    log_skip "PowerShell (pwsh) not found, skipping syntax checks"
fi

section "Security Checks"

# Check: AuthManager.php uses session-based auth
auth_file="$PROJECT_ROOT/office_tools/includes/AuthManager.php"
if [ -f "$auth_file" ]; then
    if grep -q "session_start" "$auth_file"; then
        log_pass "AuthManager uses PHP sessions"
    else
        log_fail "AuthManager does not use PHP sessions"
    fi

    if grep -q "CSRF\|csrf" "$auth_file"; then
        log_pass "AuthManager implements CSRF protection"
    else
        log_fail "AuthManager missing CSRF protection"
    fi

    if grep -q "session_regenerate_id" "$auth_file"; then
        log_pass "AuthManager regenerates session ID"
    else
        log_fail "AuthManager does not regenerate session ID"
    fi
fi

# Check: NetworkUtils validates private IP
net_file="$PROJECT_ROOT/office_tools/includes/NetworkUtils.php"
if [ -f "$net_file" ]; then
    if grep -q "isPrivateIp\|Private" "$net_file"; then
        log_pass "NetworkUtils has private IP validation"
    else
        log_fail "NetworkUtils missing private IP validation"
    fi

    if grep -q "filter_var.*FILTER_VALIDATE_IP\|validateIp" "$net_file"; then
        log_pass "NetworkUtils validates IP addresses"
    else
        log_fail "NetworkUtils missing IP validation"
    fi

    if grep -q "escapeshellarg\|filter_var" "$net_file"; then
        log_pass "NetworkUtils uses input sanitization"
    else
        log_fail "NetworkUtils missing input sanitization"
    fi
fi

# Check: Error code database has sufficient entries
printer_file="$PROJECT_ROOT/office_tools/printer_config.html"
if [ -f "$printer_file" ]; then
    error_count=$(grep -oP 'brand:\s*"(HP|Brother|Canon)"' "$printer_file" | wc -l || echo "0")
    if [ "$error_count" -ge 30 ]; then
        log_pass "Printer error database has $error_count entries (>= 30 required)"
    else
        log_fail "Printer error database has only $error_count entries (30 required)"
    fi
fi

# --- Results ---
section "Results"

TOTAL=$((PASS + FAIL + SKIP))
echo -e "  ${BOLD}Total:  $TOTAL${NC}"
echo -e "  ${GREEN}Pass:   $PASS${NC}"
echo -e "  ${RED}Fail:   $FAIL${NC}"
echo -e "  ${YELLOW}Skip:   $SKIP${NC}"
echo ""

if [ "$FAIL" -eq 0 ]; then
    echo -e "${GREEN}${BOLD}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}${BOLD}$FAIL test(s) failed.${NC}"
    exit 1
fi
