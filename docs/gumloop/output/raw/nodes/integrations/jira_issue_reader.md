> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Jira Issue Reader

This document outlines the functionality and characteristics of the Jira Issue Reader node, which enables automated issue retrieval from Jira projects.

## Node Inputs

### Required Fields

* **Resource**: Your Jira instance/site URL
* **Project**: The specific Jira project to read from
* **Issue Information**: The information to retrieve from each issue (eg. Assignee, Description, Issue Type, etc)
* **Number of Issues**: The maximum number of issues to retrieve (defaults to 10)

> Note: Use the 'Configure Inputs' option to expose these fields as inputs to the node. Especially helpful for Loop Mode operations.

### Advanced Filtering Options

The node offers three filtering methods - you can only use one at a time:

#### 1. Basic Filters

* **Status**: Filter by issue status (e.g., "To Do", "In Progress", "Done")
* **Priority**: Filter by priority level (e.g., "High", "Medium", "Low")
* **Issue Type**: Filter by the type of issue (eg. Subtask, Task)
* **Labels**: Filter by specific labels attached to issues
* **Assignee**: Filter by team member assignments
* **Custom Fields**: Filter by any custom fields configured in your Jira instance

#### 2. JQL (Jira Query Language)

When enabled under `Show More Options`, you can write custom JQL queries to filter issues with greater precision:

* Allows for complex conditions and combinations
* Follows Jira's query syntax
* Overrides basic filters when enabled

**Example JQL Queries:**

```text theme={"dark"}
project = "Marketing" AND status = "In Progress" AND priority = High
```

```text theme={"dark"}
project = "Tech" AND labels = "backend" AND created >= -30d
```

```text theme={"dark"}
project = "Support" AND type = "Bug" AND (status = "To Do" OR status = "In Progress")
```

#### 3. Saved Filter

When enabled under `Show More Options`, you can select filters already saved in your Jira instance:

* Uses existing filters you've created in Jira
* Simplifies complex filtering without writing JQL
* Easier to maintain as you can update the filter in Jira directly

> Note: You can only use one filtering method at a time - either Basic Filters, JQL, or Saved Filter.

### Issue Information Selection

Choose which information to retrieve for each issue:

* Description
* Key
* Summary
* URL
* Assignee
* Status
* Priority
* Labels
* Issue Type
* etc.

## Node Output

The node outputs lists (arrays) for each selected information field. For example:

* If you select "Summary" and "Assignee", you'll receive:
  * `summaries`: string\[] - List of issue summaries
  * `assignees`: string\[] - List of issue assignees

All outputs are provided as lists, unless the `Number of Issues to Read` input is set to `1`.

## Node Functionality

The Jira Issue Reader node serves as a bridge between your workflows and Jira, enabling automated issue retrieval and filtering.

## Key Features

### Filtering Options

* **Basic Fields**: Priority, Status, Labels, Assignee, Issue Type
* **Custom Fields**: Organization-specific fields with AND/OR logic
* **JQL**: Advanced filtering with Jira Query Language
* **Saved Filters**: Reuse existing filters from your Jira instance
* **Number of Issues**: Control how many issues to retrieve

### Custom Fields Combination

You can choose how to combine multiple custom field filters:

* **AND**: Issues must match all selected custom fields
* **OR**: Issues must match at least one selected custom field

This gives you flexibility in how you filter issues based on your organization's specific fields.

## When To Use

The Jira Issue Reader node is particularly valuable in these scenarios:

### Project Management

* Monitor open issues across projects
* Track issue status changes
* Generate workload reports
* Identify bottlenecks

### Automation Workflows

* Trigger actions based on issue status
* Create automated reports
* Send notifications for specific issue types
* Sync issues with other tools

### Example Use Cases

1. **Daily Status Report**

```text theme={"dark"}
Filters:
- Status: "In Progress"
- Priority: "High"
Information:
- Summary
- Assignee
- Status
```

Result: Lists of high-priority in-progress issues with their assignees

2. **Bug Tracking with JQL**

