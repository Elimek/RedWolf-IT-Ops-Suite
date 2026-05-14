<?php
/**
 * VPN Status Monitor - Check and manage VPN connections
 * RedWolf IT Ops Suite
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/AuthManager.php';

use RedWolf\OfficeTools\AuthManager;

AuthManager::init();

// Handle API actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    AuthManager::requireAuth();
    header('Content-Type: application/json');
    AuthManager::requireCsrf();

    $action = $_GET['action'];

    if ($action === 'status') {
        echo json_encode(getVpnStatus());
        exit;
    }

    if ($action === 'reconnect') {
        $result = reconnectVpn();
        auditLog('vpn_reconnect', 'VPN reconnect initiated');
        echo json_encode($result);
        exit;
    }

    if ($action === 'disconnect') {
        $result = disconnectVpn();
        auditLog('vpn_disconnect', 'VPN disconnect initiated');
        echo json_encode($result);
        exit;
    }

    if ($action === 'logs') {
        $logs = getVpnLogs();
        echo json_encode(['logs' => $logs]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// Show login if not authenticated
if (!AuthManager::checkAuth()) {
    AuthManager::renderLoginPage('/office_tools/vpn_status.php');
    exit;
}

/**
 * Gather VPN status information
 */
function getVpnStatus(): array
{
    $status = [
        'connected'   => false,
        'type'        => 'None',
        'virtual_ip'  => null,
        'public_ip'   => null,
        'duration'    => null,
        'bytes_in'    => null,
        'bytes_out'   => null,
        'details'     => [],
    ];

    $isWindows = PHP_OS_FAMILY === 'Windows';

    if ($isWindows) {
        // Check VPN adapters on Windows
        $adapters = @shell_exec('powershell -Command "Get-NetAdapter | Where-Object { $_.InterfaceDescription -match \'VPN|TAP|WireGuard|OpenVPN\' -or $_.Name -match \'VPN\' } | ConvertTo-Json" 2>NUL');
        if ($adapters) {
            $adapterList = json_decode($adapters, true);
            if (!isset($adapterList['Name'])) {
                $adapterList = [$adapterList];
            }
            foreach ($adapterList as $adapter) {
                if (($adapter['Status'] ?? '') === 'Up') {
                    $status['connected'] = true;
                    $status['type'] = $adapter['InterfaceDescription'] ?? 'VPN';
                    $status['virtual_ip'] = $adapter['InterfaceDescription'] ?? null;
                    break;
                }
            }
        }

        // Check rasdial for active VPN connections
        $rasdial = @shell_exec('rasdial 2>NUL');
        if ($rasdial && trim($rasdial) !== '') {
            $status['connected'] = true;
            $status['details'][] = ['label' => 'Connection', 'value' => trim($rasdial)];
        }
    } else {
        // Linux/Unix checks

        // Check OpenVPN
        $openvpnPid = file_exists('/var/run/openvpn.pid');
        $tun0 = @shell_exec('ip link show tun0 2>/dev/null');
        if ($openvpnPid || $tun0) {
            $status['connected'] = true;
            $status['type'] = 'OpenVPN';

            $ipConfig = @shell_exec('ip addr show tun0 2>/dev/null');
            if ($ipConfig && preg_match('/inet\s+(\d+\.\d+\.\d+\.\d+)/', $ipConfig, $m)) {
                $status['virtual_ip'] = $m[1];
            }

            $status['details'][] = ['label' => 'Interface', 'value' => 'tun0'];
            $status['details'][] = ['label' => 'PID File', 'value' => $openvpnPid ? '/var/run/openvpn.pid exists' : 'Not found'];
        }

        // Check WireGuard
        $wgShow = @shell_exec('wg show 2>/dev/null');
        if ($wgShow && !str_contains($wgShow, 'No WireGuard interfaces')) {
            $status['connected'] = true;
            $status['type'] = 'WireGuard';

            if (preg_match('/interface:\s+(\S+)/', $wgShow, $m)) {
                $status['details'][] = ['label' => 'Interface', 'value' => $m[1]];
            }

            if (preg_match('/public key:\s+(\S+)/', $wgShow, $m)) {
                $status['details'][] = ['label' => 'Public Key', 'value' => substr($m[1], 0, 16) . '...'];
            }

            if (preg_match('/endpoint:\s+(\S+)/', $wgShow, $m)) {
                $status['details'][] = ['label' => 'Endpoint', 'value' => $m[1]];
            }

            // Parse transfer stats
            if (preg_match('/transfer:\s+([\d.]+\s+\w+).*?,\s+([\d.]+\s+\w+)/s', $wgShow, $m)) {
                $status['bytes_in'] = $m[1] ?? null;
                $status['bytes_out'] = $m[2] ?? null;
            }

            // Get VPN IP from wg show
            $wgIp = @shell_exec('wg show all allowed-ips 2>/dev/null');
            if ($wgIp && preg_match('/(\d+\.\d+\.\d+\.\d+\/\d+)/', $wgIp, $m)) {
                $status['virtual_ip'] = explode('/', $m[1])[0];
            }
        }

        // Check connection duration (interface up time)
        $interface = ($status['type'] === 'OpenVPN') ? 'tun0' : (($status['type'] === 'WireGuard') ? 'wg0' : '');
        if ($interface) {
            $ifstat = @shell_exec("cat /sys/class/net/$interface/operstate 2>/dev/null");
            if (trim($ifstat) === 'up') {
                $status['details'][] = ['label' => 'State', 'value' => 'Up'];
            }
        }
    }

    // Get public IP for comparison
    $publicIp = @file_get_contents('https://api.ipify.org?format=text', false, stream_context_create(['http' => ['timeout' => 3]]));
    if ($publicIp) {
        $status['public_ip'] = trim($publicIp);
    }

    // Check environment variables for VPN hints
    $vpnEnvVars = ['VPN_ADDR', 'VPN_GATEWAY', 'OPENVPN_CONF', 'WG_CONF'];
    foreach ($vpnEnvVars as $var) {
        $val = getenv($var);
        if ($val) {
            $status['details'][] = ['label' => "Env:$var", 'value' => $val];
        }
    }

    return $status;
}

