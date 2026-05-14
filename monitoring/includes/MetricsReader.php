<?php
declare(strict_types=1);

/**
 * RedWolf IT Ops Suite - Metrics Reader
 * Reads and parses JSONL metric files for dashboard and alert consumption
 */

namespace RedWolf\Monitoring;

class MetricsReader
{
    private string $metricsDir;
    private int $maxDataPoints;

    public function __construct(string $metricsDir = '/var/log/redwolf/metrics', int $maxDataPoints = 3600)
    {
        $this->metricsDir = rtrim($metricsDir, '/');
        $this->maxDataPoints = $maxDataPoints;
    }

    /**
     * Get the most recent metric snapshot from today's JSONL file
     * Falls back to the most recent available file
     */
    public function getLatestMetrics(): ?array
    {
        $filePath = $this->getLatestFile();
        if ($filePath === null || !is_readable($filePath)) {
            return null;
        }

        $lines = $this->readFileTail($filePath, 1);
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Get metrics for a time range across multiple JSONL files
     */
    public function getMetricsRange(string $from, string $to): array
    {
        $fromDate = strtotime($from);
        $toDate = strtotime($to);

        if ($fromDate === false || $toDate === false || $fromDate > $toDate) {
            return [];
        }

        $metrics = [];
        $currentDate = $fromDate;

        while ($currentDate <= $toDate) {
            $dateStr = date('Y-m-d', $currentDate);
            $filePath = $this->metricsDir . '/' . $dateStr . '.jsonl';

            if (is_readable($filePath)) {
                $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $data = json_decode($line, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                        $metricTimestamp = strtotime($data['timestamp'] ?? '');
                        if ($metricTimestamp !== false) {
                            if ($metricTimestamp >= $fromDate && $metricTimestamp <= $toDate) {
                                $metrics[] = $data;
                            }
                        }
                    }
                }
            }

            $currentDate = strtotime('+1 day', $currentDate);
        }

        return $metrics;
    }

    /**
     * Get the latest process snapshot (top processes from most recent reading)
     */
    public function getTopProcesses(): array
    {
        $metrics = $this->getLatestMetrics();
        return $metrics['top_processes'] ?? [];
    }

    /**
     * Get the last N network data points formatted for Chart.js consumption
     * Returns arrays of timestamps, bytes_in, and bytes_out
     */
    public function getNetworkChartData(int $points = 60): array
    {
        $filePath = $this->getLatestFile();
        if ($filePath === null || !is_readable($filePath)) {
            return ['labels' => [], 'bytes_in' => [], 'bytes_out' => []];
        }

        $lines = $this->readFileTail($filePath, $points);
        $lines = array_reverse($lines); // chronological order

        $labels = [];
        $bytesIn = [];
        $bytesOut = [];

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $labels[] = date('H:i:s', strtotime($data['timestamp']));
                $bytesIn[] = (float)($data['network_io']['in'] ?? 0);
                $bytesOut[] = (float)($data['network_io']['out'] ?? 0);
            }
        }

        return [
            'labels' => $labels,
            'bytes_in' => $bytesIn,
            'bytes_out' => $bytesOut,
        ];
    }

    /**
     * Get the last N CPU/Memory data points for trend display
     */
    public function getCpuMemoryHistory(int $points = 60): array
    {
        $filePath = $this->getLatestFile();
        if ($filePath === null || !is_readable($filePath)) {
            return ['labels' => [], 'cpu' => [], 'memory' => []];
        }

        $lines = $this->readFileTail($filePath, $points);
        $lines = array_reverse($lines);

        $labels = [];
        $cpu = [];
        $memory = [];

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $labels[] = date('H:i:s', strtotime($data['timestamp']));
                $cpu[] = (float)($data['cpu_used_percent'] ?? 0);
                $memory[] = (float)($data['memory_usage_percent'] ?? 0);
            }
        }

        return ['labels' => $labels, 'cpu' => $cpu, 'memory' => $memory];
    }

    /**
     * Get consecutive readings for alert evaluation
     * Returns the last N readings from the current JSONL file
     */
    public function getRecentReadings(int $count = 5): array
    {
        $filePath = $this->getLatestFile();
        if ($filePath === null || !is_readable($filePath)) {
            return [];
        }

        $lines = $this->readFileTail($filePath, $count);
        $lines = array_reverse($lines);

        $readings = [];
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $readings[] = $data;
            }
        }

        return $readings;
    }

    /**
     * Find the most recent JSONL file in the metrics directory
     */
    private function getLatestFile(): ?string
    {
        if (!is_dir($this->metricsDir)) {
            return null;
        }

        $files = glob($this->metricsDir . '/*.jsonl');
        if (empty($files)) {
            return null;
        }

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        return $files[0];
    }

    /**
     * Read the last N lines from a file efficiently
     */
    private function readFileTail(string $filePath, int $count): array
    {
        $file = new \SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        $startLine = max(0, $totalLines - $count);

        $lines = [];
        $file->seek($startLine);
        while (!$file->eof()) {
            $line = $file->fgets();
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
