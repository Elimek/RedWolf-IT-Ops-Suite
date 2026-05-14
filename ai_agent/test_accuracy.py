#!/usr/bin/env python3
"""
RedWolf AI Ticket Classifier - Accuracy Test Script.

Tests the keyword-based classifier against labeled test data
and reports per-category accuracy, precision, recall, and F1 scores.

Usage:
    python ai_agent/test_accuracy.py [--output report.txt]
"""

from __future__ import annotations

import json
import os
import sys
import time
from collections import defaultdict
from pathlib import Path
from typing import Any

# Add the core module to the path
SCRIPT_DIR = Path(__file__).resolve().parent
CORE_DIR = SCRIPT_DIR / "core"
sys.path.insert(0, str(CORE_DIR))

from keyword_classifier import classify as keyword_classify


def load_test_data(path: Path) -> list[dict[str, Any]]:
    """Load test tickets from JSON file."""
    with open(path, "r", encoding="utf-8") as f:
        return json.load(f)


def calculate_metrics(
    tp: dict[str, int],
    fp: dict[str, int],
    fn: dict[str, int],
    category: str,
) -> dict[str, float]:
    """Calculate precision, recall, and F1 for a single category."""
    precision = tp[category] / (tp[category] + fp[category]) if (tp[category] + fp[category]) > 0 else 0.0
    recall = tp[category] / (tp[category] + fn[category]) if (tp[category] + fn[category]) > 0 else 0.0
    f1 = 2 * precision * recall / (precision + recall) if (precision + recall) > 0 else 0.0
    return {
        "precision": round(precision, 4),
        "recall": round(recall, 4),
        "f1": round(f1, 4),
    }


def run_test(test_data: list[dict[str, Any]]) -> tuple[str, dict[str, Any]]:
    """Run classification on all test tickets and compute metrics.

    Returns a tuple of (report_text, summary_dict).
    """
    categories = sorted(set(t["category"] for t in test_data))

    tp: dict[str, int] = defaultdict(int)  # true positive
    fp: dict[str, int] = defaultdict(int)  # false positive
    fn: dict[str, int] = defaultdict(int)  # false negative
    correct = 0
    total = len(test_data)
    errors: list[dict[str, str]] = []

    print(f"Classifying {total} test tickets...")
    start_time = time.monotonic()

    for ticket in test_data:
        result = keyword_classify(ticket["text"])
        predicted = result["category"]
        actual = ticket["category"]

        if predicted == actual:
            tp[actual] += 1
            correct += 1
        else:
            fp[predicted] += 1
            fn[actual] += 1
            errors.append({
                "id": str(ticket["id"]),
                "actual": actual,
                "predicted": predicted,
                "text": ticket["text"][:80] + "...",
            })

    elapsed = time.monotonic() - start_time
    overall_accuracy = correct / total if total > 0 else 0.0

    # --- Build report ---
    lines: list[str] = []
    lines.append("=" * 80)
    lines.append("  RedWolf AI Ticket Classifier - Keyword Classifier Accuracy Report")
    lines.append("=" * 80)
    lines.append(f"")
    lines.append(f"  Total tickets tested : {total}")
    lines.append(f"  Correct predictions  : {correct}")
    lines.append(f"  Overall accuracy     : {overall_accuracy:.2%}")
    lines.append(f"  Classification time  : {elapsed * 1000:.1f} ms total ({elapsed * 1000 / total:.1f} ms avg)")
    lines.append(f"  Classifier used      : keyword (rule-based fallback)")
    lines.append(f"")

    # Per-category table
    lines.append(f"{'Category':<18} {'Support':>8} {'Precision':>10} {'Recall':>10} {'F1':>10}")
    lines.append("-" * 58)

    all_metrics: dict[str, dict[str, float]] = {}
    for cat in categories:
        support = tp[cat] + fn[cat]
        metrics = calculate_metrics(tp, fp, fn, cat)
        all_metrics[cat] = metrics
        lines.append(
            f"{cat:<18} {support:>8} {metrics['precision']:>10.2%} "
            f"{metrics['recall']:>10.2%} {metrics['f1']:>10.2%}"
        )

    lines.append("-" * 58)

    # Macro averages
    if categories:
        macro_precision = sum(m["precision"] for m in all_metrics.values()) / len(categories)
        macro_recall = sum(m["recall"] for m in all_metrics.values()) / len(categories)
        macro_f1 = sum(m["f1"] for m in all_metrics.values()) / len(categories)
    else:
        macro_precision = macro_recall = macro_f1 = 0.0

    lines.append(
        f"{'MACRO AVG':<18} {total:>8} {macro_precision:>10.2%} "
        f"{macro_recall:>10.2%} {macro_f1:>10.2%}"
    )
    lines.append("")

    # Misclassification details
    if errors:
        lines.append(f"Misclassifications ({len(errors)}):")
        lines.append("-" * 80)
        for err in errors:
            lines.append(f"  #{err['id']:>3} | expected: {err['actual']:<15} | got: {err['predicted']:<15} | {err['text']}")
    else:
        lines.append("No misclassifications.")

    lines.append("")
    lines.append("=" * 80)

    report = "\n".join(lines)
    summary = {
        "total": total,
        "correct": correct,
        "accuracy": round(overall_accuracy, 4),
        "macro_precision": round(macro_precision, 4),
        "macro_recall": round(macro_recall, 4),
        "macro_f1": round(macro_f1, 4),
        "elapsed_ms": round(elapsed * 1000, 1),
        "per_category": all_metrics,
        "errors": errors,
    }

    return report, summary


def main() -> None:
    """Main entry point."""
    output_file = None
    if "--output" in sys.argv:
        idx = sys.argv.index("--output")
        if idx + 1 < len(sys.argv):
            output_file = sys.argv[idx + 1]

    test_data_path = SCRIPT_DIR / "data" / "test.json"
    if not test_data_path.exists():
        print(f"Error: Test data not found at {test_data_path}", file=sys.stderr)
        sys.exit(1)

    test_data = load_test_data(test_data_path)
    report, summary = run_test(test_data)

    # Print to terminal
    print(report)

    # Optionally write to file
    if output_file:
        with open(output_file, "w", encoding="utf-8") as f:
            f.write(report)
        print(f"\nReport saved to: {output_file}")


if __name__ == "__main__":
    main()
