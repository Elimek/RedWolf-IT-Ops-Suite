<?php
declare(strict_types=1);

/**
 * RedWolf IT Ops Suite - Monitoring Dashboard
 * Displays real-time system metrics with interactive charts
 * Auto-refreshes every 60 seconds
 */

require_once __DIR__ . '/includes/MetricsReader.php';

use RedWolf\Monitoring\MetricsReader;

$reader = new MetricsReader();
$metrics = $reader->getLatestMetrics();
$topProcesses = $reader->getTopProcesses();
$networkData = $reader->getNetworkChartData(60);

// Fallback values when no metrics are available
$hostname = $metrics['hostname'] ?? php_uname('n');
$uptimeSeconds = (int)($metrics['uptime_seconds'] ?? 0);
$uptimeDays = $uptimeSeconds > 0 ? round($uptimeSeconds / 86400, 1) : 0;
$cpuUsed = (float)($metrics['cpu_used_percent'] ?? 0);
$memUsed = (float)($metrics['memory_usage_percent'] ?? 0);
$diskUsed = (float)($metrics['disk_usage_percent'] ?? 0);
$lastUpdate = $metrics['timestamp'] ?? 'No data available';

function getStatusColor(float $value): string
{
    if ($value < 70) return '#28a745'; // green
    if ($value <= 85) return '#ffc107'; // yellow
    return '#dc3545'; // red
}

