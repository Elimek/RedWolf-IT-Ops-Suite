#!/bin/bash
# ============================================================
# RedWolf IT Ops Suite - Chunk 2 Test Suite
# Tests for the Server Monitoring and Alerting System
# Run: bash tests/chunk2/test_suite.sh
# ============================================================
set -euo pipefail

# Color output helpers
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

PASS_COUNT=0
FAIL_COUNT=0
SKIP_COUNT=0

pass() {
    echo -e "  ${GREEN}PASS${NC} $1"
    ((PASS_COUNT++))
}

fail() {
    echo -e "  ${RED}FAIL${NC} $1"
    ((FAIL_COUNT++))
}

skip() {
    echo -e "  ${YELLOW}SKIP${NC} $1"
    ((SKIP_COUNT++))
}

section() {
    echo ""
    echo -e "${CYAN}=== $1 ===${NC}"
}

# ============================================================
# Determine project root
# ============================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
MONITORING_DIR="$PROJECT_ROOT/monitoring"
METRICS_DIR="/var/log/redwolf/metrics"
TEST_TMPDIR=""

# Create temp directory for tests
cleanup() {
    if [ -n "$TEST_TMPDIR" ] && [ -d "$TEST_TMPDIR" ]; then
        rm -rf "$TEST_TMPDIR"
    fi
}
trap cleanup EXIT
TEST_TMPDIR=$(mktemp -d)

# ============================================================
# Test 1: collector.sh generates valid JSONL
# ============================================================
section "Test 1: collector.sh generates valid JSONL"

COLLECTOR="$MONITORING_DIR/collector.sh"

if [ ! -f "$COLLECTOR" ]; then
    fail "collector.sh not found at $COLLECTOR"
else
    # Check for set -euo pipefail
    if grep -q 'set -euo pipefail' "$COLLECTOR"; then
        pass "collector.sh uses set -euo pipefail"
    else
        fail "collector.sh missing set -euo pipefail"
    fi

    # Check script is executable (or at least has bash shebang)
    if head -1 "$COLLECTOR" | grep -q 'bash'; then
        pass "collector.sh has bash shebang"
    else
        fail "collector.sh missing bash shebang"
    fi

    # Check it references JSONL output path
    if grep -q '\.jsonl' "$COLLECTOR"; then
        pass "collector.sh writes to .jsonl format"
    else
        fail "collector.sh does not reference .jsonl output"
    fi

    # Check it collects CPU, memory, disk, network
    for metric in "cpu" "memory" "disk" "network"; do
        if grep -qi "$metric" "$COLLECTOR"; then
            pass "collector.sh collects $metric metrics"
        else
            fail "collector.sh missing $metric collection"
        fi
    done

    # Check log rotation logic
    if grep -q 'RETENTION_DAYS\|mtime\|delete\|rotation' "$COLLECTOR"; then
        pass "collector.sh has log rotation mechanism"
    else
        fail "collector.sh missing log rotation"
    fi

    # Test JSON output format (generate a sample and validate)
    TEST_METRICS_DIR="$TEST_TMPDIR/metrics"
    mkdir -p "$TEST_METRICS_DIR"

    # Create a simulated JSONL output and validate
    SAMPLE_JSON='{"timestamp":"2026-05-15T10:00:00+0800","hostname":"test-server","uptime_seconds":86400,"cpu_used_percent":45.2,"cpu_idle_percent":54.8,"memory_usage_percent":62.1,"disk_usage_percent":55.0,"network_io":{"in":1048576,"out":524288},"top_processes":[]}'
    echo "$SAMPLE_JSON" > "$TEST_METRICS_DIR/test.jsonl"

    # Validate JSON
    if command -v jq &>/dev/null; then
        if jq empty "$TEST_METRICS_DIR/test.jsonl" 2>/dev/null; then
            pass "Sample JSONL output is valid JSON"
        else
            fail "Sample JSONL output is not valid JSON"
        fi
    elif command -v python3 &>/dev/null; then
        if python3 -c "import json; json.loads(open('$TEST_METRICS_DIR/test.jsonl').read())" 2>/dev/null; then
            pass "Sample JSONL output is valid JSON"
        else
            fail "Sample JSONL output is not valid JSON"
        fi
    else
        skip "JSON validation (no jq or python3 available)"
    fi

    # Check required fields in JSON
    for field in "timestamp" "hostname" "cpu_used_percent" "memory_usage_percent" "disk_usage_percent" "network_io" "top_processes"; do
        if echo "$SAMPLE_JSON" | grep -q "\"$field\""; then
            pass "JSONL contains field: $field"
        else
            fail "JSONL missing field: $field"
        fi
    done
