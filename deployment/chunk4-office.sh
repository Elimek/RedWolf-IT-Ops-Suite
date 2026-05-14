#!/bin/bash
# ============================================================
# Chunk 4 Deployment: Office Support Tools
# ============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "=== Chunk 4: Deploying Office Support Tools ==="

# Create required directories
echo "[1/4] Creating directories and setting permissions..."
sudo mkdir -p /var/log/redwolf
sudo chmod 755 /var/log/redwolf

# Configure sudoers for network tools
echo "[2/4] Configuring sudoers for privileged operations..."
SUDOERS_LINE="www-data ALL=(ALL) NOPASSWD: /usr/bin/fping, /usr/bin/nmap, /usr/bin/systemctl, /usr/bin/ip"
if sudo grep -q "www-data.*fping" /etc/sudoers 2>/dev/null; then
    echo "  Sudoers already configured."
else
    echo "$SUDOERS_LINE" | sudo tee /etc/sudoers.d/redwolf-office > /dev/null
    sudo chmod 440 /etc/sudoers.d/redwolf-office
    echo "  Sudoers configured."
fi

# Verify office tools
echo "[3/4] Verifying office tools..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/office_tools/network_scanner.php 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ]; then
    echo "  network_scanner.php: HTTP $HTTP_CODE - OK"
else
    echo "  WARNING: network_scanner.php returned HTTP $HTTP_CODE"
fi

# Summary
echo "[4/4] Office tools deployment complete."
echo "  Available tools:"
echo "    - Network Scanner: /office_tools/network_scanner.php"
echo "    - Printer Config:  /office_tools/printer_config.html"
echo "    - VPN Status:      /office_tools/vpn_status.php"

echo "=== Chunk 4 Deployment Complete ==="
