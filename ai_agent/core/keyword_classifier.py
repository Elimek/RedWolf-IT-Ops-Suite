"""
Keyword-based fallback classifier for IT support tickets.
Used when Ollama LLM is unavailable or times out.
"""

from __future__ import annotations

import re
from dataclasses import dataclass
from typing import TypedDict


class ClassificationResult(TypedDict):
    category: str
    confidence: float
    reasoning: str
    priority: str


@dataclass
class KeywordPattern:
    category: str
    patterns: list[str]
    priority: str


# Patterns ordered from most specific to least specific
KEYWORD_RULES: list[KeywordPattern] = [
    KeywordPattern(
        category="printer",
        patterns=[
            r"\bprinter\b", r"\bprint\s*(?:queue|job|spool)\b", r"\bpaper\s*jam\b",
            r"\btoner\b", r"\bcartridge\b", r"\bprintout\b", r"\bcopier\b",
            r"\bscan(?:ner|ning)?\b.*(?:not working|broken|error)", r"\bprint\s*driver\b",
        ],
        priority="medium",
    ),
    KeywordPattern(
        category="vpn",
        patterns=[
            r"\bvpn\b", r"\bvirtual\s*private\s*network\b", r"\bremote\s*access\b",
            r"\btunnel\b", r"\banyconnect\b", r"\bglobal\s*protect\b",
            r"\bcannot\s*(?:connect|dial)\b.*\b(?:remote|home|outside)\b",
        ],
        priority="medium",
    ),
    KeywordPattern(
        category="email",
        patterns=[
            r"\bemail\b", r"\bmail(?:box)?\b", r"\boutlook\b", r"\b(?:send|receive)\s*(?:mail|email)\b",
            r"\bmailbox\s*(?:full|quota)\b", r"\bdistribution\s*list\b", r"\bcalendar\b.*(?:sync|share)",
            r"\bspam\b", r"\bphishing\b", r"\bforwarding\b.*(?:email|mail)\b",
        ],
        priority="medium",
    ),
    KeywordPattern(
        category="network",
        patterns=[
            r"\binternet\b.*(?:down|slow|not working|outage)\b", r"\bwi-?fi\b", r"\bwifi\b",
            r"\blan\b", r"\bdns\b", r"\bdhcp\b", r"\bip\s*address\b", r"\bsubnet\b",
            r"\bfirewall\b", r"\bswitch\b", r"\brouter\b", r"\bcable\b.*(?:disconnect|loose|damaged)\b",
            r"\b(?:network|connection)\b.*(?:drop|down|slow|intermittent|unstable)\b",
            r"\b(?:no|cannot)\s*(?:connect|access)\b.*(?:internet|network)\b",
            r"\bbandwidth\b", r"\blatency\b", r"\bping\b.*(?:timeout|high)\b",
        ],
        priority="high",
    ),
    KeywordPattern(
        category="security",
        patterns=[
            r"\bmalware\b", r"\bvirus\b", r"\bransomware\b", r"\btrojan\b",
            r"\bsecurity\b.*(?:incident|breach|alert|violation)\b", r"\bunauthorized\s*access\b",
            r"\bphishing\b", r"\bsuspicious\b.*(?:email|activity|link|file)\b",
            r"\bpassword\b.*(?:compromised|leak|reset|stolen)\b", r"\bdata\s*breach\b",
            r"\bbrute\s*force\b", r"\bintrusion\b", r"\b(?:ddos|dos)\b",
            r"\bvulnerability\b", r"\bexploit\b",
        ],
        priority="high",
    ),
    KeywordPattern(
        category="access_request",
        patterns=[
            r"\b(?:need|request|grant|create|new)\s*(?:access|permission|account)\b",
            r"\brole\s*(?:change|request)\b", r"\buser\s*(?:account|creation)\b",
            r"\bonboard(?:ing)?\b", r"\boffboard(?:ing)?\b", r"\bterminat(?:ion|e|ed)\b.*(?:access|account)\b",
            r"\b(?:add|remove)\s*(?:user|member)\b.*(?:group|team|system)\b",
            r"\baccess\s*(?:level|role|rights)\b", r"\bpermission\s*(?:request|grant|deny)\b",
            r"\bunlock\s*(?:account|user)\b", r"\breset\s*(?:account|password)\b",
        ],
        priority="low",
    ),
    KeywordPattern(
        category="hardware",
        patterns=[
            r"\b(?:laptop|desktop|computer|pc|monitor|screen|keyboard|mouse)\b.*(?:broken|not working|dead|crash|won't start|fail|faulty)\b",
            r"\b(?:pos|point\s*of\s*sale)\s*(?:terminal|system|machine)\b",
            r"\bbarcode\s*scanner\b", r"\bhard\s*drive\b", r"\bssd\b", r"\bram\b",
            r"\bmotherboard\b", r"\bbattery\b.*(?:not charging|dead|drain)\b",
            r"\bscreen\b.*(?:crack|broken|flicker|black)\b",
            r"\b(?:blue\s*screen|bsod)\b", r"\bboot\b.*(?:fail|loop|slow)\b",
            r"\b(?:fan|overheat|overheating)\b", r"\bpower\s*(?:supply|button|cable)\b",
            r"\b(?:usb|port|hdmi|displayport)\b.*(?:not working|broken)\b",
            r"\bphone\b.*(?:broken|not working|dead)\b",
            r"\bdevice\b.*(?:hardware|physical)\b.*(?:issue|problem|fail)\b",
        ],
        priority="high",
    ),
    KeywordPattern(
        category="software",
        patterns=[
            r"\b(?:windows|macos|linux|os)\b.*(?:crash|error|freeze|slow|update|install)\b",
            r"\b(?:microsoft\s*(?:office|365)|excel|word|powerpoint|teams|sharepoint)\b",
            r"\bsoftware\b.*(?:crash|error|bug|update|install|fail|hang)\b",
            r"\b(?:app|application)\b.*(?:crash|error|not responding|freeze)\b",
            r"\b(?:inventory|warehouse|crm|erp)\s*(?:system|software|management)\b",
            r"\b(?:install|uninstall|reinstall)\b.*(?:software|program|application)\b",
            r"\bupdate\b.*(?:fail|error|stuck|pending)\b",
            r"\b(?:error|exception|crash)\s*(?:code|report|log)\b",
            r"\blicense\b.*(?:expired|invalid|activation)\b",
            r"\bplugin\b.*(?:crash|error|conflict)\b",
            r"\b(?:slow|freezing|lagging|unresponsive)\b.*(?:system|computer|program)\b",
        ],
        priority="medium",
    ),
]


