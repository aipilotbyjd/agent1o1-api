> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Jira Issue Updater

This document outlines the functionality and characteristics of the Jira Issue Updater node, which enables updating fields and properties of existing issues in your Jira projects.

## Node Inputs

### Required Fields

* **Resource**: Your Jira instance/site URL
* **Project**: The specific Jira project containing the issues to update

### Issue Selection Methods

Choose one of these methods to select which issues to update:

#### 1. Single Issue Update

* **Issue Key**: The unique identifier of a specific issue (e.g., CCS-4). Only used when not using JQL or Saved Filter.

#### 2. JQL-Based Update

* **Use JQL?**: Enable to use Jira Query Language to find issues to update
* **JQL Query**: Enter a custom JQL query to select multiple issues
  * Example: `project = CCS AND priority = High`
  * Example: `assignee = currentUser() AND status = Open`
  * Example: `created >= -7d` (issues created in last 7 days)

> Note: You can expose the JQL Query as a dynamic input under 'Configure Inputs'. This allows you to pass JQL queries from previous nodes in your workflow.

#### 3. Saved Filter Update

* **Use Saved Filter?**: Enable to use pre-configured JQL filters from your Jira instance
* **Filter**: Select from your saved Jira filters

> Note: Only one selection method can be used at a time.

### Field Selection

Select which fields you want to update in the issue(s):

* **Summary**: Update the issue title/summary
* **Custom Fields**: Update any custom fields configured in your Jira instance
* **Description**: Update the detailed explanation of the issue
* **Priority**: Modify the issue's priority level
* **Labels**: Add or remove labels associated with the issue
* **Assignee**: Change the team member assigned to the issue
* **Comment**: Add a new comment to the issue

> Note: Use the 'Configure Inputs' option to expose these fields as inputs to the node. This is particularly useful for Loop Mode operations.

### Custom Fields Guide

Custom fields are special fields that your team has added to Jira. Here's how to work with them:

1. First, select which custom fields you want to use by clicking the "Fields" dropdown
2. Once selected, these fields appear as new inputs in your node
3. Different types of custom fields work in different ways:

#### Types of Custom Field Inputs

When you select custom fields in the Fields dropdown, they appear as input fields in your node. Here's how to handle different types of custom field inputs:

1. **Single-Value Custom Fields**
   * Appears as: A text input field expecting a single value
   * Example: For a "Component" field, enter: "Frontend"
   * Note: Even though these might be dropdowns in Jira, they appear as text inputs in the node

2. **Multi-Value Custom Fields**
   * Appears as: A text input field that accepts multiple values
   * Example: For an "Affected Systems" field, enter: "Website,Mobile App,API"
   * Note: Use commas to separate multiple values

3. **Cascading (Parent-Child) Custom Fields**
   * Appears as: A text input field expecting a parent-child relationship
   * Example: For a "Location" field, enter: "North America > United States"
   * Note: Use the ">" symbol to separate parent and child values

4. **Numeric Custom Fields**
   * Appears as: A text input field expecting a number
   * Example: For a "Story Points" field, enter: "5"
   * Note: Only enter numeric values for these fields

## Node Output

The node outputs two key pieces of information:

* **Updated Issue Key** (`List`): A list containing the key(s) of the updated issue(s)
* **Updated Issue URL** (`List`): A list containing direct link(s) to access the updated issue(s) in Jira

## Node Functionality

The Jira Issue Updater node serves as a tool for automating updates to existing Jira issues, enabling efficient issue management and workflow automation.

## Key Features

### Issue Selection Methods

* **Single Issue**: Update one specific issue by key
* **JQL Query**: Update multiple issues matching custom criteria
* **Saved Filters**: Update issues using pre-configured filters

### Field Selection

* Standard Fields: Summary, Description, Priority, Labels, Assignee, and Comments
* Custom Fields: Support for organization-specific fields
* Flexible Updates: Only selected fields are modified

### Loop Mode Support

* Enable Loop Mode to update multiple issues in batch
* Useful for bulk issue updates from data sources
* Can iterate over arrays (list inputs) from previous nodes

## When To Use

The Jira Issue Updater node is particularly valuable in these scenarios:

* Update issue statuses based on external triggers
* Modify issue details from automated processes
* Sync issue information with other systems
* Bulk update issue priorities or assignees
* Standardize issue descriptions
* Add automated comments based on external events

## Example Use Cases

1. **Smart Ticket Updates from Slack**

```text theme={"dark"}
Slack Message Reader → Ask AI → Jira Issue Updater
```

Monitor Slack channels for issue updates and automatically:

* Update existing ticket descriptions with new information
* Modify priority based on urgency in messages
* Update custom fields with new customer feedback
* Add relevant labels based on conversation context
* Add comments documenting customer communications

2. **Bulk Priority Updates with JQL**

```text theme={"dark"}
Google Sheet Reader → Jira Issue Updater (with JQL)
```

Update multiple issue priorities at once:

* JQL Query: `project = "Marketing" AND labels = "campaign-q1" AND status != Done`
* Priority field connected to Sheet column with new values
* Updates all matching marketing campaign issues in one operation

3. **Email-Driven Updates**

```text theme={"dark"}
Gmail Reader → Extract Data → Jira Issue Updater
```

Monitor specific email threads to:

* Update ticket status based on client responses
* Append new information to descriptions
* Update custom fields with latest communications
* Modify assignee based on email participants
* Add comments with email content for record-keeping

4. **Team Workload Balancing with Saved Filter**

```text theme={"dark"}
Jira Issue Updater (with Saved Filter)
```

Use a saved filter for team workload management:

* Saved Filter: "Overdue Support Tasks"
* Reassign issues to available team members
* Update priority levels based on due dates
* Add "needs-attention" label to flagged items
* Add comments explaining reassignment decisions

5. **AI-Enhanced Issue Refinement with Dynamic JQL**

```text theme={"dark"}
Ask AI → Jira Issue Updater (with JQL as input)
```

Use AI to generate appropriate JQL based on business rules:

* AI generates JQL query based on business conditions
* Connected as dynamic input to JQL field
* Updates matching issues with standardized descriptions
* Adds consistency across issue documentation
* Adds comments summarizing AI-driven changes

## Important Considerations

1. **Authentication**: Requires setup in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. **Permissions**: Node can only update issues the authenticated user has permission to modify
3. **Field Validation**: Ensure provided values match the expected format for each field type
4. **Issue Existence**: Issues must exist before they can be updated
5. **Custom Fields**: Must be properly configured in your Jira instance
6. **JQL Knowledge**: For advanced filtering, basic familiarity with JQL syntax is helpful. For reference, check [Atlassian's JQL documentation](https://support.atlassian.com/jira-software-cloud/docs/advanced-search-reference-jql-fields/)
7. **Saved Filters**: Only filters visible to the authenticated user will be available

In summary, the Jira Issue Updater node is a powerful tool for automating Jira issue updates, enabling efficient project tracking and workflow automation through flexible issue selection and field updates. When combined with the AI nodes, it can create sophisticated automation workflows for intelligent issue management.
