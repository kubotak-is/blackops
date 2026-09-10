#!/usr/bin/env python3
"""Collect the visible documentation glyph set for the Noto Sans JP subset."""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path


WEBSITE_FILES = {
    "README.md",
    "blume.config.ts",
    "content-map.mjs",
    "scripts/execution-walkthrough.mjs",
}
SKIP_PARTS = {".astro", ".blume", ".git", ".svelte-kit", "dist", "node_modules"}
TEXT_SUFFIXES = {".astro", ".json", ".md", ".mdx", ".mjs", ".ts"}


def source_files(repo: Path):
    guide = repo / "docs" / "guide"
    website = repo / "docs" / "website"
    for root in (guide, website):
        if not root.is_dir():
            continue
        for path in sorted(root.rglob("*")):
            if not path.is_file() or path.suffix not in TEXT_SUFFIXES:
                continue
            relative = path.relative_to(root).as_posix()
            if set(path.relative_to(root).parts) & SKIP_PARTS:
                continue
            if root is guide:
                if "assets" in path.relative_to(root).parts:
                    continue
            elif not (
                relative.startswith("src/content/")
                or relative.startswith("components/")
                or relative.startswith("pages/")
                or relative.startswith("public/diagrams/")
                or relative in WEBSITE_FILES
            ):
                continue
            yield path


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--repo-root", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    repo = args.repo_root.resolve()
    output = args.output.resolve()

    codepoints = set(range(0x20, 0x7F))
    # These controls are authored by the pinned Archify viewer at generation
    # time, rather than in the documentation source files walked below.
    codepoints.update(map(ord, "←↗⇄"))
    files = []
    for path in source_files(repo):
        try:
            raw = path.read_bytes()
            text = raw.decode("utf-8")
        except (UnicodeDecodeError, OSError):
            continue
        codepoints.update(ord(char) for char in text if char.isprintable())
        files.append(
            {
                "path": path.relative_to(repo).as_posix(),
                "bytes": len(raw),
                "sha256": hashlib.sha256(raw).hexdigest(),
            }
        )

    values = sorted(codepoints)
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text("\n".join(f"U+{value:04X}" for value in values) + "\n", encoding="ascii")
    manifest = {
        "schema": 1,
        "files": files,
        "codepointCount": len(values),
        "codepointsSha256": hashlib.sha256(output.read_bytes()).hexdigest(),
    }
    output.with_suffix(".json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    print(json.dumps(manifest, ensure_ascii=False, separators=(",", ":")))


if __name__ == "__main__":
    main()
