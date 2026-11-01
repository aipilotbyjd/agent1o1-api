> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Slides

> Create, read, and manage Google Slides presentations with AI-powered automation.

Google Slides is Google's cloud-based presentation tool for creating and collaborating on slide decks. The Google Slides MCP server lets you search, create, edit, and manage presentations using natural language.

## What Can It Do?

* **Search and open presentations** in Google Drive
* **Create new presentations** with slides, text, tables, images, and styling
* **Add and duplicate slides** with predefined or custom layouts
* **Add elements** like text boxes, images, shapes, tables, videos, and lines
* **Update elements** including styling, position, and text content
* **Manage tables** by inserting/deleting rows, columns, and merging cells
* **Find and replace text** across an entire presentation
* **Work with speaker notes** — read and update notes per slide
* **Manage comments** — list, create, reply, resolve, and delete

## Where to Use It

### In Agents (Recommended)

Add Google Slides as a tool to any agent. The agent can then create and edit presentations conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Slides tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Create a presentation with a title slide")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Presentation Tools

| Tool                       | Description                                                                                                                           |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| **Search Presentations**   | Search for Google Slides presentations in Drive                                                                                       |
| **Get Presentation**       | Get presentation content, structure, and metadata                                                                                     |
| **Get Presentation Link**  | Get shareable and present-mode links for a presentation                                                                               |
| **Export Presentation**    | Export a presentation to PDF or PPTX format                                                                                           |
| **Create Presentation**    | Create a new presentation with optional slides, content, and styling, optionally inside a specific Drive folder (by folder ID or URL) |
| **Duplicate Presentation** | Create a copy of an existing presentation                                                                                             |

### Slide & Element Tools

| Tool               | Description                                                      |
| ------------------ | ---------------------------------------------------------------- |
| **Add Slide**      | Add a new slide or duplicate an existing slide                   |
| **Add Element**    | Add a text box, image, shape, table, video, or line to a slide   |
| **Update Element** | Update styling, transform, or properties of an existing element  |
| **Manage Table**   | Insert or delete rows/columns, or merge/unmerge cells in a table |
| **Find Replace**   | Find and replace text across an entire presentation              |

### Speaker Notes & Comments

| Tool                     | Description                                                           |
| ------------------------ | --------------------------------------------------------------------- |
| **Get Speaker Notes**    | Get speaker notes for one or all slides                               |
| **Update Speaker Notes** | Update speaker notes for a slide                                      |
| **Manage Comments**      | List, create, reply to, resolve, or delete comments on a presentation |

## Example Prompts

Use these with your agent or in the Agent Node:

**Create a presentation:**

```text theme={"dark"}
Create a presentation titled "Q3 Review" with a title slide and three content slides
```

**Add content to a slide:**

```text theme={"dark"}
Add a text box with "Key Metrics" to the second slide of my presentation
```

**Export a presentation:**

```text theme={"dark"}
Export my presentation as a PDF for sharing
```

**Work with speaker notes:**

```text theme={"dark"}
Add speaker notes to slide 3 with talking points about the revenue forecast
```

**Find and replace:**

```text theme={"dark"}
Replace all instances of "2025" with "2026" in the presentation
```

**Manage comments:**

```text theme={"dark"}
List all comments on the presentation and resolve the ones about formatting
```

**Duplicate a presentation:**

```text theme={"dark"}
Make a copy of the Q3 Review presentation for Q4 planning
```

## Troubleshooting

| Issue                                    | Solution                                                                                                                           |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right presentation | Use specific presentation titles or IDs                                                                                            |
| Action not completing                    | Check that you've authenticated with Google                                                                                        |
| Unexpected results                       | The agent may chain multiple tools (e.g., searching first, then editing). Review the agent's reasoning to understand its approach. |
| Tool not available                       | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Add a summary slide to my Q3 presentation" will search for the presentation first, then add the slide. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Slides MCP server](https://www.gumloop.com/mcp/gslides) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
