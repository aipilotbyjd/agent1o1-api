> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Typeform Submission Reader

This document outlines the functionality and characteristics of the Typeform Submission Reader node, which enables automated form response retrieval from Typeform.

## Node Inputs

### Required Fields

* **Workspace**: Select Typeform workspace
* **Form**: Choose specific form to read from

### Optional Fields

* **Response Limit**: Number of responses to retrieve
* **Fields**: Select form fields to extract

## Node Output

Selected form fields provided as lists (string\[]).

## Node Functionality

The Typeform Submission Reader node retrieves form submissions from Typeform.

**Key features include**:

* Multiple field selection
* Response limiting
* Trigger capability
* Loop Mode support for batch operations
* Secure authentication with Gumloop

### Trigger Functionality

This node can also function as a trigger to start your workflow when new form submissions arrive. Learn more about triggers in our [Workflow Triggers documentation](https://docs.gumloop.com/core-concepts/workflow_triggers).

## When To Use

The Typeform Submission Reader node is valuable for form data processing. Common use cases include:

* **Lead Collection**: Process new form submissions
* **Survey Analysis**: Gather response data
* **Registration Processing**: Handle event signups
* **Feedback Management**: Collect user feedback

**Some specific examples**:

* Creating leads from contact forms
* Processing job applications
* Analyzing customer feedback
* Managing event registrations

## Important Considerations:

* Requires Authentication with Typeform - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)

In summary, the Typeform Submission Reader node streamlines form response collection from Typeform, with optional trigger functionality for automated response processing.
