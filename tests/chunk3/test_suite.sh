#!/usr/bin/env bash
# RedWolf IT Ops - Chunk 3 Test Suite
# Tests for the AI Ticket Classifier component

set -euo pipefail

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

PASS=0
FAIL=0
SKIP=0
TOTAL=0

pass_test() { ((PASS++)); ((TOTAL++)); echo -e "  ${GREEN}PASS${NC} $*"; }
fail_test() { ((FAIL++)); ((TOTAL++)); echo -e "  ${RED}FAIL${NC} $*"; }
skip_test() { ((SKIP++)); ((TOTAL++)); echo -e "  ${YELLOW}SKIP${NC} $*"; }

separator() { echo -e "${CYAN}--- $* ---${NC}"; }

# --- Paths ---
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
AI_DIR="$PROJECT_ROOT/ai_agent"
CORE_DIR="$AI_DIR/core"
DATA_DIR="$AI_DIR/data"

# Ensure PYTHONPATH includes core directory
export PYTHONPATH="$CORE_DIR:$PYTHONPATH"

echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN} RedWolf Chunk 3 - AI Classifier Tests${NC}"
echo -e "${CYAN}========================================${NC}"
echo ""

# --- Test 1: Ollama service reachability ---
separator "Test 1: Ollama Service Reachability"
if curl -s --connect-timeout 2 http://localhost:11434/api/tags > /dev/null 2>&1; then
    pass_test "Ollama is reachable at localhost:11434"
    # Check model availability
    if curl -s http://localhost:11434/api/tags 2>/dev/null | grep -q "qwen2.5:7b"; then
        pass_test "qwen2.5:7b model is available"
    else
        fail_test "qwen2.5:7b model not found in Ollama"
    fi
else
    skip_test "Ollama not reachable at localhost:11434 (classifier will use keyword fallback)"
fi
echo ""

