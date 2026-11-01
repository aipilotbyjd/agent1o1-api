# Docker Patterns for Bruno

Everywhere outside GitHub Actions — GitLab CI, Jenkins, Azure Pipelines, Bitbucket, Kubernetes jobs, and local pre-push hooks — prefer the **official Bruno CLI Docker image**, `usebruno/cli`. It bakes `bru` on top of Node so there is nothing to install on the host, and it gives byte-for-byte identical runs locally and in the pipeline.

## Image facts

- **Entrypoint is `bru`; working directory is `/bruno`.** Bind-mount your collection to `/bruno`; everything after the image name is passed straight to `bru`. So `usebruno/cli run --env ci` runs `bru run --env ci` inside the container.
- **Registries:** Docker Hub (`usebruno/cli`) and GHCR (`ghcr.io/usebruno/cli`) — identical images.
- **Variants:** `alpine` (default, ~133 MB) for almost everyone; `debian` if you hit a glibc/SSL edge case.
- **Non-root by default** (UID 1000) and **multi-arch** (`linux/amd64`, `linux/arm64`), so it runs natively on ARM and Apple Silicon.
- **Pinning:** `latest` floats (fine locally); `usebruno/cli:3.5` floats within a minor; `usebruno/cli:3.5.0` is immutable; `...@sha256:` is a digest. **In CI, pin to the latest published exact version** (resolve it with `python scripts/latest_version.py` or `npm view @usebruno/cli version`) so new setups start current and the pipeline can't change underneath you. `3.5.0` is the latest as of this writing — confirm the current one rather than copying that number.

## First run / local

```bash
# from inside your collection directory
docker run --rm -v "$(pwd):/bruno" usebruno/cli run --env ci --reporter-junit results.xml

# a subfolder, recursing, against staging
docker run --rm -v "$(pwd):/bruno" usebruno/cli run ./smoke --env staging -r
```

`-v "$(pwd):/bruno"` is required so `bru` can see your request files; `--rm` cleans up the container on exit. On Windows use `${PWD}` (PowerShell) or `%cd%` (CMD) instead of `$(pwd)`.

## Secrets

Forward the host/CI variable with `-e NAME` (no value on the command line) and let the collection read it. Avoid `--env-var token=$SECRET`, which the shell expands before Docker sees it, exposing the literal in process listings and logs.

```bash
docker run --rm -v "$(pwd):/bruno" -e API_TOKEN \
  usebruno/cli:3.5.0 run --env ci --reporter-junit results.xml
```

Redact sensitive data from reports with `--reporter-skip-headers "Authorization Cookie X-Api-Key"` (or `--reporter-skip-all-headers`, `--reporter-skip-request-body`, `--reporter-skip-response-body`, `--reporter-skip-body`).

## Hardened, read-only container

For security-sensitive pipelines, mount the checkout read-only and give Bruno only a writable reports path:

```bash
docker run --rm \
  --read-only \
  --tmpfs /tmp \
  -v "$(pwd):/bruno:ro" \
  -v "$(pwd)/reports:/reports" \
  usebruno/cli:3.5.0 run --env ci --reporter-junit /reports/results.xml
```

## GitLab CI

```yaml
bruno-tests:
  image: usebruno/cli:3.5.0
  variables:
    # entrypoint is `bru`; GitLab runs the script in the image, so call bru via the entrypoint
    GIT_DEPTH: "1"
  script:
    - bru run --env ci --reporter-junit results.xml --reporter-html report.html
  artifacts:
    when: always
    paths:
      - results.xml
      - report.html
    reports:
      junit: results.xml
```

GitLab mounts the repo as the working directory and overrides the entrypoint when it runs `script:`, so call `bru` explicitly. Pass secrets as masked CI/CD variables; reference them from the collection. Native JUnit integration comes from `artifacts:reports:junit`.

## Jenkins (declarative pipeline)

```groovy
pipeline {
  agent {
    docker {
      image 'usebruno/cli:3.5.0'
      args '--entrypoint=""'   // let Jenkins run shell steps in the container
    }
  }
  stages {
    stage('API tests') {
      steps {
        sh 'bru run --env ci --reporter-junit results.xml --reporter-html report.html'
      }
    }
  }
  post {
    always {
      junit 'results.xml'
      archiveArtifacts artifacts: 'report.html', allowEmptyArchive: true
    }
  }
}
```

Inject secrets with the `withCredentials` / `environment` blocks and let the collection read the variable.

## Azure Pipelines

```yaml
steps:
  - script: |
      docker run --rm -v "$(Build.SourcesDirectory):/bruno" -e API_TOKEN \
        usebruno/cli:3.5.0 run --env ci --reporter-junit results.xml
    displayName: Run Bruno
    env:
      API_TOKEN: $(API_TOKEN)   # secret pipeline variable
  - task: PublishTestResults@2
    condition: always()
    inputs:
      testResultsFormat: JUnit
      testResultsFiles: results.xml
```

## Kubernetes Job

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: bruno-api-tests
spec:
  backoffLimit: 0
  template:
    spec:
      restartPolicy: Never
      containers:
        - name: bruno
          image: usebruno/cli:3.5.0
          args: ["run", "--env", "ci", "--reporter-junit", "/reports/results.xml"]
          env:
            - name: API_TOKEN
              valueFrom:
                secretKeyRef:
                  name: bruno-secrets
                  key: api-token
          volumeMounts:
            - { name: collection, mountPath: /bruno }
            - { name: reports, mountPath: /reports }
      volumes:
        - name: collection
          configMap: { name: bruno-collection }   # or a git-sync sidecar / PVC
        - name: reports
          emptyDir: {}
```

## Data-driven runs

Drive the same request once per row of a fixtures file (visible to the container if it lives inside the mounted directory):

```bash
docker run --rm -v "$(pwd):/bruno" \
  usebruno/cli:3.5.0 run ./checkout \
  --csv-file-path ./data/accounts.csv \
  --reporter-junit results.xml
```

## Reproducing GitHub runs locally

Because the Action and the image both wrap the same `bru run`, a command you debug in the container works in the Action and vice versa — handy for reproducing a CI failure on your laptop.
