> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# BigQuery Workload Identity Federation

> Connect BigQuery to Gumloop without OAuth or static service-account keys using GCP Workload Identity Federation.

This guide walks you through connecting BigQuery to Gumloop using **Workload Identity Federation (WIF)**. Instead of per-user OAuth (which forces each teammate to reconnect whenever your GCP session-control window expires) or static service-account key files (which many security policies prohibit), Gumloop acts as an OIDC identity provider that your GCP project federates. Tokens are minted on demand and are short-lived, and no long-lived secrets are ever stored.

<Note>
  **Intended Audience:** GCP administrators with permission to manage workload identity pools and service accounts (`roles/iam.workloadIdentityPoolAdmin` and `roles/iam.serviceAccountAdmin`, or `roles/owner`). This setup is performed once and enables keyless BigQuery access for your whole team.
</Note>

## Why Use Workload Identity Federation?

<CardGroup cols={2}>
  <Card title="Keyless" icon="key">
    No service-account key files to store, rotate, or leak. Gumloop never holds a long-lived secret for your project.
  </Card>

  <Card title="No Daily Reconnect" icon="arrows-rotate">
    Federation is not tied to a human session, so session-control reauth windows never interrupt your agents or flows.
  </Card>

  <Card title="Short-Lived Tokens" icon="clock">
    Gumloop mints a fresh, short-lived access token for each request via GCP STS. Nothing durable is persisted.
  </Card>

  <Card title="You Stay In Control" icon="user-shield">
    You decide which service account Gumloop may impersonate and exactly which Gumloop tenant your pool trusts.
  </Card>
</CardGroup>

## How It Works

<Steps>
  <Step title="Gumloop mints an OIDC token">
    At query time, Gumloop signs a short-lived OIDC token identifying your Gumloop organization. Its issuer is `https://api.gumloop.com`.
  </Step>

  <Step title="GCP STS verifies and exchanges it">
    Your workload identity pool verifies the token's signature against Gumloop's published JWKS and checks your attribute condition, then exchanges it for a federated token.
  </Step>

  <Step title="Gumloop impersonates your service account">
    The federated token impersonates the target service account you nominate, yielding a short-lived Google access token scoped to BigQuery.
  </Step>

  <Step title="Gumloop queries BigQuery">
    That access token is used for the BigQuery call, then discarded.
  </Step>
</Steps>

Gumloop's OIDC issuer publishes its discovery document and public keys, which your pool uses to verify tokens:

* Discovery: `https://api.gumloop.com/.well-known/openid-configuration`
* JWKS: `https://api.gumloop.com/oauth/jwks`

<Accordion title="Claims included in the Gumloop OIDC token">
  Every claim is derived server-side from the authenticated credential owner; none come from user input.

  * `iss` — `https://api.gumloop.com`
  * `sub` — `gumloop:project:<workspace_id>` or `gumloop:user:<user_id>`
  * `aud` — the full resource path of your pool provider
  * `gumloop_org_id` — your Gumloop organization ID (use this to lock down the pool)
  * `gumloop_owner_type` — `project` or `user`
  * `gumloop_owner_id` — the Gumloop workspace or user ID that owns the credential
  * `iat` / `exp` — issued-at and a short (5 minute) expiry
</Accordion>

***

## Prerequisites

Before you begin, gather:

* Your **GCP project ID** and **project number** (`gcloud projects describe YOUR_PROJECT_ID --format="value(projectNumber)"`)
* Your **Gumloop organization ID** — provided by Gumloop. This is what you will pin your pool to so that only your tenant's tokens are accepted.
* Permission to create workload identity pools and service accounts in the project

Then enable the APIs this setup relies on:

```bash theme={"dark"}
gcloud services enable iamcredentials.googleapis.com bigquery.googleapis.com \
  --project="YOUR_PROJECT_ID"
```

* `iamcredentials.googleapis.com` — required for Gumloop to impersonate your service account (the `generateAccessToken` call). Without it, token exchange fails with a `403 SERVICE_DISABLED` error.
* `bigquery.googleapis.com` — required to run queries.

<Warning>
  **Pin your pool to your Gumloop organization.** Because Gumloop's issuer can mint tokens for any Gumloop customer, you must set an attribute condition that restricts your pool to your own `gumloop_org_id`. Without it, any Gumloop tenant's token could be accepted by your pool. This is the single most important step for tenant isolation.
</Warning>

***

## Step 1: Create a Workload Identity Pool

```bash theme={"dark"}
gcloud iam workload-identity-pools create gumloop-pool \
  --project="YOUR_PROJECT_ID" \
  --location="global" \
  --display-name="Gumloop"
```

## Step 2: Add Gumloop as an OIDC Provider

Create an OIDC provider in the pool that trusts Gumloop's issuer, maps the token claims, and restricts access to your Gumloop organization.

```bash theme={"dark"}
gcloud iam workload-identity-pools providers create-oidc gumloop-oidc \
  --project="YOUR_PROJECT_ID" \
  --location="global" \
  --workload-identity-pool="gumloop-pool" \
  --issuer-uri="https://api.gumloop.com" \
  --attribute-mapping="google.subject=assertion.sub,attribute.gumloop_org_id=assertion.gumloop_org_id,attribute.gumloop_owner_id=assertion.gumloop_owner_id" \
  --attribute-condition="assertion.gumloop_org_id == 'YOUR_GUMLOOP_ORG_ID'"
```

