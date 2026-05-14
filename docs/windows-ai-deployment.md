# Windows AI Deployment Guide

## RedWolf IT Ops - AI Ticket Classifier on Windows

This guide covers setting up the AI ticket classifier components (Ollama + FastAPI) on a Windows machine.

---

## Prerequisites

- Windows 10/11 (64-bit)
- 8 GB RAM minimum (16 GB recommended)
- 10 GB free disk space for the LLM model
- Administrator access

---

## Step 1: Install WSL2

Ollama runs natively on Linux. On Windows, use WSL2 as the recommended runtime.

### Enable WSL2

Open PowerShell as Administrator:

```powershell
wsl --install
```

This enables WSL2, installs the default Ubuntu distribution, and enables the Windows Hypervisor Platform.

Restart your computer when prompted.

### Verify Installation

Open your WSL2 terminal (Ubuntu) and verify:

```bash
uname -r
# Should show something like: 5.15.0-xxxx-generic
```

---

## Step 2: Install Ollama in WSL2

Inside your WSL2 terminal:

```bash
curl -fsSL https://ollama.com/install.sh | sh
```

### Pull the Model

```bash
ollama pull qwen2.5:7b
```

This downloads approximately 4.7 GB. Verify it's installed:

```bash
ollama list
# Should show: qwen2.5:7b
```

### Test Ollama

```bash
ollama run qwen2.5:7b "Hello, can you classify this IT ticket: My printer is jammed"
```

Press Ctrl+D to exit the interactive session.

---

## Step 3: Set Up Python Environment

Inside WSL2:

```bash
sudo apt update
sudo apt install -y python3 python3-pip python3-venv
```

### Create a Virtual Environment

```bash
cd /mnt/c/Users/Elimek/RedWolf-IT-Ops-Suite/ai_agent
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

---

## Step 4: Start the FastAPI Service

```bash
cd /mnt/c/Users/Elimek/RedWolf-IT-Ops-Suite/ai_agent
source venv/bin/activate
bash start.sh
```

The service starts on `http://localhost:8001`.

### Verify It Works

From another terminal:

```bash
curl http://localhost:8001/health
# Expected: {"status":"ok","model":"qwen2.5:7b"}

curl -X POST http://localhost:8001/classify \
  -H "Content-Type: application/json" \
  -d '{"text":"The printer is jammed","ticket_id":"TEST-001"}'
```

---

## Step 5: Configure PHP to Access the Service

The PHP API endpoint (`api_endpoint.php`) calls the Python service at `http://localhost:8001`.

If PHP runs on Windows (XAMPP/WAMP) and Python runs in WSL2, use the WSL2 IP address:

```bash
# In WSL2, find the IP address:
hostname -I
# Example output: 172.18.240.123
```

Update the `PYTHON_SERVICE_URL` constant in `api_endpoint.php`:

```php
define('PYTHON_SERVICE_URL', 'http://172.18.240.123:8001/classify');
```

> **Note:** The WSL2 IP address changes on each reboot. For production, configure a static IP or use a startup script.

---

## Running as Background Services

### Ollama (starts automatically)

Ollama auto-starts when WSL2 launches. To ensure WSL2 starts on boot:

```powershell
# In PowerShell (Administrator)
wsl --install --no-distribution
# Then set your default distro
wsl --set-default Ubuntu
```

### FastAPI with systemd (WSL2)

Create a systemd service file in WSL2:

```bash
sudo tee /etc/systemd/system/redwolf-classifier.service > /dev/null <<EOF
[Unit]
Description=RedWolf AI Ticket Classifier
After=network.target

[Service]
Type=simple
User=$USER
WorkingDirectory=/mnt/c/Users/Elimek/RedWolf-IT-Ops-Suite/ai_agent
ExecStart=/mnt/c/Users/Elimek/RedWolf-IT-Ops-Suite/ai_agent/venv/bin/uvicorn core.classifier:app --host 0.0.0.0 --port 8001
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable redwolf-classifier
sudo systemctl start redwolf-classifier
```

---

## Troubleshooting

### Ollama Not Starting

```bash
# Check if ollama is running
pgrep ollama

# Check logs
journalctl -u ollama -f

# Restart
sudo systemctl restart ollama
```

### FastAPI Service Errors

```bash
# Check service status
sudo systemctl status redwolf-classifier

# Check logs
journalctl -u redwolf-classifier -f

# Test manually
cd /mnt/c/Users/Elimek/RedWolf-IT-Ops-Suite/ai_agent
source venv/bin/activate
python -m uvicorn core.classifier:app --host 0.0.0.0 --port 8001
```

### WSL2 Networking Issues

If Windows cannot reach the WSL2 service:

1. Check firewall: Windows Defender may block WSL2 ports
2. Forward ports from Windows to WSL2:
   ```powershell
   netsh interface portproxy add v4tov4 listenport=8001 listenaddress=0.0.0.0 connectport=8001 connectaddress=$(wsl hostname -I)
   ```

### Out of Memory

The qwen2.5:7b model needs approximately 5 GB RAM. If your system has limited RAM:

```bash
# Check available memory
free -h

# Use a smaller model if needed
ollama pull qwen2.5:3b
# Then update MODEL_NAME in classifier.py
```

### Python Import Errors

If you see `ModuleNotFoundError`:

```bash
cd /mnt/c/Users/Elimek/RedWolf-IT-Ops-Suite/ai_agent
source venv/bin/activate
pip install -r requirements.txt
```

Ensure the `PYTHONPATH` includes the core directory when running tests:

```bash
export PYTHONPATH=/mnt/c/Users/Elimek/RedWolf-IT-Ops-Suite/ai_agent/core:$PYTHONPATH
```
