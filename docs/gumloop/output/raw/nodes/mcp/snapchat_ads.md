> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Snapchat Ads

> Manage Snapchat advertising, audiences, and measurement with AI-powered automation.

Snapchat Ads connects to the Snap Marketing API. The Snapchat Ads MCP server lets you manage ad accounts, campaigns, ad squads, ads, creatives, and media, build audiences, pull performance stats, send conversion events, and browse Snap's public ads library using natural language.

## What Can It Do?

* **Browse accounts** including organizations, ad accounts, funding sources, and billing centers
* **Manage campaigns, ad squads, and ads** by creating, listing, and updating them
* **Create creatives and upload media** from your Gumloop workspace
* **Build audiences** with customer lists, lookalikes, and profile engagement segments
* **Explore targeting options** for demographics, devices, geo, and interests, and estimate audience size
* **Pull performance stats** at account, campaign, ad squad, and ad level, plus Snap Pixel event stats
* **Send server-side conversion events** to the Snap Conversions API
* **Browse the public ads library** for EU ad delivery and live sponsored content

## Where to Use It

### In Agents (Recommended)

Add Snapchat Ads as a tool to any agent. The agent can then manage your advertising conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Snapchat Ads tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Pause spend reporting for last week's campaigns")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Account Tools

| Tool                     | Description                                                             |
| ------------------------ | ----------------------------------------------------------------------- |
| **List Organizations**   | List the organizations you belong to, optionally with their ad accounts |
| **List Ad Accounts**     | List the ad accounts under an organization                              |
| **Get Ad Account**       | Get details for a single ad account by ID                               |
| **List Funding Sources** | List the funding sources under an organization                          |
| **List Billing Centers** | List the billing centers under an organization                          |

### Campaign Tools

| Tool                | Description                                               |
| ------------------- | --------------------------------------------------------- |
| **List Campaigns**  | List the campaigns in an ad account                       |
| **Get Campaign**    | Get details for a single campaign by ID                   |
| **Create Campaign** | Create a campaign in an ad account                        |
| **Update Campaign** | Update a campaign by replacing all of its writable fields |

### Ad Squad Tools

| Tool                | Description                                                |
| ------------------- | ---------------------------------------------------------- |
| **List Ad Squads**  | List ad squads in an ad account or under a single campaign |
| **Get Ad Squad**    | Get details for a single ad squad by ID                    |
| **Create Ad Squad** | Create an ad squad within a campaign                       |
| **Update Ad Squad** | Update an ad squad by replacing all of its writable fields |

### Ad Tools

| Tool          | Description                                                       |
| ------------- | ----------------------------------------------------------------- |
| **List Ads**  | List ads in an ad account, or under a single campaign or ad squad |
| **Get Ad**    | Get details for a single ad by ID                                 |
| **Create Ad** | Create an ad within an ad squad from a creative                   |
| **Update Ad** | Update an ad by replacing all of its writable fields              |

### Creative and Media Tools

| Tool                | Description                                                                       |
| ------------------- | --------------------------------------------------------------------------------- |
| **List Creatives**  | List the creatives in an ad account                                               |
| **Get Creative**    | Get details for a single creative by ID                                           |
| **Create Creative** | Create a creative in an ad account from uploaded media                            |
| **List Media**      | List the media (videos and images) in an ad account                               |
| **Get Media**       | Get details for a single media item by ID                                         |
| **Upload Media**    | Create a media entity and upload the file from your Gumloop workspace in one step |

### Audience and Targeting Tools

| Tool                        | Description                                                                                                |
| --------------------------- | ---------------------------------------------------------------------------------------------------------- |
| **List Audience Segments**  | List the audience segments in an ad account                                                                |
| **Get Audience Segment**    | Get details for a single audience segment by ID                                                            |
| **Create Audience Segment** | Create an audience segment in an ad account                                                                |
| **Add Users To Segment**    | Add emails, phone numbers, or mobile ad IDs to a customer-list segment (hashed with SHA256 before upload)  |
| **Get Audience Size**       | Get the estimated audience size for an existing ad squad or a prospective ad squad spec                    |
| **List Targeting Options**  | List valid targeting values for a dimension (demographics, device, geo, interests, or location categories) |

