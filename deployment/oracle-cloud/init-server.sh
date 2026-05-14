#!/bin/bash
# ============================================================
# RedWolf IT Ops Suite - Oracle Cloud Server Initialization
# Run this FIRST on a fresh Ubuntu 22.04 ARM instance
# Usage: bash init-server.sh
# ============================================================
set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

echo ""
echo -e "${BOLD}${CYAN}============================================${NC}"
echo -e "${BOLD}  RedWolf IT Ops Suite - Server Init           ${NC}"
echo -e "${BOLD}  Oracle Cloud Ubuntu 22.04 ARM                ${NC}"
echo -e "${BOLD}${CYAN}============================================${NC}"
echo ""

# -----------------------------------------------------------
# Phase 1: System Update
# -----------------------------------------------------------
echo -e "${BOLD}[1/6] System Update${NC}"
sudo apt-get update -y
sudo apt-get upgrade -y
echo -e "  ${GREEN}[OK]${NC} System updated"

# -----------------------------------------------------------
# Phase 2: Install Docker
# -----------------------------------------------------------
echo -e "${BOLD}[2/6] Installing Docker${NC}"

# Remove conflicting packages
for pkg in docker.io docker-doc docker-compose docker-compose-v2 podman-docker containerd runc; do
    sudo apt-get remove -y $pkg 2>/dev/null || true
done

# Add Docker GPG key and repo
sudo apt-get install -y ca-certificates curl gnupg
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update -y
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Start and enable Docker
sudo systemctl start docker
sudo systemctl enable docker

# Add current user to docker group
sudo usermod -aG docker $USER

echo -e "  ${GREEN}[OK]${NC} Docker installed: $(docker --version | grep -oP 'Docker version \K[^,]+')"

# -----------------------------------------------------------
# Phase 3: Configure Firewall (iptables on Oracle Cloud)
# -----------------------------------------------------------
echo -e "${BOLD}[3/6] Configuring Firewall${NC}"

# Oracle Cloud uses iptables - Docker manages its own chain
# We just need to ensure Docker's ports are accessible
# The OCI Security List handles external access rules

# Allow established connections
sudo iptables -I INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
sudo iptables -I INPUT -p tcp --dport 22 -j ACCEPT
sudo iptables -I INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT -p tcp --dport 8080 -j ACCEPT
sudo iptables -I INPUT -p tcp --dport 11434 -j ACCEPT
sudo iptables -I INPUT -p tcp --dport 8001 -j ACCEPT

# Persist iptables rules
sudo apt-get install -y iptables-persistent
sudo netfilter-persistent save

echo -e "  ${GREEN}[OK]${NC} Firewall configured (ports: 22, 80, 8080, 11434, 8001)"

# -----------------------------------------------------------
# Phase 4: Install Additional Dependencies
# -----------------------------------------------------------
echo -e "${BOLD}[4/6] Installing Dependencies${NC}"

sudo apt-get install -y \
    python3 python3-pip python3-venv \
    sysstat bc jq \
    git curl wget \
    net-tools fping \
    lsof

# Create log directory
sudo mkdir -p /var/log/redwolf
sudo chmod 777 /var/log/redwolf

echo -e "  ${GREEN}[OK]${NC} All dependencies installed"

# -----------------------------------------------------------
# Phase 5: Clone Project (or copy files)
# -----------------------------------------------------------
echo -e "${BOLD}[5/6] Setting Up Project${NC}"

PROJECT_DIR="$HOME/RedWolf-IT-Ops-Suite"

if [ -d "$PROJECT_DIR" ]; then
    echo -e "  ${YELLOW}[SKIP]${NC} Project directory exists"
else
    # Check if files were uploaded (scp) or need git clone
    if [ -f "/tmp/RedWolf-IT-Ops-Suite.tar.gz" ]; then
        echo "  Extracting uploaded project..."
        tar -xzf /tmp/RedWolf-IT-Ops-Suite.tar.gz -C $HOME/
        echo -e "  ${GREEN}[OK]${NC} Project extracted"
    else
        echo -e "  ${YELLOW}[INFO]${NC} No uploaded files found."
        echo "  To deploy, either:"
        echo "    1. Upload project: scp -i ~/.ssh/redwolf-oracle -r RedWolf-IT-Ops-Suite ubuntu@<IP>:~/"
        echo "    2. Or git clone after pushing to GitHub"
        echo ""
        echo -e "  ${YELLOW}Waiting for project files...${NC}"

        # Wait for files to appear (useful if uploading in background)
        for i in $(seq 1 60); do
            if [ -d "$PROJECT_DIR" ]; then
                echo -e "  ${GREEN}[OK]${NC} Project directory detected"
                break
            fi
            if [ -f "/tmp/RedWolf-IT-Ops-Suite.tar.gz" ]; then
                tar -xzf /tmp/RedWolf-IT-Ops-Suite.tar.gz -C $HOME/
                echo -e "  ${GREEN}[OK]${NC} Project extracted"
                break
            fi
            sleep 2
        done
    fi
fi

if [ -d "$PROJECT_DIR" ]; then
    cd "$PROJECT_DIR"
    # Fix permissions for Docker volumes
    chmod -R 755 .
    echo -e "  ${GREEN}[OK]${NC} Project ready at $PROJECT_DIR"
else
    echo -e "  ${RED}[WARN]${NC} Project not found. Run deploy.sh after uploading files."
fi

# -----------------------------------------------------------
# Phase 6: Configure Docker Group (needs re-login)
# -----------------------------------------------------------
echo -e "${BOLD}[6/6] Final Setup${NC}"

# Create .env from defaults
cd "$PROJECT_DIR" 2>/dev/null || cd "$HOME"
if [ ! -f ".env" ]; then
    cat > .env << 'ENVEOF'
# RedWolf IT Ops Suite - Environment
APP_ENV=prod
APP_DEBUG=false

# Database
DB_HOST=db
DB_PORT=3306
DB_NAME=redwolf
DB_USER=redwolf
DB_PASS=RedWolf_Demo_2026!
DB_ROOT_PASS=Root_Demo_2026!

# Web
WEB_PORT=80
PMA_PORT=8080

# Ollama
OLLAMA_PORT=11434
OLLAMA_MODEL=qwen2.5:7b

# Currency
CURRENCY_DEFAULT=HKD
USD_RATE=7.82
CNY_RATE=1.08

# Monitoring
CPU_THRESHOLD=90
DISK_THRESHOLD=85
ENVEOF
    echo -e "  ${GREEN}[OK]${NC} Created .env"
else
    echo -e "  ${GREEN}[OK]${NC} .env already exists"
fi

echo ""
echo -e "${BOLD}${GREEN}============================================${NC}"
echo -e "${BOLD}  Server Initialization Complete!               ${NC}"
echo -e "${BOLD}${GREEN}============================================${NC}"
echo ""
echo -e "  ${YELLOW}Important:${NC} Run this to activate Docker group:"
echo "    newgrp docker"
echo ""
echo "  Then deploy with:"
echo "    cd ~/RedWolf-IT-Ops-Suite"
echo "    bash deployment/oracle-cloud/deploy.sh"
echo ""
echo -e "  ${YELLOW}Or re-login and deploy.${NC}"
echo ""
