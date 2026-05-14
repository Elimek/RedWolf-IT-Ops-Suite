<?php
/**
 * NetworkUtils - Network scanning and validation utilities
 * RedWolf IT Ops Suite
 */

declare(strict_types=1);

namespace RedWolf\OfficeTools;

class NetworkUtils
{
    /**
     * Validate an IPv4 address
     */
    public static function validateIp(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /**
     * Validate that start IP is less than or equal to end IP
     */
    public static function validateIpRange(string $start, string $end): bool
    {
        if (!self::validateIp($start) || !self::validateIp($end)) {
            return false;
        }
        if (!self::isPrivateIp($start) || !self::isPrivateIp($end)) {
            return false;
        }
        return ip2long($start) <= ip2long($end);
    }

    /**
     * Check if an IP address is in a private range
     */
    public static function isPrivateIp(string $ip): bool
    {
        if (!self::validateIp($ip)) {
            return false;
        }

        $long = ip2long($ip);

        // 10.0.0.0/8
        if (($long & 0xFF000000) === 0x0A000000) {
            return true;
        }

        // 172.16.0.0/12
        if (($long & 0xFFF00000) === 0xAC100000) {
            return true;
        }

        // 192.168.0.0/16
        if (($long & 0xFFFF0000) === 0xC0A80000) {
            return true;
        }

        return false;
    }

    /**
     * Perform reverse DNS lookup for an IP address
     */
    public static function getHostname(string $ip): string
    {
        if (!self::validateIp($ip)) {
            return 'Invalid IP';
        }

        $hostname = @gethostbyaddr($ip);
        if ($hostname === false || $hostname === $ip) {
            return 'N/A';
        }

        return $hostname;
    }

    /**
     * Ping a host with timeout and return response time in ms
     */
    public static function pingHost(string $ip, int $timeoutMs = 1000): array
    {
        if (!self::validateIp($ip)) {
            return ['reachable' => false, 'time_ms' => null, 'error' => 'Invalid IP address'];
        }

        $timeoutSec = (int) ceil($timeoutMs / 1000);

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = sprintf('ping -n 1 -w %d %s', $timeoutMs, escapeshellarg($ip));
        } elseif (self::commandExists('fping')) {
            $cmd = sprintf('fping -C 1 -t %d %s 2>&1', $timeoutMs, escapeshellarg($ip));
        } else {
            $cmd = sprintf('ping -c 1 -W %d %s 2>&1', $timeoutSec, escapeshellarg($ip));
        }

        $startTime = microtime(true);
        $output = @shell_exec($cmd);
        $elapsed = microtime(true) - $startTime;

        if ($output === null || trim($output) === '') {
            return ['reachable' => false, 'time_ms' => null, 'error' => 'No response'];
        }

        // Parse response time
        $timeMs = null;
        if (preg_match('/[=<]([0-9.]+)\s*ms/i', $output, $matches)) {
            $timeMs = (float) $matches[1];
        } elseif (preg_match('/time[=<]([0-9.]+)/i', $output, $matches)) {
            $timeMs = (float) $matches[1];
        } elseif ($elapsed > 0 && $elapsed < $timeoutSec + 1) {
            $timeMs = round($elapsed * 1000, 1);
        }

        $alive = str_contains(strtolower($output), 'ttl=') ||
                 str_contains(strtolower($output), 'bytes from') ||
                 str_contains(strtolower($output), 'reply from');

        return [
            'reachable' => $alive,
            'time_ms'   => $timeMs,
            'error'     => $alive ? null : 'Host unreachable'
        ];
    }

    /**
     * Scan specified ports on a host
     */
    public static function scanPorts(string $ip, array $ports = [], int $timeoutSec = 2): array
    {
        if (empty($ports)) {
            $ports = [22, 80, 443, 3389, 5900];
        }

        if (!self::validateIp($ip)) {
            return [];
        }

        $openPorts = [];

        foreach ($ports as $port) {
            if (PHP_OS_FAMILY === 'Windows') {
                $result = @shell_exec(
                    sprintf('powershell -Command "Test-NetConnection -ComputerName %s -Port %d -WarningAction SilentlyContinue -InformationLevel Quiet" 2>NUL',
                        escapeshellarg($ip), (int) $port)
                );
                if (trim($result) === 'True') {
                    $openPorts[] = (int) $port;
                }
            } else {
                $connection = @fsockopen($ip, (int) $port, $errno, $errstr, $timeoutSec);
                if ($connection) {
                    $openPorts[] = (int) $port;
                    fclose($connection);
                }
            }
        }

        return $openPorts;
    }

    /**
     * Get a human-readable service name for common ports
     */
    public static function portServiceName(int $port): string
    {
        $services = [
            21    => 'FTP',
            22    => 'SSH',
            23    => 'Telnet',
            25    => 'SMTP',
            53    => 'DNS',
            80    => 'HTTP',
            110   => 'POP3',
            143   => 'IMAP',
            443   => 'HTTPS',
            445   => 'SMB',
            993   => 'IMAPS',
            995   => 'POP3S',
            3306  => 'MySQL',
            3389  => 'RDP',
            5432  => 'PostgreSQL',
            5900  => 'VNC',
            6379  => 'Redis',
            8080  => 'HTTP-Alt',
            8443  => 'HTTPS-Alt',
            9100  => 'Printer',
        ];
        return $services[$port] ?? "Port $port";
    }

    /**
     * Generate a list of IPs in a range
     */
    public static function ipRange(string $start, string $end): array
    {
        $startLong = ip2long($start);
        $endLong = ip2long($end);

        if ($startLong === false || $endLong === false) {
            return [];
        }

        // Limit to 512 hosts max to prevent abuse
        $count = $endLong - $startLong + 1;
        if ($count > 512) {
            return [];
        }

        $ips = [];
        for ($i = $startLong; $i <= $endLong; $i++) {
            $ips[] = long2ip($i);
        }

        return $ips;
    }

    /**
     * Check if a command-line tool exists
     */
    private static function commandExists(string $command): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = "where $command 2>NUL";
        } else {
            $cmd = "command -v $command 2>/dev/null";
        }
        return !empty(shell_exec($cmd));
    }
}
