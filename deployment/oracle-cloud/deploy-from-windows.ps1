<#
.SYNOPSIS
    RedWolf IT Ops Suite - Windows Upload & Deploy Helper
    Compresses project, uploads to Oracle Cloud, and deploys remotely.
.DESCRIPTION
    Run this from your Windows machine after creating your Oracle Cloud instance.
    It will:
    1. Compress the project
    2. Upload via SCP
    3. SSH in and run init + deploy + verify
.PARAMETER Ip
    Public IP of your Oracle Cloud instance
.PARAMETER SshKey
    Path to SSH private key (default: ~/.ssh/redwolf-oracle)
.EXAMPLE
    .\deploy-from-windows.ps1 -Ip "152.70.xxx.xxx"
.EXAMPLE
    .\deploy-from-windows.ps1 -Ip "152.70.xxx.xxx" -SshKey "C:\Users\Elimek\.ssh\redwolf-oracle"
#>

param(
    [Parameter(Mandatory=$true)]
    [string]$Ip,

    [string]$SshKey = "$env:USERPROFILE\.ssh\redwolf-oracle"
)

$ErrorActionPreference = "Stop"
$ProjectDir = "C:\Users\Elimek\RedWolf-IT-Ops-Suite"
$ZipFile = "$env:TEMP\RedWolf-IT-Ops-Suite.zip"
$RemoteUser = "ubuntu"

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  RedWolf IT Ops Suite - Deploy Helper     " -ForegroundColor Cyan
Write-Host "  Target: ${RemoteUser}@${Ip}              " -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Compress
Write-Host "[1/4] Compressing project..." -ForegroundColor Yellow
if (Test-Path $ZipFile) { Remove-Item $ZipFile -Force }
Compress-Archive -Path $ProjectDir -DestinationPath $ZipFile -Force
$Size = (Get-Item $ZipFile).Length / 1MB
Write-Host "  [OK] Compressed: $([math]::Round($Size, 1)) MB" -ForegroundColor Green

# Step 2: Upload
Write-Host "[2/4] Uploading to server..." -ForegroundColor Yellow
scp -i $SshKey $ZipFile "${RemoteUser}@${Ip}:~/RedWolf-IT-Ops-Suite.zip"
Write-Host "  [OK] Upload complete" -ForegroundColor Green

# Step 3: Extract
Write-Host "[3/4] Extracting on server..." -ForegroundColor Yellow
ssh -i $SshKey "${RemoteUser}@${Ip}" @"
cd ~
rm -rf RedWolf-IT-Ops-Suite
unzip -o RedWolf-IT-Ops-Suite.zip
rm RedWolf-IT-Ops-Suite.zip
echo '  [OK] Extracted'
"@
Write-Host "  [OK] Files extracted" -ForegroundColor Green

# Step 4: Deploy
Write-Host "[4/4] Deploying on server (this takes ~5 min)..." -ForegroundColor Yellow
Write-Host ""
ssh -i $SshKey "${RemoteUser}@${Ip}" "cd ~/RedWolf-IT-Ops-Suite && bash deployment/oracle-cloud/init-server.sh && newgrp docker << 'INNEREOF'
cd ~/RedWolf-IT-Ops-Suite
bash deployment/oracle-cloud/deploy.sh
INNEREOF"

# Step 5: Verify
Write-Host ""
Write-Host "Running acceptance tests..." -ForegroundColor Yellow
ssh -i $SshKey "${RemoteUser}@${Ip}" "cd ~/RedWolf-IT-Ops-Suite && newgrp docker << 'INNEREOF'
cd ~/RedWolf-IT-Ops-Suite
bash deployment/oracle-cloud/verify.sh http://$Ip
INNEREOF"

Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  Deploy Helper Complete!                   " -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Open in browser:" -ForegroundColor Cyan
Write-Host "    Main:        http://$Ip" -ForegroundColor White
Write-Host "    Products:    http://$Ip/product.php" -ForegroundColor White
Write-Host "    Monitoring:  http://$Ip/monitoring/dashboard.php" -ForegroundColor White
Write-Host "    AI Agent:    http://$Ip/ai_agent/classifier.html" -ForegroundColor White
Write-Host "    Office:      http://$Ip/office_tools/network_scanner.php" -ForegroundColor White
Write-Host "    phpMyAdmin:  http://${Ip}:8080" -ForegroundColor White
Write-Host ""

# Cleanup
Remove-Item $ZipFile -Force -ErrorAction SilentlyContinue
