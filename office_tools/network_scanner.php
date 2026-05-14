<?php
/**
 * Network Scanner - Scan IP ranges for live hosts, open ports, and hostnames
 * RedWolf IT Ops Suite
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/AuthManager.php';
require_once __DIR__ . '/includes/NetworkUtils.php';

use RedWolf\OfficeTools\AuthManager;
use RedWolf\OfficeTools\NetworkUtils;

AuthManager::init();

// Handle AJAX scan requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    AuthManager::requireAuth();
    header('Content-Type: application/json');

    $action = $_GET['action'];

    if ($action === 'scan_host') {
        AuthManager::requireCsrf();
        $ip = $_POST['ip'] ?? '';
        if (!NetworkUtils::validateIp($ip) || !NetworkUtils::isPrivateIp($ip)) {
            echo json_encode(['error' => 'Invalid or non-private IP address']);
            exit;
        }

        $ping = NetworkUtils::pingHost($ip, 1000);
        $ports = NetworkUtils::scanPorts($ip, [22, 80, 443, 3389, 5900], 1);
        $hostname = $ping['reachable'] ? NetworkUtils::getHostname($ip) : 'N/A';
        $portLabels = array_map([NetworkUtils::class, 'portServiceName'], $ports);

        echo json_encode([
            'ip'        => $ip,
            'reachable' => $ping['reachable'],
            'hostname'  => $hostname,
            'ports'     => $portLabels,
            'time_ms'   => $ping['time_ms'],
            'error'     => $ping['error'],
        ]);
        exit;
    }

    if ($action === 'start_scan') {
        AuthManager::requireCsrf();
        $startIp = $_POST['start_ip'] ?? '192.168.1.1';
        $endIp = $_POST['end_ip'] ?? '192.168.1.254';

        if (!NetworkUtils::validateIpRange($startIp, $endIp)) {
            echo json_encode(['error' => 'Invalid IP range. Only private ranges are allowed.']);
            exit;
        }

        $ips = NetworkUtils::ipRange($startIp, $endIp);
        if (empty($ips)) {
            echo json_encode(['error' => 'IP range is empty or too large (max 512 hosts).']);
            exit;
        }

        // Log scan start to audit
        try {
            $dbPath = dirname(__DIR__) . '/sql/redwolf.db';
            if (file_exists($dbPath)) {
                $db = new SQLite3($dbPath);
                $stmt = $db->prepare(
                    'INSERT INTO audit_log (timestamp, user, action, details, ip_address)
                     VALUES (datetime("now"), ?, ?, ?, ?)'
                );
                $stmt->bindValue(1, AuthManager::currentUser() ?? 'unknown', SQLITE3_TEXT);
                $stmt->bindValue(2, 'network_scan', SQLITE3_TEXT);
                $stmt->bindValue(3, "Scan started: $startIp - $endIp (" . count($ips) . " hosts)", SQLITE3_TEXT);
                $stmt->bindValue(4, $_SERVER['REMOTE_ADDR'] ?? 'cli', SQLITE3_TEXT);
                $stmt->execute();
                $db->close();
            }
        } catch (Throwable $e) {
            // Non-critical
        }

        echo json_encode(['success' => true, 'total' => count($ips), 'start' => $startIp, 'end' => $endIp]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    AuthManager::requireAuth();
    $results = json_decode($_GET['data'] ?? '[]', true);
    if (empty($results)) {
        echo 'No data to export.';
        exit;
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="network_scan_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['IP Address', 'Status', 'Hostname', 'Open Ports', 'Response Time (ms)']);
    foreach ($results as $row) {
        fputcsv($out, [
            $row['ip'],
            $row['reachable'] ? 'Online' : 'Offline',
            $row['hostname'] ?? 'N/A',
            implode(', ', $row['ports'] ?? []),
            $row['time_ms'] ?? 'N/A',
        ]);
    }
    fclose($out);
    exit;
}

// Show login page if not authenticated
if (!AuthManager::checkAuth()) {
    AuthManager::renderLoginPage('/office_tools/network_scanner.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Scanner - RedWolf IT Ops</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .scanner-card { box-shadow: 0 0.25rem 0.75rem rgba(0,0,0,.1); }
        .scan-row { font-size: 0.9rem; }
        .badge-online { background-color: #198754; }
        .badge-offline { background-color: #6c757d; }
        #progress-bar { transition: width 0.2s ease; }
        .host-count { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2><i class="bi bi-wifi"></i> Network Scanner</h2>
                <p class="text-muted">Scan your local network for active hosts, open ports, and hostnames.</p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card scanner-card">
                    <div class="card-header bg-dark text-white">
                        <i class="bi bi-gear"></i> Scan Configuration
                    </div>
                    <div class="card-body">
                        <form id="scan-form">
                            <?php echo AuthManager::csrfField(); ?>
                            <div class="mb-3">
                                <label for="start_ip" class="form-label">Start IP</label>
                                <input type="text" class="form-control" id="start_ip" name="start_ip"
                                       value="192.168.1.1" placeholder="192.168.1.1" required
                                       pattern="^(\d{1,3}\.){3}\d{1,3}$">
                            </div>
                            <div class="mb-3">
                                <label for="end_ip" class="form-label">End IP</label>
                                <input type="text" class="form-control" id="end_ip" name="end_ip"
                                       value="192.168.1.254" placeholder="192.168.1.254" required
                                       pattern="^(\d{1,3}\.){3}\d{1,3}$">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Scan Ports</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="22" id="port22" checked disabled>
                                    <label class="form-check-label" for="port22">SSH (22)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="80" id="port80" checked disabled>
                                    <label class="form-check-label" for="port80">HTTP (80)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="443" id="port443" checked disabled>
                                    <label class="form-check-label" for="port443">HTTPS (443)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="3389" id="port3389" checked disabled>
                                    <label class="form-check-label" for="port3389">RDP (3389)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="5900" id="port5900" checked disabled>
                                    <label class="form-check-label" for="port5900">VNC (5900)</label>
                                </div>
                            </div>
                            <button type="button" id="btn-start" class="btn btn-danger w-100">
                                <i class="bi bi-search"></i> Start Scan
                            </button>
                            <button type="button" id="btn-stop" class="btn btn-secondary w-100 mt-2 d-none">
                                <i class="bi bi-stop-circle"></i> Stop Scan
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card scanner-card">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-ul"></i> Scan Results</span>
                        <div>
                            <span class="badge bg-light text-dark host-count" id="host-count">0 hosts found</span>
                            <button type="button" id="btn-export" class="btn btn-sm btn-outline-light ms-2 d-none">
                                <i class="bi bi-download"></i> Export CSV
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- Progress bar -->
                        <div id="progress-container" class="d-none">
                            <div class="progress" style="height: 4px; border-radius: 0;">
                                <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-danger"
                                     role="progressbar" style="width: 0%"></div>
                            </div>
                            <div class="text-center text-muted py-1" style="font-size: 0.8rem;">
                                Scanning <span id="progress-ip">...</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>IP Address</th>
                                        <th>Status</th>
                                        <th>Hostname</th>
                                        <th>Open Ports</th>
                                        <th>Response</th>
                                    </tr>
                                </thead>
                                <tbody id="results-body">
                                    <tr id="empty-row">
                                        <td colspan="5" class="text-center text-muted py-5">
                                            <i class="bi bi-wifi-off" style="font-size: 2rem;"></i>
                                            <p class="mt-2">No scan results yet. Configure the range and start scanning.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        let scanning = false;
        let abortController = null;
        let scanResults = [];
        let onlineCount = 0;

        const btnStart = document.getElementById('btn-start');
        const btnStop = document.getElementById('btn-stop');
        const btnExport = document.getElementById('btn-export');
        const resultsBody = document.getElementById('results-body');
        const progressContainer = document.getElementById('progress-container');
        const progressBar = document.getElementById('progress-bar');
        const progressIp = document.getElementById('progress-ip');
        const hostCount = document.getElementById('host-count');

        async function scanHost(ip) {
            if (!scanning) return null;

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('ip', ip);

            try {
                const resp = await fetch('?action=scan_host', { method: 'POST', body: formData });
                if (!resp.ok) return null;
                return await resp.json();
            } catch (e) {
                return null;
            }
        }

        function addResultRow(result) {
            if (document.getElementById('empty-row')) {
                document.getElementById('empty-row').remove();
            }

            const statusBadge = result.reachable
                ? '<span class="badge badge-online">Online</span>'
                : '<span class="badge badge-offline">Offline</span>';

            const ports = result.ports && result.ports.length > 0
                ? result.ports.map(p => `<span class="badge bg-info text-dark me-1">${p}</span>`).join('')
                : '<span class="text-muted">-</span>';

            const time = result.time_ms != null ? result.time_ms + ' ms' : '-';

            const row = document.createElement('tr');
            row.className = 'scan-row';
            if (result.reachable) row.classList.add('table-success');
            row.innerHTML = `
                <td><code>${result.ip}</code></td>
                <td>${statusBadge}</td>
                <td>${result.hostname || 'N/A'}</td>
                <td>${ports}</td>
                <td>${time}</td>
            `;
            resultsBody.appendChild(row);

            if (result.reachable) {
                onlineCount++;
                hostCount.textContent = onlineCount + ' host' + (onlineCount !== 1 ? 's' : '') + ' found';
            }
        }

        async function startScan() {
            const startIp = document.getElementById('start_ip').value.trim();
            const endIp = document.getElementById('end_ip').value.trim();

            if (!startIp || !endIp) {
                alert('Please enter both start and end IP addresses.');
                return;
            }

            // Validate format
            const ipRegex = /^(\d{1,3}\.){3}\d{1,3}$/;
            if (!ipRegex.test(startIp) || !ipRegex.test(endIp)) {
                alert('Invalid IP address format.');
                return;
            }

            // Clear previous results
            resultsBody.innerHTML = '';
            scanResults = [];
            onlineCount = 0;
            hostCount.textContent = '0 hosts found';
            btnExport.classList.add('d-none');
            progressContainer.classList.remove('d-none');

            // Notify server of scan start
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('start_ip', startIp);
            formData.append('end_ip', endIp);

            try {
                const resp = await fetch('?action=start_scan', { method: 'POST', body: formData });
                const data = await resp.json();
                if (!data.success) {
                    alert(data.error || 'Failed to start scan.');
                    progressContainer.classList.add('d-none');
                    return;
                }
            } catch (e) {
                alert('Error communicating with server: ' + e.message);
                progressContainer.classList.add('d-none');
                return;
            }

            scanning = true;
            btnStart.classList.add('d-none');
            btnStop.classList.remove('d-none');

            // Generate IP list client-side
            const start = startIp.split('.').map(Number);
            const end = endIp.split('.').map(Number);
            const startLong = (start[0] << 24) + (start[1] << 16) + (start[2] << 8) + start[3];
            const endLong = (end[0] << 24) + (end[1] << 16) + (end[2] << 8) + end[3];
            const total = endLong - startLong + 1;

            // Scan up to 6 hosts in parallel batches
            const batchSize = 6;
            for (let i = 0; i < total && scanning; i += batchSize) {
                const batch = [];
                for (let j = i; j < Math.min(i + batchSize, total); j++) {
                    const n = startLong + j;
                    batch.push([
                        (n >>> 24) & 0xFF,
                        (n >>> 16) & 0xFF,
                        (n >>> 8) & 0xFF,
                        n & 0xFF
                    ].join('.'));
                }

                progressIp.textContent = batch[batch.length - 1];
                progressBar.style.width = Math.round(((i + batch.length) / total) * 100) + '%';

                const promises = batch.map(ip => scanHost(ip));
                const results = await Promise.all(promises);

                for (const result of results) {
                    if (result && !result.error) {
                        addResultRow(result);
                        if (result.reachable) scanResults.push(result);
                    }
                }
            }

            scanning = false;
            btnStart.classList.remove('d-none');
            btnStop.classList.add('d-none');
            progressContainer.classList.add('d-none');
            progressBar.style.width = '100%';

            if (scanResults.length > 0) {
                btnExport.classList.remove('d-none');
            }
        }

        function stopScan() {
            scanning = false;
            btnStart.classList.remove('d-none');
            btnStop.classList.add('d-none');
            progressContainer.classList.add('d-none');
        }

        function exportCsv() {
            const data = encodeURIComponent(JSON.stringify(scanResults));
            window.location.href = '?export=csv&data=' + data;
        }

        btnStart.addEventListener('click', startScan);
        btnStop.addEventListener('click', stopScan);
        btnExport.addEventListener('click', exportCsv);
    })();
    </script>
</body>
</html>
