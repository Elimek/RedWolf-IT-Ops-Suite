<#
.SYNOPSIS
    RedWolf IT Officer Demo - Printer Diagnostic Tool (PowerShell)
.DESCRIPTION
    Diagnostic tool for printer troubleshooting on Windows.
    Lists printers, tests connectivity to printer IPs, and manages the print spooler.
.NOTES
    Requires PowerShell 5.1+
    Some actions require Administrator privileges
#>

param(
    [string]$PrinterIP,
    [int]$PrinterPort = 9100,
    [switch]$ListPrinters,
    [switch]$RestartSpooler,
    [switch]$CheckSpooler
)

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  RedWolf Printer Diagnostic Tool" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Function: List all installed printers
function Get-InstalledPrinters {
    Write-Host "[1] Installed Printers:" -ForegroundColor Yellow
    try {
        $printers = Get-Printer -ErrorAction Stop
        if ($printers.Count -eq 0) {
            Write-Host "  No printers installed." -ForegroundColor Gray
        } else {
            $printers | Format-Table Name, PortName, DriverName, Shared -AutoSize
        }
    } catch {
        Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Red
    }
    Write-Host ""
}

# Function: Test connectivity to printer IP
function Test-PrinterConnection {
    param([string]$IP, [int]$Port = 9100)

    Write-Host "[2] Testing Printer Connection:" -ForegroundColor Yellow
    Write-Host "  Target: $IP`:$Port"

    # Test basic connectivity
    $pingResult = Test-Connection -ComputerName $IP -Count 2 -Quiet
    if ($pingResult) {
        Write-Host "  [OK] Ping successful ($IP is reachable)" -ForegroundColor Green
    } else {
        Write-Host "  [FAIL] Ping failed ($IP is not reachable)" -ForegroundColor Red
        return
    }

    # Test printer port
    try {
        $tcpClient = New-Object System.Net.Sockets.TcpClient
        $connectResult = $tcpClient.BeginConnect($IP, $Port, $null, $null)
        $waitResult = $connectResult.AsyncWaitHandle.WaitOne(3000, $false)

        if ($waitResult) {
            Write-Host "  [OK] Port $Port is open (printer is listening)" -ForegroundColor Green
            $tcpClient.EndConnect($connectResult)
        } else {
            Write-Host "  [WARN] Port $Port is closed or filtered" -ForegroundColor Yellow
        }
        $tcpClient.Close()
    } catch {
        Write-Host "  [FAIL] Port test error: $($_.Exception.Message)" -ForegroundColor Red
    }
    Write-Host ""
}

# Function: Check print spooler status
function Get-SpoolerStatus {
    Write-Host "[3] Print Spooler Status:" -ForegroundColor Yellow
    try {
        $spooler = Get-Service -Name Spooler -ErrorAction Stop
        Write-Host "  Service: Print Spooler"
        Write-Host "  Status:  $($spooler.Status)" -ForegroundColor $(if ($spooler.Status -eq 'Running') { 'Green' } else { 'Red' })
        Write-Host "  Start Type: $($spooler.StartType)"

        if ($spooler.Status -ne 'Running') {
            Write-Host "  [WARNING] Print Spooler is not running!" -ForegroundColor Red
            Write-Host "  Run with -RestartSpooler to restart it." -ForegroundColor Yellow
        }
    } catch {
        Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Red
    }
    Write-Host ""
}

# Function: Restart print spooler
function Restart-PrintSpooler {
    Write-Host "[4] Restarting Print Spooler..." -ForegroundColor Yellow

    if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        Write-Host "  [ERROR] Administrator privileges required to restart the spooler." -ForegroundColor Red
        Write-Host "  Please run PowerShell as Administrator and try again." -ForegroundColor Yellow
        return
    }

    try {
        Restart-Service -Name Spooler -Force -ErrorAction Stop
        Start-Sleep -Seconds 2
        $status = (Get-Service -Name Spooler).Status
        if ($status -eq 'Running') {
            Write-Host "  [OK] Print Spooler restarted successfully." -ForegroundColor Green
        } else {
            Write-Host "  [FAIL] Spooler status: $status" -ForegroundColor Red
        }
    } catch {
        Write-Host "  [ERROR] Failed to restart spooler: $($_.Exception.Message)" -ForegroundColor Red
    }
    Write-Host ""
}

# Main execution
if ($ListPrinters) {
    Get-InstalledPrinters
}

if ($PrinterIP) {
    Test-PrinterConnection -IP $PrinterIP -Port $PrinterPort
}

if ($CheckSpooler) {
    Get-SpoolerStatus
}

if ($RestartSpooler) {
    Restart-PrintSpooler
}

# If no parameters, run all checks
if (-not ($ListPrinters -or $PrinterIP -or $CheckSpooler -or $RestartSpooler)) {
    Get-InstalledPrinters
    Get-SpoolerStatus
    Write-Host "Usage examples:" -ForegroundColor Gray
    Write-Host "  .\PrinterConfig.ps1 -PrinterIP 192.168.1.100" -ForegroundColor Gray
    Write-Host "  .\PrinterConfig.ps1 -ListPrinters" -ForegroundColor Gray
    Write-Host "  .\PrinterConfig.ps1 -RestartSpooler" -ForegroundColor Gray
}

Write-Host "============================================" -ForegroundColor Cyan
