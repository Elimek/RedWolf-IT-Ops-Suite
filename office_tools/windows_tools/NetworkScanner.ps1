<#
.SYNOPSIS
    RedWolf Network Scanner - PowerShell equivalent of network_scanner.php
.DESCRIPTION
    Scans an IP range for live hosts, open ports, and hostnames.
    Supports ping sweep, port scanning on common ports, and CSV export.
.NOTES
    RedWolf IT Ops Suite
    Requires PowerShell 5.1+ (Windows) or PowerShell 7+ (cross-platform)
#>

param(
    [Parameter(Mandatory = $false)]
    [string]$StartIp = "192.168.1.1",

    [Parameter(Mandatory = $false)]
    [string]$EndIp = "192.168.1.254",

    [Parameter(Mandatory = $false)]
    [int[]]$Ports = @(22, 80, 443, 3389, 5900),

    [Parameter(Mandatory = $false)]
    [int]$TimeoutMs = 1000,

    [Parameter(Mandatory = $false)]
    [int]$PortTimeoutMs = 2000,

    [Parameter(Mandatory = $false)]
    [switch]$ExportCsv,

    [Parameter(Mandatory = $false)]
    [string]$CsvPath = "network_scan_$(Get-Date -Format 'yyyy-MM-dd_HHmmss').csv"
)

# --- Helper Functions ---

function Test-PrivateIP {
    param([string]$IpAddress)

    $ip = [System.Net.IPAddress]::Parse($IpAddress)

    # 10.0.0.0/8
    if ($ip.AddressFamily -eq 'InterNetwork') {
        $bytes = $ip.GetAddressBytes()
        if ($bytes[0] -eq 10) { return $true }
        # 172.16.0.0/12
        if ($bytes[0] -eq 172 -and $bytes[1] -ge 16 -and $bytes[1] -le 31) { return $true }
        # 192.168.0.0/16
        if ($bytes[0] -eq 192 -and $bytes[1] -eq 168) { return $true }
    }
    return $false
}

function Get-IPRange {
    param([string]$Start, [string]$End)

    $startLong = [uint32][System.Net.IPAddress]::Parse($Start).Address
    $endLong = [uint32][System.Net.IPAddress]::Parse($End).Address

    # Handle byte order
    $startBytes = [System.Net.IPAddress]::Parse($Start).GetAddressBytes()
    [Array]::Reverse($startBytes)
    $startLong = [BitConverter]::ToUInt32($startBytes, 0)

    $endBytes = [System.Net.IPAddress]::Parse($End).GetAddressBytes()
    [Array]::Reverse($endBytes)
    $endLong = [BitConverter]::ToUInt32($endBytes, 0)

    $ips = @()
    for ($i = $startLong; $i -le $endLong; $i++) {
        $bytes = [BitConverter]::GetBytes($i)
        [Array]::Reverse($bytes)
        $ips += [string][System.Net.IPAddress]::new($bytes)
    }
    return $ips
}

function Test-HostPort {
    param(
        [string]$IpAddress,
        [int]$Port,
        [int]$TimeoutMs = 2000
    )

    try {
        $tcpClient = New-Object System.Net.Sockets.TcpClient
        $result = $tcpClient.BeginConnect($IpAddress, $Port, $null, $null)
        $waited = $result.AsyncWaitHandle.WaitOne($TimeoutMs, $false)
        if ($waited -and $tcpClient.Connected) {
            $tcpClient.EndConnect($result)
            $tcpClient.Close()
            return $true
        }
        $tcpClient.Close()
        return $false
    }
    catch {
        return $false
    }
}

function Get-HostName {
    param([string]$IpAddress)

    try {
        $hostname = [System.Net.Dns]::GetHostEntry($IpAddress).HostName
        return $hostname
    }
    catch {
        return "N/A"
    }
}

function Get-PortServiceName {
    param([int]$Port)

    $services = @{
        21    = 'FTP'
        22    = 'SSH'
        23    = 'Telnet'
        25    = 'SMTP'
        53    = 'DNS'
        80    = 'HTTP'
        110   = 'POP3'
        143   = 'IMAP'
        443   = 'HTTPS'
        445   = 'SMB'
        993   = 'IMAPS'
        995   = 'POP3S'
        3306  = 'MySQL'
        3389  = 'RDP'
        5432  = 'PostgreSQL'
        5900  = 'VNC'
        6379  = 'Redis'
        8080  = 'HTTP-Alt'
        9100  = 'Printer'
    }

    if ($services.ContainsKey($Port)) {
        return "$($services[$Port]) ($Port)"
    }
    return "Port $Port"
}

