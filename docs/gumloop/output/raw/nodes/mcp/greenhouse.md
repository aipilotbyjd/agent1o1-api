> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Greenhouse

> Manage recruiting workflows with AI-powered applicant tracking and candidate management.

Greenhouse is a leading applicant tracking system (ATS) and recruiting platform. The Greenhouse MCP server lets you manage candidates, applications, interviews, jobs, and scorecards through the Greenhouse Harvest API using natural language.

## What Can It Do?

* **Manage candidates** including create, update, delete, anonymize, and merge
* **View activity history** for candidates and applications
* **Track applications** through stages with reject, hire, and move actions
* **Schedule and manage interviews** with interviewers and locations
* **Maintain jobs and job posts** with notes and interview stages
* **Review scorecards** with questions, answers, and candidate attributes
* **List and download attachments** like resumes, cover letters, and offer packets

## Where to Use It

### In Agents (Recommended)

Add Greenhouse as a tool to any agent. The agent can then interact with your recruiting data conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Greenhouse account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Greenhouse tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all active candidates for the Engineering role")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Candidate Tools

| Tool                        | Description                                                                               |
| --------------------------- | ----------------------------------------------------------------------------------------- |
| **List Candidates**         | List candidates with filtering by email, tags, custom fields, and date ranges             |
| **List Candidate Activity** | List recent activity metadata for a candidate                                             |
| **Create Candidate**        | Create a new candidate with contact details, tags, and an optional application            |
| **Update Candidate**        | Update candidate info including name, company, title, and contact details                 |
| **Delete Candidate**        | Permanently delete a candidate                                                            |
| **Anonymize Candidate**     | Anonymize specific candidate fields (e.g., email, phone, attachments) for GDPR compliance |
| **Merge Candidates**        | Merge a secondary candidate into a primary candidate record                               |

### Application Tools

| Tool                          | Description                                                                        |
| ----------------------------- | ---------------------------------------------------------------------------------- |
| **List Applications**         | List applications with filtering by candidate, job, stage, status, and date ranges |
| **List Application Activity** | List recent activity metadata for an application                                   |
| **Reject Application**        | Reject an application with a reason, optional notes, and rejection email           |
| **Unreject Application**      | Reverse a previous rejection on an application                                     |
| **Hire Application**          | Mark an application as hired with optional start date and opening                  |
| **Move Application**          | Move an application to a different stage or transfer to another job                |
| **Update Application**        | Update application fields like source, referrer, recruiter, and custom fields      |
| **Delete Application**        | Permanently delete an application                                                  |

### Interview Tools

| Tool                  | Description                                                                         |
| --------------------- | ----------------------------------------------------------------------------------- |
| **List Interviews**   | List interviews with filtering by job, application, status, and date ranges         |
| **Create Interview**  | Schedule a new interview with interviewers, time, location, and video conferencing  |
| **Update Interview**  | Update interview details like time, location, and interviewers                      |
| **Delete Interview**  | Permanently delete an interview                                                     |
| **List Interviewers** | List interviewers with filtering by interview, user, scorecard, and response status |

### Job Tools

| Tool                          | Description                                                                                   |
| ----------------------------- | --------------------------------------------------------------------------------------------- |
| **List Job Posts**            | List job posts with filtering by job, board, and status (active, live, internal)              |
| **List Jobs**                 | List jobs with filtering by department, office, status (open, draft, closed), and date ranges |
| **Update Job**                | Update job details including name, notes, department, offices, and custom fields              |
| **List Job Interview Stages** | List interview stages for jobs with filtering options                                         |
| **List Job Notes**            | List job notes with filtering by job, user, and visibility                                    |
| **Create Job Note**           | Add a note to a job with visibility settings                                                  |
| **Update Job Note**           | Update an existing job note                                                                   |
| **Delete Job Note**           | Permanently delete a job note                                                                 |

### Organization Tools

| Tool                 | Description                                                        |
| -------------------- | ------------------------------------------------------------------ |
| **List Users**       | List users with filtering by agency, office, department, and email |
| **List Approvers**   | List approvers with filtering by group, user, and status           |
| **List Departments** | List departments in your Greenhouse account                        |

### Attachment Tools

| Tool                 | Description                                                                                                                                                                                        |
| -------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **List Attachments** | List attachments (resumes, cover letters, offer packets) with filtering by candidate, application, type, and date ranges. Supports downloading files and storing them in Gumloop workspace storage |

### Scorecard Tools

| Tool                                             | Description                                                                         |
| ------------------------------------------------ | ----------------------------------------------------------------------------------- |
| **List Scorecards**                              | List scorecards with filtering by interview kit, submitter, application, and status |
| **List Scorecard Questions**                     | List scorecard questions with optional multi-choice question options                |
| **List Scorecard Question Answers**              | List question answers with optional selected option details                         |
| **List Scorecard Question Candidate Attributes** | List mappings between scorecard questions and candidate attributes                  |
| **List Scorecard Candidate Attributes**          | List candidate attributes with ratings from scorecards                              |

## Example Prompts

Use these with your agent or in the Agent Node:

**List active candidates:**

```text theme={"dark"}
Show me all candidates who applied in the last 30 days
```

**Create a candidate:**

```text theme={"dark"}
Create a new candidate named Jane Smith with email jane@example.com and tag her as Engineering
```

**Move an application:**

```text theme={"dark"}
Move the application for John Doe to the Interview stage
```

**Schedule an interview:**

```text theme={"dark"}
Schedule a technical interview for application 12345 tomorrow at 2pm in the NYC office
```

**Review scorecards:**

```text theme={"dark"}
Show me all completed scorecards for the Senior Engineer role
```

**Reject an application:**

```text theme={"dark"}
Reject application 67890 with reason "Position filled" and send a rejection email
```

**Download attachments:**

```text theme={"dark"}
Download all resumes for candidate 5851191 and save them to workspace storage
```

## Troubleshooting

| Issue                 | Solution                                                                                                                                        |
| --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Authentication failed | Verify your Greenhouse account is connected and has the required Harvest API permissions                                                        |
| Candidate not found   | Use specific names, emails, or candidate IDs for accurate lookups                                                                               |
| Action not completing | Ensure your Greenhouse user has the necessary permissions for the operation                                                                     |
| Pagination issues     | Use the `max_limit` parameter to control how many results are returned                                                                          |
| Tool not available    | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                             |
| Unexpected results    | The agent may chain multiple tools (e.g., finding the candidate first, then updating). Review the agent's reasoning to understand its approach. |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Reject all applications for the closed Marketing Manager role" will list the job, find its applications, then reject each one. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Greenhouse MCP server](https://www.gumloop.com/mcp/greenhouse) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
