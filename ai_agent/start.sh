#!/usr/bin/env bash
# RedWolf AI Ticket Classifier - Startup Script
# Starts the FastAPI classifier service on port 8001

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CORE_DIR="$SCRIPT_DIR/core"
REQUIREMENTS="$SCRIPT_DIR/requirements.txt"
PORT=8001

# --- Color helpers ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()  { echo -e "${GREEN}[INFO]${NC} $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $*"; }
error() { echo -e "${RED}[ERROR]${NC} $*"; }

# --- Check Python availability ---
if command -v python3 &>/dev/null; then
    PYTHON="python3"
elif command -v python &>/dev/null; then
    PYTHON="python"
else
    error "Python 3 is not installed. Please install Python 3.10+ and try again."
    exit 1
fi

PY_VERSION=$($PYTHON --version 2>&1 || echo "unknown")
info "Found $PY_VERSION"

# --- Install dependencies ---
if [ -f "$REQUIREMENTS" ]; then
    info "Checking dependencies..."
    if ! $PYTHON -c "import fastapi" 2>/dev/null; then
        warn "Dependencies missing. Installing from requirements.txt..."
        $PYTHON -m pip install -r "$REQUIREMENTS"
        if [ $? -ne 0 ]; then
            error "Failed to install dependencies. Try running manually:"
            error "  $PYTHON -m pip install -r $REQUIREMENTS"
            exit 1
        fi
        info "Dependencies installed successfully."
    else
        info "Dependencies already satisfied."
    fi
else
    warn "requirements.txt not found. Skipping dependency installation."
fi

# --- Check if port is already in use ---
if command -v lsof &>/dev/null && lsof -i :$PORT &>/dev/null; then
    warn "Port $PORT is already in use. Another classifier instance may be running."
    warn "Kill the existing process or use a different port."
    exit 1
fi

# --- Check prompt template ---
if [ ! -f "$SCRIPT_DIR/prompt_template.txt" ]; then
    warn "prompt_template.txt not found. The classifier will use a built-in fallback prompt."
fi

# --- Start the service ---
info "Starting RedWolf AI Ticket Classifier on port $PORT..."
info "Press Ctrl+C to stop."
echo ""

cd "$CORE_DIR"
exec $PYTHON -m uvicorn classifier:app --host 0.0.0.0 --port $PORT
