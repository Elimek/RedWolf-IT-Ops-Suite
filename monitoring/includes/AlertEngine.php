<?php
declare(strict_types=1);

/**
 * RedWolf IT Ops Suite - Alert Engine
 * Evaluates system metrics against configurable thresholds,
 * manages alert lifecycle (create, suppress, notify, resolve)
 */

namespace RedWolf\Monitoring;

use PDO;

class AlertEngine
{
    private PDO $db;
    private int $cooldownSeconds;
    private ?string $smtpHost;
    private ?string $smtpPort;
    private ?string $smtpUser;
    private ?string $smtpPass;
    private ?string $smtpFrom;
    private ?string $webhookUrl;
    private ?string $alertEmail;

    // Alert thresholds (can be overridden via .env)
    private float $cpuCriticalThreshold;
    private float $cpuSustainedCount;
    private float $memoryWarningThreshold;
    private float $memoryCriticalThreshold;
    private float $diskWarningThreshold;
    private float $diskCriticalThreshold;
    private float $nginx5xxThreshold;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;

        // Cooldown configuration (default 1 hour)
        $this->cooldownSeconds = (int)($config['ALERT_COOLDOWN_SECONDS'] ?? 3600);

        // SMTP configuration
        $this->smtpHost = $config['SMTP_HOST'] ?? null;
        $this->smtpPort = $config['SMTP_PORT'] ?? '587';
        $this->smtpUser = $config['SMTP_USER'] ?? null;
        $this->smtpPass = $config['SMTP_PASS'] ?? null;
        $this->smtpFrom = $config['SMTP_FROM'] ?? null;
        $this->webhookUrl = $config['WEBHOOK_URL'] ?? null;
        $this->alertEmail = $config['ALERT_EMAIL'] ?? null;

