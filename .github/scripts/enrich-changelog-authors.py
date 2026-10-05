#!/usr/bin/env python3
"""Enrich the newest Release Please changelog section with GitHub authors."""

from __future__ import annotations

import json
import os
import re
import urllib.error
import urllib.request
from pathlib import Path

CHANGELOG = Path("CHANGELOG.md")
REPOSITORY = os.environ["GITHUB_REPOSITORY"]
TOKEN = os.environ["GH_TOKEN"]

COMMIT_RE = re.compile(
    r"(?P<prefix>\(\[(?P<short>[0-9a-f]{7,40})\]\("
    r"https://github\.com/[^/]+/[^/]+/commit/(?P<sha>[0-9a-f]{40})\)\))"
)
AUTHOR_RE = re.compile(r" — \[@[^\]]+\]\(https://github\.com/[^)]+\)\s*$")


def github_author(sha: str) -> tuple[str, str] | None:
    request = urllib.request.Request(
        f"https://api.github.com/repos/{REPOSITORY}/commits/{sha}",
        headers={
            "Accept": "application/vnd.github+json",
            "Authorization": f"Bearer {TOKEN}",
            "X-GitHub-Api-Version": "2022-11-28",
            "User-Agent": "oreof-changelog-author-enricher",
        },
    )

    try:
        with urllib.request.urlopen(request) as response:
            payload = json.load(response)
    except urllib.error.HTTPError as error:
        print(f"Warning: cannot resolve GitHub author for {sha[:7]}: HTTP {error.code}")
        return None

    author = payload.get("author")
    if not author or not author.get("login"):
        print(f"Warning: commit {sha[:7]} is not linked to a GitHub account")
        return None

    return author["login"], author["html_url"]


def main() -> None:
    content = CHANGELOG.read_text(encoding="utf-8")

    # Release Please prepends the newest release. Only modify that first section,
    # so old changelog entries remain untouched.
    next_release = content.find("\n## ", 3)
    if next_release == -1:
        newest, remainder = content, ""
    else:
        newest, remainder = content[:next_release], content[next_release:]

    cache: dict[str, tuple[str, str] | None] = {}
    output: list[str] = []

    for line in newest.splitlines(keepends=True):
        match = COMMIT_RE.search(line)
        if not match or AUTHOR_RE.search(line.rstrip("\n")):
            output.append(line)
            continue

        sha = match.group("sha")
        if sha not in cache:
            cache[sha] = github_author(sha)

        author = cache[sha]
        if author is None:
            output.append(line)
            continue

        login, profile_url = author
        newline = "\n" if line.endswith("\n") else ""
        line = line.rstrip("\n") + f" — [@{login}]({profile_url})" + newline
        output.append(line)

    updated = "".join(output) + remainder
    if updated != content:
        CHANGELOG.write_text(updated, encoding="utf-8")
        print("CHANGELOG.md enriched with GitHub authors.")
    else:
        print("No changelog author enrichment needed.")


if __name__ == "__main__":
    main()
