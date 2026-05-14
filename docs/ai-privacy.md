# AI Privacy Architecture

## RedWolf IT Ops Suite - Privacy-First Design

---

## Overview

The RedWolf AI Ticket Classifier is designed with privacy as a fundamental requirement. All ticket data is processed entirely within the local network using a self-hosted large language model. No data ever leaves the organization's infrastructure.

---

## Data Flow

```
1. USER TYPES TICKET (Browser)
       |
       v
2. FRONTEND (classifier.html)
   - Runs in the user's browser
   - Sends ticket text via fetch() to PHP API
   - No data sent to any external service
       |
       v
3. PHP API (api_endpoint.php)
   - Receives and validates input
   - Forwards to local Python service via HTTP
   - Saves classification result to local MySQL
   - No data sent to any external service
       |
       v
4. PYTHON FASTAPI (classifier.py :8001)
   - Receives classification request
   - Calls local Ollama API
   - Falls back to keyword classifier if needed
   - Caches results in memory (volatile)
   - No data sent to any external service
       |
       v
5. OLLAMA (qwen2.5:7b :11434)
   - Runs entirely on local hardware
   - Processes prompt and generates classification
   - No internet connection required
   - No data sent to any external service
       |
       v
6. RESULT RETURNED
   - Classification flows back through the chain
   - Only the result (category, confidence, etc.) is displayed
   - Original ticket text stored only in local MySQL (if configured)
```

---

## Why Local LLM vs Cloud API

### Privacy

- **Local LLM**: Ticket text never leaves the network. IT support tickets may contain internal system details, employee names, IP addresses, and security-sensitive information. Processing this data through a cloud API (OpenAI, Google, AWS) would mean transmitting potentially sensitive data to third parties.

- **Cloud API**: Data is sent over the internet to the API provider's servers. Even with privacy policies and data processing agreements, you are trusting a third party with your data.

### Compliance

- **Data sovereignty**: All data remains on-premises, meeting requirements for data that must not cross jurisdictional boundaries.
- **No vendor lock-in**: The classifier uses open-source models (Qwen 2.5) that can be run without any vendor dependency.
- **Audit trail**: Every classification happens locally and can be logged, audited, and reviewed without relying on external logging.
- **GDPR/HIPAA considerations**: No personal data is transmitted to data processors outside the organization.

### Reliability

- **No internet dependency**: The classifier works even during internet outages.
- **Consistent latency**: No network latency from API calls to external services.
- **No rate limits**: Process as many tickets as your hardware allows.
- **No API costs**: No per-request charges. Only hardware costs.

### Trade-offs

- **Accuracy**: Local 7B parameter models may be less accurate than large cloud models (GPT-4, Claude). The keyword fallback provides a guaranteed minimum accuracy floor.
- **Hardware requirements**: Running LLMs locally requires GPU/CPU resources. A dedicated machine with 8+ GB RAM is recommended.
- **Setup complexity**: More initial setup compared to a simple API key configuration.

---

## Security Considerations

### Access Control

- The FastAPI service listens on `0.0.0.0:8001` by default. In production, configure it to listen only on `127.0.0.1` or restrict access via firewall rules.
- The PHP API endpoint should be behind authentication in production.

### Data Retention

- In-memory cache (Python): Cleared on service restart. No persistent storage of ticket text.
- MySQL storage (PHP): Ticket text is stored in the `ticket_classifications` table. Configure retention policies based on organizational requirements.
- Ollama: Does not store conversation history. Each request is processed independently.

### Network Segmentation

For optimal security:

1. Place the AI classifier service in an internal-only network segment
2. Restrict access to the classifier API endpoints to authorized PHP servers only
3. Block external access to port 8001 (FastAPI) and port 11434 (Ollama) at the firewall level

---

## Recommended Configuration for Production

```
[FIREWALL BLOCKS ALL EXTERNAL TRAFFIC TO:]

  Port 11434 (Ollama)   - Internal only
  Port 8001  (FastAPI)   - Internal only
  Port 80/443 (PHP/Web)  - Standard web access

[ACCESS PATTERN:]

  User Browser --HTTP--> Web Server (PHP)
  Web Server --HTTP--> FastAPI (localhost:8001)
  FastAPI --HTTP--> Ollama (localhost:11434)
```

---

## Compliance Summary

| Requirement | Status |
|---|---|
| No data leaves local network | Met (all processing local) |
| No external API calls | Met (Ollama runs locally) |
| No data transmitted to third parties | Met |
| Audit capability | Met (MySQL logging) |
| Data retention control | Met (local database) |
| No vendor dependency | Met (open-source model) |
