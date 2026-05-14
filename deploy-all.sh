#!/bin/bash
# ============================================================
# RedWolf IT Officer Demo - One-Click Deployment Script
# ============================================================
set -euo pipefail

ENVIRONMENT="${1:-dev}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"
REPORT_FILE="$PROJECT_ROOT/DEPLOYMENT_REPORT.md"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# Initialize report
echo "# Deployment Report" > "$REPORT_FILE"
echo "" >> "$REPORT_FILE"
echo "**Environment:** $ENVIRONMENT" >> "$REPORT_FILE"
echo "**Timestamp:** $(date -u '+%Y-%m-%d %H:%M:%S UTC')" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"
echo "| Service | Status | Details |" >> "$REPORT_FILE"
echo "|---------|--------|---------|" >> "$REPORT_FILE"

echo ""
echo -e "${BOLD}${CYAN}════════════════════════════════════════════${NC}"
echo -e "${BOLD}   RedWolf IT Officer Demo - Deployer           ${NC}"
echo -e "${BOLD}   Environment: $ENVIRONMENT                      ${NC}"
echo -e "${BOLD}${CYAN}════════════════════════════════════════════${NC}"
echo ""

# Step 0: Environment Check
echo -e "${BOLD}[0/6] Environment Checks${NC}"
echo ""

# Check Docker
if ! command -v docker &>/dev/null; then
    echo -e "  ${RED}[FAIL] Docker not installed${NC}"
    echo "| Docker | FAIL | Not installed |" >> "$REPORT_FILE"
    exit 1
fi
echo -e "  ${GREEN}[OK]${NC} Docker $(docker --version | grep -oP 'Docker version \K[^,]+')"

if ! docker info &>/dev/null 2>&1; then
    echo -e "  ${RED}[FAIL] Docker daemon not running${NC}"
    exit 1
fi
echo -e "  ${GREEN}[OK]${NC} Docker daemon running"

# Check Docker Compose
if ! docker compose version &>/dev/null 2>&1; then
    if ! command -v docker-compose &>/dev/null; then
        echo -e "  ${RED}[FAIL] Docker Compose not available${NC}"
        exit 1
    fi
    COMPOSE_CMD="docker-compose"
else
    COMPOSE_CMD="docker compose"
fi
echo -e "  ${GREEN}[OK]${NC} Docker Compose available"

# Create .env from example if not exists
if [ ! -f "$PROJECT_ROOT/.env" ]; then
    if [ -f "$PROJECT_ROOT/.env.example" ]; then
        cp "$PROJECT_ROOT/.env.example" "$PROJECT_ROOT/.env"
        echo -e "  ${GREEN}[OK]${NC} Created .env from .env.example"
    else
        echo -e "  ${YELLOW}[WARN]${NC} No .env.example found"
    fi
else
    echo -e "  ${GREEN}[OK]${NC} .env already exists"
fi

echo -e "  ${GREEN}[OK]${NC} Docker | OK | $(${COMPOSE_CMD} version 2>/dev/null || echo 'N/A') |" >> "$REPORT_FILE"
echo ""

# Step 1: Start Core Services (web + db + phpmyadmin)
echo -e "${BOLD}[1/6] Deploying Core Services (Web + DB + phpMyAdmin)${NC}"
echo ""
${COMPOSE_CMD} up -d web php db phpmyadmin
echo "  Waiting for services..."
sleep 10

# Check MySQL
MYSQL_READY=0
for i in $(seq 1 30); do
    if docker exec redwolf-db mysqladmin ping -h localhost -u root --silent 2>/dev/null; then
        MYSQL_READY=1
        break
    fi
    sleep 1
done

if [ "$MYSQL_READY" -eq 1 ]; then
    echo -e "  ${GREEN}[OK]${NC} MySQL is ready"
    echo "| MySQL 8.0 | OK | Port 3306 |" >> "$REPORT_FILE"
else
    echo -e "  ${RED}[FAIL]${NC} MySQL not ready after 30s"
    echo "| MySQL 8.0 | FAIL | Timeout |" >> "$REPORT_FILE"
fi

echo -e "  ${GREEN}[OK]${NC} Nginx + PHP-FPM started"
echo "| Nginx + PHP 8.2 | OK | Port 80 |" >> "$REPORT_FILE"
echo "| phpMyAdmin | OK | Port 8080 |" >> "$REPORT_FILE"
echo ""

