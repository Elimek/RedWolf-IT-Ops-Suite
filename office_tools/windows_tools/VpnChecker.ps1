<#
.SYNOPSIS
    RedWolf VPN Checker - PowerShell equivalent of vpn_status.php
.DESCRIPTION
    Checks VPN connection status on Windows, displays VPN details,
    and provides reconnect/disconnect functionality.
.NOTES
    RedWolf IT Ops Suite
    Requires PowerShell 5.1+
    Run as Administrator for connect/disconnect actions
#>

param(
    [Parameter(Mandatory = $false)]
    [ValidateSet('status', 'reconnect', 'disconnect', 'logs')]
    [string]$Action = "status",

    [Parameter(Mandatory = $false)]
    [string]$VpnName,

    [Parameter(Mandatory = $false)]
    [int]$LogCount = 50
)

# --- Helper Functions ---

function Get-VpnStatus {
    $status = @{
        Connected   = $false
        VpnType     = "None"
        VpnName     = $null
        VirtualIP   = $null
        ServerAddress = $null
        ConnectionTime = $null
        BytesIn     = $null
        BytesOut    = $null
        AdapterName = $null
    }

    # Method 1: Get-VpnConnection (Windows 8+ / Server 2012+)
    $vpnConnection = Get-VpnConnection -ErrorAction SilentlyContinue |
        Where-Object { $_.ConnectionStatus -eq 'Connected' }

    if ($vpnConnection) {
        $vpn = $vpnConnection | Select-Object -First 1
        $status.Connected = $true
        $status.VpnName = $vpn.Name
        $status.VpnType = $vpn.TunnelType
        $status.ServerAddress = $vpn.ServerAddress
        $status.AdapterName = $vpn.Name

        # Get connection time
        $adapter = Get-NetAdapter | Where-Object { $_.Name -eq $vpn.Name -or $_.InterfaceAlias -eq $vpn.Name }
        if ($adapter) {
            $status.VirtualIP = ($adapter | Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue).IPAddress
            # Get interface up time
            try {
                $status.ConnectionTime = (Get-Date) - $adapter.MediaConnectionState
            } catch {
                $status.ConnectionTime = $null
            }
        }
    }

    # Method 2: Check network adapters with VPN keywords
    if (-not $status.Connected) {
        $vpnAdapters = Get-NetAdapter -ErrorAction SilentlyContinue |
            Where-Object {
                $_.InterfaceDescription -match 'VPN|TAP|WireGuard|OpenVPN|SSTP|L2TP|IKEv2|PPTP' -or
                $_.Name -match 'VPN|WireGuard|OpenVPN|Tunnel'
            } |
            Where-Object { $_.Status -eq 'Up' }

        if ($vpnAdapters) {
            $adapter = $vpnAdapters | Select-Object -First 1
            $status.Connected = $true
            $status.VpnName = $adapter.Name
            $status.VpnType = $adapter.InterfaceDescription
            $status.AdapterName = $adapter.Name

            $ip = $adapter | Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue
            if ($ip) {
                $status.VirtualIP = $ip.IPAddress
            }
        }
    }

    # Method 3: rasdial for legacy VPN connections
    if (-not $status.Connected) {
        $rasdial = rasdial 2>&1
        if ($LASTEXITCODE -eq 0 -and $rasdial -notmatch 'No connections') {
            $status.Connected = $true
            $status.VpnName = ($rasdial | Select-Object -First 1).Trim()
            $status.VpnType = "Dial-Up VPN"
        }
    }

    # Get traffic statistics if connected
    if ($status.Connected -and $status.AdapterName) {
        try {
            $stats = Get-NetAdapterStatistics -Name $status.AdapterName -ErrorAction SilentlyContinue
            if ($stats) {
                $status.BytesIn = $stats.ReceivedBytes
                $status.BytesOut = $stats.SentBytes
            }
        } catch {
            # Non-critical
        }
    }

    return $status
}

function Format-Bytes {
    param([long]$Bytes)

    if ($Bytes -ge 1GB) {
        return "{0:N2} GB" -f ($Bytes / 1GB)
    }
    elseif ($Bytes -ge 1MB) {
        return "{0:N2} MB" -f ($Bytes / 1MB)
    }
    elseif ($Bytes -ge 1KB) {
        return "{0:N2} KB" -f ($Bytes / 1KB)
    }
    else {
        return "$Bytes Bytes"
    }
}

