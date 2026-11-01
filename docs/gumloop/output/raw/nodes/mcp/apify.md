> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Apify

> Discover and run web scraping Actors with AI-powered automation.

Apify is a platform for web scraping, data extraction, and automation. The Apify MCP server lets you search the Apify Store, run Actors and saved tasks, monitor runs, and retrieve results using natural language.

## What Can It Do?

* **Search and discover Actors** in the Apify Store
* **Run Actors** synchronously or asynchronously with validated input
* **Manage saved tasks** for preconfigured Actor runs
* **Monitor run status** and retrieve logs
* **Read dataset results** from completed runs

## Where to Use It

### In Agents (Recommended)

Add Apify as a tool to any agent. The agent can then discover and run Actors conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Apify tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Run a web scraper Actor on a URL")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Actor Discovery

| Tool                     | Description                                        |
| ------------------------ | -------------------------------------------------- |
| **Search Actors**        | Search runnable Actors in the Apify Store          |
| **Get Actor**            | Get Actor metadata and optionally its input schema |
| **Validate Actor Input** | Validate input for an Actor build before running   |

### Running Actors

| Tool            | Description                                  |
| --------------- | -------------------------------------------- |
| **Run Actor**   | Run an Actor synchronously or asynchronously |
| **Get Run**     | Get Actor run status and metadata            |
| **Abort Run**   | Abort a running Actor run                    |
| **Get Run Log** | Get the log output from an Actor run         |

### Saved Tasks

| Tool           | Description                                            |
| -------------- | ------------------------------------------------------ |
| **List Tasks** | List saved Apify Actor tasks                           |
| **Get Task**   | Get a saved Apify Actor task                           |
| **Run Task**   | Run a saved Actor task synchronously or asynchronously |

### Results

| Tool                  | Description                     |
| --------------------- | ------------------------------- |
| **Get Dataset Items** | Get items from an Apify dataset |

<Info>
  The Gumloop-managed Apify key supports searching public Actors, reading public metadata, and running public limited-permission Actors synchronously. For full-permission Actors, async runs, saved tasks, run status, and dataset reads, connect your own Apify API key.
</Info>

## Example Prompts

Use these with your agent or in the Agent Node:

**Search for an Actor:**

```text theme={"dark"}
Find an Actor in the Apify Store that can scrape Google Maps reviews
```

**Run a scraper:**

```text theme={"dark"}
Run the web scraper Actor on https://example.com and return the results
```

**Check run status:**

```text theme={"dark"}
Check the status of my last Apify run
```

**Get results:**

```text theme={"dark"}
Get the dataset items from my completed Actor run
```

**Run a saved task:**

```text theme={"dark"}
Run my saved "Daily Product Scrape" task
```

## Troubleshooting

| Issue                             | Solution                                                                                                            |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| "Full-permission Actor" error     | Some Actors require your own Apify API key. Connect it in the integration settings                                  |
| Run stuck or timing out           | For long-running Actors, use async mode and check status with Get Run                                               |
| Agent not finding the right Actor | Use specific keywords or the Actor's full name from the Apify Store                                                 |
| Tool not available                | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |

<Tip>
  Agents can chain tools together automatically. For example, asking "Scrape product data from this URL" will search for a suitable Actor, validate the input, run it, and return the results. Review the agent's reasoning if results seem off.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Apify MCP server](https://www.gumloop.com/mcp/apify) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
