#!/bin/bash
# ============================================================
# RedWolf IT Ops Suite - Oracle Cloud Deployment
# Run AFTER init-server.sh
# Usage: bash deploy.sh
# ============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"
cd "$PROJECT_ROOT"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

COMPOSE_CMD="docker compose"

echo ""
echo -e "${BOLD}${CYAN}============================================${NC}"
echo -e "${BOLD}  RedWolf IT Ops Suite - Deployment              ${NC}"
echo -e "${BOLD}  Environment: Production                        ${NC}"
echo -e "${BOLD}${CYAN}============================================${NC}"
echo ""
echo -e "  Project: $PROJECT_ROOT"
echo ""

# -----------------------------------------------------------
# Pre-flight Checks
# -----------------------------------------------------------
echo -e "${BOLD}[Pre-flight] Checking Environment${NC}"

if ! command -v docker &>/dev/null; then
    echo -e "  ${RED}[FAIL] Docker not found. Run init-server.sh first.${NC}"
    exit 1
fi
echo -e "  ${GREEN}[OK]${NC} Docker $(docker --version | grep -oP 'Docker version \K[^,]+')"

if ! docker info &>/dev/null 2>&1; then
    echo -e "  ${RED}[FAIL] Docker not running. Run: sudo systemctl start docker${NC}"
    exit 1
fi
echo -e "  ${GREEN}[OK]${NC} Docker daemon running"

if [ ! -f ".env" ]; then
    echo -e "  ${RED}[FAIL] .env not found. Run init-server.sh first.${NC}"
    exit 1
fi
echo -e "  ${GREEN}[OK]${NC} .env found"

if [ ! -f "docker-compose.yml" ]; then
    echo -e "  ${RED}[FAIL] docker-compose.yml not found in $PROJECT_ROOT${NC}"
    exit 1
fi
echo -e "  ${GREEN}[OK]${NC} docker-compose.yml found"

echo ""

# -----------------------------------------------------------
# Phase 1: Start Core Services
# -----------------------------------------------------------
echo -e "${BOLD}[1/5] Starting Core Services (Nginx + PHP + MySQL + phpMyAdmin)${NC}"

${COMPOSE_CMD} up -d web php db phpmyadmin

echo -e "  Waiting for MySQL..."
MYSQL_READY=0
for i in $(seq 1 60); do
    if docker exec redwolf-db mysqladmin ping -h localhost -u root --silent 2>/dev/null; then
        MYSQL_READY=1
        break
    fi
    sleep 2
done

if [ "$MYSQL_READY" -eq 1 ]; then
    echo -e "  ${GREEN}[OK]${NC} MySQL 8.0 ready"
else
    echo -e "  ${RED}[FAIL]${NC} MySQL not ready after 120s"
    ${COMPOSE_CMD} logs db --tail 20
    exit 1
fi

# Verify web server
sleep 3
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "  ${GREEN}[OK]${NC} Nginx + PHP-FPM responding (HTTP $HTTP_CODE)"
else
    echo -e "  ${YELLOW}[WARN]${NC} Web server returned HTTP $HTTP_CODE"
fi

echo ""

# -----------------------------------------------------------
# Phase 2: Magento Lite (Chunk 1)
# -----------------------------------------------------------
echo -e "${BOLD}[2/5] Deploying Magento Lite${NC}"

# Schema auto-imported by MySQL init; verify
PRODUCT_COUNT=$(docker exec redwolf-db mysql -u redwolf -p"$(${COMPOSE_CMD} run --rm php printenv DB_PASS 2>/dev/null || echo redwolf_secret)" redwolf -N -e "SELECT COUNT(*) FROM products;" 2>/dev/null || echo "0")

# Fallback with default password
if [ "$PRODUCT_COUNT" = "0" ]; then
    PRODUCT_COUNT=$(docker exec redwolf-db mysql -u redwolf -predwolf_secret redwolf -N -e "SELECT COUNT(*) FROM products;" 2>/dev/null || echo "0")
fi
if [ "$PRODUCT_COUNT" = "0" ]; then
    # Read password from .env
    DB_PASS=$(grep DB_PASS .env | head -1 | cut -d= -f2 | tr -d ' "' | head -1)
    PRODUCT_COUNT=$(docker exec redwolf-db mysql -u redwolf -p"${DB_PASS}" redwolf -N -e "SELECT COUNT(*) FROM products;" 2>/dev/null || echo "0")
fi

echo -e "  ${GREEN}[OK]${NC} Products in database: $PRODUCT_COUNT"

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/product.php 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "  ${GREEN}[OK]${NC} Product page: HTTP $HTTP_CODE"
else
    echo -e "  ${YELLOW}[WARN]${NC} Product page: HTTP $HTTP_CODE"
fi

echo ""

# -----------------------------------------------------------
# Phase 3: Monitoring System (Chunk 2)
# -----------------------------------------------------------
echo -e "${BOLD}[3/5] Deploying Monitoring System${NC}"