function Connect-VpnConnection {
    param([string]$Name)

    if (-not $Name) {
        $available = Get-VpnConnection -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Name
        if ($available) {
            Write-Host "Available VPN connections:" -ForegroundColor Yellow
            $available | ForEach-Object { Write-Host "  - $_" -ForegroundColor Cyan }
            Write-Host ""
            $Name = Read-Host "Enter VPN name to connect"
        }
        else {
            Write-Host "[ERROR] No VPN connections configured." -ForegroundColor Red
            Write-Host "Use 'Add-VpnConnection' to create one, or configure via Windows Settings." -ForegroundColor Yellow
            return $false
        }
    }

    Write-Host "Connecting to VPN: $Name..." -ForegroundColor Yellow

    try {
        rasdial $Name 2>&1 | Out-Null
        Start-Sleep -Seconds 2

        $vpn = Get-VpnConnection -Name $Name -ErrorAction SilentlyContinue
        if ($vpn -and $vpn.ConnectionStatus -eq 'Connected') {
            Write-Host "[OK] VPN '$Name' connected successfully." -ForegroundColor Green
            return $true
        }

        Write-Host "[WARN] VPN connection status unclear. Check manually." -ForegroundColor Yellow
        return $true
    }
    catch {
        Write-Host "[ERROR] Failed to connect VPN: $_" -ForegroundColor Red
        return $false
    }
}

function Disconnect-VpnConnection {
    if (-not $VpnName) {
        $connected = Get-VpnConnection -ErrorAction SilentlyContinue |
            Where-Object { $_.ConnectionStatus -eq 'Connected' } |
            Select-Object -First 1

        if ($connected) {
            $VpnName = $connected.Name
        }
        else {
            Write-Host "[WARN] No active VPN connection found." -ForegroundColor Yellow
            return $false
        }
    }

    Write-Host "Disconnecting VPN: $VpnName..." -ForegroundColor Yellow

    try {
        rasdial /disconnect 2>&1 | Out-Null
        Start-Sleep -Seconds 1
        Write-Host "[OK] VPN disconnected." -ForegroundColor Green
        return $true
    }
    catch {
        Write-Host "[ERROR] Failed to disconnect: $_" -ForegroundColor Red
        return $false
    }
}

function Get-VpnLogs {
    Write-Host "Recent VPN-related events:" -ForegroundColor Yellow
    Write-Host ""

    # Check network profile events
    $events = Get-WinEvent -LogName 'Microsoft-Windows-NetworkProfile/Operational' -MaxEvents $LogCount -ErrorAction SilentlyContinue |
        Where-Object { $_.Message -match 'VPN|tunnel|dial' } |
        Sort-Object TimeCreated -Descending

    if ($events) {
        $events | ForEach-Object {
            $color = if ($_.Message -match 'Connected') { 'Green' }
                     elseif ($_.Message -match 'Disconnected') { 'Red' }
                     else { 'White' }
            Write-Host ("[{0}] {1}" -f $_.TimeCreated.ToString('yyyy-MM-dd HH:mm:ss'), $_.Message) -ForegroundColor $color
        }
    }
    else {
        # Fallback: check RasClient events
        $rasEvents = Get-WinEvent -LogName 'Microsoft-Windows-RasClient/Operational' -MaxEvents $LogCount -ErrorAction SilentlyContinue |
            Sort-Object TimeCreated -Descending

        if ($rasEvents) {
            $rasEvents | ForEach-Object {
                Write-Host ("[{0}] {1}" -f $_.TimeCreated.ToString('yyyy-MM-dd HH:mm:ss'), $_.Message)
            }
        }
        else {
            Write-Host "No VPN log entries found." -ForegroundColor DarkGray
            Write-Host "Try: Get-WinEvent -ListLog * | Where-Object { `$_.RecordCount -gt 0 }" -ForegroundColor Yellow
        }
    }
}

# --- Main Execution ---

Write-Host ""
Write-Host "========================================" -ForegroundColor Red
Write-Host "  RedWolf VPN Checker" -ForegroundColor White
Write-Host "========================================" -ForegroundColor Red
Write-Host ""

switch ($Action) {
    'status' {
        $status = Get-VpnStatus

        if ($status.Connected) {
            Write-Host "  Status:        " -NoNewline -ForegroundColor White
            Write-Host "CONNECTED" -ForegroundColor Green
            Write-Host "  VPN Name:      $status.VpnName" -ForegroundColor Cyan
            Write-Host "  VPN Type:      $status.VpnType" -ForegroundColor Cyan
            Write-Host "  Virtual IP:    $($status.VirtualIP ?? 'N/A')" -ForegroundColor Cyan
            Write-Host "  Server:        $($status.ServerAddress ?? 'N/A')" -ForegroundColor Cyan

            if ($status.BytesIn) {
                Write-Host "  Received:      $(Format-Bytes $status.BytesIn)" -ForegroundColor Cyan
            }
            if ($status.BytesOut) {
                Write-Host "  Sent:          $(Format-Bytes $status.BytesOut)" -ForegroundColor Cyan
            }
        }
        else {
            Write-Host "  Status:        " -NoNewline -ForegroundColor White
            Write-Host "DISCONNECTED" -ForegroundColor Red
            Write-Host "  VPN Type:      None" -ForegroundColor DarkGray
        }
    }

    'reconnect' {
        Disconnect-VpnConnection | Out-Null
        Start-Sleep -Seconds 1
        Connect-VpnConnection -Name $VpnName
    }

    'disconnect' {
        Disconnect-VpnConnection
    }

    'logs' {
        Get-VpnLogs
    }
}

Write-Host ""
