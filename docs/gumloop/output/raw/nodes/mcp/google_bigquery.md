> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# BigQuery

> Query and explore your data warehouse with AI-powered SQL automation.

Google BigQuery is a serverless data warehouse for analytics at scale. The BigQuery MCP server lets you explore projects, inspect datasets, and run SQL using natural language.

## What Can It Do?

* **Discover projects and datasets** you have access to
* **Inspect table metadata** including schema and row counts
* **Run SQL queries** and return structured results
* **Power data workflows** by chaining outputs to other tools

## Where to Use It

### In Agents (Recommended)

Add BigQuery as a tool to any agent. The agent can then query and explore your data conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with BigQuery tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Run this SQL query on my dataset")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Authentication

BigQuery supports two ways to connect your GCP credentials:

<CardGroup cols={2}>
  <Card title="Google OAuth (default)" icon="google">
    Sign in with your Google account. Quick to set up, but requires re-authentication when your GCP session-control window expires (typically every 1–24 hours, depending on your org's policy).
  </Card>

  <Card title="Workload Identity Federation" icon="key">
    Keyless, token-based access. No daily reconnect, no stored secrets. Best for teams that need always-on BigQuery access without manual re-auth.
  </Card>
</CardGroup>

### Setting up Workload Identity Federation (WIF)

WIF lets Gumloop mint short-lived access tokens by federating into your GCP project. There are no static keys or OAuth sessions to manage.

To set it up:

1. Create a workload identity pool and OIDC provider in your GCP project that trusts Gumloop's issuer (`https://api.gumloop.com`)
2. Create a target service account with the BigQuery permissions your team needs
3. Grant the pool permission to impersonate that service account
4. In Gumloop, go to your [Connectors page](https://www.gumloop.com/personal/connectors), click **Add Credential**, and select **BigQuery (Workload Identity)**
5. Enter three values: your **GCP Project Number**, the **Workload Identity Pool Resource Name** (this must be the full **provider** resource path, e.g. `projects/123456789012/locations/global/workloadIdentityPools/gumloop-pool/providers/gumloop-oidc`), and the **Target Service Account Email**

<Tip>
  Add the WIF credential at the **team** level so the whole team shares one keyless connection. Use a **personal** credential if you only need it for your own agents.
</Tip>

For the complete setup walkthrough (including exact `gcloud` commands), see the [BigQuery Workload Identity Federation guide](/nodes/integrations/bigquery-workload-identity-federation).

### FAQ

<AccordionGroup>
  <Accordion title="Can I use OAuth and WIF at the same time?">
    Yes. If you have both a standard BigQuery OAuth credential and a WIF credential, Gumloop will try your OAuth token first. If it's expired or missing, it will automatically fall back to your WIF credential. You don't need to choose one or the other.
  </Accordion>

  <Accordion title="Do I need to change anything in my agents or workflows?">
    No. WIF works transparently. Your agents and workflows continue to use BigQuery the same way. The only difference is how Gumloop authenticates behind the scenes.
  </Accordion>

  <Accordion title="Why would I pick WIF over OAuth?">
    If your GCP organization enforces short session-control windows (e.g., 1-hour reauth), OAuth credentials require frequent reconnection. WIF eliminates that. It's also the better choice for teams that prohibit static service-account key files.
  </Accordion>
</AccordionGroup>

## Available Tools

| Tool                 | Description                           |
| -------------------- | ------------------------------------- |
| **List Project Ids** | List accessible Google Cloud projects |
| **List Dataset Ids** | List datasets in a project            |
| **List Table Ids**   | List tables in a dataset              |
| **Get Dataset Info** | Get dataset metadata                  |
| **Get Table Info**   | Get table schema and row count        |
| **Execute SQL**      | Run a SQL query and return results    |

## Example Prompts

Use these with your agent or in the Agent Node:

**Discover projects:**

```text theme={"dark"}
List all Google Cloud projects I have access to
```

**Explore datasets:**

```text theme={"dark"}
List all datasets in my analytics project
```

**Run a query:**

```text theme={"dark"}
Run "SELECT * FROM sales WHERE region = 'West' LIMIT 100"
```

**Get table info:**

```text theme={"dark"}
Show me the schema and row count for the customers table
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                   |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Specify the project ID and dataset explicitly                                                                                              |
| Action not completing            | Check that you've authenticated and have BigQuery access                                                                                   |
| Unexpected results               | The agent may chain multiple tools (e.g., listing datasets first, then querying). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                        |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "What tables are in my analytics dataset?" will find the project and dataset first, then list tables. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [BigQuery MCP server](https://www.gumloop.com/mcp/gbigquery) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
