<?php
declare(strict_types=1);

/**
 * RedWolf IT Ops Suite - Fault Simulator
 * Admin-only control panel for simulating infrastructure failures
 * Used for testing alerting pipelines and incident response procedures
 */

session_start();

require_once __DIR__ . '/includes/MetricsReader.php';
require_once __DIR__ . '/includes/AlertEngine.php';

use RedWolf\Monitoring\MetricsReader;
use RedWolf\Monitoring\AlertEngine;

// ============================================================
// Configuration
// ============================================================
$envFile = dirname(__DIR__) . '/.env';
$config = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }
}

$adminUser = $config['ADMIN_USER'] ?? 'admin';
$adminPass = $config['ADMIN_PASS'] ?? 'redwolf2024';

// ============================================================
// Database connection
// ============================================================
function getDb(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $port = (int)(getenv('DB_PORT') ?: 3306);
    $name = getenv('DB_NAME') ?: 'redwolf_ops';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

// ============================================================
// Authentication
// ============================================================
$authenticated = false;
$loginError = '';

if (isset($_POST['login_submit'])) {
    if (($_POST['username'] ?? '') === $adminUser && ($_POST['password'] ?? '') === $adminPass) {
        $_SESSION['fault_admin'] = true;
        $authenticated = true;
    } else {
        $loginError = 'Invalid credentials';
    }
} elseif (isset($_SESSION['fault_admin']) && $_SESSION['fault_admin'] === true) {
    $authenticated = true;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['fault_admin']);
    header('Location: fault_simulator.php');
    exit;
}

// ============================================================
// Fault simulation actions
// ============================================================
$actionResult = '';
$actionType = ''; // success, warning, danger

if ($authenticated && isset($_POST['fault_action'])) {
    $action = $_POST['fault_action'];
    $db = getDb();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    try {
        $stmt = $db->prepare(
            "INSERT INTO audit_log (user_id, action, details, ip_address)
             VALUES (:user, :action, :details, :ip)"
        );

        switch ($action) {
            case 'stress_cpu':
                // Spawn CPU stress process in background
                $cmd = "nohup bash -c 'dd if=/dev/zero of=/dev/null bs=1M' > /dev/null 2>&1 & echo $!";
                $pid = shell_exec($cmd);
                $actionResult = "CPU stress process started (PID: " . trim($pid ?? 'unknown') . ")";
                $actionType = 'danger';
                $stmt->execute([':user' => $adminUser, ':action' => 'SIMULATE_HIGH_CPU', ':details' => $actionResult, ':ip' => $ip]);
                break;

            case 'stress_memory':
                // Allocate memory using a background process
                $cmd = "nohup php -r '"
                    . "\$arr = []; "
                    . "for (\$i = 0; \$i < 50000; \$i++) { "
                    . "\$arr[] = str_repeat(\"X\", 10240); "
                    . "} "
                    . "sleep(600);' > /dev/null 2>&1 & echo $!";
                $pid = shell_exec($cmd);
                $actionResult = "Memory leak simulation started (PID: " . trim($pid ?? 'unknown') . ")";
                $actionType = 'danger';
                $stmt->execute([':user' => $adminUser, ':action' => 'SIMULATE_MEMORY_LEAK', ':details' => $actionResult, ':ip' => $ip]);
                break;

            case 'fill_disk':
                // Write a 500MB temp file
                $tempFile = '/tmp/redwolf_disk_fill_' . getmypid() . '.dat';
                $cmd = "dd if=/dev/zero of={$tempFile} bs=1M count=500 2>/dev/null & echo $!";
                $pid = shell_exec($cmd);
                $actionResult = "Disk fill started (500MB -> {$tempFile}, PID: " . trim($pid ?? 'unknown') . ")";
                $actionType = 'warning';
                $stmt->execute([':user' => $adminUser, ':action' => 'FILL_DISK', ':details' => $actionResult, ':ip' => $ip]);
                break;

            case 'stop_nginx':
                $output = shell_exec('sudo systemctl stop nginx 2>&1') ?? '';
                if (empty($output)) {
                    $actionResult = 'Nginx service stopped successfully';
                    $actionType = 'danger';
                    $stmt->execute([':user' => $adminUser, ':action' => 'STOP_NGINX', ':details' => $actionResult, ':ip' => $ip]);
                } else {
                    $actionResult = 'Failed to stop Nginx: ' . $output;
                    $actionType = 'warning';
                    $stmt->execute([':user' => $adminUser, ':action' => 'STOP_NGINX_ATTEMPT', ':details' => $actionResult, ':ip' => $ip]);
                }
                break;

            case 'restore_all':
                $results = [];

                // Kill stress CPU processes (dd processes writing to /dev/null)
                $killCpu = shell_exec("pkill -f 'dd if=/dev/zero of=/dev/null' 2>&1");
                $results[] = 'CPU stress: killed';

                // Kill PHP memory leak processes
                $killMem = shell_exec("pkill -f 'str_repeat' 2>&1");
                $results[] = 'Memory leak: killed';

                // Remove temp disk fill files
                $rmFiles = shell_exec("rm -f /tmp/redwolf_disk_fill_*.dat 2>&1");
                $results[] = 'Disk fill files: removed';

                // Restart Nginx
                $restartNginx = shell_exec('sudo systemctl start nginx 2>&1');
                $results[] = 'Nginx: restarted';

                $actionResult = 'All services restored: ' . implode(', ', $results);
                $actionType = 'success';
                $stmt->execute([':user' => $adminUser, ':action' => 'RESTORE_ALL', ':details' => $actionResult, ':ip' => $ip]);
                break;

            default:
                $actionResult = 'Unknown action';
                $actionType = 'warning';
        }
    } catch (\Throwable $e) {
        $actionResult = 'Error: ' . $e->getMessage();
        $actionType = 'danger';
    }
}