<Accordion title="Understanding these parameters">
  * **issuer-uri** — Gumloop's OIDC issuer. GCP reads `https://api.gumloop.com/.well-known/openid-configuration` to discover the JWKS used to verify tokens.
  * **attribute-mapping** — Maps Gumloop token claims into pool attributes. `google.subject` is required; the `attribute.*` mappings let you reference claims in conditions and IAM bindings.
  * **attribute-condition** — The security gate. Only tokens whose `gumloop_org_id` equals your organization are allowed. Replace `YOUR_GUMLOOP_ORG_ID` with the value Gumloop provided.
</Accordion>

<Info>
  You do not need to set `--allowed-audiences`. Gumloop sets the token audience to the provider's full resource path, which is the default audience GCP accepts.
</Info>

## Step 3: Create the Target Service Account

This is the identity Gumloop will impersonate. Grant it only the BigQuery permissions your team needs.

```bash theme={"dark"}
gcloud iam service-accounts create bigquery-runner \
  --project="YOUR_PROJECT_ID" \
  --display-name="Gumloop BigQuery Runner"

gcloud projects add-iam-policy-binding YOUR_PROJECT_ID \
  --member="serviceAccount:bigquery-runner@YOUR_PROJECT_ID.iam.gserviceaccount.com" \
  --role="roles/bigquery.dataViewer"

gcloud projects add-iam-policy-binding YOUR_PROJECT_ID \
  --member="serviceAccount:bigquery-runner@YOUR_PROJECT_ID.iam.gserviceaccount.com" \
  --role="roles/bigquery.jobUser"
```

<Tip>
  Follow the principle of least privilege. `roles/bigquery.dataViewer` + `roles/bigquery.jobUser` is enough to run read queries. Grant narrower dataset-level roles if you prefer.
</Tip>

## Step 4: Allow the Pool to Impersonate the Service Account

Grant `roles/iam.workloadIdentityUser` on the service account to the federated identities from your pool, scoped to your `gumloop_org_id` attribute.

```bash theme={"dark"}
gcloud iam service-accounts add-iam-policy-binding \
  bigquery-runner@YOUR_PROJECT_ID.iam.gserviceaccount.com \
  --project="YOUR_PROJECT_ID" \
  --role="roles/iam.workloadIdentityUser" \
  --member="principalSet://iam.googleapis.com/projects/PROJECT_NUMBER/locations/global/workloadIdentityPools/gumloop-pool/attribute.gumloop_org_id/YOUR_GUMLOOP_ORG_ID"
```

<Warning>
  Use the **project number** (not the project ID) in the `principalSet://` member, and scope it with `attribute.gumloop_org_id/...` rather than a wildcard so only your organization's tokens can impersonate the service account.
</Warning>

## Step 5: Collect the Three Values for Gumloop

You will paste these into Gumloop:

1. **GCP Project Number** — e.g. `123456789012`
2. **Workload Identity Pool Resource Name** — the provider resource path:
   ```text theme={"dark"}
   projects/PROJECT_NUMBER/locations/global/workloadIdentityPools/gumloop-pool/providers/gumloop-oidc
   ```
3. **Target Service Account Email** — e.g. `bigquery-runner@YOUR_PROJECT_ID.iam.gserviceaccount.com`

## Step 6: Add the Credential in Gumloop

1. Go to the [Connectors page](https://www.gumloop.com/personal/connectors) (or your workspace credentials for a shared, team-wide connection).
2. Click **Add Credential** and select **BigQuery (Workload Identity)**.
3. Enter the three values from Step 5 and save.

That's it. Your BigQuery nodes and agents will now mint short-lived access tokens through Workload Identity Federation, with no OAuth prompts and no daily reconnect.

<Tip>
  Add the credential at the **workspace** level so the whole team shares one keyless connection. Add it at the **personal** level if you only need it for your own flows.
</Tip>

***

## Troubleshooting

### "Permission denied" or no credentials found

* Confirm the attribute condition value matches the `gumloop_org_id` Gumloop provided exactly.
* Confirm the `principalSet://` binding uses the **project number** and the same `attribute.gumloop_org_id` value.
* Confirm the service account has `roles/bigquery.jobUser` (needed to run queries) in addition to a data-read role.

### "IAM Service Account Credentials API has not been used... or it is disabled"

The impersonation step calls `iamcredentials.googleapis.com`, which must be enabled in your project. Enable it (and the BigQuery API), then wait a minute or two for the change to propagate:

```bash theme={"dark"}
gcloud services enable iamcredentials.googleapis.com bigquery.googleapis.com \
  --project="YOUR_PROJECT_ID"
```

### "Invalid audience" / token rejected by STS

* Make sure the **Workload Identity Pool Resource Name** you pasted into Gumloop is the full provider path ending in `/providers/<provider-id>`.

### Verifying the issuer is reachable

The discovery and JWKS endpoints are public and should return JSON:

```bash theme={"dark"}
curl https://api.gumloop.com/.well-known/openid-configuration
curl https://api.gumloop.com/oauth/jwks
```

***

## Additional Resources

* [GCP Workload Identity Federation documentation](https://cloud.google.com/iam/docs/workload-identity-federation)
* [Configuring OIDC-based federation](https://cloud.google.com/iam/docs/workload-identity-federation-with-other-providers)
* [Gumloop Credentials Guide](/core-concepts/credentials)

## Need Help?

If you run into issues not covered here, [reach out to us](https://portal.usepylon.com/gumloop/forms/help).
