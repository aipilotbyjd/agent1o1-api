#!/usr/bin/env python3
"""Resolve the latest published Bruno runtime versions for pinning.

When pinning a runtime, pin to the latest published version rather than a
stale placeholder. Two independent things get pinned:

* The CLI version (``latest_version``): the npm package ``@usebruno/cli`` is
  the source of truth. The Docker image (``usebruno/cli``) and the GitHub
  Action's ``bru-version`` input both track the same release number, so this
  one value is the CLI pin across all three runtimes.
* The GitHub Action ref (``latest_action_tag``): the ``usebruno/bruno-cli-action``
  repo is versioned separately from the CLI. This resolves its newest specific
  release tag (e.g. ``v1.0.0``) so the ``uses:`` ref pins to an exact release
  rather than the floating ``@v1`` major.

Usage:
    python scripts/latest_version.py               # CLI version, e.g. 3.5.0
    python scripts/latest_version.py --action-tag  # Action tag, e.g. v1.0.0

Equivalent shell one-liners if you'd rather not run this:
    npm view @usebruno/cli version
    git ls-remote --tags https://github.com/usebruno/bruno-cli-action.git
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
import urllib.request

REGISTRY_URL = "https://registry.npmjs.org/@usebruno/cli"
ACTION_REPO_URL = "https://github.com/usebruno/bruno-cli-action.git"
SPECIFIC_TAG_RE = re.compile(r"^v(\d+)\.(\d+)\.(\d+)$")


def latest_version(timeout: float = 10.0) -> str:
    """Return the npm dist-tag 'latest' version of @usebruno/cli."""
    with urllib.request.urlopen(REGISTRY_URL, timeout=timeout) as resp:
        data = json.load(resp)
    version = data.get("dist-tags", {}).get("latest")
    if not version:
        raise RuntimeError("npm registry response did not contain a 'latest' dist-tag")
    return version


def latest_action_tag(timeout: float = 15.0) -> str:
    """Return the newest specific release tag (vX.Y.Z) of bruno-cli-action.

    Floating major tags like 'v1' are intentionally ignored so the result is an
    exact, immutable release ref.
    """
    proc = subprocess.run(
        ["git", "ls-remote", "--tags", ACTION_REPO_URL],
        capture_output=True, text=True, timeout=timeout, check=True,
    )
    versions = []
    for line in proc.stdout.splitlines():
        ref = line.rsplit("refs/tags/", 1)[-1].strip()
        m = SPECIFIC_TAG_RE.match(ref)
        if m:
            versions.append((tuple(int(g) for g in m.groups()), ref))
    if not versions:
        raise RuntimeError("no specific vX.Y.Z release tags found on bruno-cli-action")
    return max(versions)[1]


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--action-tag", action="store_true",
                        help="Print the latest bruno-cli-action release tag instead of the CLI version")
    args = parser.parse_args()
    try:
        print(latest_action_tag() if args.action_tag else latest_version())
    except Exception as exc:  # network error, git missing, registry shape change, etc.
        target = "bruno-cli-action release tag" if args.action_tag else "@usebruno/cli version"
        sys.stderr.write(
            f"error: could not resolve the latest {target}: {exc}\n"
            "Check network access, or pin an explicit value you looked up manually.\n"
        )
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
