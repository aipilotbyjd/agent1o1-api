"""Tests for render_github_action.py: rendering, YAML safety, validation, guards.

Run with:  pytest scripts/test_render_github_action.py
"""

from __future__ import annotations

import subprocess
import sys
from pathlib import Path

import pytest

SCRIPT = Path(__file__).with_name("render_github_action.py")


def run(*args: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        [sys.executable, str(SCRIPT), *args],
        capture_output=True,
        text=True,
    )


# --- rendering: official Action (default) -----------------------------------


def test_default_uses_official_action() -> None:
    result = run("--collection-dir", "collections/api", "--env", "ci", "--tags", "smoke")
    assert result.returncode == 0
    assert "name: Bruno API Tests" in result.stdout
    assert "uses: usebruno/bruno-cli-action@v1" in result.stdout
    # The bru subcommand goes in the `command:` input; the Action prepends `bru`.
    assert "command:" in result.stdout
    assert "run --env ci --tags smoke" in result.stdout
    # No hand install when using the Action.
    assert "npm install -g @usebruno/cli" not in result.stdout
    assert "actions/setup-node" not in result.stdout
    assert "permissions:" in result.stdout
    assert "contents: read" in result.stdout


def test_action_developer_sandbox_in_command() -> None:
    result = run("--env", "ci", "--developer-sandbox")
    assert result.returncode == 0
    assert "--sandbox developer" in result.stdout


def test_action_bru_version_input() -> None:
    result = run("--env", "ci", "--bru-version", "3.5.0")
    assert result.returncode == 0
    assert "bru-version:" in result.stdout
    assert "3.5.0" in result.stdout


def test_secret_mapping_rendered() -> None:
    result = run("--secret", "API_TOKEN=PROD_TOKEN")
    assert result.returncode == 0
    assert "API_TOKEN: ${{ secrets.PROD_TOKEN }}" in result.stdout


def test_action_render_is_valid_yaml() -> None:
    yaml = pytest.importorskip("yaml")
    result = run(
        "--collection-dir", "collections/api",
        "--branch", "release/v2",      # contains a slash
        "--env", "ci",
        "--secret", "API_TOKEN=API_TOKEN",
        "--bru-version", "3.5.0",
    )
    assert result.returncode == 0
    doc = yaml.safe_load(result.stdout)
    # PyYAML reads the unquoted GitHub `on:` key as the boolean True.
    on_block = doc["on"] if "on" in doc else doc[True]
    assert on_block["push"]["branches"] == ["release/v2"]
    steps = doc["jobs"]["bruno-tests"]["steps"]
    action_step = next(s for s in steps if str(s.get("uses", "")).startswith("usebruno/bruno-cli-action"))
    assert action_step["uses"] == "usebruno/bruno-cli-action@v1"
    assert action_step["with"]["working-directory"] == "collections/api"
    assert action_step["with"]["command"].startswith("run --env ci")
    assert action_step["with"]["bru-version"] == "3.5.0"
    assert "secrets.API_TOKEN" in action_step["env"]["API_TOKEN"]


def test_action_no_secret_omits_env_block() -> None:
    yaml = pytest.importorskip("yaml")
    result = run("--env", "ci")
    assert result.returncode == 0
    doc = yaml.safe_load(result.stdout)
    steps = doc["jobs"]["bruno-tests"]["steps"]
    action_step = next(s for s in steps if str(s.get("uses", "")).startswith("usebruno/bruno-cli-action"))
    assert "env" not in action_step  # no empty env: mapping when there are no secrets


# --- rendering: npm fallback ------------------------------------------------


def test_npm_runner_installs_cli() -> None:
    result = run("--runner", "npm", "--collection-dir", "collections/api", "--env", "ci")
    assert result.returncode == 0
    assert "npm install -g @usebruno/cli" in result.stdout
    assert "actions/setup-node" in result.stdout
    assert "bru run --env ci" in result.stdout
    assert "usebruno/bruno-cli-action" not in result.stdout


