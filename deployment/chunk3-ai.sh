#!/bin/bash
# ============================================================
# Chunk 3 Deployment: AI Ticket Classifier
# ============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "=== Chunk 3: Deploying AI Ticket Classifier ==="

# Check Ollama
echo "[1/5] Checking Ollama service..."
if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
    echo "  Ollama is running."
else
    echo "  Starting Ollama via Docker..."
    docker-compose -f "$PROJECT_ROOT/docker-compose.yml" up -d ollama
    sleep 5
fi

# Pull model
echo "[2/5] Pulling AI model (qwen2.5:7b)..."
MODEL="${OLLAMA_MODEL:-qwen2.5:7b}"
if curl -s http://localhost:11434/api/tags | grep -q "$MODEL"; then
    echo "  Model $MODEL already available."
else
    echo "  Downloading $MODEL (this may take a few minutes)..."
    curl -s http://localhost:11434/api/pull -d "{\"name\": \"$MODEL\"}" | tail -1 || echo "  Model pull initiated."
fi

# Install Python dependencies
echo "[3/5] Installing Python dependencies..."
pip install fastapi uvicorn requests 2>/dev/null || pip3 install fastapi uvicorn requests 2>/dev/null || echo "  (Install manually: pip install fastapi uvicorn requests)"

# Start FastAPI server
echo "[4/5] Starting FastAPI classifier service..."
if lsof -i :8001 > /dev/null 2>&1; then
    echo "  FastAPI already running on port 8001."
else
    cd "$PROJECT_ROOT/ai_agent"
    nohup python core/classifier.py > /var/log/redwolf/classifier.log 2>&1 &
    sleep 3
fi

# Verify
echo "[5/5] Verifying classifier API..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8001/health 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo "  Classifier health check: HTTP $HTTP_CODE - OK"
else
    echo "  WARNING: Classifier returned HTTP $HTTP_CODE"
fi

echo "=== Chunk 3 Deployment Complete ==="
