# RedWolf IT Ops Suite - Monitoring System Guide (Chunk 2)

## Table of Contents

1. [System Overview](#system-overview)
2. [Metrics Collected](#metrics-collected)
3. [Dashboard Usage](#dashboard-usage)
4. [Alert Processing SOP](#alert-processing-sop)
5. [Customizing Thresholds](#customizing-thresholds)
6. [Fault Simulator](#fault-simulator)
7. [Performance Impact Analysis](#performance-impact-analysis)
8. [Troubleshooting](#troubleshooting)

---

## System Overview

The monitoring system consists of the following components:

| Component | File | Purpose |
|-----------|------|---------|
| Metrics Collector | `monitoring/collector.sh` | Collects system metrics every 60 seconds |
| Dashboard | `monitoring/dashboard.php` | Real-time visualization of system health |
| Alert Manager | `monitoring/alert_manager.php` | Threshold-based alerting with notifications |
| Fault Simulator | `monitoring/fault_simulator.php` | Inject faults for testing alert pipelines |
| Metrics Reader | `monitoring/includes/MetricsReader.php` | PHP library for reading JSONL metric files |
| Alert Engine | `monitoring/includes/AlertEngine.php` | Core alert evaluation and notification logic |

### Data Flow

```
collector.sh (cron, 60s)
    |
    v
/var/log/redwolf/metrics/YYYY-MM-DD.jsonl
    |
    +---> dashboard.php (reads latest metrics)
    |
    +---> alert_manager.php (evaluates thresholds)
              |
              +---> MySQL alerts table
              +---> Email notification (if configured)
              +---> Webhook notification (if configured)
```

---

## Metrics Collected

The collector gathers the following metrics every 60 seconds:

### CPU Metrics
- **CPU Usage %**: Calculated as `(1 - idle/total) * 100` using `/proc/stat`
- **CPU Idle %**: Complement of CPU usage
- **Collection method**: Two samples taken 1 second apart for accuracy

### Memory Metrics
- **Memory Usage %**: `(total - available) / total * 100` from `/proc/meminfo`
- Uses `MemAvailable` which accounts for reclaimable cache

### Disk Metrics
- **Disk Usage %**: Root partition usage from `df /`
- Reports percentage of disk space used

### Network Metrics
- **Bytes In**: Cumulative bytes received across all non-loopback interfaces
- **Bytes Out**: Cumulative bytes sent across all non-loopback interfaces
- Source: `/proc/net/dev`

### Process Metrics
- Top 10 processes sorted by CPU usage
- Fields: PID, process name (truncated to 50 chars), CPU%, MEM%, state

### JSONL Format

Each line in the daily JSONL file is a JSON object:

```json
{
    "timestamp": "2026-05-15T10:00:00+0800",
    "hostname": "server-01",
    "uptime_seconds": 864000,
    "cpu_used_percent": 45.2,
    "cpu_idle_percent": 54.8,
    "memory_usage_percent": 62.1,
    "disk_usage_percent": 55.0,
    "network_io": {"in": 104857600, "out": 52428800},
    "top_processes": [
        {"pid": 1234, "name": "nginx", "cpu": 5.2, "mem": 1.8, "state": "S"}
    ]
}
```

### Data Retention
- Metrics files are stored in `/var/log/redwolf/metrics/`
- Files older than **7 days** are automatically deleted by the collector
- One file per day: `YYYY-MM-DD.jsonl`

---

## Dashboard Usage

Access the dashboard at `http://<server>/monitoring/dashboard.php`.

### Features
- **Auto-refresh**: Page reloads every 60 seconds
- **4 metric cards**: CPU, Memory, Disk (circular progress), Network (line chart)
- **Top 10 processes table**: Sorted by CPU usage
- **Responsive design**: Adapts to desktop and mobile

### Color Coding
| Range | Color | Meaning |
|-------|-------|---------|
| < 70% | Green | Normal operation |
| 70-85% | Yellow | Elevated, monitor closely |
| > 85% | Red | Critical, investigate immediately |

### Network Chart
- Displays last 60 data points (approximately 1 hour of data)
- Dual lines: Inbound (blue) and Outbound (green) traffic
- Values auto-scaled to human-readable units (B, KB, MB, GB)

---

## Alert Processing SOP

### Alert Types and Thresholds

| Alert Type | Severity | Condition | Default Threshold |
|------------|----------|-----------|-------------------|
| `cpu_high` | Warning | CPU usage exceeds threshold | 90% |
| `cpu_sustained` | Critical | CPU > 90% for 3+ consecutive readings | 90% / 3 readings |
| `memory_warning` | Warning | Memory usage exceeds threshold | 85% |
| `memory_high` | Critical | Memory usage exceeds threshold | 95% |
| `disk_warning` | Warning | Disk usage exceeds threshold | 85% |
| `disk_critical` | Critical | Disk usage exceeds threshold | 90% |
| `nginx_5xx` | Critical | Nginx 5xx error rate exceeds threshold | 5% |

### Alert Lifecycle

```
Triggered --> Active --> Acknowledged --> Resolved
                       \                /
                        \-- Resolved --/
```

1. **Active**: Alert triggered, not yet acknowledged
2. **Acknowledged**: Admin has seen and acknowledged the alert
3. **Resolved**: Condition returned to normal (auto-detected) or manually resolved

### Anti-Spam Cooldown
- Same alert type is suppressed for **1 hour** by default
- Configurable via `ALERT_COOLDOWN_SECONDS` in `.env`
- Prevents notification flooding during sustained issues

### Running Alert Evaluation
1. Navigate to the Alert Manager page
2. Click **"Run Evaluation"** to manually check current metrics against thresholds
3. Review triggered alerts and acknowledge as needed

### Incident Response Procedure
1. **Receive alert** via email/webhook or check Alert Manager
2. **Acknowledge** the alert to indicate awareness
3. **Investigate** using the dashboard metrics
4. **Take action** to resolve the underlying issue
5. **Verify** the alert auto-resolves or resolve manually
6. **Document** the incident in audit log if fault simulator was involved

---

## Customizing Thresholds

All thresholds are configured via the `.env` file in the project root:

```env
# Alert Thresholds
CPU_CRITICAL_THRESHOLD=90
CPU_SUSTAINED_COUNT=3
MEMORY_WARNING_THRESHOLD=85
MEMORY_CRITICAL_THRESHOLD=95
DISK_WARNING_THRESHOLD=85
DISK_CRITICAL_THRESHOLD=90
NGINX_5XX_THRESHOLD=5

# Anti-spam
ALERT_COOLDOWN_SECONDS=3600

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=redwolf_ops
DB_USER=root
DB_PASS=

# Admin Authentication
ADMIN_USER=admin
ADMIN_PASS=changeme

# Email Notifications (optional)
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
SMTP_FROM=
ALERT_EMAIL=

# Webhook Notifications (optional)
WEBHOOK_URL=
```

### Threshold Tuning Guidelines

- **Lower thresholds** = more sensitive, more alerts (good for critical production)
- **Higher thresholds** = less noise, may miss early warnings
- Adjust `CPU_SUSTAINED_COUNT` to control how many consecutive high-CPU readings trigger the sustained alert
- Set `ALERT_COOLDOWN_SECONDS` based on your response workflow (e.g., 1800 for 30 minutes)

---

## Fault Simulator

The fault simulator is an admin-only tool for testing the alert pipeline and incident response procedures.

### Access
- URL: `http://<server>/monitoring/fault_simulator.php`
- Requires admin credentials from `.env`

### Available Actions

| Action | What It Does | Risk Level |
|--------|-------------|------------|
| Simulate High CPU | Spawns a `dd` process that maxes out CPU | High |
| Simulate Memory Leak | Allocates ~500MB of RAM via PHP process | High |
| Fill Disk | Writes 500MB temp file to `/tmp/` | Medium |
| Stop Nginx | Stops the Nginx web server service | Critical |
| Restore All | Kills stress processes, removes temp files, restarts Nginx | Safe |

### Safety Features
- Confirmation dialogs before each destructive action
- All actions logged to `audit_log` database table with IP address
- Real-time status display updates every 5 seconds
- Restore All button returns system to normal state

### Status Indicators
- **Green dot**: Service/condition is normal
- **Red dot**: Active fault detected
- **Yellow dot**: Unknown or degraded state

---

## Performance Impact Analysis

### Collector Impact

| Metric | Impact |
|--------|--------|
| CPU overhead | < 0.1% per collection cycle |
| Memory usage | ~2MB peak during collection |
| Disk I/O | ~1KB per JSON line appended |
| Network | Zero external network calls |
| Collection duration | ~2 seconds (includes 1-second CPU sample interval) |

### Dashboard Impact
- Static HTML with CDN-loaded JS libraries
- No server-side heavy computation
- Chart.js renders client-side
- Bandwidth: ~50KB per page load (excluding CDN assets)

### Database Impact
- Alert evaluation queries are lightweight (indexed queries on `alerts` table)
- Expected alert volume: < 100/day under normal conditions
- Audit log writes: only on fault simulator actions

### Overall System Load
The monitoring system is designed to add less than 0.5% overhead to the monitored server. The most significant factor is the 1-second sleep in CPU measurement, which is necessary for accuracy.

---

## Troubleshooting

### No metrics data appearing on dashboard

**Symptoms**: Dashboard shows "No data available" or all zeros.

**Causes and Solutions**:
1. **Cron not running**: Check `crontab -l | grep collector.sh`
2. **Permissions issue**: Ensure `/var/log/redwolf/metrics/` is writable
   ```bash
   sudo mkdir -p /var/log/redwolf/metrics
   sudo chmod 755 /var/log/redwolf/metrics
   ```
3. **Collector script error**: Check logs at `/var/log/redwolf/collector.log`
4. **Missing dependencies**: Install `sysstat` and `bc`
   ```bash
   sudo apt-get install sysstat bc
   ```

### Alerts not triggering

**Symptoms**: Metrics show high values but no alerts are created.

**Causes and Solutions**:
1. **Cooldown active**: Same alert type was recently triggered (within cooldown period). Check `alerts` table for recent entries.
2. **Database connection failure**: Verify `.env` database credentials are correct
3. **Thresholds too high**: Review and lower thresholds in `.env`
4. **Manual evaluation needed**: Click "Run Evaluation" on the Alert Manager page

### Notifications not sending

**Symptoms**: Alerts created in database but no email/webhook received.

**Causes and Solutions**:
1. **SMTP not configured**: Verify `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, `ALERT_EMAIL` in `.env`
2. **Webhook URL not set**: Set `WEBHOOK_URL` in `.env`
3. **PHP mail() blocked**: Check server's mail configuration
4. **Webhook timeout**: Ensure webhook URL is reachable from the server

### Fault simulator not working

**Symptoms**: Actions fail or show errors.

**Causes and Solutions**:
1. **Insufficient permissions**: Nginx stop/start requires `sudo` privileges. Configure sudoers for the web server user.
2. **Process killed by OOM**: Memory stress may be killed by the kernel's OOM killer. Check `dmesg | grep oom`.
3. **SELinux/AppArmor blocking**: May block `dd` or `php` background processes. Check security module logs.

### High disk usage from metrics files

**Symptoms**: `/var/log/redwolf/metrics/` consuming too much space.

**Causes and Solutions**:
1. **Rotation not working**: Verify collector.sh is running (cron active) - rotation happens at each collection cycle
2. **Manual cleanup**: Delete old files manually
   ```bash
   find /var/log/redwolf/metrics/ -name "*.jsonl" -mtime +7 -delete
   ```
3. **Reduce retention**: Edit `RETENTION_DAYS` in `collector.sh`

### Dashboard not auto-refreshing

**Symptoms**: Dashboard data goes stale.

**Causes and Solutions**:
1. **Meta refresh disabled by browser**: Some browsers or extensions may block meta refresh. The page also includes a JS clock update.
2. **Browser cache**: Hard refresh with Ctrl+F5
3. **Network issues**: Check that the server is reachable
