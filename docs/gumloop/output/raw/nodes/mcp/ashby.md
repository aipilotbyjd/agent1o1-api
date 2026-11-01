> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Ashby

> Streamline recruiting with AI-powered applicant tracking and scheduling.

Ashby is a modern applicant tracking system (ATS) for recruiting teams. The Ashby MCP server lets you manage jobs, candidates, applications, and interview schedules using natural language.

## What Can It Do?

* **Find and manage candidates** with search and filters
* **Create applications** and transfer them between jobs
* **Submit interview feedback** and review feedback form definitions
* **Read interview scorecards** and AI-generated criteria evaluations for applications
* **Download candidate files and interview transcripts** into Gumloop storage
* **View application history** and change application stages
* **Create and update interview schedules** with timing and participants
* **Manage interview plans, stages, and events** across jobs
* **Manage interviewer pools** with training requirements and roster updates
* **Create jobs, job postings, offers, and openings** end-to-end
* **Maintain job status** as roles open, pause, or close
* **Move applications** through stages with notes and tags
* **Read org-wide metadata** like departments, locations, sources, custom fields, archive reasons, and communication templates
* **Manage webhook subscriptions** for real-time event notifications

## Where to Use It

### In Agents (Recommended)

Add Ashby as a tool to any agent. The agent can then interact with your recruiting data conversationally, choosing the right actions based on context.

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

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Ashby tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all open jobs in Engineering")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

| Tool                                      | Description                                                           |
| ----------------------------------------- | --------------------------------------------------------------------- |
| **List/Get Users**                        | Search and get user details                                           |
| **List/Get Jobs**                         | Search jobs with filters                                              |
| **Update Job**                            | Update job properties                                                 |
| **Set Job Status**                        | Change job status (open/closed)                                       |
| **List/Get Candidates**                   | Search candidates                                                     |
| **Create Candidate**                      | Create a new candidate                                                |
| **Update Candidate**                      | Update candidate info                                                 |
| **Add Candidate Tag**                     | Tag a candidate                                                       |
| **Create Candidate Note**                 | Add a note to a candidate                                             |
| **List/Get Applications**                 | Search applications                                                   |
| **Update Application**                    | Update application properties                                         |
| **Change Application Stage**              | Move to a different stage                                             |
| **Change Application Source**             | Update the source                                                     |
| **List/Get Interviews**                   | Search interviews                                                     |
| **List/Get Interview Schedules**          | Get interview schedules                                               |
| **Create Interview Schedule**             | Schedule an interview                                                 |
| **Update Interview Schedule**             | Modify a schedule                                                     |
| **Cancel Interview Schedule**             | Cancel a schedule                                                     |
| **List/Get Interviewer Pools**            | Manage interviewer pools                                              |
| **Add User To Interviewer Pool**          | Add interviewers                                                      |
| **Create Interviewer Pool**               | Create a new interviewer pool                                         |
| **Update Interviewer Pool**               | Update an interviewer pool's title or training requirements           |
| **Create Application**                    | Create an application to consider a candidate for a job               |
| **Transfer Application**                  | Transfer an application to a different job                            |
| **List Application History**              | Get the stage transition history for an application                   |
| **Submit Application Feedback**           | Submit interview feedback for an application                          |
| **List Application Feedback**             | List interview scorecards and feedback submissions for an application |
| **List Application Criteria Evaluations** | List AI-generated criteria evaluations for an application             |
| **List Candidate Files**                  | List resume and attached files associated with a candidate            |
| **Download File**                         | Download a file or interview transcript from Ashby to Gumloop storage |
| **List Candidate Notes**                  | List all notes on a candidate with pagination                         |
| **Get Job Info**                          | Get detailed information about a specific job                         |
| **Get Job Interview Plan**                | Get the interview plan for a job including stages and activities      |
| **List Interview Plans**                  | List all interview plans with pagination                              |
| **List Interview Stages**                 | List all interview stages for a plan in order                         |
| **Get Interview Stage Info**              | Get detailed information about a specific interview stage             |
| **List Interview Stage Groups**           | List all interview stage groups with ordering                         |
| **List Interview Events**                 | List all interview events for a specific interview schedule           |
| **List Hiring Team Roles**                | List all available hiring team roles                                  |
| **List Archive Reasons**                  | List all archive reasons used when archiving applications             |
| **List Departments**                      | List all departments in the organization                              |
| **List Locations**                        | List all locations in the organization                                |
| **List Sources**                          | List all recruiting sources for candidate attribution                 |
| **List Custom Fields**                    | List all custom fields defined in the organization                    |
| **Set Custom Field Value**                | Set the value of a custom field on a candidate or application         |
| **List Communication Templates**          | List all email communication templates                                |
| **Create Job**                            | Create a new job posting                                              |
| **List Job Postings**                     | List all job postings with pagination                                 |
| **Get Job Posting Info**                  | Get detailed information about a specific job posting                 |
| **List Feedback Form Definitions**        | List all feedback form definitions for interview evaluations          |
| **Get Feedback Form Definition Info**     | Get detailed information about a specific feedback form definition    |
| **Create Offer**                          | Create a new offer for an application                                 |
| **Get Offer Info**                        | Get detailed information about a specific offer                       |
| **List Offers**                           | List all offers with pagination                                       |
| **List Openings**                         | List all job openings with pagination                                 |
| **Get Opening Info**                      | Get detailed information about a specific job opening                 |
| **Create Webhook Subscription**           | Create a webhook subscription for real-time event notifications       |
| **Delete Webhook Subscription**           | Delete a webhook subscription                                         |

## Example Prompts

Use these with your agent or in the Agent Node:

**Find open jobs:**

```text theme={"dark"}
List all open jobs in the Engineering department
```

**Search candidates:**

```text theme={"dark"}
Find candidates who applied for the Senior Engineer role
```

**Move an application:**

```text theme={"dark"}
Move Sarah Chen's application for Product Manager to the Interview stage
```

**Schedule an interview:**

```text theme={"dark"}
Schedule an interview for John Doe tomorrow at 2pm with the hiring manager
```

**Add a note:**

```text theme={"dark"}
Add a note to candidate Emily Wang: "Strong technical background, proceed to final round"
```

**Create an application:**

```text theme={"dark"}
Create an application for candidate Emily Wang for the Senior Engineer role
```

**Submit interview feedback:**

```text theme={"dark"}
Submit positive feedback for John Doe's Product Manager application using the "Technical Screen" form
```

**List openings and postings:**

```text theme={"dark"}
Show me all open openings and their active job postings in the Engineering department
```

**Check application history:**

```text theme={"dark"}
Show the stage transition history for Sarah Chen's Product Manager application
```

**Create an offer:**

```text theme={"dark"}
Create an offer for John Doe's Senior Engineer application starting next Monday
```

**Download a candidate's resume:**

```text theme={"dark"}
Download the resume for candidate Emily Wang and save it to my workspace
```

**Review interview feedback:**

```text theme={"dark"}
Show me all the interview scorecards and criteria evaluations for John Doe's Senior Engineer application
```

## Troubleshooting

| Issue                            | Solution                                                                                                                                        |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Agent not finding the right data | Use specific job titles or candidate names/emails                                                                                               |
| Action not completing            | Check that you've authenticated and have the necessary Ashby permissions                                                                        |
| Unexpected results               | The agent may chain multiple tools (e.g., finding the candidate first, then updating). Review the agent's reasoning to understand its approach. |
| Tool not available               | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                             |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "Move John's application to the next stage" will find the candidate and application first, then update the stage. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Ashby MCP server](https://www.gumloop.com/mcp/ashby) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