function getStatusLabel(float $value): string
{
    if ($value < 70) return 'Normal';
    if ($value <= 85) return 'Warning';
    return 'Critical';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title>RedWolf IT Ops - System Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --rw-bg-primary: #0f1117;
            --rw-bg-secondary: #1a1d27;
            --rw-bg-card: #1e2233;
            --rw-text-primary: #e8eaed;
            --rw-text-secondary: #9aa0a6;
            --rw-accent: #4285f4;
            --rw-accent-hover: #5b9bf7;
            --rw-success: #34a853;
            --rw-warning: #fbbc04;
            --rw-danger: #ea4335;
            --rw-border: #2d3142;
            --rw-radius: 12px;
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--rw-bg-primary);
            color: var(--rw-text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }

        /* Top bar */
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
            letter-spacing: 0.5px;
        }
        .top-bar .meta {
            font-size: 0.875rem;
            color: var(--rw-text-secondary);
        }
        .top-bar .meta span {
            margin-right: 20px;
        }
        .top-bar .meta strong {
            color: var(--rw-text-primary);
        }
        .nav-links a {
            color: var(--rw-text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            margin-left: 16px;
            transition: color 0.2s;
        }
        .nav-links a:hover, .nav-links a.active {
            color: var(--rw-accent);
        }

        /* Metric cards */
        .metric-card {
            background: var(--rw-bg-card);
            border: 1px solid var(--rw-border);
            border-radius: var(--rw-radius);
            padding: 24px;
            text-align: center;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .metric-card:hover {
            border-color: var(--rw-accent);
            box-shadow: 0 4px 20px rgba(66, 133, 244, 0.1);
        }
        .metric-card .card-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--rw-text-secondary);
            margin-bottom: 16px;
        }

        /* Circular progress ring */
        .progress-ring {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 12px;
        }
        .progress-ring svg {
            transform: rotate(-90deg);
            width: 140px;
            height: 140px;
        }
        .progress-ring .bg-circle {
            fill: none;
            stroke: var(--rw-border);
            stroke-width: 10;
        }
        .progress-ring .fg-circle {
            fill: none;
            stroke-width: 10;
            stroke-linecap: round;
            transition: stroke-dashoffset 1s ease, stroke 0.5s ease;
        }
        .progress-ring .value {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.75rem;
            font-weight: 700;
        }
        .progress-ring .status-label {
            font-size: 0.75rem;
            color: var(--rw-text-secondary);
            margin-top: 4px;
        }

        /* Network chart card */
        .network-card {
            background: var(--rw-bg-card);
            border: 1px solid var(--rw-border);
            border-radius: var(--rw-radius);
            padding: 24px;
        }
        .network-card .card-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--rw-text-secondary);
            margin-bottom: 16px;
        }

        /* Process table */
        .process-table-container {
            background: var(--rw-bg-card);
            border: 1px solid var(--rw-border);
            border-radius: var(--rw-radius);
            padding: 24px;
        }
        .process-table {
            color: var(--rw-text-primary);
            margin-bottom: 0;
        }
        .process-table thead th {
            background: var(--rw-bg-secondary);
            border-bottom: 2px solid var(--rw-border);
            color: var(--rw-text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 16px;
        }
        .process-table tbody td {
            border-bottom: 1px solid var(--rw-border);
            padding: 10px 16px;
            font-size: 0.875rem;
            vertical-align: middle;
        }
        .process-table tbody tr:hover {
            background: rgba(66, 133, 244, 0.05);
        }
        .badge-state {
            font-size: 0.75rem;
            padding: 3px 8px;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--rw-text-primary);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--rw-border);
        }

        .last-update {
            text-align: center;
            color: var(--rw-text-secondary);
            font-size: 0.8rem;
            margin-top: 16px;
        }

        @media (max-width: 768px) {
            .progress-ring { width: 110px; height: 110px; }
            .progress-ring svg { width: 110px; height: 110px; }
            .progress-ring .value { font-size: 1.4rem; }
            .top-bar { padding: 10px 16px; }
            .nav-links { display: none; }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div>
            <span class="brand">RedWolf IT Ops</span>
        </div>
        <div class="meta">
            <span>Host: <strong><?= htmlspecialchars($hostname) ?></strong></span>
            <span>Uptime: <strong><?= number_format($uptimeDays, 1) ?> days</strong></span>
            <span>Time: <strong id="currentTime"><?= date('Y-m-d H:i:s') ?></strong></span>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="alert_manager.php">Alerts</a>
            <a href="fault_simulator.php">Fault Simulator</a>
        </div>
    </div>

    <div class="container-fluid px-3 px-md-4 py-4">
        <!-- Metric Cards Row -->
        <div class="row g-3 mb-4">
            <!-- CPU Card -->
            <div class="col-6 col-lg-3">
                <div class="metric-card">
                    <div class="card-title">CPU Usage</div>
                    <div class="progress-ring">
                        <?php
                        $circumference = 2 * M_PI * 55;
                        $offset = $circumference - ($cpuUsed / 100) * $circumference;
                        $cpuColor = getStatusColor($cpuUsed);
                        ?>
                        <svg viewBox="0 0 120 120">
                            <circle class="bg-circle" cx="60" cy="60" r="55"/>
                            <circle class="fg-circle" cx="60" cy="60" r="55"
                                stroke="<?= $cpuColor ?>"
                                stroke-dasharray="<?= $circumference ?>"
                                stroke-dashoffset="<?= $offset ?>"/>
                        </svg>
                        <div class="value" style="color: <?= $cpuColor ?>"><?= number_format($cpuUsed, 1) ?>%</div>
                    </div>
                    <div class="status-label"><?= getStatusLabel($cpuUsed) ?></div>
                </div>
            </div>

            <!-- Memory Card -->
            <div class="col-6 col-lg-3">
                <div class="metric-card">
                    <div class="card-title">Memory Usage</div>
                    <div class="progress-ring">
                        <?php
                        $memColor = getStatusColor($memUsed);
                        $memOffset = $circumference - ($memUsed / 100) * $circumference;
                        ?>
                        <svg viewBox="0 0 120 120">
                            <circle class="bg-circle" cx="60" cy="60" r="55"/>
                            <circle class="fg-circle" cx="60" cy="60" r="55"
                                stroke="<?= $memColor ?>"
                                stroke-dasharray="<?= $circumference ?>"
                                stroke-dashoffset="<?= $memOffset ?>"/>
                        </svg>
                        <div class="value" style="color: <?= $memColor ?>"><?= number_format($memUsed, 1) ?>%</div>
                    </div>
                    <div class="status-label"><?= getStatusLabel($memUsed) ?></div>
                </div>
            </div>

            <!-- Disk Card -->
            <div class="col-6 col-lg-3">
                <div class="metric-card">
                    <div class="card-title">Disk Usage</div>
                    <div class="progress-ring">
                        <?php
                        $diskColor = getStatusColor($diskUsed);
                        $diskOffset = $circumference - ($diskUsed / 100) * $circumference;
                        ?>
                        <svg viewBox="0 0 120 120">
                            <circle class="bg-circle" cx="60" cy="60" r="55"/>
                            <circle class="fg-circle" cx="60" cy="60" r="55"
                                stroke="<?= $diskColor ?>"
                                stroke-dasharray="<?= $circumference ?>"
                                stroke-dashoffset="<?= $diskOffset ?>"/>
                        </svg>
                        <div class="value" style="color: <?= $diskColor ?>"><?= number_format($diskUsed, 1) ?>%</div>
                    </div>
                    <div class="status-label"><?= getStatusLabel($diskUsed) ?></div>
                </div>
            </div>

            <!-- Network Card -->
            <div class="col-6 col-lg-3">
                <div class="metric-card" style="padding: 16px;">
                    <div class="card-title">Network I/O</div>
                    <canvas id="networkChart" height="130"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Processes Table -->
        <div class="process-table-container">
            <div class="section-title">Top 10 Processes by CPU</div>
            <div class="table-responsive">
                <table class="table process-table">
                    <thead>
                        <tr>
                            <th>PID</th>
                            <th>Process Name</th>
                            <th>CPU %</th>
                            <th>MEM %</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topProcesses)): ?>
                        <tr>
                            <td colspan="5" class="text-center" style="color: var(--rw-text-secondary); padding: 32px;">
                                No process data available. Ensure collector.sh is running.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($topProcesses as $proc): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)($proc['pid'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)($proc['name'] ?? 'unknown')) ?></td>
                                <td>
                                    <?php $procCpu = (float)($proc['cpu'] ?? 0); ?>
                                    <span style="color: <?= getStatusColor($procCpu > 1 ? 90 : $procCpu) ?>">
                                        <?= number_format($procCpu, 1) ?>%
                                    </span>
                                </td>
                                <td><?= number_format((float)($proc['mem'] ?? 0), 1) ?>%</td>
                                <td>
                                    <span class="badge bg-<?= in_array($proc['state'] ?? '', ['R', 'S']) ? 'success' : 'secondary' ?> badge-state">
                                        <?= htmlspecialchars((string)($proc['state'] ?? '-')) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="last-update">
            Last updated: <?= htmlspecialchars($lastUpdate) ?> &middot; Auto-refresh every 60s
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        // Update clock
        function updateClock() {
            const now = new Date();
            const el = document.getElementById('currentTime');
            if (el) {
                el.textContent = now.getFullYear() + '-'
                    + String(now.getMonth() + 1).padStart(2, '0') + '-'
                    + String(now.getDate()).padStart(2, '0') + ' '
                    + String(now.getHours()).padStart(2, '0') + ':'
                    + String(now.getMinutes()).padStart(2, '0') + ':'
                    + String(now.getSeconds()).padStart(2, '0');
            }
        }
        setInterval(updateClock, 1000);

        // Network chart
        const networkCtx = document.getElementById('networkChart');
        if (networkCtx) {
            const labels = <?= json_encode($networkData['labels']) ?>;
            const bytesIn = <?= json_encode($networkData['bytes_in']) ?>;
            const bytesOut = <?= json_encode($networkData['bytes_out']) ?>;

            // Format bytes to human-readable
            function formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
            }

            new Chart(networkCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'In',
                            data: bytesIn,
                            borderColor: '#4285f4',
                            backgroundColor: 'rgba(66, 133, 244, 0.1)',
                            borderWidth: 2,
                            pointRadius: 0,
                            fill: true,
                            tension: 0.3,
                        },
                        {
                            label: 'Out',
                            data: bytesOut,
                            borderColor: '#34a853',
                            backgroundColor: 'rgba(52, 168, 83, 0.1)',
                            borderWidth: 2,
                            pointRadius: 0,
                            fill: true,
                            tension: 0.3,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: {
                            labels: { color: '#9aa0a6', font: { size: 11 }, boxWidth: 12 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatBytes(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#9aa0a6', maxTicksLimit: 8, font: { size: 10 } },
                            grid: { color: 'rgba(45, 49, 66, 0.5)' }
                        },
                        y: {
                            ticks: {
                                color: '#9aa0a6',
                                font: { size: 10 },
                                callback: function(val) { return formatBytes(val); }
                            },
                            grid: { color: 'rgba(45, 49, 66, 0.5)' }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