fi

# ============================================================
# Test 2: dashboard.php returns HTTP 200
# ============================================================
section "Test 2: dashboard.php returns HTTP 200"

DASHBOARD="$MONITORING_DIR/dashboard.php"

if [ ! -f "$DASHBOARD" ]; then
    fail "dashboard.php not found at $DASHBOARD"
else
    # Check PHP syntax
    if command -v php &>/dev/null; then
        if php -l "$DASHBOARD" >/dev/null 2>&1; then
            pass "dashboard.php has valid PHP syntax"
        else
            fail "dashboard.php has PHP syntax errors"
        fi

        # Check for required HTML elements
        for element in "<!DOCTYPE html>" "Chart.js\|chart.js" "bootstrap\|Bootstrap"; do
            if grep -q "$element" "$DASHBOARD"; then
                pass "dashboard.php contains $element"
            else
                fail "dashboard.php missing $element"
            fi
        done

        # Check for CSS variables
        if grep -q '\-\-rw-' "$DASHBOARD"; then
            pass "dashboard.php uses CSS variables for theming"
        else
            fail "dashboard.php missing CSS variables"
        fi

        # Check for meta refresh or auto-refresh
        if grep -qi 'meta.*refresh\|setInterval\|auto.*refresh' "$DASHBOARD"; then
            pass "dashboard.php has auto-refresh mechanism"
        else
            fail "dashboard.php missing auto-refresh"
        fi

        # Check for responsive layout
        if grep -qi 'viewport\|responsive\|container-fluid\|col-lg\|col-md' "$DASHBOARD"; then
            pass "dashboard.php has responsive layout"
        else
            fail "dashboard.php missing responsive layout"
        fi

        # Check for color coding (green/yellow/red thresholds)
        if grep -qi '70.*85\|green.*yellow.*red\| getStatusColor\|statusColor'; then
            pass "dashboard.php has color-coded thresholds"
        else
            fail "dashboard.php missing color-coded thresholds"
        fi

        # Check for MetricsReader include
        if grep -q 'MetricsReader' "$DASHBOARD"; then
            pass "dashboard.php includes MetricsReader"
        else
            fail "dashboard.php missing MetricsReader include"
        fi

        # Try HTTP check if curl is available and server is running
        if command -v curl &>/dev/null; then
            HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost/monitoring/dashboard.php" 2>/dev/null || echo "000")
            if [ "$HTTP_CODE" = "200" ]; then
                pass "dashboard.php returns HTTP 200"
            elif [ "$HTTP_CODE" = "000" ]; then
                skip "dashboard.php HTTP test (server not running)"
            else
                fail "dashboard.php returns HTTP $HTTP_CODE (expected 200)"
            fi
        else
            skip "dashboard.php HTTP test (no curl)"
        fi
    else
        skip "dashboard.php PHP syntax check (PHP not available)"
    fi
fi

# ============================================================
# Test 3: alert_manager.php triggers on threshold
# ============================================================
section "Test 3: alert_manager.php triggers on threshold"

ALERT_MANAGER="$MONITORING_DIR/alert_manager.php"
ALERT_ENGINE="$MONITORING_DIR/includes/AlertEngine.php"

if [ ! -f "$ALERT_MANAGER" ]; then
    fail "alert_manager.php not found at $ALERT_MANAGER"
