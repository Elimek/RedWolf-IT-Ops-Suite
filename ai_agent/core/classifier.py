"""
RedWolf IT Ops - AI Ticket Classifier Service.

FastAPI application that classifies IT support tickets using Ollama LLM
with keyword-based fallback for resilience.

Architecture:
    Browser (classifier.html) -> PHP API (api_endpoint.php) -> This Service -> Ollama (qwen2.5:7b)
    Fallback: Browser -> PHP API -> This Service -> Keyword Classifier
"""

from __future__ import annotations

import json
import logging
import time
from pathlib import Path
from typing import Any

import requests as http_requests
from fastapi import FastAPI, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

from keyword_classifier import classify as keyword_classify

# --- Configuration ---
OLLAMA_URL = "http://localhost:11434/api/generate"
MODEL_NAME = "qwen2.5:7b"
TIMEOUT_SECONDS = 5.0
PROMPT_FILE = Path(__file__).resolve().parent.parent / "prompt_template.txt"

# --- Logging ---
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger("classifier")

# --- In-memory cache ---
classification_cache: dict[str, dict[str, Any]] = {}

# --- Load prompt template ---
def _load_prompt_template() -> str:
    """Load the system prompt from the template file."""
    try:
        return PROMPT_FILE.read_text(encoding="utf-8")
    except FileNotFoundError:
        logger.warning("Prompt template not found at %s, using built-in fallback", PROMPT_FILE)
        return "You are an IT support ticket classifier. Classify the ticket and return JSON."


SYSTEM_PROMPT: str = _load_prompt_template()

# --- FastAPI app ---
app = FastAPI(
    title="RedWolf AI Ticket Classifier",
    description="Classifies IT support tickets using local LLM with keyword fallback",
    version="1.0.0",
)


# --- Request / Response models ---
class ClassifyRequest(BaseModel):
    text: str = Field(..., min_length=1, max_length=2000, description="Ticket description text")
    ticket_id: str = Field(..., min_length=1, max_length=100, description="Unique ticket identifier")


class ClassifyResponse(BaseModel):
    ticket_id: str
    category: str
    confidence: float
    reasoning: str
    priority: str
    classifier: str
    response_time_ms: float


# --- Core classification logic ---
def _call_ollama(text: str) -> dict[str, Any] | None:
    """Call the Ollama API to classify a ticket using the local LLM.

    Returns parsed JSON dict if successful, None if Ollama is unavailable.
    """
    payload = {
        "model": MODEL_NAME,
        "prompt": f"{SYSTEM_PROMPT}\n\n---\nTICKET:\n{text}\n---\nCLASSIFICATION:",
        "stream": False,
        "options": {
            "temperature": 0.1,
            "num_predict": 150,
        },
    }

    try:
        response = http_requests.post(
            OLLAMA_URL,
            json=payload,
            timeout=TIMEOUT_SECONDS,
        )
        response.raise_for_status()
        data = response.json()
        raw_output: str = data.get("response", "").strip()

        # Extract JSON from the response (handle possible markdown wrapping)
        json_match = json.loads(raw_output)
        return json_match

    except http_requests.exceptions.Timeout:
        logger.warning("Ollama request timed out after %.1fs", TIMEOUT_SECONDS)
        return None
    except http_requests.exceptions.ConnectionError:
        logger.warning("Ollama service unreachable at %s", OLLAMA_URL)
        return None
    except (json.JSONDecodeError, ValueError) as e:
        logger.warning("Failed to parse Ollama JSON response: %s", e)
        return None
    except http_requests.exceptions.HTTPError as e:
        logger.warning("Ollama HTTP error: %s", e)
        return None