/**
 * Attempt to reconnect VPN
 */
function reconnectVpn(): array
{
    $isWindows = PHP_OS_FAMILY === 'Windows';

    if ($isWindows) {
        // Windows: try rasdial
        $output = @shell_exec('rasdial 2>&1');
        $currentConnection = trim($output ?? '');
        if ($currentConnection && !str_contains($currentConnection, 'No connections')) {
            @shell_exec('rasdial /disconnect 2>NUL');
            sleep(1);
        }
        // Attempt reconnect - user would need to configure this
        return ['success' => false, 'message' => 'Windows VPN reconnection requires manual configuration. Please use the Windows VPN settings panel or contact IT.'];
    }

    // Linux: try restarting services
    $commands = [
        ['service' => 'openvpn', 'command' => 'sudo systemctl restart openvpn 2>&1'],
        ['service' => 'openvpn-client', 'command' => 'sudo systemctl restart openvpn-client@* 2>&1'],
        ['service' => 'wireguard', 'command' => 'sudo systemctl restart wg-quick@wg0 2>&1'],
    ];

    $results = [];
    foreach ($commands as $cmd) {
        $output = @shell_exec($cmd['command']);
        if ($output !== null) {
            $results[] = $cmd['service'] . ': ' . trim($output);
        }
    }

    return [
        'success' => true,
        'message' => 'VPN reconnection command sent.',
        'details' => $results,
    ];
}

/**
 * Disconnect VPN
 */
function disconnectVpn(): array
{
    $isWindows = PHP_OS_FAMILY === 'Windows';

    if ($isWindows) {
        $output = @shell_exec('rasdial /disconnect 2>&1');
        return ['success' => true, 'message' => 'VPN disconnect command sent.', 'details' => trim($output ?? '')];
    }

    // Linux
    $results = [];
    $output = @shell_exec('sudo systemctl stop openvpn 2>&1');
    if ($output !== null) $results[] = 'OpenVPN: ' . trim($output);

    $output = @shell_exec('sudo systemctl stop openvpn-client@* 2>&1');
    if ($output !== null) $results[] = 'OpenVPN-client: ' . trim($output);

    $output = @shell_exec('sudo wg-quick down wg0 2>&1');
    if ($output !== null) $results[] = 'WireGuard: ' . trim($output);

    return ['success' => true, 'message' => 'VPN disconnect commands sent.', 'details' => $results];
}

