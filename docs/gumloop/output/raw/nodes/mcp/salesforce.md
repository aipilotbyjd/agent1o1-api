> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Salesforce

> Manage your CRM with AI-powered Salesforce automation.

Salesforce is the world's leading CRM platform for sales, service, and marketing. The Salesforce MCP server lets you query, create, update, and manage any object using natural language.

## What Can It Do?

* **Query records** with SOQL or SOSL
* **Create, update, and delete** any object
* **Run reports** and pull data for analysis
* **View and manage dashboards** including refreshing, cloning, and updating layouts
* **Manage campaigns** by adding leads and contacts
* **Download files and attachments** from Salesforce to Gumloop storage
* **Manage file sharing** on records and create or revoke public download links
* **Publish and archive** Knowledge articles
* **Convert leads** and create related records
* **Run bulk data jobs** to insert, update, upsert, or delete large record sets asynchronously

## Where to Use It

### In Agents (Recommended)

Add Salesforce as a tool to any agent. The agent can then manage your CRM conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Salesforce tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Query accounts in California")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                          | Description                                                                                                                    |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| **Soql Query**                | Execute SOQL queries                                                                                                           |
| **Sosl Search**               | Search across objects                                                                                                          |
| **Describe Object**           | Get object metadata                                                                                                            |
| **Get Record**                | Retrieve a record by ID                                                                                                        |
| **Create Record**             | Create a new record                                                                                                            |
| **Update Record**             | Update an existing record                                                                                                      |
| **Delete Record**             | Delete a record                                                                                                                |
| **Bulk Ingest Start**         | Start an async bulk insert, update, upsert, or delete job for large record sets                                                |
| **Bulk Ingest Results**       | Get the status and results of an async bulk ingest job                                                                         |
| **Run Report**                | Execute a Salesforce report                                                                                                    |
| **List Reports**              | List available reports                                                                                                         |
| **Add Lead To Campaign**      | Add a lead to a campaign                                                                                                       |
| **Add Contact To Campaign**   | Add a contact to a campaign                                                                                                    |
| **Convert Lead**              | Convert lead to account/contact                                                                                                |
| **Get File**                  | Download a file or attachment from Salesforce to storage. Supports ContentVersion, ContentDocument, and legacy Attachment IDs. |
| **Create Report**             | Create a new Salesforce report                                                                                                 |
| **Update Report**             | Update an existing report's metadata                                                                                           |
| **Clone Report**              | Clone an existing report                                                                                                       |
| **List Dashboards**           | List recently viewed dashboards                                                                                                |
| **Get Dashboard**             | Retrieve a dashboard's results and component data                                                                              |
| **Create Dashboard**          | Create a new dashboard by cloning an existing one                                                                              |
| **Update Dashboard**          | Update a dashboard's metadata and structure                                                                                    |
| **Manage Dashboard**          | Refresh or delete a dashboard                                                                                                  |
| **Create Note**               | Create a note on a record                                                                                                      |
| **Create File**               | Upload a file                                                                                                                  |
| **Update File Sharing**       | Change the share type and visibility of a file already linked to a record                                                      |
| **Remove File From Record**   | Unlink a file from a record without deleting the file                                                                          |
| **Create Public File Link**   | Create a public link for sharing a file externally, with optional password, expiry, and download preferences                   |
| **Update Public File Link**   | Update a public file link's settings, or revoke it to cut off external access                                                  |
| **Publish Knowledge Article** | Publish a Knowledge article version, immediately or on a schedule                                                              |
| **Archive Knowledge Article** | Archive a published Knowledge article version                                                                                  |

## File Sharing and Public Links

File tools accept either a `ContentDocument` ID (starts with `069`) or a `ContentVersion` ID (starts with `068`), and resolve the underlying link for you.

* **Update File Sharing** takes the file ID plus the `record_id` it is linked to, and sets `share_type` (`V` for Viewer, `C` for Collaborator) and/or `visibility` (`AllUsers`, `InternalUsers`, or `SharedUsers`). Provide at least one of the two.
* **Remove File From Record** deletes only the link between the file and the record, so the file stays in Salesforce.
* **Create Public File Link** returns a `DistributionPublicUrl` anyone can open. Options include `name`, `password_required`, `expiry_date` (ISO 8601 UTC), `allow_view_in_browser`, `allow_original_download`, `allow_pdf_download`, `link_latest_version`, and `notify_on_visit`.
* **Update Public File Link** takes the `distribution_id` returned when the link was created. Set `revoke` to `true` to delete the link, or `expires` to `false` to remove its expiration date.