# --- Test 2: FastAPI health endpoint ---
separator "Test 2: FastAPI Health Endpoint"
if curl -s --connect-timeout 3 http://localhost:8001/health > /dev/null 2>&1; then
    HEALTH=$(curl -s http://localhost:8001/health)
    if echo "$HEALTH" | grep -q '"status":"ok"'; then
        pass_test "Health endpoint returns status ok"
    else
        fail_test "Health endpoint returned unexpected response: $HEALTH"
    fi
    if echo "$HEALTH" | grep -q "qwen2.5:7b"; then
        pass_test "Health endpoint reports correct model name"
    else
        fail_test "Health endpoint does not report expected model"
    fi
else
    skip_test "FastAPI service not running on port 8001 (start with: cd ai_agent && bash start.sh)"
fi
echo ""

# --- Test 3: Classify API returns valid JSON ---
separator "Test 3: Classify API Valid JSON Response"
CLASSIFY_RESPONSE=$(curl -s --connect-timeout 5 -X POST http://localhost:8001/classify \
    -H "Content-Type: application/json" \
    -d '{"text": "My laptop screen is broken", "ticket_id": "TEST-001"}' 2>/dev/null)

if [ -z "$CLASSIFY_RESPONSE" ]; then
    skip_test "FastAPI classify endpoint not reachable"
else
    # Check it's valid JSON
    if echo "$CLASSIFY_RESPONSE" | python3 -m json.tool > /dev/null 2>&1; then
        pass_test "Classify endpoint returns valid JSON"

        # Check required fields
        for field in "category" "confidence" "reasoning" "priority" "ticket_id"; do
            if echo "$CLASSIFY_RESPONSE" | grep -q "\"$field\""; then
                pass_test "Response contains '$field' field"
            else
                fail_test "Response missing '$field' field"
            fi
        done

        # Check category is valid
        CATEGORY=$(echo "$CLASSIFY_RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin)['category'])" 2>/dev/null)
        VALID_CATEGORIES="hardware software network security access_request printer vpn email other"
        if echo "$VALID_CATEGORIES" | grep -qw "$CATEGORY"; then
            pass_test "Category '$CATEGORY' is valid"
        else
            fail_test "Category '$CATEGORY' is not a valid category"
        fi

        # Check confidence is a valid number
        CONFIDENCE=$(echo "$CLASSIFY_RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin)['confidence'])" 2>/dev/null)
        if echo "$CONFIDENCE" | grep -qE '^[0-9]+\.[0-9]+$'; then
            pass_test "Confidence is a valid float: $CONFIDENCE"
        else
            fail_test "Confidence is not a valid float: $CONFIDENCE"
        fi
    else
        fail_test "Classify endpoint did not return valid JSON"
    fi
fi
echo ""

# --- Test 4: Accuracy test script ---
separator "Test 4: Accuracy Test Script"
if [ -f "$AI_DIR/test_accuracy.py" ] && [ -f "$DATA_DIR/test.json" ]; then
    ACCURACY_OUTPUT=$(cd "$AI_DIR" && python3 test_accuracy.py 2>&1)
    if [ $? -eq 0 ]; then
        pass_test "Accuracy test script runs without errors"

        # Extract overall accuracy
        ACCURACY_LINE=$(echo "$ACCURACY_OUTPUT" | grep "Overall accuracy" | head -1)
        if [ -n "$ACCURACY_LINE" ]; then
            pass_test "$ACCURACY_LINE"
        else
            fail_test "Could not extract accuracy from output"
        fi
    else
        fail_test "Accuracy test script failed with error"
    fi
else
    fail_test "test_accuracy.py or test.json not found"
fi
echo ""

# --- Test 5: Keyword fallback response time ---
separator "Test 5: Keyword Fallback Response Time"
FALLBACK_START=$(python3 -c "import time; print(time.monotonic())" 2>/dev/null)
python3 -c "
import sys; sys.path.insert(0, '$CORE_DIR')
from keyword_classifier import classify
result = classify('The printer is jammed and the paper is stuck')
print(result['category'])
" > /dev/null 2>&1
FALLBACK_END=$(python3 -c "import time; print(time.monotonic())" 2>/dev/null)

if [ -n "$FALLBACK_START" ] && [ -n "$FALLBACK_END" ]; then
    FALLBACK_MS=$(python3 -c "print(f'{($FALLBACK_END - $FALLBACK_START) * 1000:.1f}')" 2>/dev/null)
    FALLBACK_INT=$(python3 -c "print(int(float('$FALLBACK_MS')))" 2>/dev/null)
    if [ "$FALLBACK_INT" -lt 100 ]; then
        pass_test "Keyword fallback response time: ${FALLBACK_MS}ms (under 100ms)"
    else
        fail_test "Keyword fallback response time too slow: ${FALLBACK_MS}ms (expected under 100ms)"
    fi
else
    skip_test "Could not measure keyword fallback response time"
fi
echo ""

# --- Test 6: File existence checks ---
separator "Test 6: Required Files Exist"
REQUIRED_FILES=(
    "$AI_DIR/prompt_template.txt"
    "$AI_DIR/core/classifier.py"
    "$AI_DIR/core/keyword_classifier.py"
    "$AI_DIR/api_endpoint.php"
    "$AI_DIR/classifier.html"
    "$AI_DIR/data/test.json"
    "$AI_DIR/test_accuracy.py"
    "$AI_DIR/start.sh"
    "$AI_DIR/requirements.txt"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        pass_test "File exists: $(basename "$file")"
    else
        fail_test "File missing: $(basename "$file")"
    fi
done
echo ""

# --- Summary ---
echo -e "${CYAN}========================================${NC}"
echo -e "${CYAN}           Test Summary${NC}"
echo -e "${CYAN}========================================${NC}"
echo -e "  Total:  $TOTAL"
echo -e "  ${GREEN}Passed: $PASS${NC}"
echo -e "  ${RED}Failed: $FAIL${NC}"
echo -e "  ${YELLOW}Skipped: $SKIP${NC}"
echo ""

if [ "$FAIL" -gt 0 ]; then
    echo -e "${RED}Some tests failed.${NC}"
    exit 1
fi

echo -e "${GREEN}All tests passed (skips are OK).${NC}"
exit 0
