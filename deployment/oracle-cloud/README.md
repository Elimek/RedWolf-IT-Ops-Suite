# RedWolf IT Ops Suite - Remote Deployment Quick Start

## Overview

Deploy the entire RedWolf IT Ops Suite to an Oracle Cloud free ARM server and access it from your browser in Hong Kong. No Docker needed on your Windows machine.

**Total time from zero to live: ~50 minutes**

---

## Phase 1: Get Your Cloud Server (30 min)

### 1.1 Register Oracle Cloud

See `SIGNUP_GUIDE.md` for detailed registration steps.

Quick checklist:
- [ ] Register at https://www.oracle.com/cloud/free/
- [ ] Verify email + HKID + phone
- [ ] Add payment card (not charged for free tier)
- [ ] Wait for approval email (usually <15 min)

### 1.2 Create Instance

- [ ] Create VCN: `redwolf-vcn` (CIDR 10.0.0.0/16)
- [ ] Open ports in Security List: **22, 80, 8080, 11434**
- [ ] Create SSH key: `ssh-keygen -t ed25519 -f $env:USERPROFILE\.ssh\redwolf-oracle -N ""`
- [ ] Create instance: Ubuntu 22.04 ARM, 4 OCPU, 24GB RAM
- [ ] Note the **Public IP**

---

## Phase 2: Upload Project Files (5 min)

### Option A: SCP Upload (Recommended)

Open PowerShell on your Windows machine:

```powershell
# Compress the project
cd C:\Users\Elimek
Compress-Archive -Path RedWolf-IT-Ops-Suite -DestinationPath RedWolf-IT-Ops-Suite.zip -Force

# Upload to server (replace <IP> with your public IP)
scp -i $env:USERPROFILE\.ssh\redwolf-oracle RedWolf-IT-Ops-Suite.zip ubuntu@<IP>:~/

# Clean up local zip
Remove-Item RedWolf-IT-Ops-Suite.zip
```

### Option B: Git Clone

If you've pushed the project to GitHub:

```powershell
ssh -i $env:USERPROFILE\.ssh\redwolf-oracle ubuntu@<IP>
# On the server:
git clone https://github.com/YOUR_USERNAME/RedWolf-IT-Ops-Suite.git ~/RedWolf-IT-Ops-Suite
```

---

## Phase 3: Deploy (10 min)

SSH into your server and run two commands:

```bash
# Connect
ssh -i $env:USERPROFILE\.ssh\redwolf-oracle ubuntu@<IP>

# Extract if uploaded as zip
cd ~
unzip -o RedWolf-IT-Ops-Suite.zip
cd RedWolf-IT-Ops-Suite

# Initialize server (install Docker, configure firewall)
bash deployment/oracle-cloud/init-server.sh

# Activate Docker group (required)
newgrp docker

# Deploy everything
bash deployment/oracle-cloud/deploy.sh

# Run acceptance tests
bash deployment/oracle-cloud/verify.sh
```

---

## Phase 4: Verify in Browser (2 min)

Open your browser and check each module:

| Module | URL | What to check |
|--------|-----|---------------|
| **Landing Page** | `http://<IP>` | 4 module cards visible |
| **Magento Lite** | `http://<IP>/product.php` | 12 airsoft products listed |
| **Monitoring** | `http://<IP>/monitoring/dashboard.php` | Chart.js dashboard with metrics |
| **AI Classifier** | `http://<IP>/ai_agent/classifier.html` | Ticket classification form |
| **Office Tools** | `http://<IP>/office_tools/network_scanner.php` | Network scanning tool |
| **phpMyAdmin** | `http://<IP>:8080` | DB admin (user: redwolf) |

---

## Phase 5: AI Model Download (Background, 5-15 min)

The Ollama model (qwen2.5:7b, ~4.7GB) downloads in the background during deploy.

Check progress:
```bash
ssh -i $env:USERPROFILE\.ssh\redwolf-oracle ubuntu@<IP>
docker logs redwolf-ollama --tail 10
```

Once downloaded, the AI classifier will use full LLM inference instead of keyword fallback.

---

## Troubleshooting

### Can't access website?
```bash
# On server - check services
docker compose ps

# Check if ports are open in OCI Console
# Networking > VCN > Security Lists > Ingress Rules
# Must have: TCP 80, TCP 8080 from 0.0.0.0/0
```

### MySQL not starting?
```bash
docker compose logs db --tail 50
```

### Ollama model not downloading?
```bash
# Manual pull
docker exec redwolf-ollama ollama pull qwen2.5:7b
```

### Need to re-deploy after code changes?
```bash
cd ~/RedWolf-IT-Ops-Suite
bash deployment/oracle-cloud/deploy.sh
```

### Want to tear down everything?
```bash
cd ~/RedWolf-IT-Ops-Suite
docker compose down -v
```

---

## Cost Monitoring

Oracle Cloud Free Tier includes:
- 1 ARM instance (4 OCPU, 24GB RAM)
- 200GB block storage
- 10TB outbound/month

**Check costs:**
1. OCI Console > Billing > Cost Analysis
2. No surprises if you only use the ARM shape

**To avoid charges:**
- Never create Intel/AMD instances
- Never exceed 200GB total storage
- Never add paid services (Load Balancer, Object Storage, etc.)

---

## Demo Day Checklist

Before the interview:

- [ ] Run `verify.sh` - all tests pass
- [ ] Open each URL in browser - all pages load
- [ ] Test Magento Lite: add product to cart, check stock
- [ ] Test Monitoring: view dashboard with charts
- [ ] Test AI Classifier: submit a test ticket
- [ ] Test Office Tools: run network scan
- [ ] Prepare talking points from `SCRIPT.md`
- [ ] Review `docs/INTERVIEW_QA.md`
- [ ] Have `VALUE.md` numbers ready (HK$421K savings)