// ============================================================
// Real-time status data (for AJAX)
// ============================================================
if ($authenticated && isset($_GET['api']) && $_GET['api'] === 'status') {
    header('Content-Type: application/json');

    // Check service statuses
    $status = [
        'timestamp' => date('Y-m-d H:i:s'),
        'nginx' => 'unknown',
        'cpu_stress' => 'none',
        'disk_fill' => 'none',
        'memory_status' => 'normal',
    ];

    // Nginx status
    $nginxCheck = shell_exec('systemctl is-active nginx 2>/dev/null');
    $status['nginx'] = trim($nginxCheck ?? 'unknown');

    // CPU stress check
    $cpuStressCheck = shell_exec("pgrep -f 'dd if=/dev/zero of=/dev/null' 2>/dev/null");
    $status['cpu_stress'] = empty($cpuStressCheck) ? 'none' : 'running';

    // Disk fill check
    $diskFillCheck = shell_exec('ls -la /tmp/redwolf_disk_fill_*.dat 2>/dev/null');
    $status['disk_fill'] = empty($diskFillCheck) ? 'none' : 'active';

    // Memory check
    $memInfo = shell_exec("awk '/MemAvailable/ {a=\$2} /MemTotal/ {t=\$2} END {printf \"%.1f\", (t-a)/t*100}' /proc/meminfo 2>/dev/null");
    $memPct = (float)(trim($memInfo ?? '0'));
    $status['memory_status'] = $memPct > 85 ? 'critical' : ($memPct > 70 ? 'warning' : 'normal');
    $status['memory_usage'] = $memPct;

    echo json_encode($status);
    exit;
}

