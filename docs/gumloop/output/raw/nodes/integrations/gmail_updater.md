> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Gmail Updater

This document outlines the functionality and characteristics of the Gmail Updater node, which enables modifying Gmail threads by adding or removing labels.

## Node Inputs

The Gmail Updater node requires the following inputs:

* **Thread ID**: The Gmail thread identifier you want to update (can be obtained from Gmail Reader node)

## Node Parameters

* **Labels**: Select which Gmail labels to apply or remove
* **Update Mode**: Choose the operation mode
  * Add Label: Apply selected labels to the thread
  * Remove Label: Remove selected labels from the thread

## Node Output

The Gmail Updater node produces a status output indicating the success of the label update operation.

## Node Functionality

The Gmail Updater node automates label management for Gmail threads using the Gmail API.

**Key features include**:

* Add or remove labels from Gmail threads
* Support for multiple label operations
* Thread-based processing
* Loop Mode support for batch updates
* Secure authentication with Gumloop

## When To Use

The Gmail Updater node is particularly valuable in scenarios requiring automated email organization. Common use cases include:

* **Email Organization**: Automatically categorize emails with appropriate labels
* **Workflow Automation**: Update email status through label changes
* **Email Processing**: Mark emails as processed using custom labels
* **Email Filtering**: Apply labels based on content or sender

**Some specific examples**:

* Marking support tickets as "In Progress"
* Categorizing emails by department
* Flagging priority emails
* Organizing project-related communications

## Important Considerations

1. Requires Authentication with Gmail - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Labels must exist in your Gmail account before they can be used
3. Works with thread IDs (obtainable from Gmail Reader node)
4. For batch processing, utilize Loop Mode

## Common Workflows

### Customer Support Workflow

1. Use Gmail Reader to get new support emails
2. Process the content
3. Use Gmail Updater to add "Processing" label
4. Send response using Gmail Sender
5. Use Gmail Updater to add "Completed" label

### Email Organization Workflow

1. Use Gmail Reader to fetch specific emails
2. Analyze content or metadata
3. Use Gmail Updater to apply appropriate organizational labels

In summary, the Gmail Updater node streamlines email organization by providing automated label management capabilities, perfect for scenarios where emails need to be categorized or marked based on specific criteria.
