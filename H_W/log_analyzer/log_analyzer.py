# ==========================================
# Tool 7: Log Analyzer 
# Created by: Ahmed_Q
# ==========================================

import argparse
import ipaddress
import re
from collections import Counter
from urllib.parse import unquote

# Rules to detect common web attacks
RULES = {
    "SQL Injection": [
        re.compile(r"\bunion\s+(?:all\s+)?select\b", re.IGNORECASE),
        re.compile(
            r"""['"]\s*(?:or|and)\s*['"]?\w+['"]?\s*=\s*['"]?\w+['"]?""",
            re.IGNORECASE,
        ),
        re.compile(r"\b(?:or|and)\s+\d+\s*=\s*\d+\b", re.IGNORECASE),
    ],
    "Path Traversal": [
        re.compile(r"(?:\.\.[/\\])+", re.IGNORECASE),
        re.compile(r"/etc/passwd\b", re.IGNORECASE),
    ],
    "XSS": [
        re.compile(r"<\s*script\b", re.IGNORECASE),
        re.compile(r"\bjavascript\s*:", re.IGNORECASE),
        re.compile(r"\bon\w+\s*=", re.IGNORECASE),
    ],
}


def get_ip_address(line: str) -> str:
    """Extract IP address from the log line if possible."""
    fields = line.split()
    if not fields:
        return ""
    candidate = fields[0].strip("[]")
    try:
        return str(ipaddress.ip_address(candidate))
    except ValueError:
        return ""


def decoded_variants(line: str, max_passes: int = 2) -> list[str]:
    """Return original and URL-decoded variants of the line to catch encoded patterns."""
    variants = [line]
    for _ in range(max_passes):
        decoded = unquote(variants[-1])
        if decoded == variants[-1]:
            break
        variants.append(decoded)
    return variants


def find_matches(line: str) -> dict[str, str]:
    """Find matching attack types and patterns for a line."""
    variants = decoded_variants(line)
    matches = {}
    for attack_type, patterns in RULES.items():
        for pattern in patterns:
            if any(pattern.search(variant) for variant in variants):
                matches[attack_type] = pattern.pattern
                break
    return matches


def analyze_log(file_path: str) -> None:
    counts = Counter()
    suspicious_lines = 0
    total_lines = 0

    try:
        with open(file_path, "r", encoding="utf-8", errors="replace") as log_file:
            for line_number, line in enumerate(log_file, start=1):
                total_lines += 1
                matches = find_matches(line)
                if not matches:
                    continue
                suspicious_lines += 1
                source_ip = get_ip_address(line)
                for attack_type, signature in matches.items():
                    counts[attack_type] += 1
                    print(
                        f"[ALERT] Type: {attack_type} | "
                        f"Line: {line_number} | "
                        f"Source IP: {source_ip}"
                    )
                    print(f"        Matched Pattern: {signature}")
    except FileNotFoundError:
        print(f"Error: File not found: {file_path}")
        return
    except PermissionError:
        print(f"Error: Permission denied to read file: {file_path}")
        return
    except OSError as error:
        print(f"Error reading file: {error}")
        return

    print("\n--- Analysis Summary ---")
    print(f"Total lines read : {total_lines}")
    print(f"Suspicious lines : {suspicious_lines}")
    print(f"Total detections : {sum(counts.values())}")
    if counts:
        print("Alerts by type:")
        for attack_type, count in sorted(counts.items()):
            print(f"  - {attack_type}: {count}")
    else:
        print("No matching rule patterns detected within the specified file.")


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Analyze a web log file for common attack signatures."
    )
    parser.add_argument("logfile", help="Path to the log file to analyze.")
    args = parser.parse_args()
    analyze_log(args.logfile)

    # Print at the end of the results
    print("\n----------------------------------------")
    print("Tool 7 | Created by: Ahmed_Q")
    print("----------------------------------------")


if __name__ == "__main__":
    main()