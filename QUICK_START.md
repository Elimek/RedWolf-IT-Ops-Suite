# Quick Start Guide

> Get the RedWolf IT Ops Suite running in 5 minutes

## Prerequisites

- [ ] Docker Desktop installed and running
- [ ] 4GB+ free RAM
- [ ] Git installed

## Step 1: Get the Code (30 sec)

```bash
git clone https://github.com/YOUR_USERNAME/RedWolf-IT-Ops-Suite.git
cd RedWolf-IT-Ops-Suite
```

## Step 2: Configure (30 sec)

```bash
cp .env.example .env
```

Edit `.env` to set your admin password:
```
ADMIN_PASS=your_secure_password
```

## Step 3: Deploy (3 min)

```bash
bash deploy-all.sh
```

Wait for the script to complete. It will:
- Start Docker containers
- Initialize the database
- Import 12 product entries
- Run all tests

## Step 4: Access (1 min)

Open your browser:

| Service | URL | Login |
|---------|-----|-------|
| Main Dashboard | http://localhost | None |
| E-Commerce | http://localhost/magento_lite/product.php | None |
| Monitoring | http://localhost/monitoring/dashboard.php | admin/changeme |
| AI Classifier | http://localhost/ai_agent/classifier.html | None |
| Office Tools | http://localhost/office_tools/network_scanner.php | admin/changeme |
| phpMyAdmin | http://localhost:8080 | redwolf/redwolf_secret |

## Step 5: Verify (30 sec)

```bash
bash tests/run_all.sh all
```

All tests should pass.

## What's Next?

- Read [README.md](README.md) for full documentation
- Read [SCRIPT.md](SCRIPT.md) for the demo script
- Read [VALUE.md](VALUE.md) for business value analysis
- Customize products in `sql/schema.sql`
- Adjust alert thresholds in `.env`

## Troubleshooting

**Docker not running?**
```bash
# Start Docker Desktop, wait, then retry
bash deploy-all.sh
```

**Port 80 in use?**
```bash
# Edit .env
WEB_PORT=8081
# Then access http://localhost:8081
```

**Tests failing?**
```bash
# Check service status
docker compose ps
docker compose logs
```

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for more solutions.
