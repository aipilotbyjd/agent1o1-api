> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Affinity

> Manage your CRM relationships, deals, and notes with AI-powered automation.

Affinity is a relationship intelligence CRM built for dealmakers. The Affinity MCP server lets you search contacts, manage deals, update fields, and create notes using natural language.

## What Can It Do?

* **Search and retrieve** people, organizations, opportunities, and notes
* **Create and update** contacts, companies, deals, and list entries
* **Manage custom fields** to keep data synced across your tools
* **Add notes** to track conversations and follow-ups

## Where to Use It

### In Agents (Recommended)

Add Affinity as a tool to any agent. The agent can then interact with your CRM conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Affinity tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Search persons by email and return their name and company")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                        | Description                                          |
| --------------------------- | ---------------------------------------------------- |
| **Get All Lists**           | Retrieve all lists you have access to                |
| **Get List**                | Get details for a specific list                      |
| **Get List Entries**        | Retrieve entries from a list with pagination         |
| **Get List Entry**          | Get details for a single list entry                  |
| **Create List Entry**       | Add a person, organization, or opportunity to a list |
| **Delete List Entry**       | Remove an entity from a list                         |
| **Get Fields**              | Retrieve all fields with optional filters            |
| **Get Field Values**        | Get field values for an entity                       |
| **Create Field Value**      | Create or update a field value                       |
| **Update Field Value**      | Update an existing field value                       |
| **Delete Field Value**      | Clear a field value                                  |
| **Search Persons**          | Search people in your database                       |
| **Get Person**              | Get details for a single person                      |
| **Create Person**           | Create a new person                                  |
| **Update Person**           | Update a person's information                        |
| **Delete Person**           | Delete a person                                      |
| **Get Person Fields**       | List global person fields                            |
| **Search Organizations**    | Search organizations                                 |
| **Get Organization**        | Get details for an organization                      |
| **Create Organization**     | Create a new organization                            |
| **Update Organization**     | Update an organization                               |
| **Delete Organization**     | Delete an organization                               |
| **Get Organization Fields** | List global organization fields                      |
| **Search Opportunities**    | Search deals/opportunities                           |
| **Get Opportunity**         | Get details for an opportunity                       |
| **Create Opportunity**      | Create a new opportunity                             |
| **Update Opportunity**      | Update an opportunity                                |
| **Delete Opportunity**      | Delete an opportunity                                |
| **Get Notes**               | Retrieve notes with filters                          |
| **Get Note**                | Get a specific note                                  |
| **Create Note**             | Create a new note                                    |
| **Update Note**             | Update an existing note                              |
| **Delete Note**             | Delete a note                                        |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find key contacts:**

```text theme={"dark"}
Find all people with "Partner" in their title at venture capital firms
```

**Track a new deal:**

```text theme={"dark"}
Create a new opportunity called "Series A - TechCo" and add it to the Active Pipeline list
```

**Update deal status:**

```text theme={"dark"}
Move the "Seed Round - StartupXYZ" opportunity to the Due Diligence stage
```

**Log a meeting:**

```text theme={"dark"}
Add a note to Sarah Chen at Sequoia: "Great intro call, interested in our ML approach. Follow up next week with deck."
```

**Get pipeline overview:**

```text theme={"dark"}
Show me all opportunities in the Negotiation stage with their associated organizations
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                   |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Agent not finding the right data | Be more specific with names or add context like "in list X" or "from organization Y"                                                       |
| Action not completing            | Check that you've authenticated and have the necessary permissions in Affinity                                                             |
| Unexpected results               | The agent may chain multiple tools (e.g., listing projects first, then querying). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                        |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Find issues in the Marketing project" will automatically list projects, find the right ID, then query issues. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Affinity MCP server](https://www.gumloop.com/mcp/affinity) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
