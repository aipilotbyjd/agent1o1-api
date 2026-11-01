> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# LaunchDarkly

> Manage feature flags, segments, and environments with AI-powered release automation.

LaunchDarkly is a feature management platform. The LaunchDarkly MCP server lets you create and manage feature flags, segments, and environments using natural language.

## What Can It Do?

* **Create and manage feature flags** across projects
* **Target specific users and segments** with flag rules
* **Monitor flag status** across environments
* **Manage segments** for grouped targeting

## Where to Use It

### In Agents (Recommended)

Add LaunchDarkly as a tool to any agent. The agent can then interact with LaunchDarkly conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with LaunchDarkly tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all feature flags in production")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                        | Description                                                                            |
| --------------------------- | -------------------------------------------------------------------------------------- |
| **List Projects**           | List all projects in your LaunchDarkly account                                         |
| **List Environments**       | List all environments within a project                                                 |
| **List Feature Flags**      | List all feature flags in a project with filtering options                             |
| **Get Feature Flag**        | Get a single feature flag by key with rollout info                                     |
| **Create Feature Flag**     | Create a new feature flag                                                              |
| **Update Feature Flag**     | Update a feature flag - turn on/off, add/remove targets by context (e.g. business\_id) |
| **Delete Feature Flag**     | Delete a feature flag                                                                  |
| **Get Feature Flag Status** | Get flag status in an environment (new, active, inactive, launched)                    |
| **List Code Repositories**  | List connected code repositories for code references                                   |
| **List Segments**           | List all segments in an environment                                                    |
| **Get Segment**             | Get a segment with included/excluded contexts                                          |
| **Create Segment**          | Create a new segment                                                                   |
| **Update Segment**          | Update a segment - add/remove contexts (e.g. business\_id) to include/exclude          |
| **Delete Segment**          | Delete a segment                                                                       |

## Example Prompts

Use these with your agent or in the Agent Node:

**List flags:**

```text theme={"dark"}
Show me all feature flags in the production project
```

**Toggle a flag:**

```text theme={"dark"}
Turn on the new-checkout flag in the staging environment
```

**Check status:**

```text theme={"dark"}
What's the status of the dark-mode flag?
```

## Troubleshooting

| Issue                 | Solution                                                                                                            |
| --------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Authentication failed | Verify your LaunchDarkly credentials and that you have the required permissions                                     |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals) |
| Unexpected results    | The agent may chain multiple tools together. Review the agent's reasoning to understand its approach.               |

<Tip>
  Agents are smart enough to chain multiple API calls together. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [LaunchDarkly MCP server](https://www.gumloop.com/mcp/launchdarkly) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
