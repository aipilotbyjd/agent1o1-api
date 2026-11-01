# Bruno CLI Options Quick Reference

These `bru run` flags are identical across all three runtimes — the official GitHub Action and the official Docker image are both wrappers around `bru run`, and the npm-installed CLI runs it directly. Pick the runtime per the Runtime selection section in SKILL.md (Action for GitHub, Docker elsewhere, npm only as a fallback); the flags below are the same regardless.

## npm fallback install

When neither the Action nor the Docker image fits, install the CLI directly. Use the repo's package manager and a current LTS Node:

```bash
npm install -g @usebruno/cli
# or: pnpm add -g @usebruno/cli
```

Pin the version in CI to the latest published release (e.g. `npm install -g @usebruno/cli@3.5.0` — resolve the current latest with `python scripts/latest_version.py` or `npm view @usebruno/cli version`) for a reproducible, current run, and own the Node setup yourself.

## Common commands

Run the current collection:

```bash
bru run
```

Run a folder (add `-r` to recurse into subfolders):

```bash
bru run users -r
```

Run with an environment name:

```bash
bru run --env ci
```

Run with an environment file:

```bash
bru run --env-file ./environments/ci.bru
```

Override variables:

```bash
bru run --env ci --env-var baseUrl=$BASE_URL
```

(For secrets, prefer forwarding the variable and reading it from the collection over interpolating it here — a shell-expanded literal can leak into logs.)

Filter with tags:

```bash
bru run --tags smoke --exclude-tags skip-ci,destructive
```

Generate reports:

```bash
bru run --reporter-junit reports/bruno-junit.xml --reporter-json reports/bruno-results.json --reporter-html reports/bruno-report.html
```

Redact sensitive data from reports:

```bash
bru run --reporter-junit results.xml --reporter-skip-headers "Authorization Cookie X-Api-Key" --reporter-skip-response-body
```

Stop at the first failure (monitors, pre-push hooks):

```bash
bru run --bail
```

Use Developer Mode features:

```bash
bru run --sandbox developer
```

## Notes

- Bruno CLI v3+ defaults to Safe Mode. Use `--sandbox developer` only when a collection needs external npm packages or filesystem access. (`--sandbox=developer` with an equals sign is also accepted.)
- The deprecated `-o/--output` and `-f/--format` flags are replaced by `--reporter-junit`, `--reporter-html`, and `--reporter-json`.
- Use JUnit for CI integrations; it is also what the GitHub Action parses for its count outputs.
- Use HTML reports for reviewable artifacts and JSON for programmatic analysis.
- Pass secrets through CI secret stores rather than committing environment files with secret values.