<Warning>
  Public file links are accessible to anyone who has the URL. Set `password_required` or an `expiry_date` when sharing sensitive files, and use **Update Public File Link** with `revoke` set to `true` when the link is no longer needed.
</Warning>

## Knowledge Articles

**Publish Knowledge Article** and **Archive Knowledge Article** take an `article_version_id` (a `KnowledgeArticleVersion` ID, starting with `ka`).

Publishing supports a `pub_action` of `PUBLISH_ARTICLE` (default), `PUBLISH_ARTICLE_NEW_VERSION`, `SCHEDULE_ARTICLE_FOR_PUBLICATION`, or `PUBLISH_TRANSLATION`. For scheduled publication, also pass `pub_date` in ISO 8601 UTC.

<Info>
  The file sharing and Knowledge tools rely on Salesforce user permissions rather than extra OAuth scopes. Files require access to `ContentDocumentLink` and `ContentDistribution`, with Content Deliveries and Public Links enabled for public links. Knowledge requires Manage Salesforce Knowledge and Publish Articles, with Lightning Knowledge enabled.
</Info>

## Example Prompts

Use these with your agent or in the Agent Node:

**Query records:**

```text theme={"dark"}
Find the 10 largest accounts in California
```

**Get opportunity details:**

```text theme={"dark"}
Get the details for opportunity OPP-12345
```

**Create a task:**

```text theme={"dark"}
Create a follow-up task for the Acme account due next Friday
```

**Update a record:**

```text theme={"dark"}
Update contact john@acme.com with new phone number 415-555-1234
```

**Run a report:**

```text theme={"dark"}
Run the Q4 Pipeline report and show me the summary
```

**Create a report:**

```text theme={"dark"}
Create a new tabular report for accounts in the Technology industry
```

**Clone a report:**

```text theme={"dark"}
Clone the Q4 Pipeline report and name it Q1 Pipeline
```

**Refresh a dashboard:**

```text theme={"dark"}
Refresh the "Sales Leaderboard" dashboard and send me the results
```

**Clone a dashboard:**

```text theme={"dark"}
Create a new dashboard by cloning the "Sales Leaderboard" and name it "Q1 Sales Leaderboard"
```

**Share a file publicly:**

```text theme={"dark"}
Create a password-protected public link for the pricing PDF that expires in 30 days
```

**Revoke a public link:**

```text theme={"dark"}
Revoke the public link for the pricing PDF
```

**Publish a Knowledge article:**

```text theme={"dark"}
Publish the "Refund Policy" Knowledge article as a new version
```

## Connecting Gumloop to Salesforce

Gumloop is a **Salesforce Connected App** — it is not listed on the Salesforce AppExchange marketplace. A Salesforce administrator must authorize the connection before users can authenticate.

**Quickest setup:** Have your Salesforce admin visit the [Salesforce Connectors page](https://www.gumloop.com/personal/connectors?provider=salesforce) in Gumloop and complete the OAuth flow. This automatically installs the Gumloop connected app in your Salesforce organization.

If a non-admin user attempts to connect first, the admin will see an approval request in Salesforce under **Setup > Apps > Connected Apps > Manage Connected Apps**.

For full setup instructions, see the [Credentials page — Salesforce Setup](/core-concepts/credentials#salesforce-setup-admin-only).

<Info>
  For more details on Salesforce's connected app restrictions, see the [official Salesforce documentation](https://help.salesforce.com/s/articleView?id=005132365\&type=1).
</Info>

## Troubleshooting

| Issue                                                              | Solution                                                                                                                           |
| ------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------- |
| "Administrators need to pre-install the Gumloop application" error | A Salesforce admin must first authorize Gumloop. See [Connecting Gumloop to Salesforce](#connecting-gumloop-to-salesforce) above.  |
| Agent not finding the right data                                   | Use specific record IDs or exact names                                                                                             |
| "No ContentDocumentLink found" when updating file sharing          | The file is not linked to that record. Check both the file ID and the `record_id`.                                                 |
| Public link creation fails                                         | Ask your admin to enable Content Deliveries and Public Links, and confirm your user has `ContentDistribution` access.              |
| Knowledge publish or archive fails                                 | Confirm Lightning Knowledge is enabled and your user has Manage Salesforce Knowledge plus Publish Articles permissions.            |
| Action not completing                                              | Check that you've authenticated with Salesforce                                                                                    |
| Unexpected results                                                 | The agent may chain multiple tools (e.g., querying first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available                                                 | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Update the Acme opportunity to Closed Won" will find the opportunity first, then update it. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Salesforce MCP server](https://www.gumloop.com/mcp/salesforce) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
