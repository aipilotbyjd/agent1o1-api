> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google NotebookLM

> Create notebooks, add sources, and generate AI audio overviews in NotebookLM Enterprise.

NotebookLM turns your documents into a research assistant that answers questions and generates summaries from sources you choose. The NotebookLM MCP server lets you create notebooks, load them with sources, share them with your team, and generate audio overviews using natural language.

## What Can It Do?

* **Create and manage notebooks** including listing recently viewed ones and deleting in bulk
* **Add sources** from text, web URLs, YouTube videos, or Google Drive documents
* **Inspect notebook contents** including every source attached to a notebook
* **Share notebooks** with teammates by email
* **Generate audio overviews** that turn a notebook's sources into a podcast-style discussion
* **Download generated audio** straight into Gumloop storage for use in later steps

## Where to Use It

### In Agents (Recommended)

Add NotebookLM as a tool to any agent. The agent can then build and populate notebooks conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with NotebookLM tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Add a web page as a source to a notebook")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Authentication

<Warning>
  This integration works with **NotebookLM Enterprise** (also called Gemini Notebook Enterprise), the Google Cloud version of NotebookLM. Consumer NotebookLM at `notebooklm.google.com` has no public API and cannot be connected.
</Warning>

Sign in with the Google account you use for NotebookLM Enterprise. Access is then governed by the Google Cloud project that NotebookLM Enterprise runs in, so that project needs to be set up first.

<Warning>
  **Access is granted on the Google Cloud project, not in Gumloop.** Each person who connects needs the **Cloud NotebookLM User** IAM role on that project along with a Gemini Notebook Enterprise license. Connecting the credential still succeeds without them, but tool calls return permission errors. Contact your Google Cloud administrator if you need either one.
</Warning>

**What your Google Cloud project needs:**

1. NotebookLM Enterprise set up, with the Discovery Engine API enabled and billing active
2. The **Cloud NotebookLM User** IAM role granted to each user who will connect
3. A Gemini Notebook Enterprise license assigned to each of those users

### Granting the Cloud NotebookLM User role

A project administrator grants the role once per user, from the Google Cloud console:

