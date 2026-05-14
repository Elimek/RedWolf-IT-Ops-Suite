<?php
declare(strict_types=1);

/**
 * RedWolf IT Ops Suite - Alert Manager
 * Evaluates system metrics against thresholds, manages alert lifecycle
 * Supports email and webhook notifications with anti-spam cooldown
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

// Admin credentials
$adminUser = $config['ADMIN_USER'] ?? 'admin';
$adminPass = $config['ADMIN_PASS'] ?? 'redwolf2024';

// ============================================================
// Database connection
// ============================================================
function getDbConnection(array $config): PDO
{
    $host = $config['DB_HOST'] ?? 'localhost';
    $port = (int)($config['DB_PORT'] ?? 3306);
    $name = $config['DB_NAME'] ?? 'redwolf_ops';
    $user = $config['DB_USER'] ?? 'root';
    $pass = $config['DB_PASS'] ?? '';

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
    $inputUser = $_POST['username'] ?? '';
    $inputPass = $_POST['password'] ?? '';
    if ($inputUser === $adminUser && $inputPass === $adminPass) {
        $_SESSION['alert_admin'] = true;
        $authenticated = true;
    } else {
        $loginError = 'Invalid credentials';
    }
} elseif (isset($_SESSION['alert_admin']) && $_SESSION['alert_admin'] === true) {
    $authenticated = true;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['alert_admin']);
    $authenticated = false;
    header('Location: alert_manager.php');
    exit;
}

// ============================================================
// Handle actions (acknowledge, run evaluation)
// ============================================================
$alerts = [];
$stats = ['active' => 0, 'acked' => 0, 'resolved' => 0, 'total' => 0];
$evaluationResult = '';

if ($authenticated) {
    try {
        $db = getDbConnection($config);
        $engine = new AlertEngine($db, $config);

        // Acknowledge alert
        if (isset($_POST['ack_alert']) && isset($_POST['alert_id'])) {
            $alertId = (int)$_POST['alert_id'];
            $engine->acknowledgeAlert($alertId);
        }

        // Run evaluation
        if (isset($_GET['action']) && $_GET['action'] === 'evaluate') {
            $reader = new MetricsReader();
            $metrics = $reader->getLatestMetrics();
            if ($metrics !== null) {
                // Single reading evaluation
                $triggered = $engine->evaluateAlerts($metrics);
                // Sustained CPU check
                $recentReadings = $reader->getRecentReadings(5);
                $sustained = $engine->evaluateSustainedAlerts($recentReadings);
                $allTriggered = array_filter(array_merge($triggered, $sustained));
                $evaluationResult = count($allTriggered) > 0
                    ? count($allTriggered) . ' alert(s) triggered'
                    : 'All metrics within normal range';
            } else {
                $evaluationResult = 'No metrics data available for evaluation';
            }
        }

        // Get alerts
        $filterStatus = $_GET['status'] ?? '';
        $alerts = $engine->getAlerts($filterStatus);

        // Get stats
        $statsStmt = $db->query("SELECT status, COUNT(*) as cnt FROM alerts GROUP BY status");
        foreach ($statsStmt as $row) {
            $stats[$row['status']] = (int)$row['cnt'];
            $stats['total'] += (int)$row['cnt'];
        }
    } catch (\PDOException $e) {
        $evaluationResult = 'Database error: ' . $e->getMessage();
    }
}

// ============================================================
// Render page
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RedWolf IT Ops - Alert Manager</title>
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
        .top-bar .brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--rw-accent);
        }
        .nav-links a {
            color: var(--rw-text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            margin-left: 16px;
            transition: color 0.2s;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--rw-accent); }

        .card-dark {
            background: var(--rw-bg-card);
            border: 1px solid var(--rw-border);
            border-radius: var(--rw-radius);
            padding: 24px;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--rw-border);
        }

        /* Stats badges */
        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .stat-badge .stat-num { font-size: 1.25rem; }
        .stat-active { background: rgba(234, 67, 53, 0.15); color: var(--rw-danger); }
        .stat-acked { background: rgba(251, 188, 4, 0.15); color: var(--rw-warning); }
        .stat-resolved { background: rgba(52, 168, 83, 0.15); color: var(--rw-success); }
        .stat-total { background: rgba(66, 133, 244, 0.15); color: var(--rw-accent); }

        /* Alert list */
        .alert-item {
            background: var(--rw-bg-secondary);
            border: 1px solid var(--rw-border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 8px;
            transition: border-color 0.2s;
        }
        .alert-item:hover { border-color: var(--rw-accent); }
        .alert-item.critical { border-left: 4px solid var(--rw-danger); }
        .alert-item.warning { border-left: 4px solid var(--rw-warning); }
        .alert-item.resolved { border-left: 4px solid var(--rw-success); opacity: 0.7; }
        .alert-meta { font-size: 0.75rem; color: var(--rw-text-secondary); }
        .alert-message { font-size: 0.9rem; margin: 6px 0; }

        .badge-severity {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 3px 8px;
        }
        .badge-status {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 3px 8px;
        }

        /* Login form */
        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 60vh;
        }
        .login-form {
            background: var(--rw-bg-card);
            border: 1px solid var(--rw-border);
            border-radius: var(--rw-radius);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-form .form-control {
            background: var(--rw-bg-primary);
            border: 1px solid var(--rw-border);
            color: var(--rw-text-primary);
        }
        .login-form .form-control:focus {
            background: var(--rw-bg-primary);
            border-color: var(--rw-accent);
            color: var(--rw-text-primary);
            box-shadow: 0 0 0 0.2rem rgba(66, 133, 244, 0.25);
        }
        .btn-rw {
            background: var(--rw-accent);
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: background 0.2s;
        }
        .btn-rw:hover { background: #5b9bf7; color: #fff; }
        .btn-outline-rw {
            background: transparent;
            color: var(--rw-accent);
            border: 1px solid var(--rw-accent);
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-outline-rw:hover { background: var(--rw-accent); color: #fff; }

        .filter-btn {
            background: transparent;
            color: var(--rw-text-secondary);
            border: 1px solid var(--rw-border);
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            transition: all 0.2s;
            text-decoration: none;
        }
        .filter-btn:hover, .filter-btn.active {
            background: var(--rw-accent);
            color: #fff;
            border-color: var(--rw-accent);
            text-decoration: none;
        }

        .eval-result {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 16px;
        }
        .eval-result.ok { background: rgba(52, 168, 83, 0.15); color: var(--rw-success); }
        .eval-result.warn { background: rgba(251, 188, 4, 0.15); color: var(--rw-warning); }

        @media (max-width: 768px) {
            .top-bar { padding: 10px 16px; }
            .nav-links { display: none; }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div><span class="brand">RedWolf IT Ops</span></div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="alert_manager.php" class="active">Alerts</a>
            <a href="fault_simulator.php">Fault Simulator</a>
            <?php if ($authenticated): ?>
                <a href="alert_manager.php?logout=1" style="color: var(--rw-danger);">Logout</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container-fluid px-3 px-md-4 py-4">
        <?php if (!$authenticated): ?>
        <!-- Login Form -->
        <div class="login-container">
            <div class="login-form text-center">
                <h4 style="margin-bottom: 24px; font-weight: 700;">Alert Manager</h4>
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
        <!-- Alert Manager Content -->
        <!-- Stats Row -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <div class="stat-badge stat-active">
                <span>Active</span>
                <span class="stat-num"><?= $stats['active'] ?></span>
            </div>
            <div class="stat-badge stat-acked">
                <span>Acked</span>
                <span class="stat-num"><?= $stats['acked'] ?></span>
            </div>
            <div class="stat-badge stat-resolved">
                <span>Resolved</span>
                <span class="stat-num"><?= $stats['resolved'] ?></span>
            </div>
            <div class="stat-badge stat-total">
                <span>Total</span>
                <span class="stat-num"><?= $stats['total'] ?></span>
            </div>
            <div class="ms-auto">
                <a href="alert_manager.php?action=evaluate" class="btn btn-rw">Run Evaluation</a>
            </div>
        </div>

        <?php if ($evaluationResult): ?>
            <div class="eval-result <?= str_contains($evaluationResult, 'triggered') ? 'warn' : 'ok' ?>">
                <?= htmlspecialchars($evaluationResult) ?>
            </div>
        <?php endif; ?>

        <!-- Filter Row -->
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="alert_manager.php" class="filter-btn <?= empty($_GET['status'] ?? '') ? 'active' : '' ?>">All</a>
            <a href="alert_manager.php?status=active" class="filter-btn <?= ($_GET['status'] ?? '') === 'active' ? 'active' : '' ?>">Active</a>
            <a href="alert_manager.php?status=acked" class="filter-btn <?= ($_GET['status'] ?? '') === 'acked' ? 'active' : '' ?>">Acknowledged</a>
            <a href="alert_manager.php?status=resolved" class="filter-btn <?= ($_GET['status'] ?? '') === 'resolved' ? 'active' : '' ?>">Resolved</a>
        </div>

        <!-- Alert List -->
        <div class="card-dark">
            <div class="section-title">Alert History (Newest First)</div>
            <?php if (empty($alerts)): ?>
                <div style="text-align: center; color: var(--rw-text-secondary); padding: 32px;">
                    No alerts found
                </div>
            <?php else: ?>
                <?php foreach ($alerts as $alert): ?>
                <div class="alert-item <?= htmlspecialchars($alert['severity']) ?> <?= htmlspecialchars($alert['status']) ?>">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <span class="badge badge-severity bg-<?= $alert['severity'] === 'critical' ? 'danger' : 'warning' ?>">
                                <?= strtoupper(htmlspecialchars($alert['severity'])) ?>
                            </span>
                            <span class="badge badge-status bg-<?= $alert['status'] === 'active' ? 'danger' : ($alert['status'] === 'acked' ? 'warning' : 'success') ?>">
                                <?= strtoupper(htmlspecialchars($alert['status'])) ?>
                            </span>
                            <span class="alert-meta" style="margin-left: 8px;">
                                <?= htmlspecialchars($alert['alert_type']) ?> &middot;
                                <?= htmlspecialchars($alert['hostname'] ?? '') ?>
                            </span>
                        </div>
                        <div class="alert-meta">
                            <?= htmlspecialchars($alert['created_at'] ?? '') ?>
                            <?php if ($alert['acked_at']): ?>
                                &middot; Acked: <?= htmlspecialchars($alert['acked_at']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="alert-message"><?= htmlspecialchars($alert['message']) ?></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="alert-meta">
                            Value: <?= htmlspecialchars((string)($alert['metric_value'] ?? '-')) ?>
                            &middot; Threshold: <?= htmlspecialchars((string)($alert['threshold'] ?? '-')) ?>
                        </div>
                        <?php if ($alert['status'] === 'active'): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="alert_id" value="<?= (int)$alert['id'] ?>">
                            <button type="submit" name="ack_alert" value="1" class="btn btn-outline-rw">Acknowledge</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