/**
 * Get recent VPN logs
 */
function getVpnLogs(): string
{
    $isWindows = PHP_OS_FAMILY === 'Windows';

    if ($isWindows) {
        // Try to get Windows VPN event log
        $output = @shell_exec('powershell -Command "Get-WinEvent -LogName \'Microsoft-Windows-NetworkProfile/Operational\' -MaxEvents 50 2>$null | Format-List TimeCreated, Message" 2>NUL');
        if ($output) {
            return trim($output);
        }
        return 'No VPN logs available on this Windows system.';
    }

    // Linux - check common log locations
    $logFiles = [
        '/var/log/openvpn.log',
        '/var/log/openvpn/openvpn.log',
        '/var/log/syslog',
        '/var/log/messages',
    ];

    foreach ($logFiles as $file) {
        if (is_readable($file)) {
            $lines = @shell_exec("tail -50 $file 2>/dev/null | grep -i -E 'vpn|openvpn|wireguard|tun|wg' 2>/dev/null");
            if ($lines) {
                return trim($lines);
            }
        }
    }

    return 'No VPN log entries found in standard log locations.';
}

/**
 * Write audit log entry
 */
function auditLog(string $action, string $details): void
{
    try {
        $dbPath = dirname(__DIR__) . '/sql/redwolf.db';
        if (file_exists($dbPath)) {
            $db = new SQLite3($dbPath);
            $stmt = $db->prepare(
                'INSERT INTO audit_log (timestamp, user, action, details, ip_address)
                 VALUES (datetime("now"), ?, ?, ?, ?)'
            );
            $stmt->bindValue(1, AuthManager::currentUser() ?? 'unknown', SQLITE3_TEXT);
            $stmt->bindValue(2, $action, SQLITE3_TEXT);
            $stmt->bindValue(3, $details, SQLITE3_TEXT);
            $stmt->bindValue(4, $_SERVER['REMOTE_ADDR'] ?? 'cli', SQLITE3_TEXT);
            $stmt->execute();
            $db->close();
        }
    } catch (Throwable $e) {
        // Non-critical
    }
}

