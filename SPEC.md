# RedWolf IT Officer Demo - Specification

## Target Role
- **Position**: Assistant IT Officer / IT Officer
- **Company**: RedWolf Airsoft Specialist Ltd
- **Tech Stack**: PHP 8.2, MySQL 8.0, Linux (Ubuntu 22.04)

## Project Scope

### Chunk 1: Magento Lite E-Commerce
- Product showcase with grid layout
- Multi-currency support (HKD/USD/CNY)
- Cart management with session storage
- Concurrent-safe stock control (row-level locking)

### Chunk 2: Server Monitoring
- System metrics collection (CPU, RAM, Disk, Network)
- Real-time dashboard with Chart.js
- Alert management with cooldown
- Fault simulator for testing

### Chunk 3: AI Ticket Classifier
- Local LLM inference via Ollama
- Fallback to keyword matching
- PHP-FastAPI bridge architecture
- Privacy-first (no external API calls)

### Chunk 4: Office Support Tools
- Network scanner (ping + port scan)
- Printer configuration helper
- VPN status checker
- Windows PowerShell equivalents

## Non-Functional Requirements
- All code in English
- PSR-12 for PHP
- Shell scripts use `set -euo pipefail`
- No external dependencies (Composer/NPM)
- All configuration via environment variables
