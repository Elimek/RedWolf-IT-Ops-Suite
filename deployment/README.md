# Deployment Scripts

Module-by-module deployment scripts for the RedWolf IT Ops Suite.

## Scripts
- `chunk1-core.sh` - Deploy Magento Lite (web + db)
- `chunk2-monitoring.sh` - Deploy monitoring system (cron + metrics)
- `chunk3-ai.sh` - Deploy AI classifier (Ollama + FastAPI)
- `chunk4-office.sh` - Deploy office tools (sudoers + directories)

## Usage
Each script can be run independently after Chunk 1 (which sets up the database):
```bash
bash deployment/chunk1-core.sh    # Run first
bash deployment/chunk2-monitoring.sh
bash deployment/chunk3-ai.sh
bash deployment/chunk4-office.sh
```

Or use the master deploy script:
```bash
bash deploy-all.sh
```