$csrfToken = AuthManager::csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Status - RedWolf IT Ops</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .status-card { box-shadow: 0 0.25rem 0.75rem rgba(0,0,0,.1); }
        .status-indicator { width: 20px; height: 20px; border-radius: 50%; display: inline-block; }
        .status-connected { background-color: #198754; box-shadow: 0 0 8px rgba(25,135,84,0.5); }
        .status-disconnected { background-color: #dc3545; box-shadow: 0 0 8px rgba(220,53,69,0.5); }
        .log-box { font-family: 'Courier New', monospace; font-size: 0.8rem; background-color: #212529; color: #f8f9fa; padding: 1rem; border-radius: 0.375rem; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
        .detail-row { border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .pulse { animation: pulse 2s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2><i class="bi bi-shield-lock"></i> VPN Status Monitor</h2>
                <p class="text-muted">Check and manage your VPN connection status.</p>
            </div>
        </div>

        <!-- Status Overview -->
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card status-card">
                    <div class="card-header bg-dark text-white">
                        <i class="bi bi-info-circle"></i> Connection Status
                    </div>
                    <div class="card-body text-center">
                        <div id="status-indicator" class="mb-3">
                            <span class="status-indicator status-disconnected pulse"></span>
                        </div>
                        <h4 id="status-text">Checking...</h4>
                        <p id="vpn-type" class="text-muted">Detecting VPN type</p>
                        <hr>
                        <div class="text-start">
                            <div class="mb-2">
                                <strong>VPN IP:</strong>
                                <span id="vpn-ip" class="text-muted">N/A</span>
                            </div>
                            <div class="mb-2">
                                <strong>Public IP:</strong>
                                <span id="public-ip" class="text-muted">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card status-card h-100">
                    <div class="card-header bg-dark text-white">
                        <i class="bi bi-activity"></i> Traffic Statistics
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span><i class="bi bi-arrow-down-circle text-success"></i> Received</span>
                                <span id="bytes-in" class="fw-bold">N/A</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span><i class="bi bi-arrow-up-circle text-primary"></i> Sent</span>
                                <span id="bytes-out" class="fw-bold">N/A</span>
                            </div>
                        </div>
                        <hr>
                        <div id="details-list">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card status-card h-100">
                    <div class="card-header bg-dark text-white">
                        <i class="bi bi-gear"></i> Actions
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <button type="button" class="btn btn-success mb-2" onclick="vpnAction('reconnect')">
                            <i class="bi bi-arrow-repeat"></i> Reconnect VPN
                        </button>
                        <button type="button" class="btn btn-warning mb-2" onclick="vpnAction('disconnect')">
                            <i class="bi bi-x-circle"></i> Disconnect VPN
                        </button>
                        <button type="button" class="btn btn-info text-white" onclick="viewLogs()">
                            <i class="bi bi-terminal"></i> View Logs
                        </button>
                        <button type="button" class="btn btn-outline-secondary mt-2" onclick="refreshStatus()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- VPN Logs Panel (hidden by default) -->
        <div class="row mt-3 d-none" id="log-panel">
            <div class="col-md-12">
                <div class="card status-card">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-terminal"></i> VPN Logs</span>
                        <button type="button" class="btn btn-sm btn-outline-light" onclick="document.getElementById('log-panel').classList.add('d-none')">
                            <i class="bi bi-x-lg"></i> Close
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="log-box" id="log-content">Loading logs...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const csrfToken = document.getElementById('csrf_token').value;

    async function refreshStatus() {
        try {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);

            const resp = await fetch('?action=status', { method: 'POST', body: formData });
            if (!resp.ok) throw new Error('Server error');
            const data = await resp.json();

            updateUI(data);
        } catch (e) {
            document.getElementById('status-text').textContent = 'Error';
            document.getElementById('vpn-type').textContent = 'Could not fetch VPN status';
        }
    }

    function updateUI(data) {
        // Status indicator
        const indicator = document.querySelector('.status-indicator');
        indicator.className = 'status-indicator ' + (data.connected ? 'status-connected' : 'status-disconnected');
        if (data.connected) indicator.classList.remove('pulse');

        document.getElementById('status-text').textContent = data.connected ? 'Connected' : 'Disconnected';
        document.getElementById('vpn-type').textContent = 'VPN Type: ' + (data.type || 'None');
        document.getElementById('vpn-ip').textContent = data.virtual_ip || 'N/A';
        document.getElementById('public-ip').textContent = data.public_ip || 'N/A';
        document.getElementById('bytes-in').textContent = data.bytes_in || 'N/A';
        document.getElementById('bytes-out').textContent = data.bytes_out || 'N/A';

        // Details list
        const detailsList = document.getElementById('details-list');
        if (data.details && data.details.length > 0) {
            detailsList.innerHTML = data.details.map(d =>
                `<div class="detail-row py-2">
                    <small class="text-muted">${d.label}</small><br>
                    <span class="fw-medium">${d.value}</span>
                </div>`
            ).join('');
        } else {
            detailsList.innerHTML = '<p class="text-muted small">No additional details available.</p>';
        }
    }

    async function vpnAction(action) {
        const confirmMsg = action === 'reconnect'
            ? 'Reconnect the VPN connection?'
            : 'Disconnect the VPN connection?';

        if (!confirm(confirmMsg)) return;

        try {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);

            const resp = await fetch('?action=' + action, { method: 'POST', body: formData });
            const data = await resp.json();

            alert(data.message || (data.success ? 'Action completed.' : 'Action failed.'));
            if (data.success) refreshStatus();
        } catch (e) {
            alert('Error: ' + e.message);
        }
    }

    async function viewLogs() {
        const logPanel = document.getElementById('log-panel');
        const logContent = document.getElementById('log-content');
        logPanel.classList.remove('d-none');
        logContent.textContent = 'Loading logs...';

        try {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);

            const resp = await fetch('?action=logs', { method: 'POST', body: formData });
            const data = await resp.json();
            logContent.textContent = data.logs || 'No log entries found.';
        } catch (e) {
            logContent.textContent = 'Error loading logs: ' + e.message;
        }
    }

    // Auto-refresh status on page load
    refreshStatus();
    </script>
</body>
</html>