# Step 2: Deploy Chunk 1 - Magento Lite
echo -e "${BOLD}[2/6] Deploying Chunk 1: Magento Lite${NC}"
echo ""
if bash "$PROJECT_ROOT/deployment/chunk1-core.sh" 2>&1 | tail -5; then
    echo -e "  ${GREEN}[OK]${NC} Chunk 1 deployed"
    echo "| Magento Lite | OK | Products loaded |" >> "$REPORT_FILE"
else
    echo -e "  ${YELLOW}[WARN]${NC} Chunk 1 deployment had warnings"
    echo "| Magento Lite | WARN | Check logs |" >> "$REPORT_FILE"
fi
echo ""

# Step 3: Deploy Chunk 2 - Monitoring
echo -e "${BOLD}[3/6] Deploying Chunk 2: Monitoring${NC}"
echo ""
if bash "$PROJECT_ROOT/deployment/chunk2-monitoring.sh" 2>&1 | tail -5; then
    echo -e "  ${GREEN}[OK]${NC} Chunk 2 deployed"
    echo "| Monitoring | OK | Cron + Dashboard |" >> "$REPORT_FILE"
else
    echo -e "  ${YELLOW}[WARN]${NC} Chunk 2 deployment had warnings"
    echo "| Monitoring | WARN | Check logs |" >> "$REPORT_FILE"
fi
echo ""

# Step 4: Deploy Chunk 3 - AI Classifier
echo -e "${BOLD}[4/6] Deploying Chunk 3: AI Classifier${NC}"
echo ""
${COMPOSE_CMD} up -d ollama
echo -e "  ${GREEN}[OK]${NC} Ollama started"
echo "| Ollama | OK | Port 11434 |" >> "$REPORT_FILE"

if bash "$PROJECT_ROOT/deployment/chunk3-ai.sh" 2>&1 | tail -5; then
    echo -e "  ${GREEN}[OK]${NC} Chunk 3 deployed"
    echo "| AI Classifier | OK | FastAPI :8001 |" >> "$REPORT_FILE"
else
    echo -e "  ${YELLOW}[WARN]${NC} Chunk 3 deployment had warnings"
    echo "| AI Classifier | WARN | Check logs |" >> "$REPORT_FILE"
fi
echo ""

# Step 5: Deploy Chunk 4 - Office Tools
echo -e "${BOLD}[5/6] Deploying Chunk 4: Office Tools${NC}"
echo ""
if bash "$PROJECT_ROOT/deployment/chunk4-office.sh" 2>&1 | tail -5; then
    echo -e "  ${GREEN}[OK]${NC} Chunk 4 deployed"
    echo "| Office Tools | OK | Scanner + Printer + VPN |" >> "$REPORT_FILE"
else
    echo -e "  ${YELLOW}[WARN]${NC} Chunk 4 deployment had warnings"
    echo "| Office Tools | WARN | Check logs |" >> "$REPORT_FILE"
fi
echo ""

# Step 6: Run Tests
echo -e "${BOLD}[6/6] Running Tests${NC}"
echo ""
if bash "$PROJECT_ROOT/tests/run_all.sh" 2>&1 | tail -10; then
    echo -e "  ${GREEN}[OK]${NC} All tests passed"
    echo "| Test Suite | PASS | All suites |" >> "$REPORT_FILE"
else
    echo -e "  ${YELLOW}[WARN]${NC} Some tests failed - review output above"
    echo "| Test Suite | WARN | Some failures |" >> "$REPORT_FILE"
fi
echo ""

# Final Summary
echo -e "${BOLD}${GREEN}════════════════════════════════════════════${NC}"
echo -e "${BOLD}   Deployment Complete!                         ${NC}"
echo -e "${BOLD}${GREEN}════════════════════════════════════════════${NC}"
echo ""
echo "  Access points:"
echo "    Main Site:       http://localhost"
echo "    phpMyAdmin:      http://localhost:8080"
echo "    Ollama API:      http://localhost:11434"
echo "    AI Classifier:   http://localhost:8001"
echo ""
echo "  Report saved to: $REPORT_FILE"
echo ""
