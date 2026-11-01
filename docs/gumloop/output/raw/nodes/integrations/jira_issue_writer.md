> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Jira Issue Writer

This document outlines the functionality and characteristics of the Jira Issue Writer node, which enables automated issue creation and updates in Jira projects.

## Node Inputs

### Required Fields

* **Resource**: Your Jira instance/site URL
* **Project**: The specific Jira project where issues will be created
* **Issue Type**: The type of issue to create (e.g., "Epic", "Story", "Task", "Subtask")
* **Reporter**: The user creating the issue
* **Summary**: Short description/title of the issue

### Optional Fields

These fields will appear in the node based on your Jira project's configuration. Think of them as additional information you can add to your issue:

* **Description**: The detailed write-up of your issue. While Summary is like a title, Description is where you can explain everything in detail.
  Example: "When a user clicks the login button, nothing happens. This occurs on Chrome and Firefox browsers."

* **Priority**: How urgent the issue is. The options you see here come directly from your Jira settings.
  Example: If your Jira has priorities set as "High", "Medium", "Low", you'll see these exact options in a dropdown.

* **Labels**: Tags that help organize and find issues easily. You can add multiple labels to better categorize your issues.
  Example: An issue might have labels like "frontend", "bug", "customer-reported"

* **Assignee**: Who should work on this issue. The dropdown will show all users who can be assigned issues in your Jira project.
  Example: If "Sarah Chen" and "Mike Smith" are members of your Jira project, you'll see their names in the assignee dropdown.

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

The node outputs two key pieces of information for the created issue:

* **Issue Key**: The unique identifier for the created Jira issue
* **Issue URL**: Direct link to access the issue in Jira

## Authentication

### Credentials Configuration

* The node supports multiple credential configurations
* Use the "Credentials to use" dropdown to select from:
  * Personal Default
  * Custom credentials for specific projects/instances
* Credentials can be managed in the [Connectors page](https://www.gumloop.com/personal/connectors)

## Key Features

### Loop Mode Support

* Enable Loop Mode to create multiple issues in batch
* Useful for bulk issue creation from data sources
* Can iterate over arrays (list inputs) from previous nodes

### Field Configuration

* Flexible field selection based on issue type
* Dynamic field validation
* Support for custom fields and configurations

## When To Use

The Jira Issue Writer node helps you automatically create new issues in Jira. Here are common scenarios where it's most useful:

### Daily Project Tasks

* Create a bug ticket when someone fills out your bug report Typeform
* Generate a new task whenever a customer requests a feature through email
* Create standardized onboarding tickets for each new team member
* Set up all sprint tasks at once from your sprint planning spreadsheet

### Bulk Issue Creation

* Create 20 similar tasks at once by connecting a spreadsheet with task details
* Convert a list of requirements from Google Sheets into individual Jira stories
* Create multiple bug tickets from an error monitoring system's report
* Generate a set of standard tasks that you create frequently (like monthly maintenance tasks)

### External System Integration

* Generate an issue when a customer raises a ticket in your support system
* Create a task when someone assigns you something in Slack

The node is perfect for any situation where you find yourself manually creating the same types of Jira issues repeatedly or need to create multiple issues at once.

> Note: This node can only create new issues. It cannot update existing Jira issues.

## Example Use Cases

1. **Sprint Task Creation**

```text theme={"dark"}
Google Sheet Reader → Jira Issue Writer → Slack Message Sender
```

**Setup:**

* **Google Sheet Reader**
  * Read sprint tasks from planning sheet
* **Jira Issue Writer** (Loop Mode)
  * Issue Type: Story
  * Fields: Summary, Description, Story Points
* **Slack Notifier**
  * Notify team of created stories

2. **Bug Report Automation**

```text theme={"dark"}
Form Submission → Ask AI → Jira Issue Writer
```

**Setup:**

* **Typeform Submission**
  * Capture bug details from users Typeform submissions
* **Ask AI**
  * Analyze and format bug description
  * Suggest priority and labels
* **Jira Issue Writer**
  * Issue Type: Bug
  * Fields: All relevant bug information

3. **Customer Feature Requests**

```text theme={"dark"}
Airtable → Jira Issue Writer → Hubspot Updater
```

**Setup:**

* **Airtable**
  * Monitor "Feature Requests" table
  * Each row: Customer Name, Feature Description, Business Impact
* **Jira Issue Writer** (Loop Mode)
  * Issue Type: Story
  * Labels: "customer-request", "needs-review"
  * Description includes customer context and business impact
* **Hubspot Contact Updater**
  * Updates customer record with Jira ticket reference

## Important Considerations

1. **Authentication**: Requires setup in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. **Permissions**: Node can only access projects and issues the authenticated user has permission to view

This node serves as a powerful tool for automating Jira issue management, enabling efficient project tracking, and streamlining workflow automation through flexible issue creation and update options.