// ============================================================
// Get audit log for display
// ============================================================
$auditLog = [];
if ($authenticated) {
    try {
        $db = getDb();
        $stmt = $db->query(
            "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 20"
        );
        $auditLog = $stmt->fetchAll();
    } catch (\PDOException $e) {
        // DB not available, continue without audit log
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RedWolf IT Ops - Fault Simulator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --rw-bg-primary: #0f1117;
            --rw-bg-secondary: #1a1d27;
            --rw-bg-card: #1e2233;
            --rw-text-primary: #e8eaed;
            --rw-text-secondary: #9aa0a6;
            --rw-accent: #4285f4;
            --rw-success: #34a853;
            --rw-warning: #fbbc04;
            --rw-danger: #ea4335;
            --rw-border: #2d3142;
            --rw-radius: 12px;
        }
        body {
            background-color: var(--rw-bg-primary);
            color: var(--rw-text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }
        .top-bar {
            background: var(--rw-bg-secondary);
            border-bottom: 1px solid var(--rw-border);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .top-bar .brand { font-size: 1.25rem; font-weight: 700; color: var(--rw-accent); }
        .nav-links a {
            color: var(--rw-text-secondary); text-decoration: none;
            font-size: 0.875rem; margin-left: 16px; transition: color 0.2s;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--rw-accent); }

        .card-dark {
            background: var(--rw-bg-card);
            border: 1px solid var(--rw-border);
            border-radius: var(--rw-radius);
            padding: 24px;
        }
        .section-title {
            font-size: 1rem; font-weight: 600; margin-bottom: 16px;
            padding-bottom: 8px; border-bottom: 2px solid var(--rw-border);
        }

        /* Status indicators */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .status-item {
            background: var(--rw-bg-secondary);
            border: 1px solid var(--rw-border);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            transition: border-color 0.3s;
        }
        .status-dot {
            display: inline-block;
            width: 12px; height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }
        .status-dot.green { background: var(--rw-success); box-shadow: 0 0 8px var(--rw-success); }
        .status-dot.yellow { background: var(--rw-warning); box-shadow: 0 0 8px var(--rw-warning); }
        .status-dot.red { background: var(--rw-danger); box-shadow: 0 0 8px var(--rw-danger); }
        .status-dot.grey { background: var(--rw-text-secondary); }
        .status-label { font-size: 0.75rem; color: var(--rw-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
        .status-value { font-size: 1rem; font-weight: 600; margin-top: 4px; }

        /* Action buttons */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .btn-fault {
            border: 1px solid;
            padding: 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            background: transparent;
        }
        .btn-fault:hover { transform: translateY(-1px); }
        .btn-cpu-stress { border-color: var(--rw-danger); color: var(--rw-danger); }
        .btn-cpu-stress:hover { background: rgba(234, 67, 53, 0.15); }
        .btn-mem-leak { border-color: #e67e22; color: #e67e22; }
        .btn-mem-leak:hover { background: rgba(230, 126, 34, 0.15); }
        .btn-disk-fill { border-color: var(--rw-warning); color: var(--rw-warning); }
        .btn-disk-fill:hover { background: rgba(251, 188, 4, 0.15); }
        .btn-nginx-stop { border-color: #e74c3c; color: #e74c3c; }
        .btn-nginx-stop:hover { background: rgba(231, 76, 60, 0.15); }
        .btn-restore { border-color: var(--rw-success); color: var(--rw-success); }
        .btn-restore:hover { background: rgba(52, 168, 83, 0.15); }

        /* Audit log */
        .audit-table {
            color: var(--rw-text-primary);
            margin-bottom: 0;
            font-size: 0.8rem;
        }
        .audit-table thead th {
            background: var(--rw-bg-secondary);
            border-bottom: 2px solid var(--rw-border);
            color: var(--rw-text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 12px;
        }
        .audit-table tbody td {
            border-bottom: 1px solid var(--rw-border);
            padding: 8px 12px;
        }

        /* Result banner */
        .result-banner {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 16px;
        }
        .result-banner.success { background: rgba(52, 168, 83, 0.15); color: var(--rw-success); }
        .result-banner.warning { background: rgba(251, 188, 4, 0.15); color: var(--rw-warning); }
        .result-banner.danger { background: rgba(234, 67, 53, 0.15); color: var(--rw-danger); }

        /* Login */
        .login-container {
            display: flex; align-items: center; justify-content: center; min-height: 60vh;
        }
        .login-form {
            background: var(--rw-bg-card); border: 1px solid var(--rw-border);
            border-radius: var(--rw-radius); padding: 40px;
            width: 100%; max-width: 400px;
        }
        .login-form .form-control {
            background: var(--rw-bg-primary); border: 1px solid var(--rw-border);
            color: var(--rw-text-primary);
        }
        .login-form .form-control:focus {
            background: var(--rw-bg-primary); border-color: var(--rw-accent);
            color: var(--rw-text-primary);
            box-shadow: 0 0 0 0.2rem rgba(66, 133, 244, 0.25);
        }
        .btn-rw {
            background: var(--rw-accent); color: #fff; border: none;
            padding: 8px 20px; border-radius: 6px; font-size: 0.875rem;
        }
        .btn-rw:hover { background: #5b9bf7; color: #fff; }

        .refresh-timer {
            font-size: 0.75rem; color: var(--rw-text-secondary);
            display: flex; align-items: center; gap: 4px;
        }

        @media (max-width: 768px) {
            .top-bar { padding: 10px 16px; }
            .nav-links { display: none; }
            .action-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div><span class="brand">RedWolf IT Ops</span></div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="alert_manager.php">Alerts</a>
            <a href="fault_simulator.php" class="active">Fault Simulator</a>
            <?php if ($authenticated): ?>
                <a href="fault_simulator.php?logout=1" style="color: var(--rw-danger);">Logout</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container-fluid px-3 px-md-4 py-4">
        <?php if (!$authenticated): ?>
        <!-- Login -->
        <div class="login-container">
            <div class="login-form text-center">
                <h4 style="margin-bottom: 24px; font-weight: 700;">Fault Simulator</h4>
                <?php if ($loginError): ?>
                    <div class="alert alert-danger py-2" style="font-size: 0.85rem;"><?= htmlspecialchars($loginError) ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="login_submit" value="1">
                    <div class="mb-3 text-start">
                        <label class="form-label" style="font-size: 0.8rem; color: var(--rw-text-secondary);">Username</label>
                        <input type="text" name="username" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label" style="font-size: 0.8rem; color: var(--rw-text-secondary);">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-rw w-100 mt-2">Sign In</button>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- Warning banner -->
        <div style="background: rgba(234, 67, 53, 0.1); border: 1px solid var(--rw-danger); border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.85rem;">
            This tool simulates real infrastructure faults. Use only in staging/testing environments.
        </div>

        <?php if ($actionResult): ?>
            <div class="result-banner <?= htmlspecialchars($actionType) ?>"><?= htmlspecialchars($actionResult) ?></div>
        <?php endif; ?>

        <!-- System Status (auto-refresh) -->
        <div class="card-dark mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="section-title" style="margin-bottom: 0; border: none; padding: 0;">System Status</div>
                <div class="refresh-timer">
                    <span class="status-dot green" id="refreshDot"></span>
                    <span id="refreshCountdown">5s</span> until refresh
                </div>
            </div>
            <div class="status-grid" id="statusGrid">
                <div class="status-item">
                    <div class="status-label">Nginx</div>
                    <div class="status-value" id="statusNginx">
                        <span class="status-dot grey"></span> Checking...
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-label">CPU Stress</div>
                    <div class="status-value" id="statusCpu">
                        <span class="status-dot grey"></span> Checking...
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-label">Disk Fill</div>
                    <div class="status-value" id="statusDisk">
                        <span class="status-dot grey"></span> Checking...
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-label">Memory</div>
                    <div class="status-value" id="statusMemory">
                        <span class="status-dot grey"></span> Checking...
                    </div>
                </div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="card-dark mb-4">
            <div class="section-title">Fault Injection Controls</div>
            <div class="action-grid">
                <form method="post" onsubmit="return confirm('Simulate high CPU load? This may impact system performance.')">
                    <input type="hidden" name="fault_action" value="stress_cpu">
                    <button type="submit" class="btn-fault btn-cpu-stress w-100">
                        Simulate High CPU
                    </button>
                </form>
                <form method="post" onsubmit="return confirm('Simulate memory leak? This may exhaust available RAM.')">
                    <input type="hidden" name="fault_action" value="stress_memory">
                    <button type="submit" class="btn-fault btn-mem-leak w-100">
                        Simulate Memory Leak
                    </button>
                </form>
                <form method="post" onsubmit="return confirm('Write 500MB to disk? This simulates disk space exhaustion.')">
                    <input type="hidden" name="fault_action" value="fill_disk">
                    <button type="submit" class="btn-fault btn-disk-fill w-100">
                        Fill Disk
                    </button>
                </form>
                <form method="post" onsubmit="return confirm('Stop Nginx? This will make the web server unavailable.')">
                    <input type="hidden" name="fault_action" value="stop_nginx">
                    <button type="submit" class="btn-fault btn-nginx-stop w-100">
                        Stop Nginx
                    </button>
                </form>
                <form method="post" style="grid-column: 1 / -1;">
                    <input type="hidden" name="fault_action" value="restore_all">
                    <button type="submit" class="btn-fault btn-restore w-100" style="max-width: 300px;">
                        Restore All Services
                    </button>
                </form>
            </div>
        </div>

        <!-- Audit Log -->
        <div class="card-dark">
            <div class="section-title">Audit Log (Recent Actions)</div>
            <div class="table-responsive">
                <table class="table audit-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($auditLog)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--rw-text-secondary); padding: 24px;">
                                No audit entries
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($auditLog as $entry): ?>
                            <tr>
                                <td><?= htmlspecialchars($entry['created_at'] ?? '') ?></td>
                                <td><?= htmlspecialchars($entry['user_id'] ?? '-') ?></td>
                                <td><code><?= htmlspecialchars($entry['action'] ?? '') ?></code></td>
                                <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= htmlspecialchars($entry['details'] ?? '-') ?>
                                </td>
                                <td><?= htmlspecialchars($entry['ip_address'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($authenticated): ?>
    <script>
        // Real-time status polling (every 5 seconds)
        let countdown = 5;

        function getStatusColor(status) {
            if (status === 'active' || status === 'running') return 'red';
            if (status === 'unknown' || status === 'inactive') return 'yellow';
            return 'green';
        }

        function updateStatus() {
            fetch('fault_simulator.php?api=status')
                .then(r => r.json())
                .then(data => {
                    // Nginx
                    const nginxEl = document.getElementById('statusNginx');
                    if (nginxEl) {
                        const color = data.nginx === 'active' ? 'green' : 'red';
                        nginxEl.innerHTML = '<span class="status-dot ' + color + '"></span> ' + data.nginx;
                    }

                    // CPU Stress
                    const cpuEl = document.getElementById('statusCpu');
                    if (cpuEl) {
                        const color = data.cpu_stress === 'running' ? 'red' : 'green';
                        cpuEl.innerHTML = '<span class="status-dot ' + color + '"></span> ' +
                            (data.cpu_stress === 'running' ? 'Running' : 'None');
                    }

                    // Disk Fill
                    const diskEl = document.getElementById('statusDisk');
                    if (diskEl) {
                        const color = data.disk_fill === 'active' ? 'red' : 'green';
                        diskEl.innerHTML = '<span class="status-dot ' + color + '"></span> ' +
                            (data.disk_fill === 'active' ? 'Active' : 'None');
                    }

                    // Memory
                    const memEl = document.getElementById('statusMemory');
                    if (memEl) {
                        const color = data.memory_status === 'critical' ? 'red' :
                            (data.memory_status === 'warning' ? 'yellow' : 'green');
                        memEl.innerHTML = '<span class="status-dot ' + color + '"></span> ' +
                            data.memory_usage + '%';
                    }

                    countdown = 5;
                })
                .catch(() => {
                    countdown = 5;
                });
        }

        // Initial load
        updateStatus();

        // Poll every 5 seconds
        setInterval(() => {
            countdown--;
            const el = document.getElementById('refreshCountdown');
            if (el) el.textContent = countdown + 's';
            if (countdown <= 0) {
                updateStatus();
            }
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html>
