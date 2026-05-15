# RedWolf IT Operations Suite

红狼信息技术运维套件 — PHP 8.2 + MySQL 8.0 + Docker + AI

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker)
![Tests](https://img.shields.io/badge/tests-5%2F5-passing-success?style=flat-square)

---

## Architecture

| Module | Stack | Responsibility |
|--------|-------|----------------|
| Magento Lite | PHP + MySQL | Product catalog, cart, inventory with `SELECT FOR UPDATE` concurrency |
| Monitoring | PHP + Cron | Server health dashboard, real-time metrics |
| AI Agent | Ollama + Qwen2.5 | Ticket classification (85%+ accuracy), prompt-engineered |
| Office Tools | PHP | Employee directory, leave management, wiki |

---

## Quick Start

```bash
docker compose up -d
# → http://localhost:8080
```

---

## Testing

```bash
./test.sh
```

Coverage: service health, database, inventory concurrency, AI classification.

---

## Value

Monthly savings vs SaaS alternatives: **HK$23,600** (ROI ~475%)  
See [`VALUE.md`](./VALUE.md).

---

## License

MIT