# Install sysstat if not in Docker context
sudo apt-get install -y sysstat bc jq > /dev/null 2>&1 || true

# Create metrics directory
sudo mkdir -p /var/log/redwolf/metrics
sudo chmod 777 /var/log/redwolf/metrics

# Run collector once to generate initial data
bash monitoring/collector.sh > /dev/null 2>&1 || echo "  (Collector ran with warnings)"

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/monitoring/dashboard.php 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "  ${GREEN}[OK]${NC} Monitoring dashboard: HTTP $HTTP_CODE"
else
    echo -e "  ${YELLOW}[WARN]${NC} Monitoring dashboard: HTTP $HTTP_CODE"
fi

echo ""

# -----------------------------------------------------------
# Phase 4: AI Classifier (Chunk 3)
# -----------------------------------------------------------
echo -e "${BOLD}[4/5] Deploying AI Ticket Classifier${NC}"

# Start Ollama
${COMPOSE_CMD} up -d ollama

echo "  Waiting for Ollama to start..."
sleep 5

OLLAMA_OK=0
for i in $(seq 1 30); do
    if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
        OLLAMA_OK=1
        break
    fi
    sleep 2
done

if [ "$OLLAMA_OK" -eq 1 ]; then
    echo -e "  ${GREEN}[OK]${NC} Ollama service running"

    # Pull model (async - runs in background)
    MODEL=$(grep OLLAMA_MODEL .env | head -1 | cut -d= -f2 | tr -d ' "' || echo "qwen2.5:7b")
    echo -e "  ${YELLOW}[...]${NC} Pulling model: $MODEL (this runs in background)"
    curl -s http://localhost:11434/api/pull -d "{\"name\": \"$MODEL\", \"stream\": false}" > /dev/null 2>&1 &
    PULL_PID=$!
    echo "  Model pull PID: $PULL_PID"

    # Verify classifier HTML
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ai_agent/classifier.html 2>/dev/null || echo "000")
    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "  ${GREEN}[OK]${NC} Classifier UI: HTTP $HTTP_CODE"
    else
        echo -e "  ${YELLOW}[WARN]${NC} Classifier UI: HTTP $HTTP_CODE"
    fi
else
    echo -e "  ${YELLOW}[WARN]${NC} Ollama not responding - AI classification will use keyword fallback"
fi

echo ""

# -----------------------------------------------------------
# Phase 5: Office Tools (Chunk 4)
# -----------------------------------------------------------
echo -e "${BOLD}[5/5] Deploying Office Support Tools${NC}"

sudo mkdir -p /var/log/redwolf
sudo chmod 777 /var/log/redwolf

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/office_tools/network_scanner.php 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ]; then
    echo -e "  ${GREEN}[OK]${NC} Network Scanner: HTTP $HTTP_CODE"
else
    echo -e "  ${YELLOW}[WARN]${NC} Network Scanner: HTTP $HTTP_CODE"
fi

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/office_tools/vpn_status.php 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "  ${GREEN}[OK]${NC} VPN Status: HTTP $HTTP_CODE"
else
    echo -e "  ${YELLOW}[WARN]${NC} VPN Status: HTTP $HTTP_CODE"
fi

echo ""

# -----------------------------------------------------------
# Deployment Summary
# -----------------------------------------------------------
# Get public IP
PUBLIC_IP=$(curl -s ifconfig.me 2>/dev/null || curl -s icanhazip.com 2>/dev/null || echo "<YOUR_PUBLIC_IP>")

echo -e "${BOLD}${GREEN}============================================${NC}"
echo -e "${BOLD}  Deployment Complete!                          ${NC}"
echo -e "${BOLD}${GREEN}============================================${NC}"
echo ""
echo -e "  ${BOLD}Access your demo:${NC}"
echo ""
echo -e "  ${CYAN}Main Site:${NC}       http://${PUBLIC_IP}"
echo -e "  ${CYAN}Magento Lite:${NC}    http://${PUBLIC_IP}/product.php"
echo -e "  ${CYAN}Monitoring:${NC}      http://${PUBLIC_IP}/monitoring/dashboard.php"
echo -e "  ${CYAN}AI Classifier:${NC}   http://${PUBLIC_IP}/ai_agent/classifier.html"
echo -e "  ${CYAN}Office Tools:${NC}    http://${PUBLIC_IP}/office_tools/network_scanner.php"
echo -e "  ${CYAN}phpMyAdmin:${NC}      http://${PUBLIC_IP}:8080"
echo ""
echo -e "  ${YELLOW}Note:${NC} AI model ($MODEL) is downloading."
echo "  Check status: docker logs redwolf-ollama --tail 5"
echo "  Model size: ~4.7GB, takes 5-15 min depending on bandwidth"
echo ""
echo -e "  ${BOLD}Verify all services:${NC}"
echo "    bash deployment/oracle-cloud/verify.sh"
echo ""