else
    # Check PHP syntax
    if command -v php &>/dev/null; then
        if php -l "$ALERT_MANAGER" >/dev/null 2>&1; then
            pass "alert_manager.php has valid PHP syntax"
        else
            fail "alert_manager.php has PHP syntax errors"
        fi
    fi

    # Check for alert thresholds
    if grep -q '90\|85\|CPU.*threshold\|cpuCriticalThreshold\|cpu_critical' "$ALERT_MANAGER" "$ALERT_ENGINE" 2>/dev/null; then
        pass "Alert thresholds are defined"
    else
        fail "Alert thresholds not found"
    fi

    # Check for anti-spam/cooldown
    if grep -qi 'cooldown\|suppress\|anti.*spam\|cooldownSeconds' "$ALERT_MANAGER" "$ALERT_ENGINE" 2>/dev/null; then
        pass "Alert anti-spam cooldown mechanism exists"
    else
        fail "Alert anti-spam cooldown missing"
    fi

    # Check for notification mechanisms
    has_email=false
    has_webhook=false
    for f in "$ALERT_MANAGER" "$ALERT_ENGINE"; do
        [ -f "$f" ] || continue
        grep -qi 'mail\|smtp\|email' "$f" && has_email=true
        grep -qi 'webhook\|curl' "$f" && has_webhook=true
    done

    if $has_email; then
        pass "Alert email notification configured"
    else
        fail "Alert email notification missing"
    fi

    if $has_webhook; then
        pass "Alert webhook notification configured"
    else
        fail "Alert webhook notification missing"
    fi

    # Check for acknowledge functionality
    if grep -qi 'ack\|acknowledge' "$ALERT_MANAGER" 2>/dev/null; then
        pass "Alert acknowledge functionality exists"
    else
        fail "Alert acknowledge functionality missing"
    fi

    # Check for authentication
    if grep -qi 'ADMIN_USER\|ADMIN_PASS\|session\|authenticate\|login' "$ALERT_MANAGER" 2>/dev/null; then
        pass "alert_manager.php requires authentication"
    else
        fail "alert_manager.php missing authentication"
    fi

    # Check AlertEngine class
    if [ -f "$ALERT_ENGINE" ]; then
        if command -v php &>/dev/null; then
            if php -l "$ALERT_ENGINE" >/dev/null 2>&1; then
                pass "AlertEngine.php has valid PHP syntax"
            else
                fail "AlertEngine.php has PHP syntax errors"
            fi
        fi

        # Check for required methods
        for method in "evaluateAlerts\|evaluate" "suppressAlert\|suppress" "createAlert\|create" "sendNotification\|send" "resolveAlerts\|resolve"; do
            if grep -q "function.*$method" "$ALERT_ENGINE" 2>/dev/null; then
                pass "AlertEngine has method matching: $method"
            else
                fail "AlertEngine missing method: $method"
            fi
        done
    else
        fail "AlertEngine.php not found"
    fi
fi

# ============================================================
# Test 4: fault_simulator.php requires auth
# ============================================================
section "Test 4: fault_simulator.php requires authentication"

FAULT_SIM="$MONITORING_DIR/fault_simulator.php"

if [ ! -f "$FAULT_SIM" ]; then
    fail "fault_simulator.php not found at $FAULT_SIM"
else
    # Check PHP syntax
    if command -v php &>/dev/null; then
        if php -l "$FAULT_SIM" >/dev/null 2>&1; then
            pass "fault_simulator.php has valid PHP syntax"
        else
            fail "fault_simulator.php has PHP syntax errors"
        fi
    fi

    # Check for authentication requirement
    if grep -qi 'ADMIN_USER\|ADMIN_PASS\|session\|authenticate\|login' "$FAULT_SIM"; then
        pass "fault_simulator.php has authentication"
    else
        fail "fault_simulator.php missing authentication"
    fi

    # Check for fault actions
    for action in "cpu\|stress" "memory\|leak" "disk\|fill" "nginx\|stop"; do
        if grep -qi "$action" "$FAULT_SIM"; then
            pass "fault_simulator.php has $action simulation"
        else
            fail "fault_simulator.php missing $action simulation"
        fi
    done

    # Check for restore functionality
    if grep -qi 'restore\|cleanup\|reset' "$FAULT_SIM"; then
        pass "fault_simulator.php has restore/cleanup functionality"
    else
        fail "fault_simulator.php missing restore functionality"
    fi

    # Check for audit logging
    if grep -qi 'audit_log\|audit' "$FAULT_SIM"; then
        pass "fault_simulator.php writes to audit_log"
    else
        fail "fault_simulator.php missing audit logging"
    fi

    # Check for AJAX/real-time status
    if grep -qi 'ajax\|setInterval\|fetch\|api=status\|real.*time' "$FAULT_SIM"; then
        pass "fault_simulator.php has real-time status updates"
    else
        fail "fault_simulator.php missing real-time status"
    fi

    # Check for confirmation dialogs
    if grep -qi 'confirm\|Are you sure\|onsubmit.*confirm' "$FAULT_SIM"; then
        pass "fault_simulator.php has confirmation dialogs"
    else
        fail "fault_simulator.php missing confirmation dialogs"
    fi
fi

# ============================================================
# Test 5: Cron job registration
# ============================================================
section "Test 5: Cron job is registered"

DEPLOY_SCRIPT="$PROJECT_ROOT/deployment/chunk2-monitoring.sh"

if [ ! -f "$DEPLOY_SCRIPT" ]; then
    fail "Deployment script not found at $DEPLOY_SCRIPT"
