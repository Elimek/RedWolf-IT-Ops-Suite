# Server Monitoring & Alerting System

Real-time server monitoring dashboard with alert management and fault simulation.

## JD Alignment
- Demonstrates **Linux system administration** skills
- Shows **monitoring and alerting** capability
- Proves **shell scripting** proficiency (collector, deployment)
- Shows understanding of **Nginx** web server management

## Features
- System metrics: CPU, RAM, Disk, Network
- Real-time Chart.js dashboard (60s auto-refresh)
- Alert management with cooldown (anti-spam)
- Fault simulator for testing alert triggers
- Audit logging for admin actions

## Files
```
monitoring/
├── collector.sh           # Metrics collection (cron job)
├── dashboard.php          # Real-time monitoring dashboard
├── alert_manager.php      # Alert rule engine
└── fault_simulator.php    # Admin fault injection tool
```