# --- Main Script ---

$ErrorActionPreference = 'SilentlyContinue'
Write-Host ""
Write-Host "========================================" -ForegroundColor Red
Write-Host "  RedWolf Network Scanner" -ForegroundColor White
Write-Host "========================================" -ForegroundColor Red
Write-Host ""

# Validate IP addresses
try {
    [void][System.Net.IPAddress]::Parse($StartIp)
    [void][System.Net.IPAddress]::Parse($EndIp)
}
catch {
    Write-Host "[ERROR] Invalid IP address format." -ForegroundColor Red
    exit 1
}

# Verify private IP range
if (-not (Test-PrivateIP $StartIp) -or -not (Test-PrivateIP $EndIp)) {
    Write-Host "[ERROR] Only private IP ranges are allowed (10.x.x.x, 172.16-31.x.x, 192.168.x.x)." -ForegroundColor Red
    exit 1
}

# Generate IP list
$ipList = Get-IPRange -Start $StartIp -End $EndIp
$totalHosts = $ipList.Count

if ($totalHosts -eq 0) {
    Write-Host "[ERROR] No hosts in the specified range." -ForegroundColor Red
    exit 1
}

if ($totalHosts -gt 512) {
    Write-Host "[ERROR] Range too large (max 512 hosts). Please reduce the range." -ForegroundColor Red
    exit 1
}

Write-Host "Scanning range: $StartIp - $EndIp ($totalHosts hosts)" -ForegroundColor Cyan
Write-Host "Ports: $($Ports -join ', ')" -ForegroundColor Cyan
Write-Host "Timeout: ${TimeoutMs}ms (ping), ${PortTimeoutMs}ms (port)" -ForegroundColor Cyan
Write-Host ""
Write-Host "Starting scan..." -ForegroundColor Yellow
Write-Host ""

$results = @()
$onlineCount = 0
$progressCounter = 0

foreach ($ip in $ipList) {
    $progressCounter++

    # Progress indicator
    $percent = [math]::Round(($progressCounter / $totalHosts) * 100)
    Write-Progress -Activity "Scanning Network" -Status "Testing $ip ($percent%)" `
        -PercentComplete $percent -CurrentOperation "$onlineCount hosts found"

    # Ping test
    $pingResult = Test-Connection -ComputerName $ip -Count 1 -TimeoutSeconds ([math]::Ceiling($TimeoutMs / 1000)) -ErrorAction SilentlyContinue

    if ($pingResult) {
        $onlineCount++
        $responseTime = $pingResult.ResponseTime
        $hostname = Get-HostName -IpAddress $ip

        # Port scan
        $openPorts = @()
        foreach ($port in $Ports) {
            if (Test-HostPort -IpAddress $ip -Port $port -TimeoutMs $PortTimeoutMs) {
                $openPorts += Get-PortServiceName -Port $port
            }
        }

        $result = [PSCustomObject]@{
            IPAddress    = $ip
            Status       = "Online"
            ResponseTime = "$responseTime ms"
            Hostname     = $hostname
            OpenPorts    = if ($openPorts.Count -gt 0) { $openPorts -join ', ' } else { "None" }
        }

        $results += $result

        # Color-coded output
        $portStr = if ($openPorts.Count -gt 0) { $openPorts -join ', ' } else { "None" }
        Write-Host ("  {0,-18} {1,-10} {2,-25} {3,-30} {4}" -f `
            $ip, "ONLINE", $responseTime.ToString() + " ms", $hostname, $portStr) `
            -ForegroundColor Green
    }
}

Write-Progress -Activity "Scanning Network" -Completed

Write-Host ""
Write-Host "========================================" -ForegroundColor Red
Write-Host "  Scan Complete" -ForegroundColor White
Write-Host "========================================" -ForegroundColor Red
Write-Host "  Hosts scanned:    $totalHosts" -ForegroundColor Cyan
Write-Host "  Hosts online:     $onlineCount" -ForegroundColor Green
Write-Host "  Hosts offline:    $($totalHosts - $onlineCount)" -ForegroundColor DarkGray
Write-Host ""

# Display formatted table
if ($results.Count -gt 0) {
    $results | Format-Table -AutoSize

    # CSV Export
    if ($ExportCsv) {
        $results | Select-Object IPAddress, Status, Hostname, OpenPorts, ResponseTime |
            Export-Csv -Path $CsvPath -NoTypeInformation -Encoding UTF8
        Write-Host "Results exported to: $CsvPath" -ForegroundColor Yellow
    }
}