```text theme={"dark"}
JQL: project = "Mobile App" AND issuetype = Bug AND labels = "Critical" AND status != Done
Information:
- Key
- Description
- Status
```

Result: Lists of critical bugs in the Mobile App project that aren't done

3. **Sprint Planning with Saved Filter**

```text theme={"dark"}
Saved Filter: "Current Sprint Backlog"
Information:
- Summary
- Story Points
- Priority
```

Result: Lists of upcoming sprint tasks with effort estimates

4. **Cross-Project Executive Report**

```text theme={"dark"}
JQL: project in ("Website", "Mobile App", "API") AND created >= -7d AND priority in (Highest, High)
Information:
- Key
- Project
- Summary
- Status
```

Result: High-priority issues created in the last week across multiple projects

## Important Considerations

1. **Authentication**: Requires setup in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. **Permissions**: Node can only access projects and issues the authenticated user has permission to view
3. **JQL Knowledge**: For advanced filtering, basic familiarity with JQL syntax is helpful
4. **Saved Filters**: Only filters visible to the authenticated user will be available

## Practical Integration Examples

Here are simple yet powerful ways to use the Jira Issue Reader with AI nodes:

### 1. Bug Report Analysis

```text theme={"dark"}
Jira Issue Reader → Ask AI → Slack Message Sender
```

**Setup:**

1. **Jira Issue Reader**
   * Filter by: JQL = "project = 'Product' AND issuetype = Bug AND status = Open"
   * Get: Description, Priority, Components

2. **Ask AI**
   * Prompt: "Analyze these bug reports and:
     1. Group similar issues
     2. Identify most affected components
     3. Suggest priority order for fixes"

3. **Slack Message Sender**
   * Daily digest to #engineering channel

**Value:** Helps engineering teams quickly identify patterns in bugs and prioritize fixes.

### 2. Sprint Health Check

```text theme={"dark"}
Jira Issue Reader → Scorer → Sendgrid Email Sender
```

**Setup:**

1. **Jira Issue Reader**
   * Filter: Use Saved Filter "Current Sprint Issues"
   * Get: Story Points, Status, Blocked status

2. **Scorer**
   * Score sprint health (0-100) based on:
     * Completion rate
     * Blocked issues
     * Remaining story points

3. **Email Sender**
   * Weekly report to project managers
   * Highlights risk areas when score \< 70

**Value:** Early warning system for sprint issues, helps prevent missed deadlines.

### 3. Customer Issue Prioritization

```text theme={"dark"}
Jira Issue Reader → Extract Data → Ask AI → Slack Message Sender
```

**Setup:**

1. **Jira Issue Reader**
   * JQL: "project = 'Support' AND labels = 'customer-reported' AND created >= -14d"
   * Get: Description, Impact, Customer name

2. **Extract Data**
   * Extract from Description:
     * Reported Problems
     * Error Messages
     * Business Impact
     * Customer Urgency

3. **Ask AI**
   * Input: Extracted data + Impact + Customer name
   * Analyze impact and urgency
   * Suggest priority order
   * Identify issues needing immediate attention

4. **Slack Message Sender**
   * Alerts to #customer-success for high-priority items

**Value:** Ensures customer issues get appropriate attention and quick response.

### 4. Technical Debt Tracking

```text theme={"dark"}
Jira Issue Reader → Categorizer → Notion Page Writer
```

**Setup:**

1. **Jira Issue Reader**
   * JQL: "project in (Backend, Frontend, Infrastructure) AND labels = technical-debt"
   * Get: Description, Components, Story Points

2. **Categorizer**
   * Categories:
     * Infrastructure
     * Code Quality
     * Security
     * Performance
   * Categorize based on issue description

3. **Notion Page Writer**
   * Organized tech debt dashboard
   * Group by category with effort estimates

**Value:** Better visibility and management of technical debt, helps with sprint planning.

In summary, the Jira Issue Reader node is a powerful tool for automating Jira issue management, enabling efficient project tracking, reporting, and workflow automation through flexible filtering and comprehensive data retrieval options.
