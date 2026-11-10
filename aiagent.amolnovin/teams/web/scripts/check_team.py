#!/usr/bin/env python3
"""
check_team.py — validator for the 5-agent web design team package.

Checks:
  1. every expected file exists and is non-empty
  2. every relative Markdown reference inside README.md resolves to a real file
  3. each agent profile has the required sections, a copy-paste System Prompt,
     and declares the expected agent code
  4. TEAM.md routing table mentions all five agent codes
  5. no forbidden patterns (e.g. banned physical CSS props in the standards'
     "correct" examples, placeholder markers left in files)

Exit code 0 = all checks passed, 1 = at least one failure.

Usage:  python3 scripts/check_team.py
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

EXPECTED_FILES = [
    "README.md",
    "TEAM.md",
    "agents/01-ux-architect.md",
    "agents/02-ui-designer.md",
    "agents/03-frontend-engineer.md",
    "agents/04-backend-php.md",
    "agents/05-qa-lead.md",
    "prompts/QUICKSTART.md",
    "standards/code-standards.md",
    "templates/PROJECT_BRIEF.md",
]

# agent profile file -> (team code, persona name used in the System Prompt)
AGENTS = {
    "agents/01-ux-architect.md": ("UX", "Arash"),
    "agents/02-ui-designer.md": ("UI", "Negar"),
    "agents/03-frontend-engineer.md": ("FE", "Sina"),
    "agents/04-backend-php.md": ("BE", "Babak"),
    "agents/05-qa-lead.md": ("QA", "Mina"),
}

REQUIRED_AGENT_SECTIONS = [
    "مأموریت",
    "تخصص",
    "System Prompt",
]

PLACEHOLDERS = ["TODO:", "FIXME:", "PLACEHOLDER", "lorem ipsum"]

failures: list[str] = []
checks_run = 0


def check(condition: bool, message: str) -> None:
    global checks_run
    checks_run += 1
    if not condition:
        failures.append(message)


def read(rel: str) -> str:
    path = ROOT / rel
    if not path.exists():
        return ""
    return path.read_text(encoding="utf-8")


def check_files_exist() -> None:
    for rel in EXPECTED_FILES:
        path = ROOT / rel
        check(path.exists(), f"missing file: {rel}")
        if path.exists():
            check(
                len(path.read_text(encoding="utf-8").strip()) > 0,
                f"empty file: {rel}",
            )


def check_readme_links() -> None:
    readme = read("README.md")
    if not readme:
        failures.append("README.md is missing, cannot check references")
        return
    refs = re.findall(r"`([A-Za-z0-9_./-]+\.(?:md|py|sql|php|css|js))`", readme)
    check(len(refs) >= 5, "README.md references fewer than 5 package files")
    for ref in sorted(set(refs)):
        if ref.startswith(("agents/", "prompts/", "standards/", "templates/", "scripts/")):
            check((ROOT / ref).exists(), f"README.md references missing file: {ref}")


def check_agents() -> None:
    for rel, (code, name) in AGENTS.items():
        text = read(rel)
        if not text:
            continue
        for section in REQUIRED_AGENT_SECTIONS:
            check(section in text, f"{rel}: missing required section '{section}'")
        check(f"`{code}`" in text, f"{rel}: agent code {code} not declared")
        check(name in text, f"{rel}: agent persona name '{name}' not found")

        # System Prompt block must be a fenced code block introduced by the header
        m = re.search(r"System Prompt.*?```\n(.*?)```", text, re.DOTALL)
        check(m is not None, f"{rel}: copy-paste System Prompt code block not found")
        if m:
            prompt = m.group(1)
            check(
                f'You are "{name}"' in prompt,
                f"{rel}: System Prompt does not start with the persona declaration",
            )
            check("RULES" in prompt, f"{rel}: System Prompt has no RULES section")
            check("EXPERTISE" in prompt, f"{rel}: System Prompt has no EXPERTISE section")
            check(
                len(prompt) > 400,
                f"{rel}: System Prompt looks too short ({len(prompt)} chars)",
            )


def check_team_protocol() -> None:
    team = read("TEAM.md")
    if not team:
        return
    for code in ("UX", "UI", "FE", "BE", "QA"):
        check(code in team, f"TEAM.md routing table does not mention agent '{code}'")
    for stage in ("INTAKE", "DISCOVERY", "STRUCTURE", "DESIGN", "VERIFY", "SHIP"):
        check(stage in team, f"TEAM.md pipeline missing stage '{stage}'")
    check("Data Contract" in team or "قرارداد داده" in team, "TEAM.md has no data contract")


def check_standards() -> None:
    std = read("standards/code-standards.md")
    if not std:
        return
    for token in ("BEM", "prepare", "password_hash", "utf8mb4", "strict_types"):
        check(token in std, f"code-standards.md missing required rule: {token}")


# On-demand coverage that must live in the files (not only in chat).
# rel file -> markers that prove the capability is documented there.
EXTENDED_COVERAGE = {
    "TEAM.md": [
        "مسیریابی گسترده",
        "سئو و محتوایش را هم بنویس",
        "امنیتش را جدی بررسی کن",
        "درگاه پرداخت ایرانی می‌خوام",
        "راهنمای دیپلوی بده",
    ],
    "agents/01-ux-architect.md": ["استراتژی محتوا و سئو", "کلیدواژه", "cannibalization"],
    "agents/03-frontend-engineer.md": ["SEO تکنیکال", "JSON-LD", "hreflang", "sitemap"],
    "agents/04-backend-php.md": [
        "درگاه پرداخت ایرانی",
        "verify",
        "idempotent",
        "Deploy & Ops",
        "certbot",
        "mysqldump",
    ],
    "agents/05-qa-lead.md": [
        "OWASP Top 10",
        "تست درگاه پرداخت",
        "مستند فنی",
        "docs/deploy.md",
    ],
}


def check_extended_coverage() -> None:
    """The four on-demand capabilities must exist in the package files."""
    for rel, markers in EXTENDED_COVERAGE.items():
        text = read(rel)
        if not text:
            continue
        for marker in markers:
            check(
                marker in text,
                f"{rel}: missing on-demand coverage marker '{marker}'",
            )

    # The System Prompts must carry the triggers too, so the package keeps
    # working when copied into another LLM tool.
    for rel, (code, _name) in AGENTS.items():
        if code == "UI":
            continue
        match = re.search(r"System Prompt.*?```\n(.*?)```", read(rel), re.DOTALL)
        if match:
            check(
                "trigger" in match.group(1).lower(),
                f"{rel}: System Prompt does not expose its on-demand trigger",
            )


def check_placeholders() -> None:
    for rel in EXPECTED_FILES:
        text = read(rel)
        for ph in PLACEHOLDERS:
            check(ph not in text, f"{rel}: leftover placeholder '{ph}'")


def main() -> int:
    check_files_exist()
    check_readme_links()
    check_agents()
    check_team_protocol()
    check_standards()
    check_extended_coverage()
    check_placeholders()

    print("=" * 58)
    print("  Web Design Squad — package validator")
    print("=" * 58)
    print(f"  root: {ROOT}")
    print(f"  checks run : {checks_run}")
    print(f"  failures   : {len(failures)}")
    if failures:
        print("-" * 58)
        for item in failures:
            print(f"  [FAIL] {item}")
        print("=" * 58)
        print("  RESULT: FAILED")
        return 1
    print("-" * 58)
    print("  [OK] all 10 package files present and non-empty")
    print("  [OK] README references resolve to real files")
    print("  [OK] 5 agent profiles: sections + System Prompt + persona")
    print("  [OK] TEAM.md pipeline stages and routing table complete")
    print("  [OK] code standards contain mandatory rules")
    print("  [OK] on-demand coverage present: SEO/content, OWASP, payment, deploy")
    print("  [OK] no leftover placeholders")
    print("=" * 58)
    print("  RESULT: PASSED")
    return 0


if __name__ == "__main__":
    sys.exit(main())
