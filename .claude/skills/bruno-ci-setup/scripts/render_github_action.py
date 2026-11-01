#!/usr/bin/env python3
"""Render a GitHub Actions workflow for running Bruno.

By default this renders a workflow built on the official Bruno GitHub Action
(``usebruno/bruno-cli-action``), which is the preferred runtime on GitHub
Actions. Pass ``--runner npm`` to fall back to a hand-installed CLI workflow
(``actions/setup-node`` + ``npm install -g @usebruno/cli``) for the rare cases
where the Action is not a good fit.
"""

from __future__ import annotations

import argparse
import json
import re
from pathlib import Path
from typing import List

# GitHub env var and secret names must be valid identifiers.
IDENT_RE = re.compile(r"^[A-Za-z_][A-Za-z0-9_]*$")

# Reporter file names. Under the Action runner these sit at the collection
# (working-directory) root, which always exists, so no mkdir step is needed and
# bru never exits 2 for a missing reporter directory. Under the npm runner they
# live in a reports/ subdir created by an explicit mkdir step.
REPORTERS = [
    ("--reporter-junit", "bruno-junit.xml"),
    ("--reporter-json", "bruno-results.json"),
    ("--reporter-html", "bruno-report.html"),
]


def yaml_scalar(value: str) -> str:
    """Render a string as a safe double-quoted YAML scalar."""
    return json.dumps(str(value))


def build_run_flags(args: argparse.Namespace) -> List[str]:
    """Build the `bru run ...` flags shared across runners (without reporters)."""
    parts: List[str] = ["run"]
    if args.env:
        parts += ["--env", args.env]
    if args.env_file:
        parts += ["--env-file", args.env_file]
    if args.global_env:
        parts += ["--global-env", args.global_env]
    if args.workspace_path:
        parts += ["--workspace-path", args.workspace_path]
    if args.tags:
        parts += ["--tags", args.tags]
    if args.exclude_tags:
        parts += ["--exclude-tags", args.exclude_tags]
    if args.developer_sandbox:
        parts += ["--sandbox", "developer"]
    for env_var in args.env_var or []:
        parts += ["--env-var", env_var]
    return parts


def reporter_flags(prefix: str) -> List[str]:
    parts: List[str] = []
    for flag, name in REPORTERS:
        parts += [flag, f"{prefix}{name}"]
    return parts


def render_env(args: argparse.Namespace, indent: str) -> str:
    """Render an `env:` mapping body from --secret entries, or '' if none."""
    if not args.secret:
        return ""
    lines = []
    for item in args.secret:
        name = item.split("=", 1)[0]
        secret_name = item.split("=", 1)[1] if "=" in item else name
        lines.append(f"{indent}{name}: ${{{{ secrets.{secret_name} }}}}\n")
    return "".join(lines)


def artifact_paths(prefix: str) -> str:
    """Render the multi-line upload-artifact path list."""
    lines = [f"            {prefix}{name}\n" for _, name in REPORTERS]
    return "".join(lines)


def render_action(args: argparse.Namespace) -> str:
    collection = args.collection_dir.rstrip("/")
    prefix = collection + "/" if collection not in ("", ".") else ""
    command = " ".join(build_run_flags(args) + reporter_flags(""))

    bru_version_line = ""
    if args.bru_version:
        bru_version_line = f"          bru-version: {yaml_scalar(args.bru_version)}\n"

    env_body = render_env(args, "          ")
    env_block = f"        env:\n{env_body}" if env_body else ""

    return f"""name: Bruno API Tests

on:
  pull_request:
    branches: [{yaml_scalar(args.branch)}]
  push:
    branches: [{yaml_scalar(args.branch)}]
  workflow_dispatch:

permissions:
  contents: read

jobs:
  bruno-tests:
    runs-on: ubuntu-latest
    steps:
      - name: Check out repository
        uses: actions/checkout@v4

      - name: Run Bruno collection
        id: bruno
        uses: usebruno/bruno-cli-action@{args.action_ref}
        with:
          working-directory: {yaml_scalar(args.collection_dir)}
          command: {yaml_scalar(command)}
{bru_version_line}{env_block}
      - name: Upload Bruno reports
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: bruno-reports
          path: |
{artifact_paths(prefix)}"""