### Reporting and Measurement Tools

| Tool                      | Description                                                                                  |
| ------------------------- | -------------------------------------------------------------------------------------------- |
| **Get Ad Account Stats**  | Get performance stats for an ad account                                                      |
| **Get Campaign Stats**    | Get performance stats for a campaign                                                         |
| **Get Ad Squad Stats**    | Get performance stats for an ad squad                                                        |
| **Get Ad Stats**          | Get performance stats for an ad                                                              |
| **List Pixels**           | List the Snap Pixels under an ad account                                                     |
| **Get Pixel Stats**       | Get event stats for a Snap Pixel                                                             |
| **Send Conversion Event** | Send a server-side conversion event with hashed user identifiers to the Snap Conversions API |

### Public Ads Library Tools

| Tool                                    | Description                                                                                    |
| --------------------------------------- | ---------------------------------------------------------------------------------------------- |
| **Search Ads Library**                  | Search Snap's public ads library for ads delivered in the European Union in the last 12 months |
| **Get Ads Library Ad**                  | Get preview details for a single ad from the public ads library                                |
| **List Sponsored Content**              | List organic commercial (sponsored) content currently live on Snap                             |
| **Search Sponsored Content By Creator** | Search live sponsored content by creator name                                                  |

## Example Prompts

Use these with your agent or in the Agent Node:

**Review performance:**

```text theme={"dark"}
Show me spend, impressions, and swipes for every campaign in my Snapchat ad account this month
```

**Create a campaign:**

```text theme={"dark"}
Create a campaign called "Q4 Awareness" in my Snapchat ad account with a $500 daily budget
```

**Launch an ad:**

```text theme={"dark"}
Upload holiday_promo.mp4 from my workspace, turn it into a creative, and create an ad for the "Retargeting" ad squad
```

**Build an audience:**

```text theme={"dark"}
Create a customer-list segment called "VIP Buyers" and add the emails from my CSV
```

**Check targeting reach:**

```text theme={"dark"}
Estimate the audience size for 18-24 year olds in the US interested in fitness
```

**Browse the ads library:**

```text theme={"dark"}
Find ads from Nike that ran in Germany in the last six months
```

## Troubleshooting

| Issue                             | Solution                                                                                                                                         |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Action not completing             | Check that you've authenticated with a Snapchat account that has access to the organization and ad account                                       |
| Conversion events rejected        | Event timestamps cannot be more than 7 days in the past, `PURCHASE` events need a value, and `WEB` events need a page URL                        |
| Empty pixel stats                 | The ad account needs a Snap Pixel installed and receiving events                                                                                 |
| No stats for the requested window | `DAY` and `HOUR` granularity require both a start and end time in ISO 8601 UTC                                                                   |
| Update removed a field            | Update tools replace all writable fields, so pass every field you want to keep                                                                   |
| Unexpected results                | The agent may chain multiple tools (e.g., listing campaigns first, then pulling stats). Review the agent's reasoning to understand its approach. |
| Tool not available                | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                              |

<Note>
  **Add Users To Segment** and **Send Conversion Event** hash emails, phone numbers, and mobile ad IDs with SHA256 before sending them to Snap, so you can pass plain identifiers. Values that are already hashed are passed through unchanged.
</Note>

<Note>
  New campaigns, ad squads, and ads default to `PAUSED` so nothing spends until you activate it. Budgets are set in micro-currency (1,000,000 = 1 unit of the account's currency).
</Note>

<Warning>
  Update Campaign, Update Ad Squad, and Update Ad replace every writable field on the entity. Fetch the entity first (or include all of its current values) so you don't clear settings you meant to keep.
</Warning>

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Pause my worst-performing Snapchat campaign" will pull stats, compare campaigns, and update the right one. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Snapchat Ads MCP server](https://www.gumloop.com/mcp/snapchat_ads) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
