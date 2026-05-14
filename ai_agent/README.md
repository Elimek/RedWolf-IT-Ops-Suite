# AI Ticket Classifier

Local AI-powered IT ticket classification system with privacy-first architecture.

## JD Alignment
- Demonstrates **AI/ML integration** skills
- Shows understanding of **API design** (PHP-FastAPI bridge)
- Proves ability to **deploy and manage AI services** on-premise
- Demonstrates **privacy-conscious** IT practices

## Architecture
```
Browser (classifier.html)
    -> POST /ai_agent/api_endpoint.php
        -> curl http://python:8001/classify
            -> Ollama (qwen2.5:7b) on localhost:11434
```

## Features
- Local LLM inference (data never leaves the network)
- Keyword fallback when Ollama is unavailable
- Response caching for common queries
- Accuracy testing with 50+ labeled samples
- < 3s response time target

## Files
```
ai_agent/
├── core/
│   └── classifier.py      # FastAPI classification service
├── api_endpoint.php       # PHP bridge to Python service
├── classifier.html        # Web UI for ticket classification
├── prompt_template.txt    # LLM system prompt
├── data/
│   └── test.json          # Labeled test samples
└── test_accuracy.py       # Accuracy benchmark script
```