def render_npm(args: argparse.Namespace) -> str:
    command = "bru " + " ".join(build_run_flags(args) + reporter_flags("reports/"))
    env_body = render_env(args, "          ")
    env_block = f"        env:\n{env_body}" if env_body else "        # Add CI secrets here when needed.\n"

    return f"""name: Bruno API Tests

on:
  pull_request:
    branches: [{yaml_scalar(args.branch)}]
  push:
    branches: [{yaml_scalar(args.branch)}]
  workflow_dispatch:

permissions:
  contents: read

jobs:
  bruno-tests:
    runs-on: ubuntu-latest
    steps:
      - name: Check out repository
        uses: actions/checkout@v4

      - name: Set up Node.js
        uses: actions/setup-node@v4
        with:
          node-version: {yaml_scalar(args.node_version)}

      - name: Install Bruno CLI
        run: npm install -g @usebruno/cli{('@' + args.bru_version) if args.bru_version else ''}

      - name: Run Bruno collection
        working-directory: {yaml_scalar(args.collection_dir)}
{env_block}        run: |
          mkdir -p reports
          {command}

      - name: Upload Bruno reports
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: bruno-reports
          path: {yaml_scalar(args.collection_dir.rstrip("/") + "/reports/")}
"""


def render(args: argparse.Namespace) -> str:
    if args.runner == "npm":
        return render_npm(args)
    return render_action(args)


def main() -> int:
    parser = argparse.ArgumentParser(description="Render a GitHub Actions workflow for Bruno")
    parser.add_argument("--runner", choices=["action", "npm"], default="action",
                        help="Runtime: 'action' uses the official usebruno/bruno-cli-action (default); "
                             "'npm' falls back to setup-node + npm install of the CLI")
    parser.add_argument("--collection-dir", default=".", help="Working directory containing the Bruno collection")
    parser.add_argument("--branch", default="main", help="Target branch")
    parser.add_argument("--node-version", default="20", help="Node.js version (npm runner only)")
    parser.add_argument("--action-ref", default="v1",
                        help="Git ref for usebruno/bruno-cli-action (default v1). "
                             "Overridden by --pin-latest, which resolves the latest specific release tag")
    version_group = parser.add_mutually_exclusive_group()
    version_group.add_argument("--bru-version", help="Pin the @usebruno/cli version to this exact value (Action input or npm tag)")
    version_group.add_argument("--pin-latest", action="store_true",
                               help="Resolve the latest published @usebruno/cli version and pin to it")
    parser.add_argument("--env", help="Bruno environment name")
    parser.add_argument("--env-file", help="Bruno environment file")
    parser.add_argument("--global-env", help="Bruno global/workspace environment name")
    parser.add_argument("--workspace-path", help="Workspace path passed to Bruno CLI")
    parser.add_argument("--tags", help="Comma-separated include tags")
    parser.add_argument("--exclude-tags", help="Comma-separated exclude tags")
    parser.add_argument("--env-var", action="append", help="Additional --env-var entries, for example baseUrl=$BASE_URL")
    parser.add_argument("--secret", action="append", help="Workflow env var to GitHub secret mapping, for example API_TOKEN=API_TOKEN")
    parser.add_argument("--developer-sandbox", action="store_true", help="Add --sandbox developer")
    parser.add_argument("--out", help="Output workflow path. Prints to stdout if omitted")
    parser.add_argument("--force", action="store_true", help="Overwrite --out if it already exists")
    args = parser.parse_args()

    if args.pin_latest:
        # Resolve concrete latest values so the rendered workflow pins to real
        # numbers/tags rather than placeholders or floating tags: the CLI
        # version for bru-version, and the newest specific release tag for the
        # Action ref.
        from latest_version import latest_version, latest_action_tag
        try:
            args.bru_version = latest_version()
            args.action_ref = latest_action_tag()
        except Exception as exc:
            raise SystemExit(
                f"error: --pin-latest could not resolve the latest versions: {exc}; "
                "pass --bru-version and --action-ref with values you looked up manually instead"
            )

    validate_args(args)

    output = render(args)
    if args.out:
        path = Path(args.out)
        if path.exists() and not args.force:
            raise SystemExit(f"error: {path} already exists; pass --force to overwrite")
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(output)
        print(f"Wrote {path}")
    else:
        print(output, end="")
    return 0


def validate_args(args: argparse.Namespace) -> None:
    for item in args.secret or []:
        name, _, secret_name = item.partition("=")
        secret_name = secret_name or name
        if not IDENT_RE.match(name) or not IDENT_RE.match(secret_name):
            raise SystemExit(
                f"error: invalid --secret mapping {item!r}; expected NAME=SECRET with "
                "identifier-safe names (letters, digits, underscore; not starting with a digit)"
            )
    for item in args.env_var or []:
        key, sep, _ = item.partition("=")
        if not sep or not key:
            raise SystemExit(f"error: invalid --env-var {item!r}; expected key=value")


if __name__ == "__main__":
    raise SystemExit(main())