<Steps>
  <Step title="Open the IAM page">
    In the [Google Cloud console](https://console.cloud.google.com/iam-admin/iam), go to **IAM** and select the project that NotebookLM Enterprise runs in.
  </Step>

  <Step title="Click Grant access">
    This opens the panel where you add a new principal and assign roles to them.
  </Step>

  <Step title="Enter the user">
    In the **New principals** field, enter the user identifier. This is typically the email address for a Google Account, a user group, or the identifier for a user in a workforce identity pool.
  </Step>

  <Step title="Select the role">
    In the **Select a role** list, choose **Cloud NotebookLM User**, then click **Save**.
  </Step>

  <Step title="Assign a license">
    In addition to the **Cloud NotebookLM User** role, each user needs a license for Gemini Notebook Enterprise assigned to them.
  </Step>
</Steps>

<Info>
  **Cloud NotebookLM Admin** is a separate role for the people who administer Gemini Notebook Enterprise in the project. Working with notebooks through Gumloop only needs **Cloud NotebookLM User**.
</Info>

For the full setup flow, including licensing, see Google's [Set up NotebookLM Enterprise](https://cloud.google.com/gemini/enterprise/notebooklm-enterprise/docs/set-up-notebooklm) guide.

### Finding your project number and location

Every tool takes a **project number** and a **location**. Both appear in the URL you use to open NotebookLM Enterprise:

```text theme={"dark"}
https://notebook.cloud.google.com/LOCATION/?project=PROJECT_NUMBER
```

The project number is the 12-digit number after `project=` — not the project ID. The location is one of `global` (default), `us`, or `eu`, and must match the region your notebooks live in.

<Tip>
  Tell your agent the project number once at the start of a conversation and it will reuse it for every follow-up tool call.
</Tip>

### FAQ

<AccordionGroup>
  <Accordion title="Can I use my personal NotebookLM notebooks?">
    No. Notebooks created at `notebooklm.google.com` with a personal Google account aren't reachable through any API. Only notebooks inside a NotebookLM Enterprise project are available.
  </Accordion>

  <Accordion title="Our company signs in through Okta or Entra ID. Will this work?">
    Only if your identities are synced into Cloud Identity, so users sign in with a Google identity at `notebook.cloud.google.com`. Organizations using Workforce Identity Federation sign in at `notebook.cloud.google` (no `.com`) as workforce-pool users, and those identities can't complete a Google sign-in with Gumloop.
  </Accordion>

  <Accordion title="Why do calls fail even with the right IAM role?">
    Check whether your organization uses VPC Service Controls around Google Cloud APIs. Requests from Gumloop originate outside your network, so a service perimeter can block them even when your roles are correct. Your Cloud administrator can allow them.
  </Accordion>
</AccordionGroup>

## Available Tools

### Read Tools

| Tool                   | Description                                              |
| ---------------------- | -------------------------------------------------------- |
| **List Notebooks**     | List recently viewed notebooks in a project              |
| **Get Notebook**       | Get details of a specific notebook including its sources |
| **Get Source**         | Get details of a specific source in a notebook           |
| **Get Podcast Status** | Check the status of a podcast generation operation       |

### Write Tools

| Tool                      | Description                                                                                      |
| ------------------------- | ------------------------------------------------------------------------------------------------ |
| **Create Notebook**       | Create a new notebook                                                                            |
| **Delete Notebooks**      | Delete one or more notebooks                                                                     |
| **Share Notebook**        | Share a notebook with other users by email                                                       |
| **Add Source**            | Add a data source to a notebook. Supports text, web URL, YouTube video, or Google Drive document |
| **Delete Sources**        | Delete one or more sources from a notebook                                                       |
| **Create Audio Overview** | Generate an AI audio overview (podcast) of notebook content                                      |
| **Delete Audio Overview** | Delete an audio overview from a notebook                                                         |
| **Generate Podcast**      | Generate a podcast from text, images, audio, or video content                                    |
| **Download Podcast**      | Download a generated podcast MP3 to Gumloop storage                                              |

<Info>
  With **Share Notebook**, the people you share with also need the **Cloud NotebookLM User** role in the same project.
</Info>

<Note>
  **Generate Podcast**, **Get Podcast Status**, and **Download Podcast** use Google's standalone Podcast API, which is deprecated and requires separate allowlisting from Google. For audio generation, prefer **Create Audio Overview**, which works on a notebook's existing sources.
</Note>

## Example Prompts

Use these with your agent or in the Agent Node:

**List your notebooks:**

```text theme={"dark"}
List my NotebookLM notebooks in project 123456789012
```

**Create a notebook:**

```text theme={"dark"}
Create a NotebookLM notebook called "Q3 Competitive Research" in project 123456789012
```

**Add a web page as a source:**

```text theme={"dark"}
Add https://example.com/pricing as a source to my "Q3 Competitive Research" notebook
```

**Add research notes as text:**

```text theme={"dark"}
Add these interview notes as a text source to my research notebook
```

**Inspect a notebook:**

```text theme={"dark"}
Show me every source attached to my "Q3 Competitive Research" notebook
```

**Share with a teammate:**

```text theme={"dark"}
Share my "Q3 Competitive Research" notebook with priya@example.com as a writer
```

**Generate an audio overview:**

```text theme={"dark"}
Create an audio overview of my "Q3 Competitive Research" notebook
```

**Clean up old sources:**

```text theme={"dark"}
Delete the outdated pricing page source from my research notebook
```

## Troubleshooting

| Issue                                  | Solution                                                                                                                                                                        |
| -------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent asks for a project number        | Every tool needs it passed in. Copy the number after `project=` in your NotebookLM Enterprise URL                                                                               |
| Permission denied errors               | Check that you have the **Cloud NotebookLM User** role and a Gemini Notebook Enterprise license in that project ([how to grant them](#granting-the-cloud-notebooklm-user-role)) |
| Notebook not found                     | Check that the location matches the region in your NotebookLM URL. A notebook in `us` won't appear when querying `global`                                                       |
| No notebooks returned                  | You may be pointed at a project that has NotebookLM enabled but no notebooks yet. Create one to confirm the connection works                                                    |
| Requests blocked despite correct roles | Your organization may enforce VPC Service Controls. Ask your Cloud administrator to allow requests from Gumloop                                                                 |
| Podcast tools failing                  | The standalone Podcast API needs Google allowlisting. Use **Create Audio Overview** instead                                                                                     |
| Tool not available                     | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                                             |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Build me a notebook from these three articles" will create the notebook first, then add each source. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google NotebookLM MCP server](https://www.gumloop.com/mcp/gnotebooklm) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
