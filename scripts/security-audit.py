#!/usr/bin/env python3
"""Security audit script for AegisDock laboratory.

Scans the project for common security misconfigurations,
hardcoded secrets, and vulnerable patterns typical of the
intentionally vulnerable scenarios documented in the lab.

Usage:
    python3 scripts/security-audit.py
"""

import ast
import json
import os
import re
import sys
from pathlib import Path


def find_python_files(directory: str) -> list[Path]:
    """Recursively find all Python files in a directory."""
    return [
        Path(root) / file
        for root, _, files in os.walk(directory)
        for file in files
        if file.endswith(".py")
    ]


def check_hardcoded_secrets(filepath: Path) -> list[dict]:
    """Check for hardcoded secrets and keys in Python files."""
    issues = []
    try:
        with open(filepath, "r", encoding="utf-8") as f:
            content = f.read()
    except (UnicodeDecodeError, PermissionError):
        return issues

    # Patterns for common secrets
    secret_patterns = [
        (r"(?i)api_key\s*=\s*['\"][^'\"]{8,}['\"]", "API Key"),
        (r"(?i)api_secret\s*=\s*['\"][^'\"]{8,}['\"]", "API Secret"),
        (r"(?i)password\s*=\s*['\"][^'\"]{8,}['\"]", "Password"),
        (r"(?i)secret_key\s*=\s*['\"][^'\"]{8,}['\"]", "Secret Key"),
        (r"(?i)token\s*=\s*['\"][^'\"]{16,}['\"]", "Token"),
    ]

    for pattern, secret_type in secret_patterns:
        matches = re.finditer(pattern, content)
        for match in matches:
            issues.append({
                "type": "hardcoded_secret",
                "severity": "high",
                "file": str(filepath),
                "line": content[:match.start()].count("\n") + 1,
                "pattern": pattern,
                "detail": f"Potential {secret_type} found hardcoded",
            })

    return issues


def check_crypto_issues(filepath: Path) -> list[dict]:
    """Check for cryptographic issues in PHP files."""
    issues = []
    try:
        with open(filepath, "r", encoding="utf-8") as f:
            content = f.read()
    except (UnicodeDecodeError, PermissionError):
        return issues

    # Check for AES-CBC without HMAC in PHP
    if re.search(r"AES\.encrypt\s*\(|openssl_encrypt\s*\(", content):
        if not re.search(r"openssl_rawkey_digest|HMAC|auth_tag", content):
            issues.append({
                "type": "crypto_without_integrity",
                "severity": "high",
                "file": str(filepath),
                "detail": "AES encryption detected without integrity protection (HMAC)",
            })

    # Check for eval usage
    if re.search(r"\beval\s*\(", content):
        issues.append({
            "type": "eval_usage",
            "severity": "critical",
            "file": str(filepath),
            "detail": "Dangerous eval() usage found",
        })

    return issues


def main():
    """Run security audit on the AegisDock project."""
    project_dir = Path(".")
    issues = []

    # Check Python files
    py_files = find_python_files(project_dir / "tests")
    for filepath in py_files:
        issues.extend(check_hardcoded_secrets(filepath))
        issues.extend(check_crypto_issues(filepath))

    # Output results
    if issues:
        print(f"Found {len(issues)} potential security issues:\n")
        for issue in issues:
            print(f"[{issue['severity'].upper()}] {issue['type']}: {issue['detail']}")
            print(f"    File: {issue['file']}:{issue['line']}")
        print()
        return 1
    else:
        print("No security issues detected.")
        return 0


if __name__ == "__main__":
    sys.exit(main())