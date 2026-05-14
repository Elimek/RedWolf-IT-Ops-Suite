# Troubleshooting Guide

> Common issues and solutions for the RedWolf IT Ops Suite

---

## 1. Docker Issues

### Q: Docker daemon not running
**A:**
```bash
# Linux
sudo systemctl start docker
sudo systemctl enable docker

# macOS/Windows
# Open Docker Desktop and wait for it to start
```

### Q: Port 80 already in use
**A:** Change `WEB_PORT` in `.env`:
```
WEB_PORT=8081
```
Then access at `http://localhost:8081`

### Q: MySQL container keeps restarting
**A:** Check logs:
```bash
docker logs redwolf-db
```
Common cause: corrupt data volume. Reset:
```bash
docker compose down -v
docker compose up -d
```

---

## 2. Database Issues

### Q: "Access denied for user 'redwolf'"
**A:** Verify credentials in `.env` match what was used during setup:
```bash
grep DB_ .env
```

### Q: Products table is empty
**A:** Re-import the schema:
```bash
docker exec -i redwolf-db mysql -u root -proot_secret redwolf < sql/schema.sql
```

### Q: "SQLSTATE[HY000] [2002] Connection refused"
**A:** MySQL hasn't started yet. Wait 30 seconds and retry, or check:
```bash
docker compose ps
docker logs redwolf-db --tail 20
```

### Q: Concurrent stock test fails
**A:** This is expected if running outside Docker. Row-level locking requires InnoDB engine. Verify:
```sql
SHOW TABLE STATUS WHERE Name = 'products';
-- Engine should be InnoDB
```

---

## 3. PHP Issues

### Q: "Class 'PDO' not found"
**A:** Install MySQL extension:
```bash
# Docker
# Already included in the Docker image

# Ubuntu
sudo apt install php8.2-mysql
sudo systemctl restart php8.2-fpm
```

### Q: PHP session not working
**A:** Check session save path permissions:
```bash
# In php.ini or .user.ini
session.save_path = /tmp
# Ensure /tmp is writable
```

### Q: File upload not working
**A:** Check `upload_max_filesize` and `post_max_size` in PHP config:
```bash
php -i | grep upload_max
```

---

## 4. Magento Lite

### Q: Product page shows 500 error
**A:** Check PHP error logs:
```bash
docker logs redwolf-php --tail 50
```
Common cause: database connection failed.

### Q: Currency switcher not working
**A:** Check JavaScript console in browser DevTools. Ensure `script.js` is loaded and no 404 errors on assets.

### Q: Add to cart fails with CSRF error
**A:** Sessions may not be persisting. Check:
1. PHP session save path is writable
2. Browser allows cookies
3. No ad blocker blocking cookies

### Q: Stock goes negative
**A:** This should not happen with row-level locking. Verify:
1. Products table uses InnoDB engine
2. `updateStock()` uses `SELECT ... FOR UPDATE`
3. Transaction is committed (not rolled back)

---

## 5. Monitoring System

### Q: Dashboard shows no data
**A:** The collector.sh may not have run yet. Run manually:
```bash
bash monitoring/collector.sh
```
Then refresh the dashboard.

### Q: Collector.sh permission denied
**A:**
```bash
chmod +x monitoring/collector.sh
```

### Q: Chart.js not loading
**A:** Check internet connection (Chart.js is loaded from CDN). For offline use, download Chart.js locally and update the `<script src>` tag.

### Q: Cron job not running
**A:** Verify cron is configured:
```bash
crontab -l | grep collector
```
If empty, add manually:
```bash
(crontab -l 2>/dev/null; echo "* * * * * $(pwd)/monitoring/collector.sh >> /var/log/redwolf/collector.log 2>&1") | crontab -
```

### Q: Fault simulator buttons don't work
**A:** Requires admin login. Default credentials are in `.env`:
- Username: `admin`
- Password: `changeme`

---

## 6. AI Classifier

### Q: "Ollama service unreachable"
**A:** Ensure Ollama container is running:
```bash
docker compose up -d ollama
docker logs redwolf-ollama
```

### Q: Model not found
**A:** Pull the model:
```bash
curl http://localhost:11434/api/pull -d '{"name": "qwen2.5:7b"}'
```
Wait for download to complete (may take several minutes).

### Q: Classification returns wrong results
**A:** Check the prompt template in `ai_agent/prompt_template.txt`. Ensure it specifies the exact category names and output format.

### Q: FastAPI not starting
**A:** Check Python dependencies:
```bash
pip install fastapi uvicorn requests
python ai_agent/core/classifier.py
```

### Q: Response time > 3 seconds
**A:** This may happen on first run (model loading). Subsequent calls should be faster. If persistent:
1. Check available RAM (model needs ~4GB)
2. Try a smaller model: change `OLLAMA_MODEL` in `.env`
3. Use keyword fallback (instant, no LLM needed)

---

## 7. Office Tools

### Q: Network scanner says "invalid IP range"
**A:** Only private IP ranges are allowed (192.168.x.x, 10.x.x.x, 172.16-31.x.x). Public IPs are blocked for security.

### Q: Port scan shows all ports closed
**A:** This tool runs inside Docker, which may not have network access to the host. For accurate results, run on a native Linux installation.

### Q: VPN status always shows "disconnected"
**A:** VPN detection requires system-level access. Inside Docker, VPN interfaces are not visible. Run on a native system.

### Q: Printer error code not found
**A:** The database has ~30 common codes. For uncommon codes, check the manufacturer's website directly.

---

## 8. Testing

### Q: "bash tests/run_all.sh" shows all SKIP
**A:** Test scripts may not be executable:
```bash
chmod +x tests/run_all.sh tests/unit/*.sh tests/chunk*/test_suite.sh
```

### Q: Integration tests fail with "Connection refused"
**A:** Ensure Docker services are running:
```bash
docker compose up -d
bash deploy-all.sh
```

### Q: Performance tests show slow results
**A:** This is expected on low-resource machines. The benchmarks are calibrated for a standard development machine.

---

## 9. General

### Q: How to reset everything?
**A:**
```bash
docker compose down -v  # Remove containers and volumes
rm .env                  # Remove config
cp .env.example .env     # Reset config
bash deploy-all.sh       # Fresh start
```

### Q: How to add a new product?
**A:**
```sql
INSERT INTO products (name, price_hkd, price_usd, price_cny, stock_qty, category, sku)
VALUES ('New Product', 1000.00, 127.88, 925.93, 50, 'rifles', 'RW-NEW-001');
```

### Q: Where are logs stored?
| Log | Location |
|-----|----------|
| Nginx access | Docker: `docker logs redwolf-web` |
| PHP errors | Docker: `docker logs redwolf-php` |
| MySQL | Docker: `docker logs redwolf-db` |
| Metrics | `/var/log/redwolf/metrics/` (host) |
| Alerts | MySQL `alerts` table |
| Audit | MySQL `audit_log` table |
| Collector | `/var/log/redwolf/collector.log` |

### Q: How to change the admin password?
**A:** Edit `.env`:
```
ADMIN_PASS=your_new_password
```
Then restart: `docker compose restart`