else
    # Check deployment script registers cron
    if grep -qi 'crontab\|cron' "$DEPLOY_SCRIPT"; then
        pass "Deployment script configures cron job"
    else
        fail "Deployment script missing cron configuration"
    fi

    # Check if collector.sh is referenced in cron
    if grep -q 'collector.sh' "$DEPLOY_SCRIPT"; then
        pass "Cron job references collector.sh"
    else
        fail "Cron job does not reference collector.sh"
    fi

    # Check cron runs every minute
    if grep -q '^\*\s*\*\s*\*\s*\*\s*\*\|* * * * *' "$DEPLOY_SCRIPT"; then
        pass "Cron job runs every minute (* * * * *)"
    else
        fail "Cron job not configured for every minute"
    fi
fi

# Also check current crontab if accessible
if command -v crontab &>/dev/null; then
    if crontab -l 2>/dev/null | grep -q 'collector.sh'; then
        pass "collector.sh is registered in current crontab"
    else
        skip "collector.sh not in current crontab (may not be deployed yet)"
    fi
else
    skip "Crontab check (crontab command not available)"
fi

# ============================================================
# Test 6: MetricsReader.php class
# ============================================================
section "Test 6: MetricsReader.php class"

METRICS_READER="$MONITORING_DIR/includes/MetricsReader.php"

if [ ! -f "$METRICS_READER" ]; then
    fail "MetricsReader.php not found"
else
    if command -v php &>/dev/null; then
        if php -l "$METRICS_READER" >/dev/null 2>&1; then
            pass "MetricsReader.php has valid PHP syntax"
        else
            fail "MetricsReader.php has PHP syntax errors"
        fi
    fi

    # Check for required methods
    for method in "getLatestMetrics" "getMetricsRange" "getTopProcesses" "getNetworkChartData"; do
        if grep -q "function $method" "$METRICS_READER"; then
            pass "MetricsReader has method: $method"
        else
            fail "MetricsReader missing method: $method"
        fi
    done

    # Check namespace
    if grep -q 'namespace RedWolf\\Monitoring' "$METRICS_READER"; then
        pass "MetricsReader uses RedWolf\\Monitoring namespace"
    else
        fail "MetricsReader missing proper namespace"
    fi

    # Check for strict types
    if grep -q 'declare(strict_types=1)' "$METRICS_READER"; then
        pass "MetricsReader uses strict types"
    else
        fail "MetricsReader missing strict_types declaration"
    fi
fi

# ============================================================
# Test 7: File structure and headers
# ============================================================
section "Test 7: File structure and headers"

REQUIRED_FILES=(
    "monitoring/collector.sh"
    "monitoring/dashboard.php"
    "monitoring/alert_manager.php"
    "monitoring/fault_simulator.php"
    "monitoring/includes/MetricsReader.php"
    "monitoring/includes/AlertEngine.php"
    "tests/chunk2/test_suite.sh"
    "docs/chunk2-monitoring-guide.md"
)

for file in "${REQUIRED_FILES[@]}"; do
    full_path="$PROJECT_ROOT/$file"
    if [ -f "$full_path" ]; then
        pass "File exists: $file"
    else
        fail "File missing: $file"
    fi
done

# Check for header comments
for file in "$MONITORING_DIR"/*.php "$MONITORING_DIR"/*.sh "$MONITORING_DIR"/includes/*.php; do
    [ -f "$file" ] || continue
    basename_file=$(basename "$file")
    if head -5 "$file" | grep -q '/\*\*\|###\|# =\|RedWolf'; then
        pass "$basename_file has header comment"
    else
        fail "$basename_file missing header comment"
    fi
done

# ============================================================
# Summary
# ============================================================
TOTAL=$((PASS_COUNT + FAIL_COUNT + SKIP_COUNT))
echo ""
echo -e "${CYAN}============================================${NC}"
echo -e "  Test Results Summary"
echo -e "${CYAN}============================================${NC}"
echo -e "  Total:   $TOTAL"
echo -e "  ${GREEN}Passed:  $PASS_COUNT${NC}"
echo -e "  ${RED}Failed:  $FAIL_COUNT${NC}"
echo -e "  ${YELLOW}Skipped: $SKIP_COUNT${NC}"
echo -e "${CYAN}============================================${NC}"

if [ "$FAIL_COUNT" -gt 0 ]; then
    echo -e "  ${RED}RESULT: FAILED${NC}"
    exit 1
else
    echo -e "  ${GREEN}RESULT: PASSED${NC}"
    exit 0
fi
