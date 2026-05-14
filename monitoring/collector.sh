#!/bin/bash
# ============================================================
# RedWolf IT Ops Suite - System Metrics Collector
# Collects CPU, memory, disk, network, and process metrics
# Outputs JSON lines to /var/log/redwolf/metrics/<date>.jsonl
# Designed to run every 60 seconds via cron
# ============================================================
set -euo pipefail

# Configuration
METRICS_DIR="/var/log/redwolf/metrics"
RETENTION_DAYS=7
LOG_FILE="/var/log/redwolf/collector.log"
TIMESTAMP="$(date '+%Y-%m-%dT%H:%M:%S%z')"
DATE_FILE="$(date '+%Y-%m-%d')"
METRICS_FILE="${METRICS_DIR}/${DATE_FILE}.jsonl"

# Ensure metrics directory exists
mkdir -p "$METRICS_DIR" 2>/dev/null || {
    echo "[$TIMESTAMP] ERROR: Cannot create metrics directory: $METRICS_DIR" >> "$LOG_FILE" 2>/dev/null
    exit 1
}

# ============================================================
# Log rotation: delete files older than RETENTION_DAYS
# ============================================================
cleanup_old_files() {
    find "$METRICS_DIR" -name "*.jsonl" -type f -mtime "+${RETENTION_DAYS}" -delete 2>/dev/null || true
}

# ============================================================
# Collect CPU idle percentage using /proc/stat
# Returns CPU idle % (100 - used%)
# ============================================================
collect_cpu_idle() {
    local idle_pct
    if [ -f /proc/stat ]; then
        # Read first and second CPU stat snapshots 1 second apart
        local cpu_line1 cpu_line2
        cpu_line1=$(head -1 /proc/stat | awk '{print $2,$3,$4,$5,$6,$7,$8}')
        sleep 1
        cpu_line2=$(head -1 /proc/stat | awk '{print $2,$3,$4,$5,$6,$7,$8}')

        local idle1 idle2 total1 total2
        idle1=$(echo "$cpu_line1" | awk '{print $4+$5}')
        idle2=$(echo "$cpu_line2" | awk '{print $4+$5}')
        total1=$(echo "$cpu_line1" | awk '{s=0; for(i=1;i<=NF;i++) s+=$i; print s}')
        total2=$(echo "$cpu_line2" | awk '{s=0; for(i=1;i<=NF;i++) s+=$i; print s}')

        local diff_idle=$((idle2 - idle1))
        local diff_total=$((total2 - total1))

        if [ "$diff_total" -gt 0 ]; then
            idle_pct=$(awk "BEGIN {printf \"%.1f\", ($diff_idle/$diff_total)*100}")
        else
            idle_pct="0.0"
        fi
    else
        # Fallback: use mpstat if /proc/stat is unavailable (non-Linux)
        idle_pct=$(mpstat 1 1 2>/dev/null | tail -1 | awk '{print $NF}' || echo "0.0")
    fi
    echo "$idle_pct"
}

# ============================================================
# Collect memory usage percentage
# ============================================================
collect_memory_usage() {
    local mem_pct
    if [ -f /proc/meminfo ]; then
        local mem_total mem_available
        mem_total=$(awk '/^MemTotal:/ {print $2}' /proc/meminfo)
        mem_available=$(awk '/^MemAvailable:/ {print $2}' /proc/meminfo)
        if [ -n "$mem_total" ] && [ "$mem_total" -gt 0 ] && [ -n "$mem_available" ]; then
            mem_pct=$(awk "BEGIN {printf \"%.1f\", (($mem_total-$mem_available)/$mem_total)*100}")
        else
            mem_pct="0.0"
        fi
    else
        # Fallback for non-Linux systems
        mem_pct=$(vm_stat 2>/dev/null | awk '/Pages free/ {free=$3} /Pages active/ {active=$3} /Pages inactive/ {inactive=$3} /Pages wired/ {wired=$3} END {total=free+active+inactive+wired; used=active+wired; printf "%.1f", (used/total)*100}' || echo "0.0")
    fi
    echo "$mem_pct"
}