        // Thresholds
        $this->cpuCriticalThreshold = (float)($config['CPU_CRITICAL_THRESHOLD'] ?? 90.0);
        $this->cpuSustainedCount = (float)($config['CPU_SUSTAINED_COUNT'] ?? 3);
        $this->memoryWarningThreshold = (float)($config['MEMORY_WARNING_THRESHOLD'] ?? 85.0);
        $this->memoryCriticalThreshold = (float)($config['MEMORY_CRITICAL_THRESHOLD'] ?? 95.0);
        $this->diskWarningThreshold = (float)($config['DISK_WARNING_THRESHOLD'] ?? 85.0);
        $this->diskCriticalThreshold = (float)($config['DISK_CRITICAL_THRESHOLD'] ?? 90.0);
        $this->nginx5xxThreshold = (float)($config['NGINX_5XX_THRESHOLD'] ?? 5.0);
    }

    /**
     * Evaluate all alert rules against the given metrics
     * Returns array of triggered alerts
     */
    public function evaluateAlerts(array $metrics): array
    {
        $triggered = [];
        $hostname = $metrics['hostname'] ?? gethostname();

        // CPU: sustained high usage
        if (isset($metrics['cpu_used_percent'])) {
            $cpuVal = (float)$metrics['cpu_used_percent'];
            if ($cpuVal > $this->cpuCriticalThreshold) {
                $triggered[] = $this->handlePotentialAlert(
                    'cpu_high',
                    'critical',
                    sprintf('CPU usage at %.1f%% (threshold: %.0f%%)', $cpuVal, $this->cpuCriticalThreshold),
                    $cpuVal,
                    $this->cpuCriticalThreshold,
                    $hostname
                );
            }
        }

        // Memory: check threshold
        if (isset($metrics['memory_usage_percent'])) {
            $memVal = (float)$metrics['memory_usage_percent'];
            if ($memVal > $this->memoryCriticalThreshold) {
                $triggered[] = $this->handlePotentialAlert(
                    'memory_high',
                    'critical',
                    sprintf('Memory usage at %.1f%% (threshold: %.0f%%)', $memVal, $this->memoryCriticalThreshold),
                    $memVal,
                    $this->memoryCriticalThreshold,
                    $hostname
                );
            } elseif ($memVal > $this->memoryWarningThreshold) {
                $triggered[] = $this->handlePotentialAlert(
                    'memory_warning',
                    'warning',
                    sprintf('Memory usage at %.1f%% (warning threshold: %.0f%%)', $memVal, $this->memoryWarningThreshold),
                    $memVal,
                    $this->memoryWarningThreshold,
                    $hostname
                );
            }
        }

        // Disk: check threshold
        if (isset($metrics['disk_usage_percent'])) {
            $diskVal = (float)$metrics['disk_usage_percent'];
            if ($diskVal > $this->diskCriticalThreshold) {
                $triggered[] = $this->handlePotentialAlert(
                    'disk_critical',
                    'critical',
                    sprintf('Disk usage at %.1f%% (threshold: %.0f%%)', $diskVal, $this->diskCriticalThreshold),
                    $diskVal,
                    $this->diskCriticalThreshold,
                    $hostname
                );
            } elseif ($diskVal > $this->diskWarningThreshold) {
                $triggered[] = $this->handlePotentialAlert(
                    'disk_warning',
                    'warning',
                    sprintf('Disk usage at %.1f%% (warning threshold: %.0f%%)', $diskVal, $this->diskWarningThreshold),
                    $diskVal,
                    $this->diskWarningThreshold,
                    $hostname
                );
            }
        }

        // Nginx 5xx error rate (would need access log parsing in production)
        // This checks for high 5xx rates if available in metrics
        if (isset($metrics['nginx_5xx_rate'])) {
            $rateVal = (float)$metrics['nginx_5xx_rate'];
            if ($rateVal > $this->nginx5xxThreshold) {
                $triggered[] = $this->handlePotentialAlert(
                    'nginx_5xx',
                    'critical',
                    sprintf('Nginx 5xx error rate at %.1f%% (threshold: %.0f%%)', $rateVal, $this->nginx5xxThreshold),
                    $rateVal,
                    $this->nginx5xxThreshold,
                    $hostname
                );
            }
        }

        // Attempt auto-resolution of existing alerts
        $this->resolveAlerts($metrics);

        return array_filter($triggered);
    }

    /**
     * Evaluate sustained CPU high alerts from multiple consecutive readings
     */
    public function evaluateSustainedAlerts(array $readings): array
    {
        if (count($readings) < (int)$this->cpuSustainedCount) {
            return [];
        }

        $sustainedCount = 0;
        $latestReading = null;

        foreach ($readings as $reading) {
            if (isset($reading['cpu_used_percent'])) {
                $cpuVal = (float)$reading['cpu_used_percent'];
                if ($cpuVal > $this->cpuCriticalThreshold) {
                    $sustainedCount++;
                } else {
                    $sustainedCount = 0;
                }
            }
            $latestReading = $reading;
        }

        if ($sustainedCount >= (int)$this->cpuSustainedCount && $latestReading !== null) {
            $cpuVal = (float)$latestReading['cpu_used_percent'];
            $hostname = $latestReading['hostname'] ?? gethostname();

            return [$this->handlePotentialAlert(
                'cpu_sustained',
                'critical',
                sprintf('CPU sustained above %.0f%% for %d readings (current: %.1f%%)',
                    $this->cpuCriticalThreshold,
                    $sustainedCount,
                    $cpuVal
                ),
                $cpuVal,
                $this->cpuCriticalThreshold,
                $hostname
            )];
        }

        return [];
    }

    /**
     * Check if an alert type is within cooldown period
     */
    public function suppressAlert(string $type): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM alerts
             WHERE alert_type = :type
               AND status IN ('active', 'acked')
               AND created_at > DATE_SUB(NOW(), INTERVAL :cooldown SECOND)"
        );
        $stmt->execute([
            ':type' => $type,
            ':cooldown' => $this->cooldownSeconds,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Create a new alert in the database and send notifications
     */
    public function createAlert(string $type, string $severity, string $message, float $metricValue = 0.0, float $threshold = 0.0, string $hostname = ''): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO alerts (alert_type, severity, message, hostname, metric_value, threshold, status)
             VALUES (:type, :severity, :message, :hostname, :metric_value, :threshold, 'active')"
        );
        $stmt->execute([
            ':type' => $type,
            ':severity' => $severity,
            ':message' => $message,
            ':hostname' => $hostname,
            ':metric_value' => $metricValue,
            ':threshold' => $threshold,
        ]);

        $alertId = (int)$this->db->lastInsertId();

        // Send notifications
        $alert = [
            'id' => $alertId,
            'alert_type' => $type,
            'severity' => $severity,
            'message' => $message,
            'hostname' => $hostname,
            'metric_value' => $metricValue,
            'threshold' => $threshold,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->sendNotification($alert);

        return $alertId;
    }

    /**
     * Send notification via email and/or webhook
     */
    public function sendNotification(array $alert): void
    {
        // Email notification
        if ($this->smtpHost && $this->smtpUser && $this->alertEmail) {
            $this->sendEmail($alert);
        }

        // Webhook notification
        if ($this->webhookUrl) {
            $this->sendWebhook($alert);
        }
    }

    /**
     * Auto-resolve alerts whose conditions are now within normal range
     */
    public function resolveAlerts(array $currentMetrics): int
    {
        $resolved = 0;
        $hostname = $currentMetrics['hostname'] ?? '';
        $conditions = [];

        // Build resolution conditions based on current metrics
        if (isset($currentMetrics['cpu_used_percent'])) {
            $conditions['cpu_high'] = (float)$currentMetrics['cpu_used_percent'] < $this->cpuCriticalThreshold - 10;
            $conditions['cpu_sustained'] = (float)$currentMetrics['cpu_used_percent'] < $this->cpuCriticalThreshold - 10;
        }
        if (isset($currentMetrics['memory_usage_percent'])) {
            $conditions['memory_high'] = (float)$currentMetrics['memory_usage_percent'] < $this->memoryWarningThreshold - 5;
            $conditions['memory_warning'] = (float)$currentMetrics['memory_usage_percent'] < $this->memoryWarningThreshold - 5;
        }
        if (isset($currentMetrics['disk_usage_percent'])) {
            $conditions['disk_critical'] = (float)$currentMetrics['disk_usage_percent'] < $this->diskCriticalThreshold - 5;
            $conditions['disk_warning'] = (float)$currentMetrics['disk_usage_percent'] < $this->diskWarningThreshold - 5;
        }

        foreach ($conditions as $type => $isResolved) {
            if (!$isResolved) {
                continue;
            }

            $stmt = $this->db->prepare(
                "UPDATE alerts SET status = 'resolved', resolved_at = NOW()
                 WHERE alert_type = :type
                   AND status IN ('active', 'acked')
                   AND hostname = :hostname"
            );
            $stmt->execute([
                ':type' => $type,
                ':hostname' => $hostname,
            ]);
            $resolved += $stmt->rowCount();
        }

        return $resolved;
    }

    /**
     * Acknowledge an active alert
     */
    public function acknowledgeAlert(int $alertId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE alerts SET status = 'acked', acked_at = NOW()
             WHERE id = :id AND status = 'active'"
        );
        $stmt->execute([':id' => $alertId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get active alerts (active + acked), newest first
     */
    public function getActiveAlerts(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM alerts
             WHERE status IN ('active', 'acked')
             ORDER BY created_at DESC
             LIMIT 100"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all alerts with optional filtering
     */
    public function getAlerts(string $status = '', string $type = '', int $limit = 50): array
    {
        $sql = "SELECT * FROM alerts WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        if ($type !== '') {
            $sql .= " AND alert_type = :type";
            $params[':type'] = $type;
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit";
        $params[':limit'] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // Private helpers
    // ============================================================

    private function handlePotentialAlert(string $type, string $severity, string $message, float $metricValue, float $threshold, string $hostname): ?int
    {
        if ($this->suppressAlert($type)) {
            return null;
        }
        return $this->createAlert($type, $severity, $message, $metricValue, $threshold, $hostname);
    }

    private function sendEmail(array $alert): void
    {
        $subject = sprintf('[RedWolf Alert][%s] %s on %s', strtoupper($alert['severity']), $alert['alert_type'], $alert['hostname']);
        $body = sprintf(
            "Alert ID: %d\nType: %s\nSeverity: %s\nHost: %s\nTime: %s\nValue: %.2f (Threshold: %.2f)\nMessage: %s",
            $alert['id'],
            $alert['alert_type'],
            $alert['severity'],
            $alert['hostname'],
            $alert['created_at'],
            $alert['metric_value'],
            $alert['threshold'],
            $alert['message']
        );

        $headers = [];
        $headers[] = 'From: ' . $this->smtpFrom;
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'X-Priority: ' . ($alert['severity'] === 'critical' ? '1' : '3');

        mail($this->alertEmail, $subject, $body, implode("\r\n", $headers));
    }

    private function sendWebhook(array $alert): void
    {
        $payload = json_encode([
            'text' => sprintf('[%s] %s on %s: %s', strtoupper($alert['severity']), $alert['alert_type'], $alert['hostname'], $alert['message']),
            'alert' => $alert,
        ]);

        $ch = curl_init($this->webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
