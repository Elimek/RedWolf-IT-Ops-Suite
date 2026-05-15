# 🔧 RedWolf IT Operations Suite

> All-in-one IT operations demo for **RedWolf Airsoft Specialist Ltd**
> Built with **PHP 8.2 + MySQL 8.0** — containerized with **Docker**

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=flat-square&logo=docker)
![AI](https://img.shields.io/badge/AI-Ollama+Qwen-000000?style=flat-square&logo=ollama)
![Tests](https://img.shields.io/badge/tests-5/5-passing-green?style=flat-square)

</div>

---

## 🏗️ What's Inside

| Module | Tech | What it does |
|--------|------|-------------|
| 🛒 **Magento Lite** | PHP + MySQL | Product catalog, cart, inventory with `SELECT FOR UPDATE` concurrency |
| 📊 **Monitoring** | PHP + Cron | Server health dashboard, real-time metrics |
| 🤖 **AI Agent** | Ollama + Qwen2.5 | Smart ticket classification (85%+ accuracy) |
| 📝 **Office Tools** | PHP | Employee directory, leave management, company wiki |

---

## 🚀 Quick Start

```bash
# One command to run everything
docker compose up -d

# Or manually:
php -S localhost:8080 -t public/
```

Then open http://localhost:8080

---

## 🧪 Test Suite

```bash
./test.sh
```

Covers: service health, database, inventory, AI classification, concurrency.

---

## 💰 Value

> Estimated **HK$23,600/month** saved vs. SaaS alternatives  
> ROI: **~475%**

See [`VALUE.md`](./VALUE.md) for details.

---

## 📸 Demo

![Demo Screenshot](https://via.placeholder.com/800x400?text=RedWolf+IT+Ops+Suite)

---

<div align="center">
  <sub>Interview demo project — built in &lt;3 hours 🚀</sub>
</div>