def _validate_result(result: dict[str, Any]) -> dict[str, Any]:
    """Validate and sanitize a classification result.

    Ensures required fields exist with valid values.
    """
    valid_categories = {
        "hardware", "software", "network", "security",
        "access_request", "printer", "vpn", "email", "other",
    }
    valid_priorities = {"high", "medium", "low"}

    category = result.get("category", "other")
    if category not in valid_categories:
        category = "other"

    confidence = result.get("confidence", 0.5)
    if not isinstance(confidence, (int, float)):
        confidence = 0.5
    confidence = max(0.0, min(1.0, float(confidence)))

    reasoning = result.get("reasoning", "")
    if not isinstance(reasoning, str):
        reasoning = str(reasoning)
    if len(reasoning) > 200:
        reasoning = reasoning[:197] + "..."

    priority = result.get("priority", "medium")
    if priority not in valid_priorities:
        priority = "medium"

    return {
        "category": category,
        "confidence": round(confidence, 2),
        "reasoning": reasoning,
        "priority": priority,
    }


def classify_ticket(text: str, ticket_id: str) -> tuple[dict[str, Any], str, float]:
    """Classify a ticket, trying Ollama first then falling back to keyword classifier.

    Args:
        text: Ticket description.
        ticket_id: Unique ticket identifier.

    Returns:
        Tuple of (classification_result, classifier_used, response_time_ms).
    """
    start_ms = time.monotonic() * 1000

    # Try Ollama first
    ollama_result = _call_ollama(text)
    elapsed_ms = time.monotonic() * 1000 - start_ms

    if ollama_result is not None:
        validated = _validate_result(ollama_result)
        validated["classifier"] = "ollama"
        validated["response_time_ms"] = round(elapsed_ms, 1)
        logger.info(
            "Ollama classified ticket %s as '%s' (%.1fms)",
            ticket_id, validated["category"], elapsed_ms,
        )
        return validated, "ollama", elapsed_ms

    # Fallback to keyword classifier
    logger.info("Ollama unavailable, using keyword fallback for ticket %s", ticket_id)
    fallback_start_ms = time.monotonic() * 1000
    keyword_result = keyword_classify(text)
    fallback_elapsed = time.monotonic() * 1000 - fallback_start_ms

    keyword_result["classifier"] = "keyword"
    keyword_result["response_time_ms"] = round(elapsed_ms + fallback_elapsed, 1)

    logger.info(
        "Keyword fallback classified ticket %s as '%s' (%.1fms)",
        ticket_id, keyword_result["category"], keyword_result["response_time_ms"],
    )
    return keyword_result, "keyword", elapsed_ms + fallback_elapsed


# --- API endpoints ---
@app.post("/classify", response_model=ClassifyResponse)
async def classify_endpoint(request: ClassifyRequest) -> JSONResponse:
    """Classify an IT support ticket.

    Accepts ticket text and ID, returns classification with category,
    confidence, reasoning, and priority.
    """
    result, classifier_used, elapsed_ms = classify_ticket(request.text, request.ticket_id)

    # Cache the result
    classification_cache[request.ticket_id] = {
        **result,
        "ticket_id": request.ticket_id,
        "timestamp": time.time(),
    }

    response_data = ClassifyResponse(
        ticket_id=request.ticket_id,
        category=result["category"],
        confidence=result["confidence"],
        reasoning=result["reasoning"],
        priority=result["priority"],
        classifier=classifier_used,
        response_time_ms=result["response_time_ms"],
    )

    logger.info(
        "Classification complete: ticket=%s category=%s confidence=%.2f classifier=%s",
        request.ticket_id, result["category"], result["confidence"], classifier_used,
    )

    return JSONResponse(content=response_data.model_dump())


@app.get("/health")
async def health_check() -> dict[str, str]:
    """Health check endpoint."""
    return {"status": "ok", "model": MODEL_NAME}


@app.get("/history")
async def get_history() -> dict[str, Any]:
    """Return all cached classification results."""
    return {
        "total": len(classification_cache),
        "classifications": list(classification_cache.values()),
    }


if __name__ == "__main__":
    import uvicorn
    logger.info("Starting RedWolf AI Ticket Classifier on port 8001")
    uvicorn.run(app, host="0.0.0.0", port=8001)
