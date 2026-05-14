# Deployment Guide

## Prerequisites

- Docker 20.10+ and Docker Compose v2+
- Git
- 4GB+ RAM available
- Ports 80, 3306, 8080, 11434, 8001 available

---

## Option 1: Docker Compose (Recommended)

### Step 1: Clone and Configure

```bash
git clone https://github.com/YOUR_USERNAME/RedWolf-IT-Ops-Suite.git
cd RedWolf-IT-Ops-Suite
cp .env.example .env
# Edit .env with your credentials
nano .env
```

### Step 2: One-Click Deploy

```bash
bash deploy-all.sh
```

This will:
1. Check Docker environment
2. Start web + PHP + MySQL + phpMyAdmin + Ollama
3. Import database schema and seed data
4. Deploy all 4 modules
5. Run all tests
6. Generate deployment report

### Step 3: Verify

```bash
# Check services
docker compose ps

# Check web
curl -s -o /dev/null -w "%{http_code}" http://localhost

# Check phpMyAdmin
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080
```

---

## Option 2: Traditional Server (Ubuntu 22.04)

### Install Dependencies

```bash
sudo apt update
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql \
    php8.2-curl php8.2-mbstring php8.2-xml sysstat
```

### Configure Nginx

```bash
sudo cp docker/nginx/default.conf /etc/nginx/sites-available/redwolf
sudo ln -s /etc/nginx/sites-available/redwolf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Configure MySQL

```bash
sudo mysql -e "CREATE DATABASE IF NOT EXISTS redwolf;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'redwolf'@'localhost' IDENTIFIED BY 'redwolf_secret';"
sudo mysql -e "GRANT ALL PRIVILEGES ON redwolf.* TO 'redwolf'@'localhost';"
sudo mysql redwolf < sql/schema.sql
```

### Configure PHP-FPM

```bash
sudo cp www.conf /etc/php/8.2/fpm/pool.d/www.conf
sudo systemctl restart php8.2-fpm
```

---

## Option 3: Windows Server

### Prerequisites
- Windows Server 2019+ or Windows 11
- WSL2 with Ubuntu 22.04
- Docker Desktop for Windows

### Steps

1. Enable WSL2:
```powershell
wsl --install
```

2. Install Docker Desktop and enable WSL2 backend

3. Follow Option 1 (Docker Compose) steps within WSL2

### PowerShell Tools

The `office_tools/windows_tools/` directory contains PowerShell equivalents:
- `NetworkScanner.ps1` - Network scanning
- `VpnChecker.ps1` - VPN status checking
- `PrinterConfig.ps1` - Printer diagnostics

---

## Security Hardening

### Production Checklist
- [ ] Change all default passwords in .env
- [ ] Set `APP_DEBUG=false`
- [ ] Configure firewall (only ports 80, 443)
- [ ] Enable HTTPS with Let's Encrypt
- [ ] Set up database backups
- [ ] Restrict phpMyAdmin access to admin IPs
- [ ] Review and restrict sudoers rules
- [ ] Enable fail2ban for SSH
- [ ] Set up log rotation
- [ ] Review audit logs regularly

### Firewall (UFW)

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp
sudo ufw enable
```

---

## Backup & Recovery

### Database Backup

```bash
# Daily backup cron
0 2 * * * docker exec redwolf-db mysqldump -u root -p${DB_ROOT_PASS} redwolf | gzip > /backup/redwolf_$(date +\%Y\%m\%d).sql.gz

# Keep 30 days
find /backup -name "*.sql.gz" -mtime +30 -delete
```

### Restore

```bash
gunzip < /backup/redwolf_20260101.sql.gz | docker exec -i redwolf-db mysql -u root -p${DB_ROOT_PASS} redwolf
```

---

## Disaster Recovery

1. **Database corruption**: Restore from latest backup
2. **Container failure**: `docker compose down && docker compose up -d`
3. **Full server loss**: Restore from Git + backup, run `deploy-all.sh`
4. **Ollama model lost**: Re-pull with `docker exec redwolf-ollama ollama pull qwen2.5:7b`

---

## Environment Variables

See `.env.example` for all configurable options. Key variables:

| Variable | Default | Description |
|----------|---------|-------------|
| APP_ENV | dev | Environment (dev/staging/prod) |
| APP_DEBUG | false | Enable debug mode |
| DB_PASS | redwolf_secret | MySQL password |
| ADMIN_PASS | changeme | Admin panel password |
| OLLAMA_MODEL | qwen2.5:7b | AI model name |
| CURRENCY_DEFAULT | HKD | Default currency |
| CPU_THRESHOLD | 90 | CPU alert threshold (%) |
| SMTP_HOST | (empty) | SMTP server for alerts |

---

## Estimated Deployment Time

| Method | Experienced | New Hire |
|--------|------------|----------|
| Docker Compose | 5 minutes | 15 minutes |
| Traditional Server | 30 minutes | 60 minutes |
| Windows + WSL2 | 15 minutes | 45 minutes |