# ============================================================
# Collect disk usage percentage (root partition)
# ============================================================
collect_disk_usage() {
    local disk_pct
    disk_pct=$(df / 2>/dev/null | awk 'NR==2 {print $5}' | tr -d '%' || echo "0")
    echo "$disk_pct"
}

# ============================================================
# Collect network I/O in bytes (in/out) from /proc/net/dev
# ============================================================
collect_network_io() {
    local net_in="0"
    local net_out="0"

    if [ -f /proc/net/dev ]; then
        # Sum bytes across all network interfaces (excluding lo)
        net_in=$(awk 'NR>2 && $1 !~ /:/ {
            split($1, iface, ":");
            if (iface[1] != "lo") sum += $2
        } END {print sum+0}' /proc/net/dev)

        net_out=$(awk 'NR>2 && $1 !~ /:/ {
            split($1, iface, ":");
            if (iface[1] != "lo") sum += $10
        } END {print sum+0}' /proc/net/dev)
    else
        # Fallback: use netstat or ifconfig
        net_in=$(netstat -ib 2>/dev/null | awk 'NR>1 && $1 !~ /lo/ {sum+=$7} END {print sum+0}' || echo "0")
        net_out=$(netstat -ob 2>/dev/null | awk 'NR>1 && $1 !~ /lo/ {sum+=$10} END {print sum+0}' || echo "0")
    fi

    echo "{\"in\": $net_in, \"out\": $net_out}"
}

# ============================================================
# Collect top 10 processes by CPU usage
# ============================================================
collect_top_processes() {
    local processes
    processes=$(ps aux --sort=-%cpu 2>/dev/null | head -11 | tail -10 | awk '{
        pid=$2;
        name=$11;
        # Truncate process name to 50 chars
        if (length(name) > 50) name=substr(name, 1, 50);
        cpu=$3;
        mem=$4;
        state=$8;
        printf "{\"pid\": %d, \"name\": \"%s\", \"cpu\": %.1f, \"mem\": %.1f, \"state\": \"%s\"},", pid, name, cpu, mem, state
    }' | sed 's/,$//')

    if [ -z "$processes" ]; then
        echo "[]"
    else
        echo "[$processes]"
    fi
}

# ============================================================
# Main collection routine
# ============================================================
main() {
    cleanup_old_files

    # Collect all metrics
    local cpu_idle mem_usage disk_usage net_io top_procs
    local cpu_used hostname_val uptime_secs

    cpu_idle=$(collect_cpu_idle)
    cpu_used=$(awk "BEGIN {printf \"%.1f\", 100 - $cpu_idle}")
    mem_usage=$(collect_memory_usage)
    disk_usage=$(collect_disk_usage)
    net_io=$(collect_network_io)
    top_procs=$(collect_top_processes)
    hostname_val=$(hostname 2>/dev/null || echo "unknown")
    uptime_secs=$(cat /proc/uptime 2>/dev/null | awk '{print int($1)}' || echo "0")

    # Build JSON output
    local json_output
    json_output=$(cat <<EOF
{"timestamp":"${TIMESTAMP}","hostname":"${hostname_val}","uptime_seconds":${uptime_secs},"cpu_used_percent":${cpu_used},"cpu_idle_percent":${cpu_idle},"memory_usage_percent":${mem_usage},"disk_usage_percent":${disk_usage},"network_io":${net_io},"top_processes":${top_procs}}
EOF
    )

    # Append to daily JSONL file
    echo "$json_output" >> "$METRICS_FILE"

    # Log success (suppress in normal operation)
    # echo "[$TIMESTAMP] Metrics collected successfully" >> "$LOG_FILE" 2>/dev/null
}

# Execute main with error trapping
main || {
    echo "[$(date '+%Y-%m-%dT%H:%M:%S%z')] ERROR: Metrics collection failed" >> "$LOG_FILE" 2>/dev/null
    exit 1
}
