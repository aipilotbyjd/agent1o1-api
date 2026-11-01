> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Meta Ads

> Manage Meta (Facebook) advertising and Pages with AI-powered automation.

Meta Ads connects to the Meta (Facebook) Marketing API over the Graph API. The Meta Ads MCP server lets you manage ad accounts, campaigns, ad sets, ads, and creatives, pull performance insights, and read Facebook Page content using natural language.

## What Can It Do?

* **Browse ad accounts** you can access and their details
* **Manage campaigns** by creating, listing, activating, pausing, archiving, and deleting them
* **Manage ad sets** including budget, schedule, optimization, and targeting
* **Manage ads and creatives** by creating (individually or in batches) and updating them
* **Toggle status** to activate or pause campaigns, ad sets, and ads
* **Pull performance insights** (spend, impressions, clicks, conversions) at account, campaign, ad set, or ad level
* **Read Facebook Pages** including metadata, engagement, posts, comments, and reactions

## Where to Use It

### In Agents (Recommended)

Add Meta Ads as a tool to any agent. The agent can then manage your advertising conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Meta Ads tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Pause all active campaigns in my ad account")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Ad Accounts

| Tool                 | Description                               |
| -------------------- | ----------------------------------------- |
| **List Ad Accounts** | List the ad accounts you can access       |
| **Get Ad Account**   | Get details for a single ad account by ID |

### Campaigns

| Tool                       | Description                                                         |
| -------------------------- | ------------------------------------------------------------------- |
| **Create Campaign**        | Create a new campaign in an ad account                              |
| **List Campaigns**         | List campaigns in an ad account, optionally filtered by status      |
| **Get Campaign**           | Get details for a single campaign by ID                             |
| **Toggle Campaign Status** | Activate or pause a campaign                                        |
| **Archive Campaign**       | Archive a campaign, stopping it from running while keeping its data |
| **Delete Campaigns**       | Bulk delete campaigns using a deletion strategy (cannot be undone)  |

### Ad Sets

| Tool                     | Description                                                                                  |
| ------------------------ | -------------------------------------------------------------------------------------------- |
| **Create Ad Set**        | Create a new ad set under a campaign, defining budget, schedule, optimization, and targeting |
| **List Ad Sets**         | List ad sets in an ad account, optionally scoped to one campaign or filtered by status       |
| **Get Ad Set**           | Get details for a single ad set by ID                                                        |
| **Toggle Ad Set Status** | Activate or pause an ad set                                                                  |

### Ads

| Tool                 | Description                                                                                  |
| -------------------- | -------------------------------------------------------------------------------------------- |
| **Create Ad**        | Create an ad by combining an existing ad set with an ad creative                             |
| **Create Ads**       | Create multiple ads in one account via a single batch request (up to 50 ads)                 |
| **List Ads**         | List ads in an ad account, optionally scoped to one campaign or ad set or filtered by status |
| **Get Ad**           | Get details for a single ad by ID                                                            |
| **Update Ad**        | Update mutable fields on an existing ad                                                      |
| **Toggle Ad Status** | Activate or pause an ad                                                                      |

### Ad Creatives

| Tool                   | Description                                                             |
| ---------------------- | ----------------------------------------------------------------------- |
| **Create Ad Creative** | Create an ad creative defining the visual and textual elements of an ad |
| **List Ad Creatives**  | List the ad creatives stored in an ad account's creative library        |
| **Get Ad Creative**    | Get details for a single ad creative by ID                              |
| **Update Ad Creative** | Update an existing ad creative's name, status, or ad labels             |

### Facebook Pages

| Tool                    | Description                                                                 |
| ----------------------- | --------------------------------------------------------------------------- |
| **List Pages**          | List the Facebook Pages you manage                                          |
| **Get Page**            | Get a Page's metadata and engagement stats (followers, fan count, category) |
| **List Page Posts**     | List the posts published on a Page                                          |
| **Get Page Post**       | Get a single Page post by ID                                                |
| **List Post Comments**  | List the comments on a Page post                                            |
| **List Post Reactions** | List the reactions on a Page post                                           |

### Insights

| Tool             | Description                                                                                                   |
| ---------------- | ------------------------------------------------------------------------------------------------------------- |
| **Get Insights** | Get performance insights (spend, impressions, clicks, conversions) for an ad account, campaign, ad set, or ad |

## Example Prompts

Use these with your agent or in the Agent Node:

**Review performance:**

```text theme={"dark"}
Show me spend, impressions, and clicks for all campaigns in my ad account this month
```

**Pause a campaign:**

```text theme={"dark"}
Pause the "Summer Sale" campaign
```

**Create a campaign:**

```text theme={"dark"}
Create a new traffic campaign called "Q4 Awareness" in my ad account
```

**Create ads in bulk:**

```text theme={"dark"}
Create ads for the "Retargeting" ad set using my three latest creatives
```

**Read Page engagement:**

```text theme={"dark"}
List the most recent posts on my Facebook Page and show the comments on each
```

## Troubleshooting

| Issue                 | Solution                                                                                                                                       |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Action not completing | Check that you've authenticated with your Meta account and have access to the ad account                                                       |
| Permission errors     | Write actions (creating or updating campaigns, ad sets, ads) require the `ads_management` permission; Page tools require Page access           |
| Unexpected results    | The agent may chain multiple tools (e.g., listing campaigns first, then pausing one). Review the agent's reasoning to understand its approach. |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                            |

<Warning>
  Deleting campaigns cannot be undone. To stop a campaign while keeping its data, archive or pause it instead.
</Warning>

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Pause my worst-performing campaign" will pull insights, compare campaigns, and pause the right one. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Meta Ads MCP server](https://www.gumloop.com/mcp/meta_ads) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
