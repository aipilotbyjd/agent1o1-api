# GitHub Actions Patterns for Bruno

On GitHub Actions, prefer the **official Bruno GitHub Action**, `usebruno/bruno-cli-action`. It installs `@usebruno/cli`, runs the `bru` command you pass, parses the emitted JUnit XML, and exposes counts as step outputs. It is a thin pass-through: it does not mirror CLI flags, so any `bru` flag works by going inside `command`.

## Action interface

Inputs:

- `command` (required): the `bru` subcommand and flags, e.g. `run --env prod`. The Action prepends `bru`. If you omit `--reporter-junit`, it auto-injects one into a temp path so the count outputs are still populated.
- `working-directory` (default `.`): usually the collection root.
- `bru-version` (default `latest`): which `@usebruno/cli` version to install. Pin it in CI to the latest published release (resolve with `scripts/latest_version.py` or `npm view @usebruno/cli version`) for a reproducible, current run.

Outputs, available as `${{ steps.<id>.outputs.<name> }}`:

- `exit-code` — the `bru` process exit code.
- `passed`, `failed`, `total` — request counts.
- `duration-ms` — total run duration.

Versioning: `@v1` is the floating major (gets every backwards-compatible release); `@v1.0.0` is an immutable specific release. Pin the `uses:` ref to the **latest specific release tag** (e.g. `@v1.0.0`) so the build is reproducible and starts current; resolve the current tag with `python scripts/latest_version.py --action-tag` or `git ls-remote --tags https://github.com/usebruno/bruno-cli-action.git`. `v1.0.0` is the latest as of this writing — confirm the current one rather than copying it. Fall back to the floating `@v1` only if you can't resolve a specific tag.

## Basic workflow

```yaml
name: Bruno API Tests

on:
  pull_request:
    branches: [main]
  push:
    branches: [main]
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
        uses: usebruno/bruno-cli-action@v1.0.0
        with:
          working-directory: collections/api
          command: 'run --env ci --reporter-junit results.xml --reporter-html report.html'

      - name: Upload Bruno reports
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: bruno-reports
          path: |
            collections/api/results.xml
            collections/api/report.html
```

## With secrets

Forward the secret as an environment variable and let the collection read it. Avoid `--env-var token=${{ secrets.X }}` on the command line, where it can land in process listings and verbose logs. GitHub also auto-masks registered secrets, but forwarding is defense in depth.

```yaml
      - name: Run Bruno smoke tests
        uses: usebruno/bruno-cli-action@v1.0.0
        with:
          working-directory: collections/api
          command: 'run --env ci --tags smoke --reporter-junit results.xml'
        env:
          API_TOKEN: ${{ secrets.API_TOKEN }}
```

## With workspace path

When the collection is nested inside a Bruno workspace:

```yaml
      - uses: usebruno/bruno-cli-action@v1.0.0
        with:
          working-directory: collections/api
          command: 'run --env ci --workspace-path ../.. --reporter-junit results.xml'
```

## Developer sandbox mode

Only when the collection needs external npm packages or filesystem access (Bruno CLI v3+ defaults to safe mode):

```yaml
      - uses: usebruno/bruno-cli-action@v1.0.0
        with:
          working-directory: collections/api
          command: 'run --env ci --sandbox developer --reporter-junit results.xml'
```

## PR comment with test results

The Action only emits JUnit; rendering is delegated to downstream actions. `EnricoMi/publish-unit-test-result-action` posts a single sticky PR comment updated in place, plus a Checks-tab check run. Run it with `if: always()` so the comment still appears when Bruno fails.

```yaml
permissions:
  pull-requests: write
  checks: write
  contents: read

# ...
      - uses: usebruno/bruno-cli-action@v1.0.0
        with:
          working-directory: collections/api
          command: 'run --env ci --reporter-junit results.xml'

      - uses: EnricoMi/publish-unit-test-result-action@v2
        if: always()
        with:
          files: collections/api/results.xml
          comment_mode: failures   # always | failures | errors | off
```

For a polyglot stack that wants everything in the Checks tab, `dorny/test-reporter@v1` with `reporter: java-junit` is the alternative.

## Threshold gating (soft gate)

The step already fails on any failure. To block only past a budget, take the outputs and `continue-on-error: true` so your own logic decides the verdict:

```yaml
      - id: bruno
        uses: usebruno/bruno-cli-action@v1.0.0
        continue-on-error: true
        with:
          working-directory: collections/api
          command: 'run --env ci --tags smoke'

      - name: Enforce failure budget
        if: steps.bruno.outputs.failed != '0'
        run: |
          echo "::error::${{ steps.bruno.outputs.failed }}/${{ steps.bruno.outputs.total }} requests failed"
          if [ "${{ steps.bruno.outputs.failed }}" -gt 2 ]; then
            echo "Failure budget exceeded, blocking."
            exit 1
          fi
```

## Scheduled production monitor

Put the Action on a `schedule` trigger for a lightweight synthetic monitor. Keep it to a fast, read-only subset and `--bail` so a broken endpoint pages quickly.

```yaml
on:
  schedule:
    - cron: '*/15 * * * *'

jobs:
  monitor:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - id: bruno
        uses: usebruno/bruno-cli-action@v1.0.0
        continue-on-error: true
        with:
          working-directory: collections/api
          command: 'run --env prod --tags smoke --bail'
      - if: steps.bruno.outputs.failed != '0'
        run: ./notify-oncall.sh "${{ steps.bruno.outputs.failed }} checks failing"
```

## mTLS / private CA

Marshal certs from secrets into temp files, then point `bru` at them:

```yaml
      - name: Materialize certs
        run: |
          mkdir -p /tmp/certs && chmod 700 /tmp/certs
          echo "$CA_CERT" > /tmp/certs/ca.pem
          echo "$CLIENT_CFG" > /tmp/certs/client.json
        env:
          CA_CERT: ${{ secrets.API_CA_CERT }}
          CLIENT_CFG: ${{ secrets.API_CLIENT_CERT_CONFIG }}

      - uses: usebruno/bruno-cli-action@v1.0.0
        with:
          working-directory: collections/api
          command: 'run --env prod --cacert /tmp/certs/ca.pem --client-cert-config /tmp/certs/client.json'
```

## Recommended CI tags

- `smoke`: quick pull request checks.
- `regression`: deeper scheduled or main-branch checks.
- `skip-ci`: requests to exclude from CI.
- `destructive`: requests requiring explicit manual approval.

## Report guidance

- JUnit: CI-native test reporting and the source for the Action's count outputs.
- JSON: programmatic processing and debugging.
- HTML: a downloadable artifact for human review.

For sensitive APIs, redact headers/bodies with `--reporter-skip-headers "Authorization Cookie X-Api-Key"` (or the broader `--reporter-skip-*` flags) so credentials never reach an artifact.

## Forked-PR caveat

GitHub restricts `GITHUB_TOKEN` to read-only for `pull_request` events from forks, regardless of the `permissions` block. Downstream actions that post comments need write access, so workflows running on community contributions must use `pull_request_target` (with care — it runs with base-branch permissions and secrets).
