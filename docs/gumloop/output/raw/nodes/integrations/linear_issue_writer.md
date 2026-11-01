> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Linear Issue Writer

This document explains the Linear Issue Writer node, which enables automated issue creation in Linear workspaces.

## Node Inputs

### Required Fields

* **Issue Title**: Name of the issue
* **Issue Description**: Detailed issue content
* **Team**: Target team (e.g., "Engineering", "Design")
* **Status**: Issue state (e.g., "Backlog", "In Progress")

### Optional Fields

* **Priority**: Importance level (e.g., "Urgent", "High", "Medium", "Low")
* **Labels**: Categories or tags for the issue
* **Assignee**: Team member responsible for the issue
* **Project**: The project the issue belongs to

### Configure Inputs

All parameters can be set as dynamic inputs to the node through the "Show more options" menu or when hovering over the node. This is particularly useful for:

* Creating issues with titles/descriptions from previous nodes
* Assigning issues based on data from other sources
* Setting project information dynamically from connected nodes like Slack
* Applying labels based on categorization results

Example: You can connect a Slack Message Reader to dynamically capture project information from messages and automatically assign it to new issues.

## Node Output

* **Status**: Status of the issue creation

## Node Functionality

The Linear Issue Writer node creates issues in Linear workspaces based on your specified parameters.

**Key features include**:

* Dynamic field configuration
* Batch issue creation via Loop Mode
* Team assignment
* Priority setting
* Label management
* Project assignment
* Secure authentication with Gumloop

## Example Workflows

### 1. Customer Feedback to Issue

```text theme={"dark"}
Form Input → Extract Data → Linear Issue Writer
Setup:
- Title: Connect to "Feedback Subject" from form
- Description: Connect to "Feedback Details" from form
- Team: "Product"
- Status: "Backlog"
- Labels: "Customer Feedback"
Purpose: Convert customer feedback directly into trackable issues
```

### 2. Slack Support Request Tracker

```text theme={"dark"}
Slack Message Reader → Ask AI → Linear Issue Writer
Setup:
- Title: Connect to AI-generated summary
- Description: Connect to original Slack message content
- Team: "Support"
- Project: Connect to project name extracted from Slack
- Assignee: Connect to support team member name
Purpose: Create issues from support requests in Slack channels
```

### 3. Bug Report Automation

```text theme={"dark"}
Gmail Reader → Extract Data → Linear Issue Writer
Setup:
- Title: Connect to "Bug Title" extraction
- Description: Connect to "Bug Details" extraction
- Team: "Engineering"
- Priority: Connect to extracted severity level
- Status: "Todo"
Purpose: Generate engineering tickets from email bug reports
```

### 4. Sprint Task Creator

```text theme={"dark"}
Google Sheets Reader → Linear Issue Writer
Setup:
- Enable Loop Mode to create multiple issues
- Connect sheet columns to respective Linear fields
- Title: Connect to "Task Name" column
- Description: Connect to "Details" column
- Team: Connect to "Team" column
- Status: "Backlog"
Purpose: Bulk create sprint tasks from planning spreadsheet
```

## Important Considerations

1. Requires Authentication with Linear - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Title, Description, Team, and Status are required fields
3. Configure Inputs enables dynamic field values from other nodes
4. When using Loop Mode, ensure connected inputs have matching list sizes

In summary, the Linear Issue Writer node streamlines issue creation in Linear workspaces, supporting both individual and batch operations through configurable inputs and Loop Mode. It's particularly powerful when combined with data extraction and AI nodes to automatically generate structured issues from various sources.