def classify(text: str) -> ClassificationResult:
    """Classify a ticket using keyword pattern matching.

    Scans the input text against predefined patterns for each category.
    Returns the best matching category with confidence based on match count.

    Args:
        text: The ticket description text.

    Returns:
        A dict with category, confidence, reasoning, and priority.
    """
    text_lower = text.lower()

    best_category = "other"
    best_score = 0
    best_priority = "medium"
    matched_patterns: list[str] = []

    for rule in KEYWORD_RULES:
        score = 0
        rule_matches: list[str] = []
        for pattern in rule.patterns:
            if re.search(pattern, text_lower):
                score += 1
                rule_matches.append(pattern)

        if score > best_score:
            best_score = score
            best_category = rule.category
            best_priority = rule.priority
            matched_patterns = rule_matches

    if best_category == "other":
        reasoning = "No specific IT category patterns matched; classified as general inquiry"
    else:
        reasoning = f"Keyword match: {best_score} pattern(s) matched for {best_category}"

    # Confidence scales with number of pattern matches, maxing at 0.85 for keyword
    confidence = min(0.85, 0.4 + (best_score * 0.15))

    return ClassificationResult(
        category=best_category,
        confidence=round(confidence, 2),
        reasoning=reasoning,
        priority=best_priority,
    )
