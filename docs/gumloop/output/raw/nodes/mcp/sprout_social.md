> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Sprout Social

> Pull social analytics, inbox messages, and Listening data, and draft posts across your social profiles.

Sprout Social is a social media management platform for publishing, engagement, and analytics. The Sprout Social MCP server lets you pull profile and post analytics, read inbox messages and cases, query Listening Topics, and create draft posts using natural language.

## Prerequisites

Sprout Social's API is only available on the **Advanced** and **Enterprise** plans. Before connecting, make sure you have:

* The **API Permissions** permission on your Sprout user
* Sprout's Analytics API Terms of Service accepted under **Settings > Global Features > API**

<Info>
  Pulling X (Twitter) data also requires accepting the Sprout API X Content End User License Agreement on the same page.
</Info>

**To get your API token:**

1. Log in to Sprout Social
2. Go to **Settings > Global Features > API**
3. Click **Generate API Token** in the API Token Management section
4. Name the token and copy it. Sprout only shows it once.
5. Add it on your [Credentials page](https://www.gumloop.com/personal/connectors)

<Tip>
  Every Sprout endpoint needs a customer ID. Start with **List Customers** to find yours, then use **List Profiles** to get the profile IDs for analytics and publishing.
</Tip>

## What Can It Do?

* **Pull analytics** for social profiles and individual sent posts
* **Read the inbox**, including messages and support cases with their metadata
* **Query Listening Topics** for raw messages and aggregated metrics
* **Create draft posts** and upload media for future publication
* **List account metadata** such as profiles, tags, groups, users, teams, and queues

## Where to Use It

### In Agents (Recommended)

Add Sprout Social as a tool to any agent. The agent can then report on social performance and draft posts conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Sprout Social API token

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Sprout Social tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Get last week's impressions for our Instagram profile")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Account Metadata

| Tool               | Description                                                                                               |
| ------------------ | --------------------------------------------------------------------------------------------------------- |
| **List Customers** | List the Sprout customer accounts your API token can access, with the customer IDs every other tool needs |
| **List Profiles**  | List the social profiles in a customer account, with the profile IDs used by analytics and publishing     |
| **List Tags**      | List the message tags in a customer account, including active and archived tags                           |
| **List Groups**    | List the groups created in a customer account                                                             |
| **List Users**     | List the active users in a customer account                                                               |
| **List Topics**    | List the Listening Topics in a customer account, including their themes and data availability             |
| **List Teams**     | List the active teams in a customer account                                                               |
| **List Queues**    | List the active case queues in a customer account                                                         |

### Analytics

| Tool                      | Description                                                          |
| ------------------------- | -------------------------------------------------------------------- |
| **Get Profile Analytics** | Query daily profile-level metrics for a set of social profiles       |
| **Get Post Analytics**    | Query individual sent posts with their lifetime metrics and metadata |

### Inbox and Listening

| Tool                    | Description                                                                       |
| ----------------------- | --------------------------------------------------------------------------------- |
| **List Messages**       | Retrieve inbox messages sent by or received on your profiles, with their metadata |
| **List Cases**          | Retrieve cases with their metadata                                                |
| **List Topic Messages** | Query raw messages within a Listening Topic                                       |
| **Get Topic Metrics**   | Aggregate metrics across a Listening Topic, optionally bucketed by dimensions     |

### Publishing

| Tool                       | Description                                                                                                |
| -------------------------- | ---------------------------------------------------------------------------------------------------------- |
| **Create Publishing Post** | Create a draft Publishing Post for future publication                                                      |
| **Get Publishing Post**    | Retrieve a Publishing Post by its publishing post ID                                                       |
| **Upload Media**           | Upload a media file under 50 MiB, or have Sprout download it from a URL, and get a media ID for publishing |
| **Start Media Upload**     | Begin a multipart upload for media over 50 MiB and get a submission ID                                     |
| **Upload Media Part**      | Upload one part of a multipart media upload                                                                |
| **Complete Media Upload**  | Finish a multipart media upload and retrieve the media ID                                                  |

<Warning>
  Publishing tools create **drafts** only. Posts still need to be reviewed and scheduled in Sprout Social before they go live.
</Warning>

## Example Prompts

Use these with your agent or in the Agent Node:

**Find your account IDs:**

```text theme={"dark"}
List my Sprout customers and the social profiles under each one
```

**Profile performance:**

```text theme={"dark"}
Get impressions and engagements for our LinkedIn profile for the last 30 days
```

**Top posts:**

```text theme={"dark"}
Which of our Instagram posts got the most engagement last month?
```

**Inbox triage:**

```text theme={"dark"}
Show me unreplied inbox messages from the past 3 days and summarize the common themes
```

**Listening:**

```text theme={"dark"}
Summarize the volume trend for our brand Listening Topic over the last quarter
```

**Draft a post:**

```text theme={"dark"}
Draft a LinkedIn post announcing our new integration and schedule it for next Tuesday morning
```

## Troubleshooting

| Issue                 | Solution                                                                                                                                               |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 401 or 403 errors     | Confirm your API token is valid and your Sprout user has the API Permissions permission                                                                |
| "API not available"   | The API requires an Advanced or Enterprise Sprout plan, and the Analytics API Terms of Service must be accepted under Settings > Global Features > API |
| Missing customer ID   | Run List Customers first. Every other tool needs the customer ID it returns.                                                                           |
| No X (Twitter) data   | Accept the Sprout API X Content End User License Agreement under Settings > Global Features > API                                                      |
| Media upload rejected | Files over 50 MiB need the multipart flow: Start Media Upload, Upload Media Part, then Complete Media Upload                                           |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                    |

<Tip>
  Agents chain tools together. Asking "How did our social profiles perform last week?" will typically list customers, then profiles, then pull profile analytics. If results seem off, review the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Sprout Social MCP server](https://www.gumloop.com/mcp/sprout_social) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