def test_npm_runner_pins_version() -> None:
    result = run("--runner", "npm", "--bru-version", "3.5.0")
    assert result.returncode == 0
    assert "npm install -g @usebruno/cli@3.5.0" in result.stdout


def test_npm_runner_valid_yaml_and_node_version() -> None:
    yaml = pytest.importorskip("yaml")
    result = run("--runner", "npm", "--node-version", "18.x", "--env", "ci")
    assert result.returncode == 0
    doc = yaml.safe_load(result.stdout)
    setup = doc["jobs"]["bruno-tests"]["steps"][1]["with"]["node-version"]
    assert setup == "18.x" and isinstance(setup, str)


# --- validation -------------------------------------------------------------


def test_invalid_secret_mapping_rejected() -> None:
    result = run("--secret", "bad name=SECRET")
    assert result.returncode == 1
    assert "invalid --secret mapping" in result.stderr
    assert "Traceback" not in result.stderr


def test_invalid_secret_target_rejected() -> None:
    result = run("--secret", "API_TOKEN=1bad")  # secret name starts with a digit
    assert result.returncode == 1
    assert "invalid --secret mapping" in result.stderr


def test_invalid_env_var_rejected() -> None:
    result = run("--env-var", "noequalssign")
    assert result.returncode == 1
    assert "invalid --env-var" in result.stderr


def test_valid_env_var_accepted() -> None:
    result = run("--env-var", "baseUrl=$BASE_URL")
    assert result.returncode == 0
    assert "--env-var baseUrl=$BASE_URL" in result.stdout


def test_invalid_runner_rejected() -> None:
    result = run("--runner", "docker")
    assert result.returncode == 2  # argparse choices error
    assert "invalid choice" in result.stderr


def test_bru_version_and_pin_latest_mutually_exclusive() -> None:
    result = run("--bru-version", "3.5.0", "--pin-latest")
    assert result.returncode == 2  # argparse mutually-exclusive error
    assert "not allowed with" in result.stderr


def test_action_ref_override() -> None:
    result = run("--env", "ci", "--action-ref", "v1.0.0")
    assert result.returncode == 0
    assert "uses: usebruno/bruno-cli-action@v1.0.0" in result.stdout


def test_pin_latest_bakes_concrete_version_and_action_tag() -> None:
    import re as _re
    result = run("--env", "ci", "--pin-latest")
    if result.returncode != 0:
        pytest.skip("offline: cannot reach the registry / git to resolve latest versions")
    # A concrete semver-ish CLI version, not the literal word 'latest' or a placeholder.
    assert _re.search(r"bru-version:\s*\"(\d+\.\d+\.\d+)\"", result.stdout), result.stdout
    # A specific Action release tag (vX.Y.Z), not the floating major @v1.
    m = _re.search(r"uses: usebruno/bruno-cli-action@(v\d+\.\d+\.\d+)", result.stdout)
    assert m, f"expected a specific Action release tag in output:\n{result.stdout}"


# --- guard paths ------------------------------------------------------------


def test_refuses_overwrite_without_force(tmp_path: Path) -> None:
    out = tmp_path / "wf.yml"
    out.write_text("# hand-written")
    result = run("--out", str(out))
    assert result.returncode == 1
    assert "already exists" in result.stderr
    assert out.read_text() == "# hand-written"


def test_force_overwrites(tmp_path: Path) -> None:
    out = tmp_path / "wf.yml"
    out.write_text("# old")
    result = run("--out", str(out), "--force")
    assert result.returncode == 0
    assert "name: Bruno API Tests" in out.read_text()


def test_creates_parent_dirs(tmp_path: Path) -> None:
    out = tmp_path / "nested" / "dir" / "wf.yml"
    result = run("--out", str(out))
    assert result.returncode == 0
    assert out.exists()


if __name__ == "__main__":
    raise SystemExit(pytest.main([__file__, "-v"]))
