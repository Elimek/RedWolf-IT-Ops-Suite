#!/bin/bash
# ============================================================
# Chunk 2 Deployment: Server Monitoring System
# ============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "=== Chunk 2: Deploying Monitoring System ==="

# Install dependencies
echo "[1/4] Installing system dependencies..."
apt-get update -qq
apt-get install -y -qq sysstat bc jq > /dev/null 2>&1 || echo "  (Skipped on non-Debian - install sysstat manually)"

# Create metrics directory
echo "[2/4] Creating metrics directory..."
sudo mkdir -p /var/log/redwolf/metrics
sudo chmod 755 /var/log/redwolf/metrics

# Configure cron job
echo "[3/4] Setting up cron job for metrics collection..."
CRON_JOB="* * * * * $PROJECT_ROOT/monitoring/collector.sh >> /var/log/redwolf/collector.log 2>&1"
(crontab -l 2>/dev/null | grep -v "collector.sh"; echo "$CRON_JOB") | crontab - 2>/dev/null || echo "  (Cron setup skipped - configure manually)"

# Verify dashboard
echo "[4/4] Verifying monitoring dashboard..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/monitoring/dashboard.php 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo "  dashboard.php returned HTTP $HTTP_CODE - OK"
else
    echo "  WARNING: dashboard.php returned HTTP $HTTP_CODE"
fi

echo "=== Chunk 2 Deployment Complete ==="
